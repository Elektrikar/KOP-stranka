<?php
session_start();
require_once __DIR__ . '/class/Database.php';

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

// Get all categories
$categories = $pdo->query("
    SELECT c.id, c.name, c.image
    FROM categories c
    ORDER BY c.name
")->fetchAll(PDO::FETCH_ASSOC);

$pageData = array(
    'title' => 'Kategórie | E-shop',
    'metaDataDescription' => 'Prehľad kategórií produktov',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/categories.css')
    )
);
require_once 'theme/header.php';
?>

<div class="container">
    <div class="categories-page">
        <h1 class="page-title">Kategórie produktov</h1>

        <div class="categories-wrapper">
            <div class="categories-grid">
                <?php foreach ($categories as $category): ?>
                    <div class="category-card" 
                         style="background-image: url('img/category/<?= !empty($category['image']) ? htmlspecialchars($category['image']) : 'default-category.jpg' ?>');">
                        <div class="category-overlay"></div>
                        <div class="category-card-content">
                            <h2 class="category-title"><?= htmlspecialchars($category['name']) ?></h2>
                            <a href="category.php?id=<?= $category['id'] ?>" class="btn-view-category">Zobraziť produkty</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'theme/footer.php'; ?>