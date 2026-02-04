<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit();
}

// Check if address is filled
if (!isset($_SESSION['checkout']) || empty($_SESSION['checkout']['address'])) {
    header('Location: checkout1.php');
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

// Fetch shipping methods from database
$shipping_methods = $pdo->query("SELECT * FROM shipping_methods WHERE is_active = 1 ORDER BY price")->fetchAll(PDO::FETCH_ASSOC);

// Fetch payment methods from database
$payment_methods = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_id = (int)($_POST['shipping_method'] ?? 0);
    $payment_id = (int)($_POST['payment_method'] ?? 0);
    $terms = isset($_POST['terms']) && $_POST['terms'] === 'on';
    
    // Validate selections
    if (!$terms) {
        $error = 'Pre pokračovanie musíte súhlasiť s obchodnými podmienkami.';
    } elseif ($shipping_id === 0 || $payment_id === 0) {
        $error = 'Prosím vyberte spôsob dopravy a platby.';
    } else {
        // Validate shipping method exists
        $validShipping = false;
        $shipping_cost = 0;
        foreach ($shipping_methods as $method) {
            if ($method['id'] == $shipping_id) {
                $validShipping = true;
                $shipping_cost = $method['price'];
                break;
            }
        }
        
        // Validate payment method exists
        $validPayment = false;
        $payment_fee = 0;
        foreach ($payment_methods as $method) {
            if ($method['id'] == $payment_id) {
                $validPayment = true;
                $payment_fee = $method['price'];
                break;
            }
        }
        
        if ($validShipping && $validPayment) {
            $final_total = $total + $shipping_cost + $payment_fee;
            
            try {
                $user_id = $_SESSION['user_id'] ?? 0;
                $email = $_SESSION['checkout']['email'];

                $order_id = $order->create(
                    $user_id,
                    $email,
                    $final_total,
                    $_SESSION['checkout']['full_address'],
                    $items,
                    $shipping_id,
                    $payment_id
                );
                
                // Clear cart and checkout session
                $cart->clear();
                unset($_SESSION['checkout']);
                
                // Redirect to confirmation page
                header('Location: order_confirmation.php?id=' . $order_id);
                exit();
            } catch (Exception $e) {
                $error = 'Chyba pri vytváraní objednávky: ' . $e->getMessage();
            }
        } else {
            $error = 'Prosím vyberte platný spôsob dopravy a platby.';
        }
    }
}

$pageData = array(
    'title' => 'Doprava a platba | E-shop',
    'metaDataDescription' => 'Výber spôsobu dopravy a platby',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/checkout.css'),
        array('type' => 'js', 'src' => 'assets/js/checkout.js')
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
            <div class="step"><a href="checkout1.php">2. Kontakné údaje</a></div>
            <div class="step active">3. Doprava a platba</div>
        </div>

        <?php if ($error): ?>
            <div class="error-message" style="color: red; background: #ffeaea; padding: 15px; border-radius: 4px; margin: 20px 0;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="checkout-content">
            <div class="order-summary" data-base-total="<?= $total ?>" data-shipping-methods='<?= json_encode(array_column($shipping_methods, null, 'id')) ?>' data-payment-methods='<?= json_encode(array_column($payment_methods, null, 'id')) ?>'>
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
                    <div id="shipping-summary" style="display: none;">
                        <div class="summary-item">
                            <div class="item-info">
                                <span class="item-name">Doprava</span>
                            </div>
                            <div class="item-price" id="shipping-price">
                                <span id="shipping-amount">0,00 €</span>
                            </div>
                        </div>
                    </div>
                    <div id="payment-summary" style="display: none;">
                        <div class="summary-item">
                            <div class="item-info">
                                <span class="item-name">Platba</span>
                            </div>
                            <div class="item-price" id="payment-price">
                                <span id="payment-amount">0,00 €</span>
                            </div>
                        </div>
                    </div>
                    <div class="summary-total">
                        <span>Celková suma:</span>
                        <span class="total-price"><?= number_format($total, 2, ',', ' ') ?> €</span>
                    </div>
                </div>
            </div>

            <div class="checkout-form">
                <h3>Doprava a platba</h3>
                <form method="post" id="checkout-form">
                    <div class="shipping-methods-section">
                        <h4 style="margin-bottom: 15px;">Spôsob dopravy</h4>
                        
                        <?php if (empty($shipping_methods)): ?>
                            <p style="color: #666;">Momentálne nie sú dostupné žiadne spôsoby dopravy.</p>
                        <?php else: ?>
                            <?php foreach ($shipping_methods as $method): ?>
                                <div class="selection-option shipping-option">
                                    <input type="radio" 
                                           id="shipping_<?= $method['id'] ?>" 
                                           name="shipping_method" 
                                           value="<?= $method['id'] ?>"
                                           required
                                           data-price="<?= $method['price'] ?>"
                                           <?= ($_POST['shipping_method'] ?? 0) == $method['id'] ? 'checked' : '' ?>>
                                    <div class="option-header">
                                        <span class="option-name"><?= htmlspecialchars($method['name']) ?></span>
                                        <span class="option-price"><?= number_format($method['price'], 2, ',', ' ') ?> €</span>
                                    </div>
                                    <p class="option-description">
                                        <?= htmlspecialchars($method['description']) ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="payment-methods-section">
                        <h4 style="margin-bottom: 15px;">Spôsob platby</h4>
                        
                        <?php if (empty($payment_methods)): ?>
                            <p style="color: #666;">Momentálne nie sú dostupné žiadne spôsoby platby.</p>
                        <?php else: ?>
                            <?php foreach ($payment_methods as $method): ?>
                                <div class="selection-option payment-option">
                                    <input type="radio" 
                                           id="payment_<?= $method['id'] ?>" 
                                           name="payment_method" 
                                           value="<?= $method['id'] ?>"
                                           required
                                           data-price="<?= $method['price'] ?>"
                                           <?= ($_POST['payment_method'] ?? 0) == $method['id'] ? 'checked' : '' ?>>
                                    <div class="option-header">
                                        <span class="option-name"><?= htmlspecialchars($method['name']) ?></span>
                                        <span class="option-price"><?= number_format($method['price'], 2, ',', ' ') ?> €</span>
                                    </div>
                                    <p class="option-description">
                                        <?= htmlspecialchars($method['description']) ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="terms-agreement">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">
                            Súhlasím s <a href="#" target="_blank">obchodnými podmienkami</a> a
                            <a href="#" target="_blank">ochranou osobných údajov</a> *
                        </label>
                    </div>

                    <div class="checkout-actions">
                        <a href="checkout1.php" class="back-to-cart">← Späť na kontaktné údaje</a>
                        <button type="submit" class="btn-confirm">Potvrdiť objednávku</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'theme/footer.php'; ?>