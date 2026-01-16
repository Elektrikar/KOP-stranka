<?php
session_start();
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Product.php';
require_once __DIR__ . '/class/Cart.php';
require_once __DIR__ . '/class/User.php';
require_once __DIR__ . '/class/ProductCard.php';

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$cart = new Cart();

// Get product ID from URL
$productId = (int)($_GET['id'] ?? 0);
if ($productId === 0) {
    header('Location: index.php');
    exit();
}

// Fetch product details
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON c.id = p.category_id 
    WHERE p.id = ?
");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: index.php');
    exit();
}

// Increment view count
$updateStmt = $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
$updateStmt->execute([$productId]);

// Get related products (same category)
$relatedStmt = $pdo->prepare("
    SELECT * FROM products 
    WHERE category_id = ? AND id != ? 
    AND stock > 0 
    ORDER BY sales_count DESC 
    LIMIT 4
");
$relatedStmt->execute([$product['category_id'], $productId]);
$relatedProducts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

// Check if product is in wishlist (if user is logged in)
$inWishlist = false;
if (isset($_SESSION['user_id'])) {
    $wishlistStmt = $pdo->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?");
    $wishlistStmt->execute([$_SESSION['user_id'], $productId]);
    $inWishlist = $wishlistStmt->fetchColumn() !== false;
}

// Check if product is in cart
$cartQuantity = $cart->getQuantity($productId);
$inCart = $cartQuantity > 0;

// Handle cart actions via AJAX (will be handled by existing cart.js)
$error = '';
$success = '';

// Handle wishlist toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_wishlist'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    
    if ($inWishlist) {
        $removeStmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $removeStmt->execute([$_SESSION['user_id'], $productId]);
        $inWishlist = false;
    } else {
        $addStmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $addStmt->execute([$_SESSION['user_id'], $productId]);
        $inWishlist = true;
    }
}

$pageData = [
    'title' => htmlspecialchars($product['name']) . ' | E-shop',
    'metaDataDescription' => htmlspecialchars(substr($product['description'], 0, 160)),
    'customAssets' => [
        array('type' => 'css', 'src' => 'assets/css/product_detail.css'),
        array('type' => 'js', 'src' => 'assets/js/product_detail.js'),
        array('type' => 'css', 'src' => 'assets/css/product.css'),
        array('type' => 'js', 'src' => 'assets/js/product.js')
    ]
];

require_once 'theme/header.php';
?>

<div class="container">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="index.php">Domov</a>
        <span class="separator">›</span>
        <?php if ($product['category_name']): ?>
            <a href="category.php?id=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a>
            <span class="separator">›</span>
        <?php endif; ?>
        <span><?= htmlspecialchars($product['name']) ?></span>
    </div>

    <div class="product-detail-container">
        <!-- Product Images and Info -->
        <div class="product-main">
            <!-- Images -->
            <div class="product-images">
                <div class="main-image">
                    <img src="img/product/<?= htmlspecialchars($product['image']) ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>" 
                         id="mainProductImage">
                </div>
            </div>

            <!-- Product Info -->
            <div class="product-info">
                <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                
                <!-- Product Meta -->
                <div class="product-meta">
                    <?php if ($product['category_name']): ?>
                        <span class="category">Kategória: 
                            <a href="category.php?id=<?= $product['category_id'] ?>">
                                <?= htmlspecialchars($product['category_name']) ?>
                            </a>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Price -->
                <div class="price-section">
                    <?php if ($product['discount_price'] && $product['discount_price'] < $product['price']): ?>
                        <div class="original-price">
                            <del><?= number_format($product['price'], 2, ',', ' ') ?> €</del>
                            <span class="discount-badge">
                                -<?= round((($product['price'] - $product['discount_price']) / $product['price']) * 100) ?>%
                            </span>
                        </div>
                        <div class="current-price">
                            <span class="price"><?= number_format($product['discount_price'], 2, ',', ' ') ?> €</span>
                        </div>
                    <?php else: ?>
                        <div class="current-price">
                            <span class="price"><?= number_format($product['price'], 2, ',', ' ') ?> €</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Stock status -->
                <div class="stock-status">
                    <?php if ($product['stock'] <= 0): ?>
                        <span class="out-of-stock">Momentálne nedostupné</span>
                    <?php elseif ($product['stock'] <= 5): ?>
                        <span class="low-stock">Posledných <?= $product['stock'] ?> ks</span>
                    <?php else: ?>
                        <span class="in-stock">Skladom <?= $product['stock'] ?> ks</span>
                    <?php endif; ?>
                </div>

                <!-- Cart actions -->
                <?php if ($product['stock'] > 0): ?>
                    <div class="cart-actions" data-product-id="<?= $productId ?>">
                        <?php if ($inCart): ?>
                            <div class="in-cart" id="cart-summary-<?= $productId ?>">
                                <div class="cart-label">Produkt je v košíku</div>
                                <a href="cart.php" class="btn-view-cart">
                                    Zobraziť košík
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="add-to-cart-form" id="add-to-cart-form-<?= $productId ?>">
                                <button type="button" class="btn-add-to-cart add-to-cart-detail" data-product-id="<?= $productId ?>">Vložiť do košíka</button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Add to wishlist -->
                        <form method="post" class="wishlist-form">
                            <button type="submit" name="toggle_wishlist" class="btn-wishlist <?= $inWishlist ? 'active' : '' ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= $inWishlist ? 'currentColor' : 'none' ?>" stroke="currentColor">
                                    <path fill="currentColor" d="M12 20.325q-.349 0-.713-.125a1.65 1.65 0 0 1-.637-.4l-1.725-1.575a68 68 0 0 1-4.788-4.813Q2.002 11.026 2 8.15 2 5.8 3.575 4.225T7.5 2.65q1.325 0 2.5.563t2 1.537a5.96 5.96 0 0 1 2-1.537 5.7 5.7 0 0 1 2.5-.563q2.35 0 3.925 1.575T22 8.15q0 2.875-2.125 5.275a60 60 0 0 1-4.825 4.825l-1.7 1.55a1.65 1.65 0 0 1-.637.4q-.363.125-.713.125"></path>
                                </svg>
                                <?= $inWishlist ? 'Odstrániť zo zoznamu' : 'Pridať do zoznamu' ?>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="out-of-stock-message">
                        <p>Produkt je momentálne nedostupný.</p>
                        <!--  NOTIFY FEATURE - currently disabled
                        Chcete byť upozornený, keď bude opäť na sklade?
                        <button class="btn-notify" data-product-id="<?= $productId ?>">Upozorniť ma</button>-->
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Product description -->
        <div class="product-description-section">
            <h2>Popis produktu</h2>
            <div class="description-content">
                <?= nl2br(htmlspecialchars($product['description'])) ?>
            </div>
        </div>

        <!-- Related products -->
        <div class="product-description-section">
            <h2 style="text-align: center;">Podobné produkty</h2>
            <?php
            if (!empty($relatedProducts)) {
                echo ProductCard::renderRelatedProducts($relatedProducts, $cart, 3);
            }
            ?>
        </div>
    </div>
</div>

<?php require_once 'theme/footer.php'; ?>