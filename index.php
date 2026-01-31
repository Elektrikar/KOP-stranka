<?php
session_start();
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Product.php';
require_once __DIR__ . '/class/Cart.php';
require_once __DIR__ . '/class/ProductCard.php';

$pageData = array(
    'title' => 'E-Shop | Domov',
    'metaDataDescription' => 'Nakupujte kvalitnú elektroniku za skvelé ceny. Široký výber smartfónov, notebookov, TV, audio techniky a príslušenstva.',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/product.css'),
        array('type' => 'css', 'src' => 'assets/css/index.css'),
        array('type' => 'js', 'src' => 'assets/js/product.js')
    )
);
require_once 'theme/header.php';

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$cart = new Cart();

$featuredProducts = $pdo->query("
    SELECT * FROM products 
    WHERE stock > 0 
    ORDER BY sales_count DESC, views DESC 
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$newProducts = $pdo->query("
    SELECT * FROM products 
    WHERE stock > 0 
    ORDER BY created_at DESC 
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$discountedProducts = $pdo->query("
    SELECT * FROM products 
    WHERE discount_price IS NOT NULL 
    AND stock > 0 
    ORDER BY (price - discount_price) DESC 
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.stock > 0
    GROUP BY c.id
    ORDER BY c.name
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="hero-section">
    <div class="hero-background-container">
        <picture>
            <source media="(min-width: 1200px)" srcset="img/other/hero.webp" type="image/webp">
            <source media="(min-width: 1200px)" srcset="img/other/hero.jpg">
            <img src="img/other/hero.jpg" alt="ElectroShop - Špičková elektronika pre každý deň" class="hero-background-image" loading="eager" width="1920" height="600">
        </picture>

        <div class="hero-overlay"></div>
    </div>

    <div class="container">
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">Špičková elektronika za bezkonkurenčné ceny</h1>
                <p class="hero-subtitle">Smartfóny, notebooky, audio a príslušenstvo pre každý deň</p>
                <div class="hero-cta">
                    <a href="#featured-products" class="btn btn-primary">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 18l6-6-6-6" />
                        </svg>
                        Nakupovať teraz
                    </a>
                    <a href="categories.php" class="btn btn-secondary">Prezrieť kategórie</a>
                </div>
            </div>

            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number">100+</span>
                    <span class="stat-label">Produktov</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">2 roky</span>
                    <span class="stat-label">Záruka</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </div>
                <h3>Doprava zdarma</h3>
                <p>Pri nákupe nad 100 €</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                </div>
                <h3>Záruka 2 roky</h3>
                <p>Na všetky produkty</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                    </svg>
                </div>
                <h3>Výhodné ceny</h3>
                <p>Pravidelné akcie a zľavy</p>
            </div>

        </div>
    </div>
</section>

<div class="container">
    <!-- Featured Categories -->
    <section class="section-categories">
        <div class="section-header">
            <h2>Populárne kategórie</h2>
            <a href="categories.php" class="view-all">Zobraziť všetky</a>
        </div>
        <div class="categories-grid">
            <?php foreach ($categories as $category): ?>
                <a href="products.php?id=<?= $category['id'] ?>" class="category-card-home">
                    <div class="category-image">
                            <?php
                            $webpPath = str_replace(['.jpg', '.jpeg', '.png'], '.webp', $category['image']);
                            $fullPath = 'img/category/' . htmlspecialchars($category['image']);
                            $webpFullPath = 'img/category/' . htmlspecialchars($webpPath);
                            ?>
                            <picture>
                                <?php if (file_exists(__DIR__ . '/' . $webpFullPath)): ?>
                                    <source srcset="<?= $webpFullPath ?>" type="image/webp">
                                <?php endif; ?>
                                <img src="<?= $fullPath ?>" alt="<?= htmlspecialchars($category['name']) ?>" loading="lazy">
                            </picture>
                    </div>
                    <div class="category-info">
                        <h3><?= htmlspecialchars($category['name']) ?></h3>
                        <p><?= $category['product_count'] ?> produktov</p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="section-products" id="featured-products">
        <div class="section-header">
            <h2>Odporúčané produkty</h2>
            <a href="products.php" class="view-all">Zobraziť všetky</a>
        </div>
        <?php if (!empty($featuredProducts)): ?>
            <div class="products">
                <?php
                foreach ($featuredProducts as $productData) {
                    $product = new Product($productData);
                    $cartQty = $cart->getQuantity($product->id);
                    $card = new ProductCard($product, $cartQty, 'featured');
                    echo $card->render();
                }
                ?>
            </div>
        <?php else: ?>
            <p class="no-products">Momentálne nemáme žiadne odporúčané produkty.</p>
        <?php endif; ?>
    </section>

    <!-- Discount Banner -->
    <?php if (!empty($discountedProducts)): ?>
    <section class="discount-banner">
        <div class="banner-content">
            <div class="banner-text">
                <span class="banner-tag">LIMITOVANÁ PONUKA</span>
                <h2>Jarné zľavy až do -30%</h2>
                <p>Nenechajte si ujsť najlepšie ponuky sezóny</p>
                <a href="#discounted-products" class="btn btn-white">Preskúmať zľavy</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- New Arrivals -->
    <section class="section-products">
        <div class="section-header">
            <h2>Novinky</h2>
            <a href="prodducts.php" class="view-all">Zobraziť všetky</a>
        </div>
        <?php if (!empty($newProducts)): ?>
            <div class="products">
                <?php
                foreach ($newProducts as $productData) {
                    $product = new Product($productData);
                    $cartQty = $cart->getQuantity($product->id);
                    $card = new ProductCard($product, $cartQty, 'new');
                    echo $card->render();
                }
                ?>
            </div>
        <?php else: ?>
            <p class="no-products">Momentálne nemáme žiadne novinky.</p>
        <?php endif; ?>
    </section>

    <!-- Discounted Products -->
    <?php if (!empty($discountedProducts)): ?>
    <section class="section-products" id="discounted-products">
        <div class="section-header">
            <h2>Zľavnené ponuky</h2>
            <a href="#discounted" class="view-all">Zobraziť všetky</a>
        </div>
        <div class="products">
            <?php 
            foreach ($discountedProducts as $productData) {
                $product = new Product($productData);
                $cartQty = $cart->getQuantity($product->id);
                $card = new ProductCard($product, $cartQty, 'discounted');
                echo $card->render();
            }
            ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php require_once 'theme/footer.php'; ?>