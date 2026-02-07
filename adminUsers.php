<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();

// Pagination and sorting
$usersPerPage = 16;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $usersPerPage;

// Sorting logic
$sort = $_GET['sort'] ?? 'id';
$order_sort = $_GET['order'] ?? 'DESC';

// Validate sort and order parameters
$allowedSortColumns = ['id', 'email', 'first_name', 'last_name', 'role', 'created_at'];
if (!in_array($sort, $allowedSortColumns)) {
    $sort = 'id';
}
if (!in_array($order_sort, ['ASC', 'DESC'])) {
    $order_sort = 'DESC';
}

$nextOrder = ($order_sort === 'ASC') ? 'DESC' : 'ASC';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $userId = (int)$_POST['user_id'];
    $userRole = $_POST['role'];

    // Validate role
    $validRoles = ['user', 'manager', 'admin'];
    if (!in_array($userRole, $validRoles)) {
        $error = 'Neplatná úloha.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = :role, updated_at = NOW() WHERE id = :id");
            $stmt->execute([
                ':role' => $userRole,
                ':id' => $userId
            ]);
            $success = 'Úloha používateľa bola aktualizovaná.';
        } catch (PDOException $e) {
            $error = 'Chyba pri aktualizácii úlohy: ' . $e->getMessage();
        }
    }
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM users";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute();
$totalUsers = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalUsers / $usersPerPage);

// Get paginated and sorted users
$sql = "
    SELECT id, email, first_name, last_name, role, created_at, updated_at
    FROM users
    ORDER BY $sort $order_sort
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $usersPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageData = [
    'title' => 'Správa používateľov | Admin',
    'metaDataDescription' => 'Administrácia používateľov',
    'customAssets' => [
        ['type' => 'css', 'src' => 'assets/css/adminTables.css']
    ]
];

require_once 'theme/header.php';
?>

<div class="admin-panel" style="max-width: 1400px;">
    <div class="admin-header">
        <h1>Správa používateľov</h1>
        <a href="admin.php" class="back-link">Späť na Administračný Panel</a>
    </div>
    <?php if (isset($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="success-message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="error-message">Vyskytla sa chyba pri mazaní.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="success-message">Používateľ bol úspešne zmazaný.</div>
    <?php endif; ?>

    <?php if (empty($users)): ?>
        <div class="empty-orders">
            <p>Žiadni používatelia na zobrazenie.</p>
        </div>
    <?php else: ?>
        <table class="admin-orders-table">
            <thead>
                <tr>
                    <th><a href="?sort=id&order=<?= $sort === 'id' ? $nextOrder : 'ASC' ?>&page=1" class="sort-header <?= $sort === 'id' ? "sort-$order_sort" : '' ?>">ID <?= $sort === 'id' ? ($order_sort === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                    <th><a href="?sort=email&order=<?= $sort === 'email' ? $nextOrder : 'ASC' ?>&page=1" class="sort-header <?= $sort === 'email' ? "sort-$order_sort" : '' ?>">Email <?= $sort === 'email' ? ($order_sort === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                    <th><a href="?sort=first_name&order=<?= $sort === 'first_name' ? $nextOrder : 'ASC' ?>&page=1" class="sort-header <?= $sort === 'first_name' ? "sort-$order_sort" : '' ?>">Meno <?= $sort === 'first_name' ? ($order_sort === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                    <th><a href="?sort=last_name&order=<?= $sort === 'last_name' ? $nextOrder : 'ASC' ?>&page=1" class="sort-header <?= $sort === 'last_name' ? "sort-$order_sort" : '' ?>">Priezvisko <?= $sort === 'last_name' ? ($order_sort === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                    <th><a href="?sort=role&order=<?= $sort === 'role' ? $nextOrder : 'ASC' ?>&page=1" class="sort-header <?= $sort === 'role' ? "sort-$order_sort" : '' ?>">Úloha <?= $sort === 'role' ? ($order_sort === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                    <th><a href="?sort=created_at&order=<?= $sort === 'created_at' ? $nextOrder : 'DESC' ?>&page=1" class="sort-header <?= $sort === 'created_at' ? "sort-$order_sort" : '' ?>">Registrovaný <?= $sort === 'created_at' ? ($order_sort === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                    <th>Akcie</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): 
                    $roleLabel = ucfirst($u['role']);
                ?>
                    <tr>
                        <td class="user-id"><?= $u['id'] ?></td>
                        <td class="user-email"><?= htmlspecialchars($u['email']) ?></td>
                        <td class="user-name"><?= htmlspecialchars($u['first_name']) ?></td>
                        <td class="user-name"><?= htmlspecialchars($u['last_name']) ?></td>
                        <td>
                            <form method="post" class="status-form">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <select name="role" onchange="this.form.submit()">
                                    <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>Používateľ</option>
                                    <option value="manager" <?= $u['role'] === 'manager' ? 'selected' : '' ?>>Manažér</option>
                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Administrátor</option>
                                </select>
                                <input type="hidden" name="update_role" value="1">
                            </form>
                        </td>
                        <td class="user-date"><?= date('d.m.Y H:i', strtotime($u['created_at'])) ?></td>
                        <td class="actions">
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                <form method="post" action="adminDelete.php" class="inline-form"
                                      onsubmit="return confirm('Naozaj chcete zmazať tohto používateľa?');">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="type" value="user">
                                    <button type="submit" class="btn-delete"><svg width="20px" height="20px" viewBox="0 0 92 92">
                                        <path id="XMLID_1348_" d="M78.4,30.4l-3.1,57.8c-0.1,2.1-1.9,3.8-4,3.8H20.7c-2.1,0-3.9-1.7-4-3.8l-3.1-57.8  c-0.1-2.2,1.6-4.1,3.8-4.2c2.2-0.1,4.1,1.6,4.2,3.8l2.9,54h43.1l2.9-54c0.1-2.2,2-3.9,4.2-3.8C76.8,26.3,78.5,28.2,78.4,30.4z   M89,17c0,2.2-1.8,4-4,4H7c-2.2,0-4-1.8-4-4s1.8-4,4-4h22V4c0-1.9,1.3-3,3.2-3h27.6C61.7,1,63,2.1,63,4v9h22C87.2,13,89,14.8,89,17z   M36,13h20V8H36V13z M37.7,78C37.7,78,37.7,78,37.7,78c2,0,3.5-1.9,3.5-3.8l-1-43.2c0-1.9-1.6-3.5-3.6-3.5c-1.9,0-3.5,1.6-3.4,3.6  l1,43.3C34.2,76.3,35.8,78,37.7,78z M54.2,78c1.9,0,3.5-1.6,3.5-3.5l1-43.2c0-1.9-1.5-3.6-3.4-3.6c-2,0-3.5,1.5-3.6,3.4l-1,43.2  C50.6,76.3,52.2,78,54.2,78C54.1,78,54.1,78,54.2,78z"/>
                                    </svg></button>
                                </form>
                            <?php endif; ?>
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
            Zobrazených <?= count($users) ?> z <?= $totalUsers ?> používateľov
            (Stránka <?= $currentPage ?> z <?= $totalPages ?>)
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require 'theme/footer.php'; ?>
