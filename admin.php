<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/User.php';

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();

// Logout functionality
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
    header('Location: login.php');
    exit();
}

// Check if user is logged in and has admin or manager role
$loggedIn = false;
$currentUser = null;
$showLoginSuccess = false;

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (
        $_SESSION['user_ip'] === $_SERVER['REMOTE_ADDR'] &&
        $_SESSION['user_agent'] === $_SERVER['HTTP_USER_AGENT'] &&
        isset($_SESSION['user_id'])
    ) {
        if (time() - $_SESSION['login_time'] < 3600) {
            // Verify user still exists and has admin or manager role
            $user = new User($db);
            $currentUser = $user->getById($_SESSION['user_id']);

            if ($currentUser && in_array($currentUser->role, ['admin', 'manager'])) {
                $loggedIn = true;
                
                // Check if this is the first page load after login
                if (!isset($_SESSION['already_shown_welcome'])) {
                    $showLoginSuccess = true;
                    $_SESSION['already_shown_welcome'] = true;
                }
                
                $_SESSION['login_time'] = time();
            } else {
                session_destroy();
                header('Location: login.php');
                exit();
            }
        } else {
            session_destroy();
            header('Location: login.php');
            exit();
        }
    } else {
        session_destroy();
        header('Location: login.php');
        exit();
    }
}

// If not logged in, redirect to login
if (!$loggedIn) {
    header('Location: login.php');
    exit();
}

$isAdmin = ($currentUser->role === 'admin');
$isManager = ($currentUser->role === 'manager');

$pageData = array(
    'title' => 'Domov | ' . ($isAdmin ? 'Admin' : 'Manažér'),
    'metaDataDescription' => 'Administračný panel'
);
require_once 'theme/header.php';
?>

<div class="admin-panel">
    <div class="admin-header">
        <h2>Vitajte, <?= htmlspecialchars($currentUser->first_name . ' ' . $currentUser->last_name) ?>!</h2>
        <a href="?logout=1" class="logout-link" onclick="return confirm('Naozaj sa chcete odhlásiť?')">Odhlásiť sa</a>
    </div>

    <?php if ($showLoginSuccess): ?>
        <div class="success-message">
            Úspešne ste sa prihlásili ako <?= $isAdmin ? 'administrátor' : 'manažér' ?>
        </div>
    <?php endif; ?>

    <div class="admin-features">
        <h3><?= $isAdmin ? 'Administračný panel' : 'Manažérsky panel' ?></h3>

        <?php if ($isAdmin): ?>
            <div class="feature-card">
                <h4>Správa používateľov</h4>
                <p><a href="adminUsers.php" class="admin-link">Používatelia</a></p>
            </div>
        <?php endif; ?>

        <div class="feature-card">
            <h4>Správa objednávok</h4>
            <p><a href="adminOrders.php" class="admin-link">Objednávky</a></p>
        </div>

        <div class="feature-card">
            <h4>Správa produktov</h4>
            <p><a href="adminProducts.php" class="admin-link">Produkty</a></p>
            <p><a href="adminCategories.php" class="admin-link">Kategórie</a></p>
        </div>

        <div class="feature-card">
            <h4>Systémove Informácie</h4>
            <p>Server:
                <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE']) ?>
            </p>
            <p>PHP Verzia: <?= phpversion() ?></p>
            <p>Prihlásený z:
                <?= htmlspecialchars($_SESSION['user_ip']) ?>
            </p>
            <p>Užívateľ: <?= htmlspecialchars($currentUser->email) ?></p>
            <p>Rola: <?= $isAdmin ? 'Administrátor' : 'Manažér' ?></p>
        </div>
    </div>

    <div class="security-notice">
        Budete odhlásený o:
        <?= ceil((3600 - (time() - $_SESSION['login_time'])) / 60) ?>
        minút<br>
        Posledná aktivita:
        <?= date('d-m-Y H:i:s', $_SESSION['login_time']) ?>
    </div>
</div>

<script>
    let warningShown = false;

    function checkSessionTimeout() {
        const timeLeft = 3600 - (<?= time() ?> -
            <?= $_SESSION['login_time'] ?>);
        const minutesLeft = Math.ceil(timeLeft / 60);

        if (minutesLeft <= 5 && !warningShown) {
            warningShown = true;
            if (confirm('Vaša relácia vyprší o ' + minutesLeft + ' minút. Chcete ju predĺžiť?')) {
                location.reload();
            }
        }
    }

    setInterval(checkSessionTimeout, 60000);
</script>

<?php require_once 'theme/footer.php'; ?>