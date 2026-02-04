<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Product.php';
require_once __DIR__ . '/class/Cart.php';

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();
$cart = new Cart();

// Cart item count update
if ((isset($_GET['action']) && $_GET['action'] === 'count')) {
    $cartCount = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $cartCount += $item['quantity'];
        }
    }
    echo json_encode(['count' => $cartCount]);
    exit();
}

// Empty cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['empty_cart']) && $_POST['empty_cart'] === 'true') {
    $cart->clear();
    echo json_encode(['success' => true, 'redirect' => true]);
    exit();
}

// Add, remove, update cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $productId = $_POST['id'];
    $action = isset($_POST['action']) ? $_POST['action'] : 'add';
    
    if ($action === 'add') {
        $stmt = $pdo->prepare("SELECT stock, price, discount_price, name, image FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $productData = $stmt->fetch(PDO::FETCH_ASSOC);
        $productStock = $productData['stock'];

        $currentPrice = $productData['discount_price'] && $productData['discount_price'] < $productData['price'] 
            ? $productData['discount_price'] 
            : $productData['price'];

        $currentQty = $cart->getQuantity($productId);

        if ($currentQty >= $productStock) {
            echo json_encode([
                'success' => false,
                'message' => 'Nie je dostatok tovaru na sklade.'
            ]);
            exit();
        }

        if ($currentQty > 0) {
            $cart->add($productId);
        } else {
            $product = [
                'name' => $productData['name'],
                'price' => $currentPrice,
                'image' => $productData['image']
            ];
            $cart->add($productId, $product);
        }
    } elseif ($action === 'remove') {
        $cart->remove($productId);
    } elseif ($action === 'set' && isset($_POST['quantity'])) {
        $quantity = (int)$_POST['quantity'];

        if ($quantity > 0) {
            $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $productStock = $stmt->fetchColumn();
            
            if ($quantity > $productStock) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Nie je dostatok tovaru na sklade.'
                ]);
                exit();
            }
            
            $cart->update($productId, $quantity);
        } else {
            $cart->remove($productId);
        }
    }

    $items = $cart->getItems();
    if (!empty($items)) {
        $productIds = array_keys($items);
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, price, discount_price FROM products WHERE id IN ($placeholders)");
        $stmt->execute($productIds);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $currentPrice = $row['discount_price'] && $row['discount_price'] < $row['price'] 
                ? $row['discount_price'] 
                : $row['price'];

            if (isset($_SESSION['cart'][$row['id']])) {
                $_SESSION['cart'][$row['id']]['price'] = $currentPrice;
            }
        }

        $items = $cart->getItems();
    }
    
    $qty = $cart->getQuantity($productId);
    // Get item price and subtotal
    $item = $items[$productId] ?? null;
    $price = $item ? $item['price'] : 0;
    $subtotal = $item ? $item['price'] * $qty : 0;
    // Calculate new cart total
    $total = 0;
    foreach ($items as $it) {
        $total += $it['price'] * $it['quantity'];
    }
    
    echo json_encode([
        'success' => true,
        'quantity' => $qty,
        'subtotal' => number_format($subtotal, 2),
        'total' => number_format($total, 2)
    ]);
    exit();
}

$pageData = array(
    'title' => 'Nákupný Košík | E-shop',
    'metaDataDescription' => 'Váš nákupný košík',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/cart.css'),
        array('type' => 'js', 'src' => 'assets/js/cart.js')
    )
);
require_once 'theme/header.php';

$items = $cart->getItems();

if (!empty($items)) {
    $productIds = array_keys($items);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id, price, discount_price FROM products WHERE id IN ($placeholders)");
    $stmt->execute($productIds);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $currentPrice = $row['discount_price'] && $row['discount_price'] < $row['price'] 
            ? $row['discount_price'] 
            : $row['price'];

        if (isset($_SESSION['cart'][$row['id']])) {
            $_SESSION['cart'][$row['id']]['price'] = $currentPrice;
            $items[$row['id']]['price'] = $currentPrice;
        }
    }
}
?>
<div class="container">
    <div class="cart-top">
        <h1>Váš košík</h1><br>
        <?php if (empty($items)): ?>
    </div>
    <p>Váš košík je prázdny.</p>
<?php else: ?>
    <button id="empty-cart-btn" class="empty-cart-btn">
        Vyprázdniť košík
    </button>
</div>
<table class="cart-table">
    <?php 
    $total = 0;
    foreach ($items as $id => $item):
        $stmt = $pdo->prepare("SELECT price, discount_price FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $productData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $originalPrice = $productData['price'];
        $hasDiscount = $productData['discount_price'] && $productData['discount_price'] < $originalPrice;
        $currentPrice = $item['price'];
        $subtotal = $currentPrice * $item['quantity'];
        $total += $subtotal;
    ?>
        <tr>
            <td style="width:70px">
                <a href="product_detail.php?id=<?php echo $id; ?>">
                    <img src="img/productsmall/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                </a>
            </td>
            <td style="width:505px">
                <a href="product_detail.php?id=<?php echo $id; ?>" class="product-title">
                    <?php echo htmlspecialchars($item['name']); ?>
                </a>
            </td>
            <td>
                <div class="cart-summary">
                    <button class="cart-minus" data-id="<?php echo $id; ?>">-</button>
                    <input type="text" inputmode="numeric" min="1" class="cart-qty-input" data-id="<?php echo $id; ?>" value="<?php echo $item['quantity']; ?>">
                    <button class="cart-plus" data-id="<?php echo $id; ?>">+</button>
                </div>
            </td>
            <td>
                <?php if ($hasDiscount): ?>
                    <div class="price-display">
                        <span class="original-price" style="text-decoration: line-through; color: #999; font-size: 0.9em; display: block;">
                            <?php echo number_format($originalPrice, 2, ',', ' '); ?> €
                        </span>
                        <span class="discount-price" style="color: #f44336; font-weight: bold;">
                            <?php echo number_format($currentPrice, 2, ',', ' '); ?> € / ks
                        </span>
                    </div>
                <?php else: ?>
                    <span class="regular-price">
                        <?php echo number_format($currentPrice, 2, ',', ' '); ?> € / ks
                    </span>
                <?php endif; ?>
            </td>
            <td class="item-total">
                <?php echo number_format($subtotal, 2, ',', ' '); ?> €
            </td>
        </tr>
    <?php endforeach; ?>
    <tr class="total-row">
        <td class="total" colspan="4">Celkovo:&nbsp;</td>
        <td class="total-price"><?php echo number_format($total, 2, ',', ' '); ?> €</td>
    </tr>
</table>
<?php endif; ?>
    <div class="admin-header" style="border: none;">
        <a href="index.php" class="back-to-shop-btn">
        <span style="font-size:0.65rem;margin-right:9px;">&#9664;</span> Späť k nákupu
    </a>
        <?php if (!empty($items)): ?>
            <a href="checkout.php" class="btn-checkout">Pokračovať k pokladni</a>
        <?php endif; ?>
    </div>
<p>
    
</p>
</div>

<?php require_once 'theme/footer.php'; ?>