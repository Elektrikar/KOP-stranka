<?php
session_start();
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Product.php';
require_once __DIR__ . '/class/Cart.php';
require_once __DIR__ . '/class/ProductCard.php';

$pageData = array(
    'title' => 'Domov | E-shop',
    'metaDataDescription' => 'Domovská stránka e-shopu',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/product.css'),
        array('type' => 'js', 'src' => 'assets/js/product.js')
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
        <h1>Vitajte na našom e-shope</h1>
        <p>Všetky vaše elektronické potreby na jednom mieste.</p>
    </div>
</section>

<div class="container">
    <h2>Top Produkty</h2>
    <?php
    echo ProductCard::renderGrid($products, $cart, 'index');
    ?>
</div>

<?php require_once 'theme/footer.php'; ?>