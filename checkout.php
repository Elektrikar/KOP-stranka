<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Cart.php';

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$cart = new Cart();

if (empty($cart->getItems())) {
    header('Location: cart.php');
    exit();
}

if (!isset($_SESSION['checkout'])) {
    $_SESSION['checkout'] = [
        'address' => '',
        'shipping_method_id' => null,
        'payment_method_id' => null,
        'step' => 1
    ];
}

if (isset($_SESSION['checkout']['address']) && $_SESSION['checkout']['address']) {
    if (isset($_SESSION['checkout']['shipping_method_id']) && $_SESSION['checkout']['payment_method_id']) {
        header('Location: checkout2.php');
    }
} else {
    header('Location: checkout1.php');
}
exit();
?>