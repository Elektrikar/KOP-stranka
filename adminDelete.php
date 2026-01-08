<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/class/Database.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$id = (int)($_POST['id'] ?? 0);
if ($id === 0) {
    die("Neplatné ID produktu.");
    header('Location: adminProducts.php');
    exit();
}

$db  = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: adminProducts.php');
    exit();
}

if (!empty($product['image'])) {

    $filename = basename($product['image']);

    $bigPath   = __DIR__ . '/img/product/' . $filename;
    $smallPath = __DIR__ . '/img/productsmall/' . $filename;

    if (is_file($bigPath)) {
        unlink($bigPath);
    }

    if (is_file($smallPath)) {
        unlink($smallPath);
    }
}

$stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
$stmt->execute([$id]);

header('Location: adminProducts.php?deleted=1');
exit();
