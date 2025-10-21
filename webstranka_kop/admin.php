<?php
session_start();

// create users db table
// NON PRODUCTION: For demo purposes only
$adminUser = 'admin';
$adminPass = 'admin'; // Change this to a strong password

$rateLimitKey = 'login_attempts_' . hash('sha256', $_SERVER['REMOTE_ADDR']);
$maxAttempts = 5;
$lockoutTime = 900;

function checkRateLimit()
{
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

function incrementRateLimit()
{
    global $rateLimitKey;

    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = ['count' => 1, 'time' => time()];
    } else {
        $_SESSION[$rateLimitKey]['count']++;
    }
}

function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}


if (isset($_POST['username']) && isset($_POST['password']) && isset($_POST['csrf_token'])) {

    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Overenie bezpečnostného tokenu zlyhalo.';
    } elseif (!checkRateLimit()) {
        $error = 'Príliš veľa pokusov o prihlásenie. Skúste to znova o 15 minút.';
    } else {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];

        // Add db credential check here
        if ($username === $adminUser && $password === $adminPass) {
            session_regenerate_id(true);

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['login_time'] = time();

            unset($_SESSION[$rateLimitKey]);

            header('Location: admin.php');
            exit();
        } else {
            incrementRateLimit();
            $error = 'Neplatné prihlasovacie údaje.';
        }
    }
}

if (isset($_GET['logout'])) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
    header('Location: admin.php');
    exit();
}

$loggedIn = false;
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (
        $_SESSION['user_ip'] === $_SERVER['REMOTE_ADDR'] &&
        $_SESSION['user_agent'] === $_SERVER['HTTP_USER_AGENT']
    ) {

        if (time() - $_SESSION['login_time'] < 3600) {
            $loggedIn = true;
            $_SESSION['login_time'] = time();
        } else {
            session_destroy();
            header('Location: admin.php');
            exit();
        }
    } else {
        session_destroy();
        header('Location: admin.php');
        exit();
    }
}

$csrfToken = generateCSRFToken();

$remainingAttempts = $maxAttempts;
if (isset($_SESSION[$rateLimitKey])) {
    $remainingAttempts = $maxAttempts - $_SESSION[$rateLimitKey]['count'];
    if ($remainingAttempts < 0) $remainingAttempts = 0;
}


$pageData = array(
    'title' => 'Admin | E-shop',
    'metaDataDescription' => 'Administračný panel'
);
require_once 'theme/header.php';
?>

<?php if (!$loggedIn): ?>
    <form class="admin-login" method="post" autocomplete="off">
        <h2>Admin Prihlásenie</h2>
        <?php if (!empty($error)): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION[$rateLimitKey]) && $_SESSION[$rateLimitKey]['count'] > 0): ?>
            <div class="attempts-warning">
                Ostávajúce pokusy: <?= $remainingAttempts ?> z <?= $maxAttempts ?>
            </div>
        <?php endif; ?>

        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <input type="text" name="username" placeholder="Prihlasovacie meno" required
            autocomplete="off" autocapitalize="none" autocorrect="off">
        <input type="password" name="password" placeholder="Heslo" required
            autocomplete="off">
        <button type="submit" <?= !checkRateLimit() ? 'disabled' : '' ?>>Prihlásiť sa</button>

        <div class="security-notice">
            Z bezpečnostných dôvodov sa prosím odhláste po dokončení.
        </div>
    </form>

<?php else: ?>
    <div class="admin-panel">
        <a href="?logout=1" class="logout-link" onclick="return confirm('Naozaj sa chcete odhlásiť?')">Odhlásiť sa</a>
        <h2>Vitajte, Admin!</h2>

        <div class="success-message">
            Úspešne ste sa prihlásili ako administrátor
        </div>

        <div class="admin-features">
            <h3>Admin Dashboard</h3>

            <div class="feature-card">
                <h4>User Management</h4>
                <p>Manage users, roles, and permissions (Database integration needed)</p>
            </div>

            <div class="feature-card">
                <h4>Content Management</h4>
                <p>Edit website content and pages (Database integration needed)</p>
            </div>

            <div class="feature-card">
                <h4>Systémove Informácie</h4>
                <p>Server: <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE']) ?></p>
                <p>PHP Verzia: <?= phpversion() ?></p>
                <p>Prihlásený z: <?= htmlspecialchars($_SESSION['user_ip']) ?></p>
            </div>
        </div>

        <div class="security-notice">
            Budete odhlásený o: <?= ceil((3600 - (time() - $_SESSION['login_time'])) / 60) ?> minút<br>
            Posledná aktivita: <?= date('Y-m-d H:i:s', $_SESSION['login_time']) ?>
        </div>
    </div>
<?php endif; ?>

<script>
    // Client-side validation
    document.querySelector('form')?.addEventListener('submit', function(e) {
        const username = this.querySelector('input[name="username"]').value.trim();
        const password = this.querySelector('input[name="password"]').value;

        if (!username || !password) {
            e.preventDefault();
            alert('Prosím vyplňte všetky polia.');
        }
    });

    <?php if ($loggedIn): ?>
        let warningShown = false;

        function checkSessionTimeout() {
            const timeLeft = 3600 - (<?= time() ?> - <?= $_SESSION['login_time'] ?>);
            const minutesLeft = Math.ceil(timeLeft / 60);

            if (minutesLeft <= 5 && !warningShown) {
                warningShown = true;
                if (confirm('Vaša relácia vyprší o ' + minutesLeft + ' minút. Chcete ju predĺžiť?')) {
                    location.reload();
                }
            }
        }

        setInterval(checkSessionTimeout, 60000);
    <?php endif; ?>
</script>

<?php require_once 'theme/footer.php'; ?>