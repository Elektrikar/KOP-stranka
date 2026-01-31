<?php
session_start();
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Product.php';
require_once __DIR__ . '/class/Cart.php';
require_once __DIR__ . '/class/ProductCard.php';

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$categoryId = (int)($_GET['id'] ?? 0);
if ($categoryId === 0) {
    header('Location: index.php');
    exit();
}

$stmt = $pdo->prepare("SELECT c.id, c.name, c.description, c.image FROM categories c WHERE c.id = ?");
$stmt->execute([$categoryId]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    header('Location: index.php');
    exit();
}

$products = Product::fetchByCategory($pdo, $categoryId);
$cart = new Cart();

$metaDescription = !empty($category['description']) 
    ? htmlspecialchars($category['description']) 
    : 'Produkty v kategórii ' . $category['name'];

$pageData = array(
    'title' => $category['name'] . ' | E-shop',
    'metaDataDescription' => $metaDescription,
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/product.css'),
        array('type' => 'css', 'src' => 'assets/css/category.css'),
        array('type' => 'js', 'src' => 'assets/js/product.js')
    )
);
require_once 'theme/header.php';

$categoryImage = 'img/category/' . htmlspecialchars($category['image']);
?>

<div class="container">
    <div class="breadcrumb">
        <a href="index.php">Domov</a>
        <span class="separator">›</span>
        <a href="categories.php">Kategórie</a>
        <span class="separator">›</span>
        <span><?= htmlspecialchars($category['name']) ?></span>
    </div>

    <div class="category-header-with-image" style="background-image: url('<?= $categoryImage ?>');">
        <div class="category-header-overlay"></div>
        <div class="category-header-content">
            <h1 class="category-title"><?= htmlspecialchars($category['name']) ?></h1>
            
            <?php if (!empty($category['description'])): ?>
                <div class="category-description-container">
                    <p class="category-description"><?= htmlspecialchars($category['description']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Products section -->
    <?php if (empty($products)): ?>
        <div class="empty-category-message">
            <p>V tejto kategórii zatiaľ nie sú žiadne produkty.</p>
            <a href="categories.php" class="back-to-categories-btn">Späť na kategórie</a>
        </div>
    <?php else: ?>
        <div class="category-products-section">
            <?= ProductCard::renderGrid($products, $cart, 'category') ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'theme/footer.php'; ?>