<?php
session_start();

if (!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Order.php';

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();

$order = new Order($pdo);
$orders = $order->getByUserId($_SESSION['user_id']);

$pageData = array(
    'title' => 'Moje objednávky | E-shop',
    'metaDataDescription' => 'Prehľad vašich objednávok',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/orders.css')
    )
);
require_once 'theme/header.php';
?>

<div class="container">
    <div class="orders-page">
        <h1>Moje objednávky</h1>

        <?php if (empty($orders)): ?>
            <div class="empty-orders">
                <p>Zatiaľ nemáte žiadne objednávky.</p>
                <a href="index.php" class="btn-shop">Prejsť do obchodu</a>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $orderItem): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <h3>Objednávka #<?= str_pad($orderItem['id'], 6, '0', STR_PAD_LEFT) ?></h3>
                                <span class="order-date">Dátum: <?= date('d.m.Y H:i', strtotime($orderItem['created_at'])) ?></span>
                            </div>
                            <div class="order-status">
                                <span class="status-badge status-<?= $orderItem['status'] ?>"><?= $orderItem['status'] ?></span>
                                <span class="order-total"><?= number_format($orderItem['total'], 2, ',', ' ') ?> €</span>
                            </div>
                        </div>

                        <div class="order-details">
                            <div class="shipping-info">
                                <h4>Dodacia adresa:</h4>
                                <p><?= nl2br(htmlspecialchars($orderItem['address'])) ?></p>
                            </div>

                            <?php
                            $items = $order->getOrderDetails($orderItem['id']);
                            if (!empty($items)):
                            ?>
                                <div class="order-items-preview">
                                    <h4>Produkty:</h4>
                                    <div class="items-grid">
                                        <?php foreach ($items as $item): ?>
                                            <div class="item-preview">
                                                <?php if ($item['image']): ?>
                                                    <img src="img/productsmall/<?= htmlspecialchars($item['image']) ?>"
                                                        alt="<?= htmlspecialchars($item['name']) ?>"
                                                        class="preview-image">
                                                <?php endif; ?>
                                                <div class="preview-info">
                                                    <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                                                    <span class="item-quantity">× <?= $item['quantity'] ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="order-actions">
                            <a href="order_details.php?id=<?= $orderItem['id'] ?>" class="btn-view-details">Zobraziť detaily</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'theme/footer.php'; ?>