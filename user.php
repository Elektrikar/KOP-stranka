<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/User.php';

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();

$user = new User($pdo);
$userData = $user->getById($_SESSION['user_id']);

if (!$userData) {
    session_destroy();
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Všetky polia sú povinné.';
        } elseif (!$userData->verifyPassword($current_password)) {
            $error = 'Aktuálne heslo je nesprávne.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Nové heslá sa nezhodujú.';
        } elseif (strlen($new_password) < 8) {
            $error = 'Nové heslo musí mať aspoň 8 znakov.';
        } else {
            if ($userData->updatePassword($new_password)) {
                $success = 'Heslo bolo úspešne zmenené.';
            } else {
                $error = 'Chyba pri zmene hesla. Skúste to neskôr.';
            }
        }
    }
    elseif (isset($_POST['update_address'])) {
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        // Validate address if any field is filled
        if (!empty($address) || !empty($city) || !empty($zip_code) || !empty($country)) {
            if (empty($address)) {
                $error = 'Ulica je povinná.';
            } elseif (empty($city)) {
                $error = 'Mesto je povinné.';
            } elseif (empty($zip_code)) {
                $error = 'PSČ je povinné.';
            } elseif (empty($country)) {
                $error = 'Krajina je povinná.';
            } elseif (!preg_match('/[a-zA-Z]/', $address) || !preg_match('/\d/', $address)) {
                $error = 'Adresa musí obsahovať názov ulice aj číslo domu.';
            }
        }
        
        if (empty($error)) {
            // Update user address
            if ($userData->updateAddress($address, $city, $zip_code, $country)) {
                $success = 'Adresa bola uložená.';
                // Refresh user data
                $userData = $user->getById($_SESSION['user_id']);
            } else {
                $error = 'Chyba pri ukladaní adresy.';
            }
        }
    }
    elseif (isset($_POST['delete_account'])) {
        $confirm_email = $_POST['confirm_email'] ?? '';
        
        if (empty($confirm_email)) {
            $error = 'Pre potvrdenie zadajte svoj email.';
        } elseif ($confirm_email !== $userData->email) {
            $error = 'Zadaný email sa nezhoduje s vašim účtom.';
        } else {
            // Delete user account
            if ($userData->delete()) {
                // Clear session and redirect
                session_destroy();
                header('Location: index.php?account_deleted=1');
                exit();
            } else {
                $error = 'Chyba pri odstránení účtu. Skúste to neskôr.';
            }
        }
    }
}

$pageData = array(
    'title' => 'Môj účet | E-shop',
    'metaDataDescription' => 'Správa účtu používateľa',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/user.css')
    )
);
require_once 'theme/header.php';
?>

