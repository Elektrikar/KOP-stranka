<?php
session_start();
require_once __DIR__ . '/class/User.php';
require_once __DIR__ . '/class/Database.php';

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $role = 'user'; // Default role

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
            $error = 'Email už existuje.';
        } else {
            if ($user->create($email, $password, $first_name, $last_name, $role)) {
                $success = 'Účet bol úspešne vytvorený. Teraz sa môžete prihlásiť.';
                $email = $first_name = $last_name = '';
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
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success-message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
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
            <p>Už máte účet? <a href="login.php">Prihláste sa</a></p>
        </div>
    </form>
</div>


<script src="assets/js/register.js"></script>

<?php require_once 'theme/footer.php'; ?>