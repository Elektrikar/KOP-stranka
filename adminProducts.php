<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/class/Database.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$sql = "
    SELECT 
        p.id, 
        p.name, 
        p.price,
        p.discount_price,
        p.stock, 
        p.views,
        p.sales_count,
        p.created_at,
        p.updated_at,
        p.image, 
        c.name AS category
    FROM products p
    JOIN categories c ON c.id = p.category_id
    ORDER BY p.id DESC
";

$products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$pageData = [
    'title' => 'Správa produktov | Admin',
    'metaDataDescription' => 'Administrácia produktov',
    'customAssets' => [
        ['type' => 'css', 'src' => 'assets/css/adminOrders.css']
    ]
];

require_once 'theme/header.php';
?>

<div class="admin-panel" style="max-width: 1400px;">
    <div class="admin-header">
        <h1>Produkty</h1>
        <a href="adminAdd.php" class="import-button">Pridať produkt</a>
        <a href="admin.php" class="back-link">Späť na Administračný Panel</a>
    </div>

    <table class="admin-orders-table">
        <thead>
            <tr>
                <th>ID</th>
                <th style="width: 60px;">Obrázok</th>
                <th>Názov</th>
                <th>Kategória</th>
                <th>Cena (€)</th>
                <th>Zľava (€)</th>
                <th>Sklad</th>
                <th>Zobrazenia</th>
                <th>Predaje</th>
                <th>Pridané</th>
                <th>Upravené</th>
                <th>Akcie</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p): 
            $discountClass = '';
            if ($p['discount_price'] && $p['discount_price'] < $p['price']) {
                $discountPercent = round((($p['price'] - $p['discount_price']) / $p['price']) * 100);
                $discountClass = 'discount-active';
            }
        ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td>
                    <?php if ($p['image']): ?>
                        <img style="max-height: 60px;" src="img/productsmall/<?= htmlspecialchars($p['image']) ?>" class="table-image">
                    <?php endif; ?>
                </td>
                <td style="max-width: 200px;"><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['category']) ?></td>
                <td>
                    <?= number_format($p['price'], 2, ',', ' ') ?>
                </td>
                <td class="<?= $discountClass ?>">
                    <?php if ($p['discount_price']): ?>
                        <?= number_format($p['discount_price'], 2, ',', ' ') ?>
                        <?php if ($discountPercent): ?>
                            <span class="discount-badge">-<?= $discountPercent ?>%</span>
                        <?php endif; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?= (int)$p['stock'] ?></td>
                <td><?= (int)$p['views'] ?></td>
                <td><?= (int)$p['sales_count'] ?></td>
                <td><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></td>
                <td><?= date('d.m.Y H:i', strtotime($p['updated_at'])) ?></td>
                <td class="actions">
                    <a href="adminEdit.php?id=<?= $p['id'] ?>" class="btn-edit">Zmeniť</a>

                    <form method="post" action="adminDelete.php" class="inline-form"
                          onsubmit="return confirm('Naozaj chcete zmazať produkt?');">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn-delete">Zmazať</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.discount-active {
    color: #f44336;
    font-weight: bold;
}

.discount-badge {
    display: inline-block;
    background: #f44336;
    color: white;
    font-size: 0.8em;
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 5px;
    vertical-align: middle;
}

.admin-orders-table th {
    white-space: nowrap;
}

.admin-orders-table td {
    font-size: 0.9em;
}
</style>

<?php require 'theme/footer.php'; ?>