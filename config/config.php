<?php
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = parse_ini_file($envFile);
    if ($lines !== false) {
        foreach ($lines as $key => $value) {
            $_ENV[$key] = $value;
        }
    }
}

// load composer autoload for PHPMailer
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}
?>