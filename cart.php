<?php
session_start();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Product.php';
require_once __DIR__ . '/class/Cart.php';

$db = new Database('localhost', 'webstore', 'root', '');
$pdo = $db->getConnection();
$cart = new Cart();

// AJAX cart item count update
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $productId = $_POST['id'];
    $action = isset($_POST['action']) ? $_POST['action'] : 'add';
    if ($action === 'add') {
        // Fetch product details if not in cart
        if ($cart->getQuantity($productId) > 0) {
            $cart->add($productId);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($product) {
                $cart->add($productId, $product);
            }
        }
    } elseif ($action === 'remove') {
        $cart->remove($productId);
    } elseif ($action === 'set' && isset($_POST['quantity'])) {
        $quantity = (int)$_POST['quantity'];
        if ($quantity > 0) {
            $cart->update($productId, $quantity);
        } else {
            $cart->remove($productId);
        }
    }
    $qty = $cart->getQuantity($productId);
    // Get item price and subtotal
    $item = $cart->getItems()[$productId] ?? null;
    $price = $item ? $item['price'] : 0;
    $subtotal = $item ? $item['price'] * $qty : 0;
    // Calculate new cart total
    $items = $cart->getItems();
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
        array('type' => 'css', 'src' => 'assets/css/cartQuantityActions.css'),
        array('type' => 'js', 'src' => 'assets/js/cartQuantityActions.js')
    )
);
require_once 'theme/header.php';
?>
<div class="container">
    <h1>Váš košík</h1><br>
    <?php $items = $cart->getItems();
    if (empty($items)): ?>
        <p>Váš košík je prázdny.</p>
    <?php else: ?>
        <table class="cart-table">
            <?php $total = 0;
            foreach ($items as $id => $item):
                $subtotal = $item['price'] * $item['quantity'];
                $total += $subtotal;
            ?>
                <tr>
                    <td style="width:70px"><img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width:60px;"></td>
                    <td style="width:505px"><?php echo htmlspecialchars($item['name']); ?></td>
                    <td>
                        <div class="cart-summary" style="justify-content:center;">
                            <button class="cart-minus" data-id="<?php echo $id; ?>">-</button>
                            <input type="text" inputmode="numeric" min="1" class="cart-qty-input" data-id="<?php echo $id; ?>" value="<?php echo $item['quantity']; ?>">
                            <button class="cart-plus" data-id="<?php echo $id; ?>">+</button>
                        </div>
                    </td>
                    <td><?php echo number_format($item['price'], 2, ',', ' '); ?> € / ks</td>
                    <td><?php echo number_format($subtotal, 2, ',', ' '); ?> €</td>
                </tr>
            <?php endforeach; ?>
            <tr style="border-top:1px solid black;">
                <td colspan="4" style="text-align:right;padding-right:10rem;"><strong>Celkovo:&nbsp;</strong></td>
                <td><strong style="color:green;font-size:large;"><?php echo number_format($total, 2, ',', ' '); ?> €</strong></td>
            </tr>
        </table>
    <?php endif; ?>
    <p>
        <a href="index.php" class="back-to-shop-btn">
            <span style="font-size:0.65rem;margin-right:9px;">&#9664;</span> Späť k nákupu
        </a>
    </p>
</div>

<?php require_once 'theme/footer.php'; ?>