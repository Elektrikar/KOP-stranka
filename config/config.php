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

function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}
?>