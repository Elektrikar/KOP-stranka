<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Product.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: login.php');
    exit();
}

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();

$error = '';
$success = '';

$type = $_GET['type'] ?? 'product';
if (!in_array($type, ['product', 'category'])) {
    $type = 'product';
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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($type === 'category') {
        // Create category
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $error = 'Názov kategórie je povinný.';
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

                    $categoryDir = __DIR__ . '/img/category';

                    if (!is_dir($categoryDir)) mkdir($categoryDir, 0755, true);

                    $categoryPath = $categoryDir . '/' . $filename;

                    if (!resizeImage($tmp, $categoryPath, $mime, 500)) {
                        $error = 'Chyba pri spracovaní obrázka.';
                    } else {
                        $imagePath = $filename;
                    }
                }
            } else {
                $error = 'Nastala chyba pri nahrávaní obrázku.';
            }
        }

        if ($error === '') {
            $sql = "INSERT INTO categories (name, description, image)
                    VALUES (:name, :description, :image)";

            $stmt = $pdo->prepare($sql);

            try {
                $stmt->execute([
                    ':name'        => $name,
                    ':description' => $description,
                    ':image'       => $imagePath
                ]);

                $success = 'Kategória bola úspešne pridaná.';
            } catch (PDOException $e) {
                $error = 'Chyba databázy: ' . $e->getMessage();
            }
        }
    } else {
        // Create product
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
            } else {
                $error = 'Nastala chyba pri nahrávaní obrázku.';
            }
        }

        if ($error === '') {
            $sql = "INSERT INTO products (category_id, name, price, discount_price, stock, description, image)
                    VALUES (:category_id, :name, :price, :discount_price, :stock, :description, :image)";

            $stmt = $pdo->prepare($sql);

            try {
                $stmt->execute([
                    ':category_id' => $category_id,
                    ':name'        => $name,
                    ':price'       => $price,
                    ':discount_price' => $discountPrice,
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
}

$pageData = [
    'title' => ($type === 'category' ? 'Pridať Kategóriu' : 'Pridať Produkt') . ' | Admin',
    'metaDataDescription' => $type === 'category' ? 'Pridať novú kategóriu' : 'Pridať nový produkt do e-shopu',
    'customAssets' => [
        ['type' => 'css', 'src' => 'assets/css/adminForms.css']
    ]
];

require_once 'theme/header.php';
?>

<div class="admin-panel add-product-panel">
    <div class="admin-header">
        <h1><?= $type === 'category' ? 'Pridať kategóriu' : 'Pridať produkt' ?></h1>
        <a href="<?= $type === 'category' ? 'adminCategories.php' : 'adminProducts.php' ?>" class="back-link">Späť na <?= $type === 'category' ? 'Kategórie' : 'Produkty' ?></a>
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

        <?php if ($type === 'product'): ?>
        <div class="form-group">
            <label>Kategória</label>
            <select name="category_id" required>
                <option value="">-- Vyberte kategóriu --</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"
                        <?= (($_POST['category_id'] ?? '') == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Cena (€)</label>
            <input type="text" name="price" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Zľavnená cena (€) <small>(voliteľné)</small></label>
            <input type="text" name="discount_price" value="<?= htmlspecialchars($_POST['discount_price'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Množstvo na sklade</label>
            <input type="number" name="stock" min="0" value="<?= (int)($_POST['stock'] ?? 0) ?>">
        </div>
        <?php endif; ?>

        <div class="form-group form-group-full">
            <label>Popis</label>
            <textarea name="description" rows="6"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group form-group-full">
            <label>Obrázok</label>
            <input type="file" name="image" accept="image/*">
            <div style="font-size: 0.85em; color: #666; margin-top: 5px;">
                Povolené formáty: JPG, PNG. Obrázok sa zmenší na 500px.
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="import-button"><?= $type === 'category' ? 'Pridať kategóriu' : 'Pridať produkt' ?></button>
        </div>
    </form>
</div>

<?php require 'theme/footer.php'; ?>