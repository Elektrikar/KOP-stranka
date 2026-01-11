<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Order.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();
$order = new Order($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $status = $_POST['status'];

    if ($order->updateStatus($orderId, $status)) {
        $success = 'Stav objednávky bol aktualizovaný.';
    } else {
        $error = 'Nastala chyba pri aktualizácii stavu.';
    }
}

$orders = $order->getAll();

$pageData = [
    'title' => 'Správa objednávok | Admin',
    'metaDataDescription' => 'Administrácia objednávok',
    'customAssets' => [
        ['type' => 'css', 'src' => 'assets/css/adminOrders.css']
    ]
];

require_once 'theme/header.php';
?>

<div class="admin-orders-page admin-panel">
    <div class="admin-header">
        <h1>Správa objednávok</h1>
        <a href="admin.php" class="back-link">Späť na Administračný Panel</a>
    </div>
    <?php if (isset($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="success-message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="empty-orders">
            <p>Žiadne objednávky na zobrazenie.</p>
        </div>
    <?php else: ?>
        <div class="admin-orders-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Zákazník</th>
                        <th>Email</th>
                        <th>Celková suma</th>
                        <th>Stav</th>
                        <th>Dátum</th>
                        <th>Akcie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="order-id">#<?= str_pad($o['id'], 6, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <div class="customer-name">
                                    <?= htmlspecialchars($o['first_name'] ?? 'Guest') ?>
                                    <?= htmlspecialchars($o['last_name'] ?? '') ?>
                                </div>
                                <?php if ($o['user_id']): ?>
                                    <div class="customer-email">ID: <?= $o['user_id'] ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="customer-email"><?= htmlspecialchars($o['order_email']) ?></td>
                            <td class="order-total"><?= number_format($o['total'], 2, ',', ' ') ?> €</td>
                            <td>
                                <form method="post" class="status-form">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="pending" <?= $o['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="processing" <?= $o['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="shipped" <?= $o['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="delivered" <?= $o['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                        <option value="cancelled" <?= $o['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                            <td class="order-date"><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
                            <td>
                                <a href="order_details.php?id=<?= $o['id'] ?>" class="btn-edit">Zobraziť</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require 'theme/footer.php'; ?>