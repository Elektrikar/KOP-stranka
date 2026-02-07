<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/User.php';
require_once __DIR__ . '/class/Database.php';

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();

$rateLimitKey = 'login_attempts_' . hash('sha256', $_SERVER['REMOTE_ADDR']);
$maxAttempts = 5;
$lockoutTime = 600;

function checkRateLimit() {
    global $rateLimitKey, $maxAttempts, $lockoutTime;

    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = ['count' => 0, 'time' => time()];
    }

    $attempts = $_SESSION[$rateLimitKey];

    if (time() - $attempts['time'] > $lockoutTime) {
        $_SESSION[$rateLimitKey] = ['count' => 0, 'time' => time()];
        return true;
    }

    return $attempts['count'] < $maxAttempts;
}

function incrementRateLimit() {
    global $rateLimitKey;

    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = ['count' => 1, 'time' => time()];
    } else {
        $_SESSION[$rateLimitKey]['count']++;
    }
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

// Handle resend verification email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_verification'])) {
    $email = sanitizeInput($_POST['email'] ?? '');
    if (!empty($email)) {
        $user = new User($pdo);
        $userData = $user->getByEmail($email);
        
        if ($userData && !$userData->is_verified) {
            // Resend verification email
            require_once __DIR__ . '/class/Email.php';
            $emailService = new Email();
            
            if ($emailService->sendVerificationEmail($email, 
                $userData->first_name . ' ' . $userData->last_name, 
                $userData->verification_token)) {
                
                $success = 'Nový overovací email bol odoslaný na ' . htmlspecialchars($email);
            } else {
                $error = 'Nepodarilo sa odoslať overovací email. Skúste to neskôr.';
            }
        } else {
            $error = 'Email je už overený alebo neexistuje.';
        }
    }
}

// Handle regular login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password']) && isset($_POST['csrf_token'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Overenie bezpečnostného tokenu zlyhalo.';
    } elseif (!checkRateLimit()) {
        $error = 'Príliš veľa pokusov o prihlásenie. Skúste to znova o 15 minút.';
    } else {
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];

        $user = new User($pdo);
        $userData = $user->getByEmail($email);

        if ($userData && $userData->verifyPassword($password)) {
            // Check if email is verified
            if (!$userData->is_verified) {
                $error = 'Vaša emailová adresa nie je overená. Skontrolujte svoj email pre overovací odkaz.';
                incrementRateLimit();
                
                // Store user data for resend option
                $_SESSION['unverified_user'] = [
                    'id' => $userData->id,
                    'email' => $email,
                    'first_name' => $userData->first_name,
                    'last_name' => $userData->last_name
                ];
            } else {
                session_regenerate_id(true);

                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_id'] = $userData->id;
                $_SESSION['user_email'] = $userData->email;
                $_SESSION['user_role'] = $userData->role;
                $_SESSION['user_first_name'] = $userData->first_name;
                $_SESSION['user_last_name'] = $userData->last_name;
                $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['login_time'] = time();

                if ($userData->role === 'admin') {
                    $_SESSION['admin_logged_in'] = true;
                }

                unset($_SESSION[$rateLimitKey]);

                // Redirect based on user role
                if ($userData->role === 'admin') {
                    header('Location: admin.php');
                } else {
                    header('Location: index.php');
                }
                exit();
            }
        } else {
            incrementRateLimit();
            $error = 'Neplatné prihlasovacie údaje.';
        }
    }
}

$csrfToken = generateCSRFToken();

$remainingAttempts = $maxAttempts;
if (isset($_SESSION[$rateLimitKey])) {
    $remainingAttempts = $maxAttempts - $_SESSION[$rateLimitKey]['count'];
    if ($remainingAttempts < 0) {
        $remainingAttempts = 0;
    }
}

$pageData = array(
    'title' => 'Prihlásenie | E-shop',
    'metaDataDescription' => 'Prihlásenie do účtu'
);
require_once 'theme/header.php';
?>

<div class="login">
    <h2>Prihlásenie</h2>

    <?php if (!empty($error)): ?>
        <div class="error-message"><?= $error ?></div>
        
        <?php if (strpos($error, 'nie je overená') !== false): ?>
            <div class="verification-reminder">
                <p>Nedostali ste overovací email?</p>
                <form method="post">
                    <input type="hidden" name="resend_verification" value="1">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <button type="submit" class="btn-resend">Znovu odoslať overovací email</button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success-message"><?= $success ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <input type="email" name="email" placeholder="Email" required autocomplete="off"
            autocapitalize="none" autocorrect="off"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <input type="password" name="password" placeholder="Heslo" required autocomplete="off">

        <button type="submit" <?= !checkRateLimit() ? 'disabled' : '' ?>>Prihlásiť sa</button>

        <div class="security-notice">
            <p>Ešte nemáte účet? <a href="register.php">Zaregistrujte sa</a></p>
        </div>
    </form>
</div>

<script src="assets/js/login.js"></script>

<?php require_once 'theme/footer.php'; ?>