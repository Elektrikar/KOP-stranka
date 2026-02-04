<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$id = (int)($_POST['id'] ?? 0);
$type = $_POST['type'] ?? 'product';

if (!in_array($type, ['product', 'category', 'order', 'user'])) {
    $type = 'product';
}

if ($id === 0) {
    die("Neplatné ID.");
    header('Location: ' . ($type === 'category' ? 'adminCategories.php' : 'adminProducts.php'));
    exit();
}

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
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
} elseif ($type === 'product') {
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
} elseif ($type === 'order') {
    // Check if order exists
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Location: adminOrders.php');
        exit();
    }

    // Delete order_details first (has foreign key to orders)
    $stmt = $pdo->prepare("DELETE FROM order_details WHERE order_id = ?");
    $stmt->execute([$id]);

    // Delete order
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: adminOrders.php?deleted=1');
    exit();
} elseif ($type === 'user') {
    // Prevent deletion of current user
    if ($id === (int)$_SESSION['user_id']) {
        header('Location: adminUsers.php?error=1');
        exit();
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: adminUsers.php');
        exit();
    }

    try {
        // Delete wishlist entries
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ?");
        $stmt->execute([$id]);

        // Get all orders for this user to delete order_details
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE user_id = ?");
        $stmt->execute([$id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Delete order_details for all orders belonging to this user
        foreach ($orders as $order) {
            $stmt = $pdo->prepare("DELETE FROM order_details WHERE order_id = ?");
            $stmt->execute([$order['id']]);
        }

        // Delete orders
        $stmt = $pdo->prepare("DELETE FROM orders WHERE user_id = ?");
        $stmt->execute([$id]);

        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: adminUsers.php?deleted=1');
        exit();
    } catch (PDOException $e) {
        header('Location: adminUsers.php?error=1');
        exit();
    }
}

