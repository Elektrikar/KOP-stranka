<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/class/Database.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$id = (int)($_POST['id'] ?? 0);
$type = $_POST['type'] ?? 'product';

if (!in_array($type, ['product', 'category'])) {
    $type = 'product';
}

if ($id === 0) {
    die("Neplatné ID.");
    header('Location: ' . ($type === 'category' ? 'adminCategories.php' : 'adminProducts.php'));
    exit();
}

$db  = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

if ($type === 'category') {
    $stmt = $pdo->prepare("SELECT image FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        header('Location: adminCategories.php');
        exit();
    }

    if (!empty($item['image'])) {
        $filename = basename($item['image']);
        $path = __DIR__ . '/img/category/' . $filename;

        if (is_file($path)) {
            unlink($path);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: adminCategories.php?deleted=1');
    exit();
} else {
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
}

