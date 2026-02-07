<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: login.php');
    exit();
}

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();

$categoriesPerPage = 16;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $categoriesPerPage;

// Sorting logic
$sort = $_GET['sort'] ?? 'id';
$order = $_GET['order'] ?? 'DESC';

// Validate sort and order parameters
$allowedSortColumns = ['id', 'name', 'created_at'];
if (!in_array($sort, $allowedSortColumns)) {
    $sort = 'id';
}
if (!in_array($order, ['ASC', 'DESC'])) {
    $order = 'DESC';
}

// Build sort column name with table prefix
$sortColumn = "c.$sort";
$nextOrder = ($order === 'ASC') ? 'DESC' : 'ASC';

$sqlCount = "SELECT COUNT(*) as total FROM categories";
$countStmt = $pdo->prepare($sqlCount);
$countStmt->execute();
$totalCategories = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalCategories / $categoriesPerPage);

$sql = "
    SELECT 
        c.id, 
        c.name, 
        c.created_at,
        c.description,
        c.image
    FROM categories c
    ORDER BY $sortColumn $order
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $categoriesPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageData = [
    'title' => 'Správa kategórií | Admin',
    'metaDataDescription' => 'Administrácia kategórií',
    'customAssets' => [
        ['type' => 'css', 'src' => 'assets/css/adminTables.css']
    ]
];

require_once 'theme/header.php';
?>

<div class="admin-panel" style="max-width: 1400px;">
    <div class="admin-header">
        <h1>Kategórie</h1>
        <a href="adminAdd.php?type=category" class="import-button">Pridať kategóriu</a>
        <a href="admin.php" class="back-link">Späť na Administračný Panel</a>
    </div>

    <table class="admin-orders-table">
        <thead>
            <tr>
                <th><a href="?sort=id&order=<?= $sort === 'id' ? $nextOrder : 'ASC' ?>&page=1" class="sort-header <?= $sort === 'id' ? "sort-$order" : '' ?>">ID <?= $sort === 'id' ? ($order === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                <th style="width: 60px;">Obrázok</th>
                <th><a href="?sort=name&order=<?= $sort === 'name' ? $nextOrder : 'ASC' ?>&page=1" class="sort-header <?= $sort === 'name' ? "sort-$order" : '' ?>">Názov <?= $sort === 'name' ? ($order === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                <th>Popis</th>
                <th><a href="?sort=created_at&order=<?= $sort === 'created_at' ? $nextOrder : 'DESC' ?>&page=1" class="sort-header <?= $sort === 'created_at' ? "sort-$order" : '' ?>">Pridané <?= $sort === 'created_at' ? ($order === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                <th>Akcie</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?= $cat['id'] ?></td>
                <td>
                    <?php if ($cat['image']): ?>
                        <img style="max-height: 60px;" src="img/category/<?= htmlspecialchars($cat['image']) ?>" class="table-image">
                    <?php endif; ?>
                </td>
                <td style="max-width: 200px;"><?= htmlspecialchars($cat['name']) ?></td>
                <td style="max-width: 300px;"><?php 
                    $desc = $cat['description'] ?? '';
                    $display = strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                    echo htmlspecialchars($display);
                ?></td>
                <td><?= date('d.m.Y H:i', strtotime($cat['created_at'])) ?></td>
                <td class="actions">
                    <a href="adminEdit.php?id=<?= $cat['id'] ?>&type=category" class="btn-edit"><svg width="20px" height="20px" viewBox="0 0 1024 1024"><path d="m199.04 672.64 193.984 112 224-387.968-193.92-112-224 388.032zm-23.872 60.16 32.896 148.288 144.896-45.696L175.168 732.8zM455.04 229.248l193.92 112 56.704-98.112-193.984-112-56.64 98.112zM104.32 708.8l384-665.024 304.768 175.936L409.152 884.8h.064l-248.448 78.336L104.32 708.8zm384 254.272v-64h448v64h-448z"/></svg></a>

                    <form method="post" action="adminDelete.php" class="inline-form"
                          onsubmit="return confirm('Naozaj chcete zmazať kategóriu?');">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <input type="hidden" name="type" value="category">
                        <button type="submit" class="btn-delete"><svg width="20px" height="20px" viewBox="0 0 92 92">
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
            <a href="?sort=<?= $sort ?>&order=<?= $order ?>&page=<?= $currentPage - 1 ?>" class="pagination-link prev">← Predchádzajúca</a>
        <?php endif; ?>
        
        <div class="pagination-numbers">
            <?php
            $maxPagesToShow = 5;
            $startPage = max(1, $currentPage - floor($maxPagesToShow / 2));
            $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
            
            if ($startPage > 1) {
                echo '<a href="?sort=' . $sort . '&order=' . $order . '&page=1" class="pagination-number">1</a>';
                if ($startPage > 2) echo '<span class="pagination-dots">...</span>';
            }
            
            for ($i = $startPage; $i <= $endPage; $i++):
                if ($i == $currentPage): ?>
                    <span class="pagination-number active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?sort=<?= $sort ?>&order=<?= $order ?>&page=<?= $i ?>" class="pagination-number"><?= $i ?></a>
                <?php endif;
            endfor;
            
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) echo '<span class="pagination-dots">...</span>';
                echo '<a href="?sort=' . $sort . '&order=' . $order . '&page=' . $totalPages . '" class="pagination-number">' . $totalPages . '</a>';
            }
            ?>
        </div>
        
        <?php if ($currentPage < $totalPages): ?>
            <a href="?sort=<?= $sort ?>&order=<?= $order ?>&page=<?= $currentPage + 1 ?>" class="pagination-link next">Ďalšia →</a>
        <?php endif; ?>
    </div>
    
    <div class="pagination-info">
        Zobrazené <?= count($categories) ?> z <?= $totalCategories ?> kategórií
        (Stránka <?= $currentPage ?> z <?= $totalPages ?>)
    </div>
    <?php endif; ?>
</div>

<?php require 'theme/footer.php'; ?>