<div class="container">
    <div class="user-account-container">
        <div class="user-account-header">
            <h1>Môj účet</h1>
            <a href="index.php" class="user-back-link">Späť do obchodu</a>
        </div>
        
        <?php if ($error): ?>
            <div class="user-error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="user-success-message"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- User Information -->
        <div class="user-section">
            <h3>Informácie o účte</h3>
            <div class="user-info-display">
                <div class="user-info-row">
                    <span class="user-info-label">Meno:</span>
                    <span class="user-info-value"><?= htmlspecialchars($userData->first_name . ' ' . $userData->last_name) ?></span>
                </div>
                <div class="user-info-row">
                    <span class="user-info-label">Email:</span>
                    <span class="user-info-value"><?= htmlspecialchars($userData->email) ?></span>
                </div>
                <?php if ($userData->role !== 'user'): ?>
                <div class="user-info-row">
                    <span class="user-info-label">Typ účtu:</span>
                    <span class="user-info-value"><?= htmlspecialchars(ucfirst($userData->role)) ?></span>
                </div>
                <?php endif ?>
                <div class="user-info-row">
                    <span class="user-info-label">Registrovaný:</span>
                    <span class="user-info-value"><?= date('d.m.Y H:i', strtotime($userData->created_at)) ?></span>
                </div>
            </div>
        </div>

        <!-- Address Management -->
        <div class="user-section">
            <h3>Adresa</h3>
            <p class="user-form-help">
                Uložená adresa sa automaticky použije pri platbe.
            </p>
            <form method="post" class="user-form">
                <input type="hidden" name="update_address" value="1">
                
                <div class="form-group">
                    <label for="address">Ulica a číslo</label>
                    <input type="text" id="address" name="address" 
                        value="<?= htmlspecialchars($userData->address ?? '') ?>"
                        placeholder="Námestie slobody 123"
                        required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="city">Mesto</label>
                        <input type="text" id="city" name="city" 
                            value="<?= htmlspecialchars($userData->city ?? '') ?>"
                            placeholder="Bratislava"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="zip_code">PSČ</label>
                        <input type="text" id="zip_code" name="zip_code" 
                            value="<?= htmlspecialchars($userData->zip_code ?? '') ?>"
                            placeholder="811 01"
                            required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="country">Krajina</label>
                    <select id="country" name="country" required>
                        <option value="">Vyberte krajinu</option>
                        <option value="Slovensko" <?= ($userData->country ?? '') === 'Slovensko' ? 'selected' : '' ?>>Slovensko</option>
                        <option value="Česko" <?= ($userData->country ?? '') === 'Česko' ? 'selected' : '' ?>>Česko</option>
                        <option value="Maďarsko" <?= ($userData->country ?? '') === 'Maďarsko' ? 'selected' : '' ?>>Maďarsko</option>
                        <option value="Poľsko" <?= ($userData->country ?? '') === 'Poľsko' ? 'selected' : '' ?>>Poľsko</option>
                        <option value="Rakúsko" <?= ($userData->country ?? '') === 'Rakúsko' ? 'selected' : '' ?>>Rakúsko</option>
                        <option value="Nemecko" <?= ($userData->country ?? '') === 'Nemecko' ? 'selected' : '' ?>>Nemecko</option>
                    </select>
                </div>
                
                <div class="user-form-actions">
                    <button type="submit" class="user-button">Uložiť adresu</button>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="user-section">
            <h3>Zmena hesla</h3>
            <form method="post" class="user-form">
                <input type="hidden" name="update_password" value="1">
                
                <div class="form-group">
                    <label for="current_password">Aktuálne heslo</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">Nové heslo</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                    <div class="user-form-help">
                        Heslo musí mať aspoň 8 znakov.
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Potvrdiť nové heslo</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                
                <div class="user-form-actions">
                    <button type="submit" class="user-button">Zmeniť heslo</button>
                </div>
            </form>
        </div>

        <div class="user-danger-zone">
            <h3>Nebezpečná zóna</h3>
            <div class="user-danger-box">
                <h4>Odstránenie účtu</h4>
                <p class="user-warning-text">
                    <strong>Varovanie:</strong> Odstránením účtu natrvalo vymažete všetky vaše údaje. Túto akciu nie je možné vrátiť späť.
                </p>
                <form method="post" id="delete-account-form" class="user-form">
                    <input type="hidden" name="delete_account" value="1">
                    
                    <div class="form-group">
                        <label for="confirm_email">Pre potvrdenie zadajte svoj email:</label>
                        <input type="email" id="confirm_email" name="confirm_email" required>
                    </div>
                    
                    <div class="user-form-actions">
                        <button type="submit" class="user-delete-button" 
                                onclick="return confirm('Naozaj chcete natrvalo odstrániť svoj účet? Táto akcia je nevratná.')">
                            Odstrániť účet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswords() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Heslá sa nezhodujú.');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
    
    // Address validation
    const addressInput = document.getElementById('address');
    if (addressInput) {
        addressInput.addEventListener('blur', function() {
            const address = this.value.trim();
            if (address.length > 0) {
                const hasLetters = /[a-zA-Z]/.test(address);
                const hasNumbers = /\d/.test(address);
                
                if (!hasLetters || !hasNumbers) {
                    this.setCustomValidity('Adresa musí obsahovať názov ulice aj číslo domu.');
                } else {
                    this.setCustomValidity('');
                }
            }
        });
    }
    
    // Validate email confirmation for account deletion
    const deleteForm = document.getElementById('delete-account-form');
    const confirmEmail = document.getElementById('confirm_email');
    
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            const userEmail = "<?= $userData->email ?>";
            if (confirmEmail.value !== userEmail) {
                e.preventDefault();
                alert('Zadaný email sa nezhoduje s vaším účtom.');
                return false;
            }
            return true;
        });
    }
});
</script>

<?php require_once 'theme/footer.php'; ?>