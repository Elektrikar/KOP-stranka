<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Order.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
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
    SELECT 
        o.*,
        u.first_name,
        u.last_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
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
        ['type' => 'css', 'src' => 'assets/css/adminTables.css']
    ]
];

require_once 'theme/header.php';
?>

<div class="admin-panel" style="max-width: 1400px;">
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

    <?php if (isset($_GET['deleted'])): ?>
        <div class="success-message">Objednávka bola úspešne zmazaná.</div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="empty-orders">
            <p>Žiadne objednávky na zobrazenie.</p>
        </div>
    <?php else: ?>
        <table class="admin-orders-table">
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
                        <td class="actions">
                            <a href="order_details.php?id=<?= $o['id'] ?>" class="btn-view"><svg viewBox="0 0 24 24">
<path  fill-rule="evenodd" clip-rule="evenodd" d="M12.0001 5.25C9.22586 5.25 6.79699 6.91121 5.12801 8.44832C4.28012 9.22922 3.59626 10.0078 3.12442 10.5906C2.88804 10.8825 2.70368 11.1268 2.57736 11.2997C2.51417 11.3862 2.46542 11.4549 2.43187 11.5029C2.41509 11.5269 2.4021 11.5457 2.393 11.559L2.38227 11.5747L2.37911 11.5794L2.10547 12.0132L2.37809 12.4191L2.37911 12.4206L2.38227 12.4253L2.393 12.441C2.4021 12.4543 2.41509 12.4731 2.43187 12.4971C2.46542 12.5451 2.51417 12.6138 2.57736 12.7003C2.70368 12.8732 2.88804 13.1175 3.12442 13.4094C3.59626 13.9922 4.28012 14.7708 5.12801 15.5517C6.79699 17.0888 9.22586 18.75 12.0001 18.75C14.7743 18.75 17.2031 17.0888 18.8721 15.5517C19.72 14.7708 20.4039 13.9922 20.8757 13.4094C21.1121 13.1175 21.2964 12.8732 21.4228 12.7003C21.4859 12.6138 21.5347 12.5451 21.5682 12.4971C21.585 12.4731 21.598 12.4543 21.6071 12.441L21.6178 12.4253L21.621 12.4206L21.6224 12.4186L21.9035 12L21.622 11.5809L21.621 11.5794L21.6178 11.5747L21.6071 11.559C21.598 11.5457 21.585 11.5269 21.5682 11.5029C21.5347 11.4549 21.4859 11.3862 21.4228 11.2997C21.2964 11.1268 21.1121 10.8825 20.8757 10.5906C20.4039 10.0078 19.72 9.22922 18.8721 8.44832C17.2031 6.91121 14.7743 5.25 12.0001 5.25ZM4.29022 12.4656C4.14684 12.2885 4.02478 12.1311 3.92575 12C4.02478 11.8689 4.14684 11.7115 4.29022 11.5344C4.72924 10.9922 5.36339 10.2708 6.14419 9.55168C7.73256 8.08879 9.80369 6.75 12.0001 6.75C14.1964 6.75 16.2676 8.08879 17.8559 9.55168C18.6367 10.2708 19.2709 10.9922 19.7099 11.5344C19.8533 11.7115 19.9753 11.8689 20.0744 12C19.9753 12.1311 19.8533 12.2885 19.7099 12.4656C19.2709 13.0078 18.6367 13.7292 17.8559 14.4483C16.2676 15.9112 14.1964 17.25 12.0001 17.25C9.80369 17.25 7.73256 15.9112 6.14419 14.4483C5.36339 13.7292 4.72924 13.0078 4.29022 12.4656ZM14.25 12C14.25 13.2426 13.2427 14.25 12 14.25C10.7574 14.25 9.75005 13.2426 9.75005 12C9.75005 10.7574 10.7574 9.75 12 9.75C13.2427 9.75 14.25 10.7574 14.25 12ZM15.75 12C15.75 14.0711 14.0711 15.75 12 15.75C9.92898 15.75 8.25005 14.0711 8.25005 12C8.25005 9.92893 9.92898 8.25 12 8.25C14.0711 8.25 15.75 9.92893 15.75 12Z" fill="currentColor"/>
</svg></a>
                            <form method="post" action="adminDelete.php" class="inline-form"
                                  onsubmit="return confirm('Naozaj chcete zmazať túto objednávku?');">
                                <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                <input type="hidden" name="type" value="order">
                                <button type="submit" class="btn-delete"><svg viewBox="0 0 92 92">
                                    <path id="XMLID_1348_" d="M78.4,30.4l-3.1,57.8c-0.1,2.1-1.9,3.8-4,3.8H20.7c-2.1,0-3.9-1.7-4-3.8l-3.1-57.8  c-0.1-2.2,1.6-4.1,3.8-4.2c2.2-0.1,4.1,1.6,4.2,3.8l2.9,54h43.1l2.9-54c0.1-2.2,2-3.9,4.2-3.8C76.8,26.3,78.5,28.2,78.4,30.4z   M89,17c0,2.2-1.8,4-4,4H7c-2.2,0-4-1.8-4-4s1.8-4,4-4h22V4c0-1.9,1.3-3,3.2-3h27.6C61.7,1,63,2.1,63,4v9h22C87.2,13,89,14.8,89,17z   M36,13h20V8H36V13z M37.7,78C37.7,78,37.7,78,37.7,78c2,0,3.5-1.9,3.5-3.8l-1-43.2c0-1.9-1.6-3.5-3.6-3.5c-1.9,0-3.5,1.6-3.4,3.6  l1,43.3C34.2,76.3,35.8,78,37.7,78z M54.2,78c1.9,0,3.5-1.6,3.5-3.5l1-43.2c0-1.9-1.5-3.6-3.4-3.6c-2,0-3.5,1.5-3.6,3.4l-1,43.2  C50.6,76.3,52.2,78,54.2,78C54.1,78,54.1,78,54.2,78z"/>
                                </svg></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

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