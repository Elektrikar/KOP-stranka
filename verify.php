<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/User.php';

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();

$error = '';
$success = '';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Chýbajúci overovací token.';
} else {
    $user = new User($pdo);
    $userData = $user->getByVerificationToken($token);
    
    if (!$userData) {
        $error = 'Overovací token je neplatný alebo vypršal.';
    } else {
        if ($userData->verify($token)) {
            $success = 'Vaša emailová adresa bola úspešne overená! Teraz sa môžete prihlásiť.';
        } else {
            $error = 'Nepodarilo sa overiť email. Skúste to prosím znova alebo kontaktujte podporu.';
        }
    }
}

$pageData = array(
    'title' => 'Overenie emailu | E-shop',
    'metaDataDescription' => 'Overenie emailovej adresy'
);
require_once 'theme/header.php';
?>

<div class="login">
    <h2>Overenie emailu</h2>

    <?php if (!empty($error)): ?>
        <div class="error-message"><?= $error ?></div>
        <div class="verification-help">
            <p>Ak máte problémy s overením:</p>
            <ul>
                <li>Pre istotu skontrolujte aj priečinok Spam</li>
                <li>Odkaz je platný 24 hodín od registrácie</li>
                <li>Môžete sa <a href="register.php">zaregistrovať znova</a></li>
                <li>Alebo nás <a href="mailto:info@elektroobchod.online">kontaktovať</a></li>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success-message"><?= $success ?></div>
        <div class="verification-success">
            <a href="login.php" class="btn-primary">Prejsť na prihlásenie</a>
            <p style="margin-top: 15px;">
                Alebo pokračujte v <a href="index.php">nákupe</a>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'theme/footer.php'; ?>