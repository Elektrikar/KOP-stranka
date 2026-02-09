<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/class/Database.php';
require_once __DIR__ . '/class/Order.php';

$db = new Database(env('DB_HOST'), env('DB_NAME'), env('DB_USER'), env('DB_PASS'));
$pdo = $db->getConnection();

$order = new Order($pdo);

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId === 0) {
    header('Location: index.php');
    exit();
}

$orderData = $order->getById($orderId);
$orderDetails = $order->getOrderDetails($orderId);

if (!$orderData) {
    header('Location: index.php');
    exit();
}

// Check permission
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$isManager = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'manager';
$isOwner = isset($_SESSION['user_id']) && $orderData->user_id == $_SESSION['user_id'];

if (!$isAdmin && !$isManager && !$isOwner) {
    header('Location: index.php');
    exit();
}

$shippingMethod = strtolower($orderData->shipping_name ?? '');

$allStatuses = [
    'pending' => 'Čaká na spracovanie',
    'processing' => 'Spracováva sa',
    'shipped' => 'Odoslané',
    'delivered' => 'Doručené',
    'ready_for_pickup' => 'Pripravené na odber',
    'picked_up' => 'Vyzdvihnuté',
    'cancelled' => 'Zrušené'
];

$statusProgression = [
    // Regular shipping progression
    'regular' => ['pending', 'processing', 'shipped', 'delivered', 'cancelled'],
    // Personal pickup progression
    'pickup' => ['pending', 'processing', 'ready_for_pickup', 'picked_up', 'cancelled']
];

// Determine which progression to use
if (strpos($shippingMethod, 'osobný odber') !== false || strpos($shippingMethod, 'pickup') !== false) {
    $progressionType = 'pickup';
    $availableStatuses = [
        'pending' => 'Čaká na spracovanie',
        'processing' => 'Spracováva sa',
        'ready_for_pickup' => 'Pripravené na odber',
        'picked_up' => 'Vyzdvihnuté',
        'cancelled' => 'Zrušené'
    ];
} else {
    $progressionType = 'regular';
    $availableStatuses = [
        'pending' => 'Čaká na spracovanie',
        'processing' => 'Spracováva sa',
        'shipped' => 'Odoslané',
        'delivered' => 'Doručené',
        'cancelled' => 'Zrušené'
    ];
}

// Handle status update
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'] ?? '';
    
    // Validate status
    if (!array_key_exists($newStatus, $availableStatuses)) {
        $error = 'Neplatný stav objednávky.';
    } else {
        // Prevent backward status changes
        $currentIndex = array_search($orderData->status, $statusProgression[$progressionType]);
        $newIndex = array_search($newStatus, $statusProgression[$progressionType]);
        
        if ($currentIndex !== false && $newIndex !== false && $newIndex < $currentIndex) {
            $error = 'Nie je možné vrátiť objednávku na starší stav.';
        } else {
            if ($order->updateStatus($orderId, $newStatus)) {
                $success = 'Stav objednávky bol aktualizovaný.';
                $orderData = $order->getById($orderId);
            } else {
                $error = 'Nastala chyba pri aktualizácii stavu.';
            }
        }
    }
}

function getDisplayStatus($status) {
    $statusLabels = [
        'pending' => 'Čaká na spracovanie',
        'processing' => 'Spracováva sa',
        'shipped' => 'Odoslané',
        'delivered' => 'Doručené',
        'ready_for_pickup' => 'Pripravené na odber',
        'picked_up' => 'Vyzdvihnuté',
        'cancelled' => 'Zrušené'
    ];
    
    return $statusLabels[$status] ?? $status;
}

$pageData = array(
    'title' => 'Detail objednávky | E-shop',
    'metaDataDescription' => 'Detailné informácie o objednávke',
    'customAssets' => array(
        array('type' => 'css', 'src' => 'assets/css/order_details.css')
    )
);
require_once 'theme/header.php';
?>

