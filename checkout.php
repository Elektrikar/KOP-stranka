<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit();
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Cart.php';
require_once __DIR__ . '/class/Order.php';

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();

$cart = new Cart();
$order = new Order($pdo);

$items = $cart->getItems();
$total = $cart->getTotal();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $zipCode = trim($_POST['zip_code'] ?? '');
    $country = trim($_POST['country'] ?? '');

    // Validate inputs
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Prosím zadajte platný email.';
    } elseif (empty($firstName) || empty($lastName)) {
        $error = 'Prosím vyplňte vaše meno a priezvisko.';
    } elseif (empty($address) || empty($city) || empty($zipCode) || empty($country)) {
        $error = 'Prosím vyplňte všetky adresné údaje.';
    } else {
        $fullAddress = $firstName . ' ' . $lastName . "\n" .
            $address . "\n" .
            $zipCode . ' ' . $city . "\n" .
            $country;

        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

        try {
            $orderId = $order->create($userId, $email, $total, $fullAddress, $items);

            if ($orderId) {
                $cart->clear();

                header("Location: order_confirmation.php?id=$orderId");
                exit();
            } else {
                $error = 'Nastala chyba pri vytváraní objednávky. Skúste to prosím znova.';
            }
        } catch (Exception $e) {
            $error = 'Chyba pri vytváraní objednávky: ' . $e->getMessage();
        }
    }
}

$pageData = array(
    'title' => 'Pokladňa | E-shop',
    'metaDataDescription' => 'Dokončenie nákupu',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/checkout.css')
    )
);
require_once 'theme/header.php';
?>

<div class="container">
    <div class="checkout-top">
        <h1>Pokladňa</h1>
    </div>

    <div class="checkout-container">
        <div class="checkout-steps">
            <div class="step"><a href="cart.php">1. Košík</a></div>
            <div class="step active">2. Doprava a platba</div>
            <div class="step">3. Potvrdenie</div>
        </div>

        <?php if ($error): ?>
            <div class="error-message" style="color: red; background: #ffeaea; padding: 15px; border-radius: 4px; margin: 20px 0;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="checkout-content">
            <div class="order-summary">
                <h3>Vaša objednávka</h3>
                <div class="summary-items">
                    <?php 
                    $productIds = array_keys($items);
                    if (!empty($productIds)) {
                        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
                        $stmt = $pdo->prepare("SELECT id, price, discount_price FROM products WHERE id IN ($placeholders)");
                        $stmt->execute($productIds);
                        $productPrices = [];
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $productPrices[$row['id']] = $row['discount_price'] ?: $row['price'];
                        }
                        
                        foreach ($items as $id => $item) {
                            if (isset($productPrices[$id])) {
                                $_SESSION['cart'][$id]['price'] = $productPrices[$id];
                                $items[$id]['price'] = $productPrices[$id];
                            }
                        }
                    }
                    
                    foreach ($items as $id => $item): 
                        $stmt = $pdo->prepare("SELECT price, discount_price FROM products WHERE id = ?");
                        $stmt->execute([$id]);
                        $productData = $stmt->fetch(PDO::FETCH_ASSOC);
                        $originalPrice = $productData['price'];
                        $hasDiscount = !empty($productData['discount_price']) && $productData['discount_price'] < $originalPrice;
                    ?>
                        <div class="summary-item">
                            <div class="item-info">
                                <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="item-quantity">× <?= $item['quantity'] ?></span>
                            </div>
                            <div class="item-price">
                                <?php if ($hasDiscount): ?>
                                    <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                        <span style="text-decoration: line-through; color: #999; font-size: 0.9em;">
                                            <?= number_format($originalPrice * $item['quantity'], 2, ',', ' ') ?> €
                                        </span>
                                        <span style="color: #f44336; font-weight: bold;">
                                            <?= number_format($item['price'] * $item['quantity'], 2, ',', ' ') ?> €
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <?= number_format($item['price'] * $item['quantity'], 2, ',', ' ') ?> €
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="summary-total">
                    <span>Celková suma:</span>
                    <span class="total-price"><?= number_format($total, 2, ',', ' ') ?> €</span>
                </div>
            </div>

            <div class="checkout-form">
                <h3>Dodacie údaje</h3>
                <form method="post" id="checkout-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required
                                value="<?= htmlspecialchars($_POST['email'] ?? ($_SESSION['user_email'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">Meno *</label>
                            <input type="text" id="first_name" name="first_name" required
                                value="<?= htmlspecialchars($_POST['first_name'] ?? ($_SESSION['user_first_name'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Priezvisko *</label>
                            <input type="text" id="last_name" name="last_name" required
                                value="<?= htmlspecialchars($_POST['last_name'] ?? ($_SESSION['user_last_name'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Adresa *</label>
                        <input type="text" id="address" name="address" required
                            value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                            placeholder="Ulica a číslo domu">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">Mesto *</label>
                            <input type="text" id="city" name="city" required
                                value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="zip_code">PSČ *</label>
                            <input type="text" id="zip_code" name="zip_code" required
                                value="<?= htmlspecialchars($_POST['zip_code'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="country">Krajina *</label>
                        <select id="country" name="country" required>
                            <option value="">Vyberte krajinu</option>
                            <option value="Slovensko" <?= ($_POST['country'] ?? '') === 'Slovensko' ? 'selected' : '' ?>>Slovensko</option>
                            <option value="Česko" <?= ($_POST['country'] ?? '') === 'Česko' ? 'selected' : '' ?>>Česko</option>
                            <option value="Maďarsko" <?= ($_POST['country'] ?? '') === 'Maďarsko' ? 'selected' : '' ?>>Maďarsko</option>
                            <option value="Poľsko" <?= ($_POST['country'] ?? '') === 'Poľsko' ? 'selected' : '' ?>>Poľsko</option>
                            <option value="Rakúsko" <?= ($_POST['country'] ?? '') === 'Rakúsko' ? 'selected' : '' ?>>Rakúsko</option>
                        </select>
                    </div>

                    <div class="payment-methods">
                        <h3>Spôsob platby</h3>
                        <div class="payment-option">
                            <input type="radio" id="payment_cod" name="payment_method" value="cod" checked>
                            <label for="payment_cod">Dobierka</label>
                            <p class="payment-description">Platba pri prevzatí tovaru</p>
                        </div>
                        <!--<div class="payment-option">
                            <input type="radio" id="payment_bank" name="payment_method" value="bank">
                            <label for="payment_bank">Bankový prevod</label>
                            <p class="payment-description">Platba vopred na účet</p>
                        </div>-->
                    </div>

                    <div class="terms-agreement">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">
                            Súhlasím s <a href="#" target="_blank">obchodnými podmienkami</a> a
                            <a href="#" target="_blank">ochranou osobných údajov</a> *
                        </label>
                    </div>

                    <div class="checkout-actions">
                        <a href="cart.php" class="back-to-cart">Späť do košíka</a>
                        <button type="submit" class="btn-confirm">Potvrdiť objednávku</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('checkout-form').addEventListener('submit', function(e) {
        if (!document.getElementById('terms').checked) {
            e.preventDefault();
            alert('Pre pokračovanie musíte súhlasiť s obchodnými podmienkami.');
        }
    });
</script>

<?php require_once 'theme/footer.php'; ?>