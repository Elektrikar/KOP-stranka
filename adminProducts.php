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
    SELECT p.id, p.name, p.price, p.stock, p.image, c.name AS category
    FROM products p
    JOIN categories c ON c.id = p.category_id
    ORDER BY p.id DESC
";

$products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

require_once 'theme/header.php';
?>

<div class="admin-panel" style="max-width: 1200px;">
    <div class="admin-header">
        <h1>Produkty</h1>
        <a href="adminAdd.php" class="import-button">Pridať produkt</a>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Obrázok</th>
                <th>Názov</th>
                <th>Kategória</th>
                <th>Cena (€)</th>
                <th>Sklad</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td>
                    <?php if ($p['image']): ?>
                        <img src="img/productsmall/<?= htmlspecialchars($p['image']) ?>" class="table-image">
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['category']) ?></td>
                <td><?= number_format($p['price'], 2, ',', ' ') ?></td>
                <td><?= (int)$p['stock'] ?></td>
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

<?php require 'theme/footer.php'; ?>
