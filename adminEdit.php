<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/class/Database.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: adminProducts.php');
    exit();
}

// Load product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: adminProducts.php');
    exit();
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

function resizeImage($srcPath, $destPath, $mime, $maxSize) {
    list($width, $height) = getimagesize($srcPath);

    $scale = min($maxSize / $width, $maxSize / $height, 1);

    $newWidth  = (int)($width * $scale);
    $newHeight = (int)($height * $scale);

    switch ($mime) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($srcPath);
            break;
        case 'image/png':
            $src = imagecreatefrompng($srcPath);
            break;
        default:
            return false;
    }

    $dst = imagecreatetruecolor($newWidth, $newHeight);

    if ($mime === 'image/png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0,
        $newWidth, $newHeight, $width, $height
    );

    if ($mime === 'image/jpeg') {
        imagejpeg($dst, $destPath, 90);
    } else {
        imagepng($dst, $destPath, 6);
    }

    unset($src);
    unset($dst);

    return true;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = trim($_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $stock       = (int)$_POST['stock'];
    $description = trim($_POST['description']);

    // PRICE VALIDATION
    $price = null;
    $priceRaw = str_replace(',', '.', trim($_POST['price']));

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $priceRaw)) {
        $error = 'Cena musí byť číslo (napr. 12.99).';
    } else {
        $price = (float)$priceRaw;
    }

    // DISCOUNT PRICE VALIDATION
    $discountPrice = null;
    $discountPriceRaw = str_replace(',', '.', trim($_POST['discount_price'] ?? ''));

    if ($discountPriceRaw !== '') {
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $discountPriceRaw)) {
            $error = 'Zľavnená cena musí byť číslo (napr. 12.99).';
        } else {
            $discountPrice = (float)$discountPriceRaw;
            if ($discountPrice >= $price) {
                $error = 'Zľavnená cena musí byť nižšia ako bežná cena.';
            }
        }
    }

    // IMAGE UPLOAD HANDLING
    $imagePath = $product['image']; // Keep existing image by default

    if ($error === '' && !empty($_FILES['image']['name'])) {
        $img = $_FILES['image'];

        if ($img['error'] === UPLOAD_ERR_OK) {
            $tmp = $img['tmp_name'];
            $mime = mime_content_type($tmp);
            $allowed = ['image/jpeg', 'image/png'];

            if (!in_array($mime, $allowed, true)) {
                $error = 'Povolené sú iba JPG a PNG obrázky.';
            } else {
                // Delete old images if they exist
                if (!empty($product['image'])) {
                    $oldFilename = basename($product['image']);
                    $oldBigPath = __DIR__ . '/img/product/' . $oldFilename;
                    $oldSmallPath = __DIR__ . '/img/productsmall/' . $oldFilename;
                    
                    if (is_file($oldBigPath)) {
                        unlink($oldBigPath);
                    }
                    if (is_file($oldSmallPath)) {
                        unlink($oldSmallPath);
                    }
                }

                $ext = ($mime === 'image/jpeg') ? 'jpg' : 'png';
                $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', pathinfo($img['name'], PATHINFO_FILENAME));
                $filename = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safeName . '.' . $ext;

                $bigDir   = __DIR__ . '/img/product';
                $smallDir = __DIR__ . '/img/productsmall';

                if (!is_dir($bigDir))   mkdir($bigDir, 0755, true);
                if (!is_dir($smallDir)) mkdir($smallDir, 0755, true);

                $bigPath   = $bigDir . '/' . $filename;
                $smallPath = $smallDir . '/' . $filename;

                if (!resizeImage($tmp, $bigPath, $mime, 500)) {
                    $error = 'Chyba pri spracovaní veľkého obrázka.';
                }
                elseif (!resizeImage($tmp, $smallPath, $mime, 60)) {
                    $error = 'Chyba pri spracovaní malého obrázka.';
                }
                else {
                    $imagePath = $filename;
                }
            }
        } elseif ($img['error'] !== UPLOAD_ERR_NO_FILE) {
            $error = 'Nastala chyba pri nahrávaní obrázku.';
        }
    }

    if ($error === '') {
        $sql = "
            UPDATE products
            SET category_id = :category_id,
                name = :name,
                price = :price,
                discount_price = :discount_price,
                stock = :stock,
                description = :description,
                image = :image,
                updated_at = NOW()
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':category_id' => $category_id,
            ':name'        => $name,
            ':price'       => $price,
            ':discount_price' => $discountPrice,
            ':stock'       => $stock,
            ':description' => $description,
            ':image'       => $imagePath,
            ':id'          => $id
        ]);

        // Refresh product data after update
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        $success = 'Produkt bol upravený.';
    }
}

$pageData = [
    'title' => 'Upraviť Produkt | Admin',
    'metaDataDescription' => 'Upraviť existujúci produkt',
    'customAssets' => [
        ['type' => 'css', 'src' => 'assets/css/adminForms.css']
    ]
];

require_once 'theme/header.php';
?>

<div class="admin-panel add-product-panel">
    <div class="admin-header">
        <h1>Upraviť produkt</h1>
        <a href="adminProducts.php" class="back-link">Späť na zoznam produktov</a>
    </div>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="product-form">

        <div class="form-group">
            <label>Názov</label>
            <input type="text" name="name" required
                   value="<?= htmlspecialchars($_POST['name'] ?? $product['name']) ?>">
        </div>

        <div class="form-group">
            <label>Kategória</label>
            <select name="category_id" required>
                <option value="">-- Vyberte kategóriu --</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"
                        <?= (($_POST['category_id'] ?? $product['category_id']) == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Cena (€)</label>
            <input type="text" name="price" required
                   value="<?= htmlspecialchars($_POST['price'] ?? $product['price']) ?>">
        </div>

        <div class="form-group">
            <label>Zľavnená cena (€) <small>(voliteľné)</small></label>
            <input type="text" name="discount_price"
                   value="<?= htmlspecialchars($_POST['discount_price'] ?? $product['discount_price']) ?>">
        </div>

        <div class="form-group">
            <label>Množstvo na sklade</label>
            <input type="number" name="stock" min="0"
                   value="<?= (int)($_POST['stock'] ?? $product['stock']) ?>">
        </div>

        <div class="form-group form-group-full">
            <label>Popis</label>
            <textarea name="description" rows="6"><?= htmlspecialchars($_POST['description'] ?? $product['description']) ?></textarea>
        </div>

        <div class="form-group form-group-full">
            <label>Aktuálny obrázok</label>
            <?php if ($product['image']): ?>
                <div style="margin: 10px 0;">
                    <img src="img/productsmall/<?= htmlspecialchars($product['image']) ?>" 
                         alt="Aktuálny obrázok" 
                         style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; padding: 5px; border-radius: 4px;">
                    <div style="margin-top: 5px; font-size: 0.9em; color: #666;">
                        Aktuálny obrázok: <?= htmlspecialchars($product['image']) ?>
                    </div>
                </div>
            <?php else: ?>
                <div style="margin: 10px 0; color: #666; font-style: italic;">
                    Produkt nemá priradený obrázok.
                </div>
            <?php endif; ?>
            
            <label style="margin-top: 15px; display: block;">Nový obrázok <small>(voliteľné)</small></label>
            <input type="file" name="image" accept="image/*">
            <div style="font-size: 0.85em; color: #666; margin-top: 5px;">
                Povolené formáty: JPG, PNG. Veľký obrázok sa zmenší na 500px, malý na 60px.
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="import-button">Uložiť zmeny</button>
        </div>
    </form>
</div>

<?php require 'theme/footer.php'; ?>