<div class="container">
    <div class="order-details-container">
        <div class="order-details-header">
            <h1>Detail objednávky #<?= str_pad($orderData->id, 6, '0', STR_PAD_LEFT) ?></h1>
            <a href="<?= $isAdmin ? 'adminOrders.php' : 'orders.php' ?>" class="back-link">Späť na zoznam</a>
        </div>

        <div class="order-info-grid">
            <div class="info-card">
                <h3>Informácie o objednávke</h3>
                <div class="info-row">
                    <span class="info-label">Číslo objednávky:</span>
                    <span class="info-value">#<?= str_pad($orderData->id, 6, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Dátum vytvorenia:</span>
                    <span class="info-value"><?= date('d.m.Y H:i', strtotime($orderData->created_at)) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Posledná aktualizácia:</span>
                    <span class="info-value"><?= date('d.m.Y H:i', strtotime($orderData->updated_at)) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Stav:</span>
                    <span class="info-value status-badge-small status-<?= $orderData->status ?>">
                        <?= htmlspecialchars(getDisplayStatus($orderData->status, $progressionType)) ?>
                    </span>
                </div>
            </div>

            <div class="info-card">
                <h3>Zákaznícke informácie</h3>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= htmlspecialchars($orderData->order_email) ?></span>
                </div>
                <?php if ($orderData->user_id): ?>
                    <div class="info-row">
                        <span class="info-label">ID používateľa:</span>
                        <span class="info-value"><?= $orderData->user_id ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <h3>Spôsob doručenia a platby</h3>
                <div class="info-row">
                    <span class="info-label">Spôsob doručenia:</span>
                    <span class="info-value"><?= htmlspecialchars($orderData->shipping_name ?? 'Neuvedené') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Spôsob platby:</span>
                    <span class="info-value"><?= htmlspecialchars($orderData->payment_name ?? 'Neuvedené') ?></span>
                </div>
            </div>

            <div class="info-card full-width">
                <h3>Dodacia adresa</h3>
                <div class="address-display">
                    <?= nl2br(htmlspecialchars($orderData->address)) ?>
                </div>
            </div>
        </div>

        <div class="order-items-table">
            <h3>Produkty v objednávke</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Produkt</th>
                        <th>Cena</th>
                        <th>Množstvo</th>
                        <th>Celkom</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $subtotal = 0;
                    foreach ($orderDetails as $item): 
                        $itemTotal = $item['price'] * $item['quantity'];
                        $subtotal += $itemTotal;
                    ?>
                        <tr>
                            <td>
                                <div class="product-cell">
                                    <?php if ($item['image']): ?>
                                        <img src="img/productsmall/<?= htmlspecialchars($item['image']) ?>"
                                            alt="<?= htmlspecialchars($item['name']) ?>">
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($item['name']) ?></span>
                                </div>
                            </td>
                            <td><?= number_format($item['price'], 2, ',', ' ') ?> €</td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= number_format($itemTotal, 2, ',', ' ') ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php if ($orderData->shipping_price): ?>
                    <tr>
                        <td colspan="3" class="text-right">Doprava (<?= htmlspecialchars($orderData->shipping_name ?? 'Neuvedené') ?>):</td>
                        <td><?= number_format($orderData->shipping_price, 2, ',', ' ') ?> €</td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($orderData->payment_price): ?>
                    <tr>
                        <td colspan="3" class="text-right">Poplatok za platbu (<?= htmlspecialchars($orderData->payment_name ?? 'Neuvedené') ?>):</td>
                        <td><?= number_format($orderData->payment_price, 2, ',', ' ') ?> €</td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td colspan="3" class="text-right"><strong>Celková suma:</strong></td>
                        <td class="total-cell"><strong><?= number_format($orderData->total, 2, ',', ' ') ?> €</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if ($isAdmin || $isManager): ?>
            <div class="admin-actions">
                <h3>Administratívne akcie</h3>
                <?php if ($error): ?>
                    <div class="error-message" style="margin-bottom: 15px;"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success-message" style="margin-bottom: 15px;"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <div class="action-row">
                    <form method="post" action="" class="status-update-form">
                        <input type="hidden" name="order_id" value="<?= $orderData->id ?>">
                        <div class="form-group">
                            <label for="status">Zmeniť stav:</label>
                            <select name="status" id="status">
                                <?php foreach ($availableStatuses as $statusKey => $statusLabel): ?>
                                    <?php 
                                    // Check if this status is allowed (not before current status)
                                    $isAllowed = true;
                                    $currentIndex = array_search($orderData->status, $statusProgression[$progressionType]);
                                    $optionIndex = array_search($statusKey, $statusProgression[$progressionType]);
                                    
                                    if ($currentIndex !== false && $optionIndex !== false && $optionIndex < $currentIndex) {
                                        $isAllowed = false;
                                    }
                                    
                                    // Prevent cancelling if order is already delivered or picked up
                                    if ($statusKey === 'cancelled' && in_array($orderData->status, ['delivered', 'picked_up'])) {
                                        $isAllowed = false;
                                    }
                                    ?>
                                    <option value="<?= $statusKey ?>" 
                                        <?= $orderData->status === $statusKey ? 'selected' : '' ?>
                                        <?= $isAllowed ? '' : 'disabled' ?>>
                                        <?= htmlspecialchars($statusLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="update_status" value="1" class="btn-update">Aktualizovať</button>
                        </div>
                    </form>
                    <?php if ($isAdmin): ?>
                        <form method="post" action="adminDelete.php" class="inline-form"
                                onsubmit="return confirm('Naozaj chcete zmazať túto objednávku?');">
                            <input type="hidden" name="id" value="<?= $orderData->id ?>">
                            <input type="hidden" name="type" value="order">
                            <button type="submit" class="btn-delete">Zmazať</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('status');
    const currentStatus = '<?= $orderData->status ?>';
    const progressionType = '<?= $progressionType ?>';
    
    // Define status progression orders
    const statusProgression = {
        'regular': ['pending', 'processing', 'shipped', 'delivered', 'cancelled'],
        'pickup': ['pending', 'processing', 'ready_for_pickup', 'picked_up', 'cancelled']
    };
    
    if (statusSelect && progressionType && statusProgression[progressionType]) {
        const progression = statusProgression[progressionType];
        const currentIndex = progression.indexOf(currentStatus);
        
        // Disable options that are before current status
        Array.from(statusSelect.options).forEach(option => {
            const optionIndex = progression.indexOf(option.value);
            if (optionIndex >= 0 && optionIndex < currentIndex) {
                option.disabled = true;
            }
        });
        
        // Validate on form submit
        const form = statusSelect.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const selectedIndex = progression.indexOf(statusSelect.value);
                if (selectedIndex >= 0 && selectedIndex < currentIndex) {
                    e.preventDefault();
                    alert('Nie je možné vrátiť objednávku na starší stav.');
                    return false;
                }
                return true;
            });
        }
    }
    
    // Different badge for pickup orders
    const shippingMethod = '<?= strtolower($orderData->shipping_name ?? '') ?>';
    if (shippingMethod.includes('osobný odber') || shippingMethod.includes('pickup')) {
        const statusBadges = document.querySelectorAll('.status-delivered');
        statusBadges.forEach(badge => {
            if (badge.textContent.includes('Doručené')) {
                badge.textContent = 'Pripravené na odber';
                badge.className = badge.className.replace('status-delivered', 'status-ready_for_pickup');
            }
        });
    }
});
</script>

<?php require_once 'theme/footer.php'; ?>