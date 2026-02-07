<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit();
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Cart.php';

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();

$cart = new Cart();

$items = $cart->getItems();
$total = $cart->getTotal();

$error = '';

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/class/User.php';
    $user = new User($pdo);
    $userData = $user->getById($_SESSION['user_id']);
} else {
    $userData = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['checkout_temp'] = [
        'email' => trim($_POST['email'] ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'zip_code' => trim($_POST['zip_code'] ?? ''),
        'country' => trim($_POST['country'] ?? '')
    ];

    if (isset($_POST['back_to_cart']) || isset($_POST['back_to_cart_btn'])) {
        header('Location: cart.php');
        exit();
    }
    
    $email = trim($_POST['email'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $zipCode = trim($_POST['zip_code'] ?? '');
    $country = trim($_POST['country'] ?? '');

    // Validate inputs if continuing with checkout
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Prosím zadajte platný email.';
    } elseif (empty($firstName) || empty($lastName)) {
        $error = 'Prosím vyplňte vaše meno a priezvisko.';
    } elseif (empty($address) || empty($city) || empty($zipCode) || empty($country)) {
        $error = 'Prosím vyplňte všetky adresné údaje.';
    } elseif (!preg_match('/[a-zA-Z]/', $address) || !preg_match('/\d/', $address)) {
        $error = 'Adresa musí obsahovať názov ulice aj číslo domu.';
    } else {
        $_SESSION['checkout'] = [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'address' => $address,
            'city' => $city,
            'zip_code' => $zipCode,
            'country' => $country,
            'full_address' => $firstName . ' ' . $lastName . "\n" .
                             $address . "\n" .
                             $zipCode . ' ' . $city . "\n" .
                             $country
        ];

        // Clear temp data
        unset($_SESSION['checkout_temp']);
        
        header('Location: checkout2.php');
        exit();
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
            <div class="step"><a id="step-cart">1. Košík</a></div>
            <div class="step active">2. Kontakné údaje</div>
            <div class="step"><a id="step-shipping">3. Doprava a platba</a></div>
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
                <h3>Kontakné údaje</h3>
                <form method="post" id="checkout-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required
                                value="<?= htmlspecialchars($_SESSION['checkout_temp']['email'] ?? ($_SESSION['checkout']['email'] ?? ($_POST['email'] ?? ($_SESSION['user_email'] ?? '')))) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">Meno *</label>
                            <input type="text" id="first_name" name="first_name" required
                                value="<?= htmlspecialchars($_SESSION['checkout_temp']['first_name'] ?? ($_SESSION['checkout']['first_name'] ?? ($_POST['first_name'] ?? ($_SESSION['user_first_name'] ?? '')))) ?>">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Priezvisko *</label>
                            <input type="text" id="last_name" name="last_name" required
                                value="<?= htmlspecialchars($_SESSION['checkout_temp']['last_name'] ?? ($_SESSION['checkout']['last_name'] ?? ($_POST['last_name'] ?? ($_SESSION['user_last_name'] ?? '')))) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Adresa *</label>
                        <input type="text" id="address" name="address" required
                            value="<?= htmlspecialchars($_SESSION['checkout_temp']['address'] ?? ($_SESSION['checkout']['address'] ?? ($_POST['address'] ?? ($userData->address ?? '')))) ?>"
                            placeholder="Ulica a číslo domu">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">Mesto *</label>
                            <input type="text" id="city" name="city" required
                                value="<?= htmlspecialchars($_SESSION['checkout_temp']['city'] ?? ($_SESSION['checkout']['city'] ?? ($_POST['city'] ?? ($userData->city ?? '')))) ?>">
                        </div>
                        <div class="form-group">
                            <label for="zip_code">PSČ *</label>
                            <input type="text" id="zip_code" name="zip_code" required
                                value="<?= htmlspecialchars($_SESSION['checkout_temp']['zip_code'] ?? ($_SESSION['checkout']['zip_code'] ?? ($_POST['zip_code'] ?? ($userData->zip_code ?? '')))) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="country">Krajina *</label>
                        <select id="country" name="country" required>
                            <option value="">Vyberte krajinu</option>
                            <option value="Slovensko" <?= ($_SESSION['checkout_temp']['country'] ?? ($_SESSION['checkout']['country'] ?? ($_POST['country'] ?? ($userData->country ?? '')))) === 'Slovensko' ? 'selected' : '' ?>>Slovensko</option>
                            <option value="Česko" <?= ($_SESSION['checkout_temp']['country'] ?? ($_SESSION['checkout']['country'] ?? ($_POST['country'] ?? ($userData->country ?? '')))) === 'Česko' ? 'selected' : '' ?>>Česko</option>
                            <option value="Maďarsko" <?= ($_SESSION['checkout_temp']['country'] ?? ($_SESSION['checkout']['country'] ?? ($_POST['country'] ?? ($userData->country ?? '')))) === 'Maďarsko' ? 'selected' : '' ?>>Maďarsko</option>
                            <option value="Poľsko" <?= ($_SESSION['checkout_temp']['country'] ?? ($_SESSION['checkout']['country'] ?? ($_POST['country'] ?? ($userData->country ?? '')))) === 'Poľsko' ? 'selected' : '' ?>>Poľsko</option>
                            <option value="Rakúsko" <?= ($_SESSION['checkout_temp']['country'] ?? ($_SESSION['checkout']['country'] ?? ($_POST['country'] ?? ($userData->country ?? '')))) === 'Rakúsko' ? 'selected' : '' ?>>Rakúsko</option>
                        </select>
                    </div>

                    <div class="checkout-actions">
                        <a href="#" class="back-to-cart">Späť do košíka</a>
                        <button type="submit" class="btn-confirm">Pokračovať</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stepCart = document.getElementById('step-cart');
    const stepShipping = document.getElementById('step-shipping');
    const checkoutForm = document.getElementById('checkout-form');
    const backToCartBtn = document.querySelector('.back-to-cart');

    if (stepCart && checkoutForm) {
        stepCart.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'back_to_cart';
            hiddenInput.value = '1';
            checkoutForm.appendChild(hiddenInput);
            
            checkoutForm.submit();
            return false;
        });
        stepCart.style.cursor = 'pointer';
    }

    if (stepShipping && checkoutForm) {
        stepShipping.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            checkoutForm.submit();
            return false;
        });
        stepShipping.style.cursor = 'pointer';
    }

    if (backToCartBtn && checkoutForm) {
        backToCartBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'back_to_cart_btn';
            hiddenInput.value = '1';
            checkoutForm.appendChild(hiddenInput);
            
            checkoutForm.submit();
            return false;
        });
    }
});
</script>

<?php require_once 'theme/footer.php'; ?>