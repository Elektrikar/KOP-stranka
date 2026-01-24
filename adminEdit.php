<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/class/Database.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: adminProducts.php');
    exit();
}

// Load product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: adminProducts.php');
    exit();
}

// Load categories
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = trim($_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $stock       = (int)$_POST['stock'];
    $description = trim($_POST['description']);

    // PRICE VALIDATION
    $price = null;
    $priceRaw = str_replace(',', '.', trim($_POST['price']));

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $priceRaw)) {
        $error = 'Cena musí byť číslo (napr. 12.99).';
    } else {
        $price = (float)$priceRaw;
    }

    // DISCOUNT PRICE VALIDATION
    $discountPrice = null;
    $discountPriceRaw = str_replace(',', '.', trim($_POST['discount_price'] ?? ''));

    if ($discountPriceRaw !== '') {
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $discountPriceRaw)) {
            $error = 'Zľavnená cena musí byť číslo (napr. 12.99).';
        } else {
            $discountPrice = (float)$discountPriceRaw;
            if ($discountPrice >= $price) {
                $error = 'Zľavnená cena musí byť nižšia ako bežná cena.';
            }
        }
    }

    if ($error === '') {
        $sql = "
            UPDATE products
            SET category_id = :category_id,
                name = :name,
                price = :price,
                discount_price = :discount_price,
                stock = :stock,
                description = :description,
                updated_at = NOW()
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':category_id' => $category_id,
            ':name'        => $name,
            ':price'       => $price,
            ':discount_price' => $discountPrice,
            ':stock'       => $stock,
            ':description' => $description,
            ':id'          => $id
        ]);

        // Refresh product data after update
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        $success = 'Produkt bol upravený.';
    }
}

require_once 'theme/header.php';
?>

<div class="admin-panel add-product-panel">
    <div class="admin-header">
        <h1>Upraviť produkt</h1>
        <a href="adminProducts.php" class="back-link">Späť na zoznam produktov</a>
    </div>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="product-form">

        <div class="form-group">
            <label>Názov</label>
            <input type="text" name="name" required
                   value="<?= htmlspecialchars($_POST['name'] ?? $product['name']) ?>">
        </div>

        <div class="form-group">
            <label>Kategória</label>
            <select name="category_id" required>
                <option value="">-- Vyberte kategóriu --</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"
                        <?= (($_POST['category_id'] ?? $product['category_id']) == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Cena (€)</label>
            <input type="text" name="price" required
                   value="<?= htmlspecialchars($_POST['price'] ?? $product['price']) ?>">
        </div>

        <div class="form-group">
            <label>Zľavnená cena (€) <small>(voliteľné)</small></label>
            <input type="text" name="discount_price"
                   value="<?= htmlspecialchars($_POST['discount_price'] ?? $product['discount_price']) ?>">
        </div>

        <div class="form-group">
            <label>Množstvo na sklade</label>
            <input type="number" name="stock" min="0"
                   value="<?= (int)($_POST['stock'] ?? $product['stock']) ?>">
        </div>

        <div class="form-group form-group-full">
            <label>Popis</label>
            <textarea name="description" rows="6"><?= htmlspecialchars($_POST['description'] ?? $product['description']) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="import-button">Uložiť zmeny</button>
        </div>
    </form>
</div>

<?php require 'theme/footer.php'; ?>