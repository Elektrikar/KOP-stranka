<?php
session_start();
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Product.php';
require_once __DIR__ . '/class/Cart.php';
require_once __DIR__ . '/class/ProductCard.php';

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$categoryId = (int)($_GET['id'] ?? 0);
$noCategory = ($categoryId === 0);

$productsPerPage = 16;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $productsPerPage;

if ($noCategory) {
    $sqlCount = "SELECT COUNT(*) as total FROM products";
    $sql = "SELECT * FROM products ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $pageTitle = "Všetky produkty";
    $category = null;
    $metaDescription = 'Prehľad všetkých produktov v našom e-shope';

    $countStmt = $pdo->prepare($sqlCount);
    $countStmt->execute();
    $totalProducts = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $productsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $productsArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $products = array_map(function($data) { return new Product($data); }, $productsArray);
} else {
    $stmt = $pdo->prepare("SELECT c.id, c.name, c.description, c.image FROM categories c WHERE c.id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        header('Location: index.php');
        exit();
    }
    
    $sqlCount = "SELECT COUNT(*) as total FROM products WHERE category_id = :categoryId";
    $sql = "SELECT * FROM products WHERE category_id = :categoryId ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $pageTitle = $category['name'];
    $metaDescription = !empty($category['description']) 
        ? htmlspecialchars($category['description']) 
        : 'Produkty v kategórii ' . $category['name'];

    $countStmt = $pdo->prepare($sqlCount);
    $countStmt->bindValue(':categoryId', $categoryId, PDO::PARAM_INT);
    $countStmt->execute();
    $totalProducts = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':categoryId', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $productsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $productsArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $products = array_map(function($data) { return new Product($data); }, $productsArray);
}

$totalPages = ceil($totalProducts / $productsPerPage);

$cart = new Cart();

$pageData = array(
    'title' => $pageTitle . ' | E-shop',
    'metaDataDescription' => $metaDescription,
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/product.css'),
        array('type' => 'css', 'src' => 'assets/css/products.css'),
        array('type' => 'js', 'src' => 'assets/js/product.js')
    )
);
require_once 'theme/header.php';

$categoryImage = $category ? ('img/category/' . htmlspecialchars($category['image'])) : '';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="index.php">Domov</a>
        <span class="separator">›</span>
        <?php if ($noCategory): ?>
            <span>Všetky produkty</span>
        <?php else: ?>
            <span><?= htmlspecialchars($category['name']) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($noCategory): ?>
        <div class="category-header-with-image" style="background-image: url('img/category/all.jpg');">
            <div class="category-header-overlay"></div>
            <div class="category-header-content">
                <h1 class="category-title">Všetky produkty</h1>
                <div class="category-description-container">
                    <p class="category-description">Prehľad všetkých produktov v e-shope</p>
                </div>
            </div>
        </div>
    <?php else: ?>
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
    <?php endif; ?>

    <!-- Products section -->
    <?php if (empty($products)): ?>
        <div class="empty-category-message">
            <?php if ($noCategory): ?>
                <p>Momentálne nemáme žiadne produkty v ponuke.</p>
                <a href="index.php" class="back-to-categories-btn">Späť na domovskú stránku</a>
            <?php else: ?>
                <p>V tejto kategórii zatiaľ nie sú žiadne produkty.</p>
                <a href="categories.php" class="back-to-categories-btn">Späť na kategórie</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="category-products-section">
            <?= ProductCard::renderGrid($products, $cart, $noCategory ? 'all' : 'category') ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="?<?= $noCategory ? '' : 'id=' . $categoryId . '&' ?>page=<?= $currentPage - 1 ?>" class="pagination-link prev">← Predchádzajúca</a>
            <?php endif; ?>
            
            <div class="pagination-numbers">
                <?php
                $maxPagesToShow = 5;
                $startPage = max(1, $currentPage - floor($maxPagesToShow / 2));
                $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                
                if ($startPage > 1) {
                    echo '<a href="?' . ($noCategory ? '' : 'id=' . $categoryId . '&') . 'page=1" class="pagination-number">1</a>';
                    if ($startPage > 2) echo '<span class="pagination-dots">...</span>';
                }
                
                for ($i = $startPage; $i <= $endPage; $i++):
                    if ($i == $currentPage): ?>
                        <span class="pagination-number active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= $noCategory ? '' : 'id=' . $categoryId . '&' ?>page=<?= $i ?>" class="pagination-number"><?= $i ?></a>
                    <?php endif;
                endfor;
                
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) echo '<span class="pagination-dots">...</span>';
                    echo '<a href="?' . ($noCategory ? '' : 'id=' . $categoryId . '&') . 'page=' . $totalPages . '" class="pagination-number">' . $totalPages . '</a>';
                }
                ?>
            </div>
            
            <?php if ($currentPage < $totalPages): ?>
                <a href="?<?= $noCategory ? '' : 'id=' . $categoryId . '&' ?>page=<?= $currentPage + 1 ?>" class="pagination-link next">Ďalšia →</a>
            <?php endif; ?>
        </div>
        
        <div class="pagination-info">
            Zobrazené <?= min($productsPerPage, count($products)) ?> z <?= $totalProducts ?> produktov
            (Stránka <?= $currentPage ?> z <?= $totalPages ?>)
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'theme/footer.php'; ?>