<?php
session_start();
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

if (isset($_POST['email']) && isset($_POST['password']) && isset($_POST['csrf_token'])) {
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
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <input type="email" name="email" placeholder="Email" required autocomplete="off"
            autocapitalize="none" autocorrect="off">

        <input type="password" name="password" placeholder="Heslo" required autocomplete="off">

        <button type="submit" <?= !checkRateLimit() ? 'disabled' : '' ?>>Prihlásiť sa</button>

        <div class="security-notice">
            <p>Ešte nemáte účet? <a href="register.php">Zaregistrujte sa</a></p>
        </div>
    </form>
</div>

<script src="assets/js/login.js"></script>

<?php require_once 'theme/footer.php'; ?>