<?php
session_start();

require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Order.php';

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$order = new Order($pdo);

$orderId = (int)($_GET['id'] ?? 0);

if ($orderId === 0) {
    header('Location: index.php');
    exit();
}

$orderData = $order->getById($orderId);
$orderDetails = $order->getOrderDetails($orderId);

if (!$orderData) {
    header('Location: index.php');
    exit();
}

$pageData = array(
    'title' => 'Potvrdenie objednávky | E-shop',
    'metaDataDescription' => 'Potvrdenie vašej objednávky',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/confirmation.css')
    )
);
require_once 'theme/header.php';
?>

<div class="container">
    <div class="confirmation-page">
        <div class="confirmation-header">
            <svg class="confirmation-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <h1>Ďakujeme za vašu objednávku!</h1>
            <p>Vaša objednávka bola úspešne prijatá.</p>
        </div>

        <div class="confirmation-details">
            <div class="detail-card">
                <h3>Informácie o objednávke</h3>
                <div class="detail-row">
                    <span class="detail-label">Číslo objednávky:</span>
                    <span class="detail-value">#<?= str_pad($orderData->id, 6, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Dátum:</span>
                    <span class="detail-value"><?= date('d.m.Y H:i', strtotime($orderData->created_at)) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Stav:</span>
                    <span class="detail-value status-<?= $orderData->status ?>"><?= $orderData->status ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Celková suma:</span>
                    <span class="detail-value"><?= number_format($orderData->total, 2, ',', ' ') ?> €</span>
                </div>
            </div>

            <div class="detail-card">
                <h3>Dodacie údaje</h3>
                <div class="address-box">
                    <?= nl2br(htmlspecialchars($orderData->address)) ?>
                </div>
            </div>

            <?php if (!empty($orderDetails)): ?>
                <div class="detail-card">
                    <h3>Produkty v objednávke</h3>
                    <?php foreach ($orderDetails as $item): ?>
                        <div class="order-item">
                            <?php if ($item['image']): ?>
                                <div class="item-image">
                                    <img src="img/productsmall/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                </div>
                            <?php endif; ?>
                            <div class="item-info">
                                <h4><?= htmlspecialchars($item['name']) ?></h4>
                                <div class="item-meta">
                                    <span>Množstvo: <?= $item['quantity'] ?></span>
                                    <span>Cena: <?= number_format($item['price'], 2, ',', ' ') ?> €</span>
                                    <span>Celkom: <?= number_format($item['price'] * $item['quantity'], 2, ',', ' ') ?> €</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="confirmation-actions">
            <a href="index.php" class="btn-continue">Pokračovať v nákupe</a>
            <?php if (isset($_SESSION['user_logged_in'])): ?>
                <a href="orders.php" class="btn-orders">Zobraziť moje objednávky</a>
            <?php endif; ?>
        </div>
<!--
        <div class="confirmation-help">
            <p>Ak máte akékoľvek otázky ohľadom vašej objednávky, kontaktujte nás na <a href="mailto:info@eshop.sk">info@eshop.sk</a></p>
        </div>-->
    </div>
</div>

<?php require_once 'theme/footer.php'; ?>