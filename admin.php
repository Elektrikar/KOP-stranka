<?php
session_start();
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/User.php';

$db = new Database('localhost', 'webstore', 'root', '');
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

// Check if user is logged in and has admin role
$loggedIn = false;
$currentUser = null;

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (
        $_SESSION['user_ip'] === $_SERVER['REMOTE_ADDR'] &&
        $_SESSION['user_agent'] === $_SERVER['HTTP_USER_AGENT'] &&
        isset($_SESSION['user_id'])
    ) {
        if (time() - $_SESSION['login_time'] < 3600) {
            // Verify user still exists and has admin role
            $user = new User($db);
            $currentUser = $user->getById($_SESSION['user_id']);
            
            if ($currentUser && $currentUser->role === 'admin') {
                $loggedIn = true;
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

$pageData = array(
    'title' => 'Domov | Admin',
    'metaDataDescription' => 'Administračný panel'
);
require_once 'theme/header.php';
?>

<div class="admin-panel">
    <div class="admin-header">
        <h2>Vitajte, <?= htmlspecialchars($currentUser->first_name . ' ' . $currentUser->last_name) ?>!</h2>
        <a href="?logout=1" class="logout-link" onclick="return confirm('Naozaj sa chcete odhlásiť?')">Odhlásiť sa</a>
    </div>

    <div class="success-message">
        Úspešne ste sa prihlásili ako administrátor
    </div>

    <div class="admin-features">
        <h3>Admin Dashboard</h3>

        <div class="feature-card">
            <h4>User Management</h4>
            <p>Manage users, roles, and permissions</p>
            <?php
            $user = new User($db);
            $allUsers = $user->getAll();
            ?>
            <p>Total Users: <?= count($allUsers) ?></p>
        </div>

        <div class="feature-card">
            <h4>Content Management</h4>
            <p>Edit website content and pages (Database integration needed)</p>
        </div>

        <div class="feature-card">
            <h4>Správa produktov</h4>
            <p><a href="adminAdd.php" class="admin-link">Pridať produkt</a></p>
            <p><a href="adminImport.php" class="admin-link">Importovať hromadne produkty</a></p>
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