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
                    <a href="adminEdit.php?id=<?= $p['id'] ?>" class="btn-edit"><svg width="20px" height="20px" viewBox="0 0 1024 1024"><path d="m199.04 672.64 193.984 112 224-387.968-193.92-112-224 388.032zm-23.872 60.16 32.896 148.288 144.896-45.696L175.168 732.8zM455.04 229.248l193.92 112 56.704-98.112-193.984-112-56.64 98.112zM104.32 708.8l384-665.024 304.768 175.936L409.152 884.8h.064l-248.448 78.336L104.32 708.8zm384 254.272v-64h448v64h-448z"/></svg></a>

                    <form method="post" action="adminDelete.php" class="inline-form"
                          onsubmit="return confirm('Naozaj chcete zmazať produkt?');">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn-delete"><svg width="20px" height="20px" viewBox="0 0 92 92">
                            <path id="XMLID_1348_" d="M78.4,30.4l-3.1,57.8c-0.1,2.1-1.9,3.8-4,3.8H20.7c-2.1,0-3.9-1.7-4-3.8l-3.1-57.8  c-0.1-2.2,1.6-4.1,3.8-4.2c2.2-0.1,4.1,1.6,4.2,3.8l2.9,54h43.1l2.9-54c0.1-2.2,2-3.9,4.2-3.8C76.8,26.3,78.5,28.2,78.4,30.4z   M89,17c0,2.2-1.8,4-4,4H7c-2.2,0-4-1.8-4-4s1.8-4,4-4h22V4c0-1.9,1.3-3,3.2-3h27.6C61.7,1,63,2.1,63,4v9h22C87.2,13,89,14.8,89,17z   M36,13h20V8H36V13z M37.7,78C37.7,78,37.7,78,37.7,78c2,0,3.5-1.9,3.5-3.8l-1-43.2c0-1.9-1.6-3.5-3.6-3.5c-1.9,0-3.5,1.6-3.4,3.6  l1,43.3C34.2,76.3,35.8,78,37.7,78z M54.2,78c1.9,0,3.5-1.6,3.5-3.5l1-43.2c0-1.9-1.5-3.6-3.4-3.6c-2,0-3.5,1.5-3.6,3.4l-1,43.2  C50.6,76.3,52.2,78,54.2,78C54.1,78,54.1,78,54.2,78z"/>
                        </svg></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require 'theme/footer.php'; ?>