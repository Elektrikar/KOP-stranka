<?php
session_start();
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Product.php';
require_once __DIR__ . '/class/Cart.php';

$pageData = array(
    'title' => 'Domov | E-shop',
    'metaDataDescription' => 'Domovská stránka e-shopu',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/productQuantityActions.css'),
        array('type' => 'js', 'src' => 'assets/js/productQuantityActions.js')
    )
);
require_once 'theme/header.php';

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$cart = new Cart();
$products = Product::fetchAll($pdo);
?>

<section class="hero">
    <div class="container">
        <h1>Vitajte  na našom e-shope</h1>
        <p>Všetky vaše elektronické potreby na jednom mieste.</p>
    </div>
</section>

<div class="container">
    <h2>Top Produkty</h2>
    <div class="products">
        <?php
        require_once __DIR__ . '/class/Product.php';
        require_once __DIR__ . '/class/Cart.php';
        $db = new Database('localhost', 'webstore', 'root', '');
        $pdo = $db->getConnection();
        $cart = new Cart();
        $products = Product::fetchAll($pdo);

        // Display products
        foreach ($products as $product) {
            $cartQty = $cart->getQuantity($product->id);
            echo '<div class="product-card" data-product-id="' . htmlspecialchars($product->id) . '">';
                echo '<div class="top">';
                    echo '<img src="' . htmlspecialchars($product->image_path) . '" alt="' . htmlspecialchars($product->name) . '" class="product-image">';
                    echo '<div class="product-info">';
                        echo '<h3 class="product-title">' . htmlspecialchars($product->name) . '</h3>';
                        echo '<p>' . htmlspecialchars($product->description) . '</p>';
                    echo '</div>';
                echo '</div>';
                echo '<div class="bottom">';
                    echo '<div class="product-price">' . number_format($product->price, 2, ',') . ' €</div>';
                    if ($cartQty > 0) {
                        echo cartSummaryHtml($cartQty);
                    } else {
                        echo '<button class="btn btn-cart add-to-cart" data-product-id="' . htmlspecialchars($product->id) . '">Do košíka</button>';
                    }
                echo '</div>';
            echo '</div>';
        }
        function cartSummaryHtml($qty)
        {
            return '<div class="cart-summary">
                        <div class="cart-label">V košíku</div>
                        <div class="cart-container">
                            <button class="cart-minus">-</button>
                            <div class="cart-qty">' . $qty . '</div>
                            <button class="cart-plus">+</button>
                        </div>
                    </div>';
        }
        ?>
    </div>
</div>

<?php require_once 'theme/footer.php'; ?>