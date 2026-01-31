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

// Pagination and sorting
$ordersPerPage = 16;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $ordersPerPage;

// Sorting logic
$sort = $_GET['sort'] ?? 'created_at';
$order_sort = $_GET['order'] ?? 'DESC';

// Validate sort and order parameters
$allowedSortColumns = ['id', 'order_email', 'total', 'status', 'created_at'];
if (!in_array($sort, $allowedSortColumns)) {
    $sort = 'created_at';
}
if (!in_array($order_sort, ['ASC', 'DESC'])) {
    $order_sort = 'DESC';
}

$nextOrder = ($order_sort === 'ASC') ? 'DESC' : 'ASC';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $orderStatus = $_POST['status'];

    if ($order->updateStatus($orderId, $orderStatus)) {
        $success = 'Stav objednávky bol aktualizovaný.';
    } else {
        $error = 'Nastala chyba pri aktualizácii stavu.';
    }
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM orders";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute();
$totalOrders = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalOrders / $ordersPerPage);

// Get paginated and sorted orders
$sql = "
    SELECT * FROM orders
    ORDER BY $sort $order_sort
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $ordersPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <th><a href="?sort=id&order=<?= $sort === 'id' ? $nextOrder : 'ASC' ?>&page=1" class="sort-header <?= $sort === 'id' ? "sort-$order_sort" : '' ?>">ID <?= $sort === 'id' ? ($order_sort === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th>Zákazník</th>
                        <th><a href="?sort=order_email&order=<?= $sort === 'order_email' ? $nextOrder : 'ASC' ?>&page=1" class="sort-header <?= $sort === 'order_email' ? "sort-$order_sort" : '' ?>">Email <?= $sort === 'order_email' ? ($order_sort === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="?sort=total&order=<?= $sort === 'total' ? $nextOrder : 'DESC' ?>&page=1" class="sort-header <?= $sort === 'total' ? "sort-$order_sort" : '' ?>">Celková suma <?= $sort === 'total' ? ($order_sort === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="?sort=status&order=<?= $sort === 'status' ? $nextOrder : 'ASC' ?>&page=1" class="sort-header <?= $sort === 'status' ? "sort-$order_sort" : '' ?>">Stav <?= $sort === 'status' ? ($order_sort === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="?sort=created_at&order=<?= $sort === 'created_at' ? $nextOrder : 'DESC' ?>&page=1" class="sort-header <?= $sort === 'created_at' ? "sort-$order_sort" : '' ?>">Dátum <?= $sort === 'created_at' ? ($order_sort === 'ASC' ? '↑' : '↓') : '' ?></a></th>
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
                                <a href="order_details.php?id=<?= $o['id'] ?>" class="btn-view">Zobraziť</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="?sort=<?= $sort ?>&order=<?= $order_sort ?>&page=<?= $currentPage - 1 ?>" class="pagination-link prev">← Predchádzajúca</a>
            <?php endif; ?>
            
            <div class="pagination-numbers">
                <?php
                $maxPagesToShow = 5;
                $startPage = max(1, $currentPage - floor($maxPagesToShow / 2));
                $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                
                if ($startPage > 1) {
                    echo '<a href="?sort=' . $sort . '&order=' . $order_sort . '&page=1" class="pagination-number">1</a>';
                    if ($startPage > 2) echo '<span class="pagination-dots">...</span>';
                }
                
                for ($i = $startPage; $i <= $endPage; $i++):
                    if ($i == $currentPage): ?>
                        <span class="pagination-number active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?sort=<?= $sort ?>&order=<?= $order_sort ?>&page=<?= $i ?>" class="pagination-number"><?= $i ?></a>
                    <?php endif;
                endfor;
                
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) echo '<span class="pagination-dots">...</span>';
                    echo '<a href="?sort=' . $sort . '&order=' . $order_sort . '&page=' . $totalPages . '" class="pagination-number">' . $totalPages . '</a>';
                }
                ?>
            </div>
            
            <?php if ($currentPage < $totalPages): ?>
                <a href="?sort=<?= $sort ?>&order=<?= $order_sort ?>&page=<?= $currentPage + 1 ?>" class="pagination-link next">Ďalšia →</a>
            <?php endif; ?>
        </div>
        
        <div class="pagination-info">
            Zobrazené <?= count($orders) ?> z <?= $totalOrders ?> objednávok
            (Stránka <?= $currentPage ?> z <?= $totalPages ?>)
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require 'theme/footer.php'; ?>