<!DOCTYPE html>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets\js\cartBadge.js"></script>
<?php
// Fill $pageData with defaults if not set
if (!isset($pageData) || !is_array($pageData)) {
    $pageData = array();
}
$pageData = array_merge([
    'title' => 'Shop',
    'metaDataDescription' => 'add meta description here',
    'customAssets' => []
], $pageData);
?>
<html lang="en-US">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageData['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageData['metaDataDescription']); ?>" />
    <link rel="stylesheet" href="assets\css\main.css">
    <?php
    foreach ($pageData['customAssets'] as $asset) {
        if ($asset['type'] === 'js') {
            echo '<script type="text/javascript" src="' . htmlspecialchars($asset['src']) . '"></script>';
        } elseif ($asset['type'] === 'css') {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($asset['src']) . '">';
        }
    }
    ?>
    <!-- Add favicon here -->
</head>

<body>
    <div class="page-wrapper">
        <header>
            <div class="container">
                <nav>
                    <div class="logo"><a href="index.php">Domov</a></div>
                    <ul class="nav-links">
                        <li><a href="#">
                                <p>Produkty</p>
                            </a></li>
                        <li><a href="#">
                                <p>Kategórie</p>
                            </a></li>
                        <li><a href="#">
                                <p>O Nás</p>
                            </a></li>
                        <li><a href="#">
                                <p>Kontakt</p>
                            </a></li>
                        <li style="position:relative;">
                            <?php
                            require_once __DIR__ . '/../class/Cart.php';
                            $cartBadge = '';
                            $cartCount = 0;
                            if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                                foreach ($_SESSION['cart'] as $item) {
                                    $cartCount += $item['quantity'];
                                }
                            }
                            if ($cartCount > 0) {
                                $cartBadge = '<span class="cart-badge">' . $cartCount . '</span>';
                            }
                            ?>
                            <a href="cart.php" style="position:relative;display:inline-block;">
                                <svg class="cart-icon" focusable="false" aria-hidden="true" viewBox="0 0 21 20">
                                    <path d="M6.5 20C5.95 20 5.47917 19.8042 5.0875 19.4125C4.69583 19.0208 4.5 18.55 4.5 18C4.5 17.45 4.69583 16.9792 5.0875 16.5875C5.47917 16.1958 5.95 16 6.5 16C7.05 16 7.52083 16.1958 7.9125 16.5875C8.30417 16.9792 8.5 17.45 8.5 18C8.5 18.55 8.30417 19.0208 7.9125 19.4125C7.52083 19.8042 7.05 20 6.5 20ZM16.5 20C15.95 20 15.4792 19.8042 15.0875 19.4125C14.6958 19.0208 14.5 18.55 14.5 18C14.5 17.45 14.6958 16.9792 15.0875 16.5875C15.4792 16.1958 15.95 16 16.5 16C17.05 16 17.5208 16.1958 17.9125 16.5875C18.3042 16.9792 18.5 17.45 18.5 18C18.5 18.55 18.3042 19.0208 17.9125 19.4125C17.5208 19.8042 17.05 20 16.5 20ZM4.7 2H19.45C19.8333 2 20.125 2.17083 20.325 2.5125C20.525 2.85417 20.5333 3.2 20.35 3.55L16.8 9.95C16.6167 10.2833 16.3708 10.5417 16.0625 10.725C15.7542 10.9083 15.4167 11 15.05 11H7.6L6.5 13H17.5C17.7833 13 18.0208 13.0958 18.2125 13.2875C18.4042 13.4792 18.5 13.7167 18.5 14C18.5 14.2833 18.4042 14.5208 18.2125 14.7125C18.0208 14.9042 17.7833 15 17.5 15H6.5C5.75 15 5.18333 14.6708 4.8 14.0125C4.41667 13.3542 4.4 12.7 4.75 12.05L6.1 9.6L2.5 2H1.5C1.21667 2 0.979167 1.90417 0.7875 1.7125C0.595833 1.52083 0.5 1.28333 0.5 1C0.5 0.716667 0.595833 0.479167 0.7875 0.2875C0.979167 0.0958333 1.21667 0 1.5 0H3.125C3.30833 0 3.48333 0.05 3.65 0.15C3.81667 0.25 3.94167 0.391667 4.025 0.575L4.7 2Z"></path>
                                </svg>
                                <?php echo $cartBadge; ?>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </header>