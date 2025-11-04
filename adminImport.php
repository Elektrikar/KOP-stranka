<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin.php');
    exit();
}

require_once __DIR__ . '/class/Database.php';

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$importMessage = '';
$importError = '';

function sanitizePrice($price) {
    return (float) preg_replace('/[^0-9,.]/', '', str_replace(',', '.', $price));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    try {
        $selectedFiles = $_POST['files'] ?? [];
        $importCount = 0;
        
        foreach ($selectedFiles as $file) {
            $jsonFile = __DIR__ . '/webscraping_results/' . basename($file);
            if (!file_exists($jsonFile)) {
                throw new Exception("File not found: " . htmlspecialchars($file));
            }

            $jsonContent = file_get_contents($jsonFile);
            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON in file: " . htmlspecialchars($file));
            }

            if (!isset($data['product']) || !is_array($data['product'])) {
                throw new Exception("Invalid product data in: " . htmlspecialchars($file));
            }

            $pdo->beginTransaction();

            preg_match('/(\d+)_backup\.json$/', $file, $matches);
            $categoryId = isset($matches[1]) ? (int)$matches[1] : 0;
            
            if ($categoryId === 0) {
                throw new Exception("Could not determine category ID from filename: " . htmlspecialchars($file));
            }

            $stmt = $pdo->prepare("
                INSERT INTO products (category_id, name, price, description, image)
                VALUES (:category_id, :name, :price, :description, :image)
                ON DUPLICATE KEY UPDATE
                category_id = VALUES(category_id),
                name = VALUES(name),
                price = VALUES(price),
                description = VALUES(description),
                image = VALUES(image)
            ");

            foreach ($data['product'] as $product) {
                $price = sanitizePrice($product['price']);
                
                $stmt->execute([
                    'category_id' => $categoryId,
                    'name' => $product['name'],
                    'price' => $price,
                    'image' => $product['img'],
                    'description' => $product['description']
                ]);
                $importCount++;
            }

            $pdo->commit();
        }

        $importMessage = "Successfully imported $importCount products.";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $importError = "Error during import: " . $e->getMessage();
    }
}

$jsonFiles = glob(__DIR__ . '/webscraping_results/*_backup.json');
$jsonFiles = array_map('basename', $jsonFiles);

$pageData = array(
    'title' => 'Importovať Produkty | Admin',
    'metaDataDescription' => 'Importovať produkty z JSON súborov'
);
require_once 'theme/header.php';
?>

<div class="admin-panel">
    <div class="admin-header">
        <h1>Importovať Produkty</h1>
        <a href="admin.php" class="back-link">Späť na Administračný Panel</a>
    </div>

    <?php if ($importMessage): ?>
        <div class="success-message"><?= htmlspecialchars($importMessage) ?></div>
    <?php endif; ?>

    <?php if ($importError): ?>
        <div class="error-message"><?= htmlspecialchars($importError) ?></div>
    <?php endif; ?>

    <form method="post" class="import-form">
        <div class="form-group">
            <h3>Vybrať súbory:</h3>
            <?php foreach ($jsonFiles as $file): ?>
            <div class="checkbox-group">
                <input type="checkbox" name="files[]" value="<?= htmlspecialchars($file) ?>" 
                       id="<?= htmlspecialchars($file) ?>">
                <label for="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></label>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" name="import" class="import-button">Importovať vybraté súbory</button>
    </form>
</div>

<?php require_once 'theme/footer.php'; ?>