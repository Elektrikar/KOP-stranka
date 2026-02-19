<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/User.php';
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Email.php';

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();

$error = '';
$success = '';
$show_verification_message = false;

// Resend verification email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_verification'])) {
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    
    if (empty($email)) {
        $error = 'Email je povinný.';
    } else {
        $user = new User($pdo);
        $existingUser = $user->getByEmail($email);

        if ($existingUser && !$existingUser->is_verified) {
            // Regenerate verification token
            if ($existingUser->resendVerificationToken()) {
                $emailService = new Email($pdo);
                if ($emailService->sendVerificationEmail($email, $first_name . ' ' . $last_name, $existingUser->verification_token)) {
                    $success = 'Nový overovací email bol odoslaný. Skontrolujte svoju emailovú schránku.';
                    $show_verification_message = true;
                } else {
                    $error = 'Nepodarilo sa odoslať overovací email. Skúste to prosím neskôr.';
                }
            } else {
                $error = 'Nepodarilo sa vygenerovať nový overovací token.';
            }
        } else {
            $error = 'Email je už overený alebo neexistuje.';
        }
    }
}

// Normal registration
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['resend_verification'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $role = 'user';

    if (empty($email) || empty($password) || empty($confirm_password) || empty($first_name) || empty($last_name)) {
        $error = 'Všetky polia sú povinné.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Neplatný formát emailu.';
    } elseif (strlen($password) < 8) {
        $error = 'Heslo musí mať aspoň 8 znakov.';
    } elseif ($password !== $confirm_password) {
        $error = 'Heslá sa nezhodujú.';
    } else {
        $user = new User($pdo);
        $existingUser = $user->getByEmail($email);

        if ($existingUser) {
            if (!$existingUser->is_verified) {
                // User exists but not verified - show resend option
                $show_resend_option = true;
                $resend_email = $email;
                $resend_first_name = $first_name;
                $resend_last_name = $last_name;
                
                $error = 'Tento email už existuje ale nie je overený. Želáte si znovu odoslať overovací email?';
            } else {
                $error = 'Email už existuje.';
            }
        } else {
            if ($user->create($email, $password, $first_name, $last_name, $role)) {
                $newUser = $user->getByEmail($email);
                
                if ($newUser) {
                    // Send verification email
                    $emailService = new Email($pdo);
                    if ($emailService->sendVerificationEmail($email, $first_name . ' ' . $last_name, $newUser->verification_token)) {
                        $show_verification_message = true;
                        $success = 'Účet bol úspešne vytvorený! Skontrolujte svoj email pre potvrdenie registrácie.';
                    } else {
                        $error = 'Účet bol vytvorený, ale nepodarilo sa odoslať overovací email. Kontaktujte prosím podporu.';
                    }
                } else {
                    $error = 'Účet bol vytvorený, ale nepodarilo sa načítať údaje. Skúste sa prihlásiť.';
                }
            } else {
                $error = 'Chyba pri vytváraní účtu. Skúste to prosím znova.';
            }
        }
    }
}

$pageData = array(
    'title' => 'Vytvoriť účet | E-shop',
    'metaDataDescription' => 'Vytvorte si nový účet'
);
require_once 'theme/header.php';
?>

<div class="login">
    <h2>Vytvoriť účet</h2>

    <?php if (!empty($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
        
        <?php if (isset($show_resend_option) && $show_resend_option): ?>
            <div class="resend-option" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                <form method="post" id="resend-form">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($resend_email ?? '') ?>">
                    <input type="hidden" name="first_name" value="<?= htmlspecialchars($resend_first_name ?? '') ?>">
                    <input type="hidden" name="last_name" value="<?= htmlspecialchars($resend_last_name ?? '') ?>">
                    <button type="submit" name="resend_verification" class="btn-resend">
                        Áno, odoslať znovu overovací email
                    </button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success-message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!$show_verification_message): ?>
        <form method="post" autocomplete="off" id="registration-form">
            <input type="email" id="email" name="email" placeholder="Email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required autocomplete="email">

            <input type="text" id="first_name" name="first_name" placeholder="Meno"
                value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                required autocomplete="given-name">

            <input type="text" id="last_name" name="last_name" placeholder="Priezvisko"
                value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                required autocomplete="family-name">

            <input type="password" id="password" name="password" placeholder="Heslo"
                required autocomplete="new-password"
                minlength="8">

            <input type="password" id="confirm_password" name="confirm_password" placeholder="Potvrdiť heslo"
                required autocomplete="new-password">

            <button type="submit" class="btn-primary">Zaregistrujte sa</button>

            <div class="security-notice">
                <p style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Už máte účet? <a href="login.php">Prihláste sa</a></p>
                <p style="margin-top: 10px;">Pri vytvorení účtu súhlasíte s <a href="terms.php" target="_blank">obchodnými podmienkami</a> a <a href="privacy_policy.php" target="_blank">ochranou osobných údajov</a>.</p>
            </div>
        </form>
    <?php else: ?>
        <div class="verification-info">
            <p>Poslali sme vám overovací email na adresu: <strong><?= htmlspecialchars($_POST['email'] ?? '') ?></strong></p>
            <p>Pre dokončenie registrácie kliknite na odkaz v emaili.</p>
            <p>Odkaz je platný 24 hodín.</p>
            <div class="verification-actions">
                <p style="margin-top: 15px;">
                    Nedostali ste email?
                    <form method="post" style="display:inline">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <input type="hidden" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        <input type="hidden" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        <button type="submit" name="resend_verification" class="btn-resend">Znovu odoslať</button>
                    </form>
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validate passwords
    const regForm = document.getElementById('registration-form');
    if (regForm) {
        regForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Heslo musí mať aspoň 8 znakov.');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Heslá sa nezhodujú.');
                return false;
            }
            
            return true;
        });
    }
});
</script>

<?php require_once 'theme/footer.php'; ?>