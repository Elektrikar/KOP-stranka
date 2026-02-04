<?php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Order.php';

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
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

// Check permission
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$isOwner = isset($_SESSION['user_id']) && $orderData->user_id == $_SESSION['user_id'];

if (!$isAdmin && !$isOwner) {
    header('Location: index.php');
    exit();
}

$pageData = array(
    'title' => 'Detail objednávky | E-shop',
    'metaDataDescription' => 'Detailné informácie o objednávke',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/order_details.css')
    )
);
require_once 'theme/header.php';
?>

<div class="container">
    <div class="order-details-container">
        <div class="order-details-header">
            <h1>Detail objednávky #<?= str_pad($orderData->id, 6, '0', STR_PAD_LEFT) ?></h1>
            <a href="<?= $isAdmin ? 'adminOrders.php' : 'orders.php' ?>" class="back-link">Späť na zoznam</a>
        </div>

        <div class="order-info-grid">
            <div class="info-card">
                <h3>Informácie o objednávke</h3>
                <div class="info-row">
                    <span class="info-label">Číslo objednávky:</span>
                    <span class="info-value">#<?= str_pad($orderData->id, 6, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Dátum vytvorenia:</span>
                    <span class="info-value"><?= date('d.m.Y H:i', strtotime($orderData->created_at)) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Posledná aktualizácia:</span>
                    <span class="info-value"><?= date('d.m.Y H:i', strtotime($orderData->updated_at)) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Stav:</span>
                    <span class="info-value status-<?= $orderData->status ?>"><?= $orderData->status ?></span>
                </div>
            </div>

            <div class="info-card">
                <h3>Zákaznícke informácie</h3>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= htmlspecialchars($orderData->order_email) ?></span>
                </div>
                <?php if ($orderData->user_id): ?>
                    <div class="info-row">
                        <span class="info-label">ID používateľa:</span>
                        <span class="info-value"><?= $orderData->user_id ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-card full-width">
                <h3>Dodacia adresa</h3>
                <div class="address-display">
                    <?= nl2br(htmlspecialchars($orderData->address)) ?>
                </div>
            </div>
        </div>

        <div class="order-items-table">
            <h3>Produkty v objednávke</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Produkt</th>
                        <th>Cena</th>
                        <th>Množstvo</th>
                        <th>Celkom</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderDetails as $item): ?>
                        <tr>
                            <td>
                                <div class="product-cell">
                                    <?php if ($item['image']): ?>
                                        <img src="img/productsmall/<?= htmlspecialchars($item['image']) ?>"
                                            alt="<?= htmlspecialchars($item['name']) ?>">
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($item['name']) ?></span>
                                </div>
                            </td>
                            <td><?= number_format($item['price'], 2, ',', ' ') ?> €</td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= number_format($item['price'] * $item['quantity'], 2, ',', ' ') ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right"><strong>Celková suma:</strong></td>
                        <td class="total-cell"><?= number_format($orderData->total, 2, ',', ' ') ?> €</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if ($isAdmin): ?>
            <div class="admin-actions">
                <h3>Administratívne akcie</h3>
                <form method="post" action="adminOrders.php" class="status-update-form">
                    <input type="hidden" name="order_id" value="<?= $orderData->id ?>">
                    <div class="form-group">
                        <label for="status">Zmeniť stav:</label>
                        <select name="status" id="status">
                            <option value="pending" <?= $orderData->status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="processing" <?= $orderData->status === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="shipped" <?= $orderData->status === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="delivered" <?= $orderData->status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $orderData->status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <button type="submit" name="update_status" value="1" class="btn-update">Aktualizovať</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'theme/footer.php'; ?>