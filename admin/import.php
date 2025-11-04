<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../admin.php');
    exit();
}

require_once '../class/Database.php';

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
            $jsonFile = "../webscraping_results/" . basename($file);
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

$jsonFiles = glob("../webscraping_results/*_backup.json");
$jsonFiles = array_map('basename', $jsonFiles);

$pageData = array(
    'title' => 'Import Products | Admin',
    'metaDataDescription' => 'Import products from JSON files'
);
require_once '../theme/header.php';
?>

<div class="admin-panel">
    <div class="admin-header">
        <h1>Import Products</h1>
        <a href="../admin.php" class="back-link">Back to Admin Panel</a>
    </div>

    <?php if ($importMessage): ?>
        <div class="success-message"><?= htmlspecialchars($importMessage) ?></div>
    <?php endif; ?>

    <?php if ($importError): ?>
        <div class="error-message"><?= htmlspecialchars($importError) ?></div>
    <?php endif; ?>

    <form method="post" class="import-form">
        <div class="form-group">
            <h3>Select JSON files to import:</h3>
            <?php foreach ($jsonFiles as $file): ?>
            <div class="checkbox-group">
                <input type="checkbox" name="files[]" value="<?= htmlspecialchars($file) ?>" 
                       id="<?= htmlspecialchars($file) ?>">
                <label for="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></label>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" name="import" class="import-button">Import Selected Files</button>
    </form>
</div>

<style>
.admin-panel {
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.back-link {
    padding: 8px 16px;
    background: #f0f0f0;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
}

.import-form {
    margin-top: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.checkbox-group {
    margin: 10px 0;
}

.checkbox-group label {
    margin-left: 10px;
}

.import-button {
    padding: 10px 20px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.import-button:hover {
    background: #0056b3;
}

.success-message {
    padding: 10px;
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 4px;
    color: #155724;
    margin-bottom: 20px;
}

.error-message {
    padding: 10px;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    color: #721c24;
    margin-bottom: 20px;
}
</style>

<?php require_once '../theme/footer.php'; ?>