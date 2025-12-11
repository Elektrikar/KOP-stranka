<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Product.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();

$error = '';
$success = '';

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

function resizeImage($srcPath, $destPath, $mime) {
    list($width, $height) = getimagesize($srcPath);

    $maxSize = 500;
    $scale = min($maxSize / $width, $maxSize / $height, 1);

    $newWidth = (int)($width * $scale);
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

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    if ($mime === 'image/jpeg') {
        imagejpeg($dst, $destPath, 90);
    } else {
        imagepng($dst, $destPath, 6);
    }

    unset($src);
    unset($dst);
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $stock       = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $description = trim($_POST['description'] ?? '');

    $price = null;
    $priceRaw = str_replace(',', '.', trim($_POST['price'] ?? ''));

    if ($priceRaw === '') {
        $error = 'Cena je povinná.';
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $priceRaw)) {
        $error = 'Cena musí byť číslo (napr. 12.99).';
    } else {
        $price = (float)$priceRaw;
    }

    if ($name === '' || $category_id === 0  || $stock === 0) {
        $error = 'Názov, kategória a množstvo sú povinné.';
    }

    $imagePath = null;

    if ($error === '' && !empty($_FILES['image']['name'])) {
        $img = $_FILES['image'];

        if ($img['error'] === UPLOAD_ERR_OK) {
            $tmp = $img['tmp_name'];
            $mime = mime_content_type($tmp);
            $allowed = ['image/jpeg', 'image/png'];

            if (!in_array($mime, $allowed, true)) {
                $error = 'Povolené sú iba JPG a PNG obrázky.';
            } else {
                $ext = ($mime === 'image/jpeg') ? 'jpg' : 'png';
                $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', pathinfo($img['name'], PATHINFO_FILENAME));
                $filename = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safeName . '.' . $ext;

                $destDir = __DIR__ . '/img/product';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);

                $dest = $destDir . '/' . $filename;

                if (!resizeImage($tmp, $dest, $mime)) {
                    $error = 'Chyba pri spracovaní obrázka.';
                } else {
                    $imagePath = 'img/product/' . $filename;
                }
            }
        } else {
            $error = 'Nastala chyba pri nahrávaní obrázku.';
        }
    }

    if ($error === '') {
        $sql = "INSERT INTO products (category_id, name, price, stock, description, image)
                VALUES (:category_id, :name, :price, :stock, :description, :image)";

        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute([
                ':category_id' => $category_id,
                ':name'        => $name,
                ':price'       => $price,
                ':stock'       => $stock,
                ':description' => $description,
                ':image'       => $imagePath
            ]);

            $success = 'Produkt bol úspešne pridaný.';
        } catch (PDOException $e) {
            $error = 'Chyba databázy: ' . $e->getMessage();
        }
    }
}

$pageData = [
    'title' => 'Pridať Produkt | Admin',
    'metaDataDescription' => 'Pridať nový produkt do e-shopu',
];

require_once 'theme/header.php';
?>

<div class="admin-panel add-product-panel">
    <div class="admin-header">
        <h1>Pridať produkt</h1>
        <a href="admin.php" class="back-link">Späť na Administračný Panel</a>
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
            <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Kategória</label>
            <select name="category_id" required>
                <option value="">-- Vyberte kategóriu --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"
                        <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Cena (€)</label>
            <input type="text" name="price" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Množstvo na sklade</label>
            <input type="number" name="stock" min="0" value="<?= (int)($_POST['stock'] ?? 0) ?>">
        </div>

        <div class="form-group form-group-full">
            <label>Popis</label>
            <textarea name="description" rows="6"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label>Obrázok</label>
            <input type="file" name="image" accept="image/*">
        </div>

        <div class="form-actions">
            <button type="submit" class="import-button">Pridať produkt</button>
        </div>
    </form>
</div>

<?php require 'theme/footer.php'; ?>