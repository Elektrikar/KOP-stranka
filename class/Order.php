<?php

class Order
{
    private $db;

    public $id;
    public $user_id;
    public $order_email;
    public $status;
    public $total;
    public $address;
    public $shipping_id;
    public $payment_id;
    public $created_at;
    public $updated_at;
    public $shipping_name;
    public $shipping_price;
    public $shipping_description;
    public $payment_name;
    public $payment_price;
    public $payment_description;

    public function __construct($db = null)
    {
        $this->db = $db;
    }

    public function create($user_id, $email, $total, $address, $cartItems, $shipping_id = null, $payment_id = null)
    {
        if (!$this->db) {
            throw new Exception("Database connection not available");
        }

        if (empty($cartItems)) {
            throw new Exception("Cart is empty");
        }

        try {
            $this->db->beginTransaction();

            $updatedCartItems = [];
            $calculatedTotal = 0;
            
            // Get shipping cost if shipping_id is provided
            $shipping_cost = 0;
            if ($shipping_id) {
                $shipping_stmt = $this->db->prepare("SELECT price FROM shipping_methods WHERE id = ?");
                $shipping_stmt->execute([$shipping_id]);
                $shipping_data = $shipping_stmt->fetch(PDO::FETCH_ASSOC);
                if ($shipping_data) {
                    $shipping_cost = $shipping_data['price'];
                }
            }
            
            // Get payment fee if payment_id is provided
            $payment_fee = 0;
            if ($payment_id) {
                $payment_stmt = $this->db->prepare("SELECT price FROM payment_methods WHERE id = ?");
                $payment_stmt->execute([$payment_id]);
                $payment_data = $payment_stmt->fetch(PDO::FETCH_ASSOC);
                if ($payment_data) {
                    $payment_fee = $payment_data['price'];
                }
            }
            
            foreach ($cartItems as $productId => $item) {
                if (!isset($item['quantity']) || !isset($item['price'])) {
                    throw new Exception("Invalid cart item structure for product ID: $productId");
                }

                $priceStmt = $this->db->prepare("SELECT price, discount_price, stock FROM products WHERE id = ?");
                $priceStmt->execute([$productId]);
                $priceData = $priceStmt->fetch(PDO::FETCH_ASSOC);

                if (!$priceData) {
                    throw new Exception("Product with ID: $productId not found");
                }

                if ($item['quantity'] > $priceData['stock']) {
                    throw new Exception("Insufficient stock for product ID: $productId. Available: {$priceData['stock']}, Requested: {$item['quantity']}");
                }

                $finalPrice = $priceData['discount_price'] && $priceData['discount_price'] < $priceData['price'] 
                    ? $priceData['discount_price'] 
                    : $priceData['price'];

                $updatedCartItems[$productId] = [
                    'quantity' => $item['quantity'],
                    'price' => $finalPrice,
                    'name' => $item['name'] ?? '',
                    'image' => $item['image'] ?? ''
                ];

                $calculatedTotal += $finalPrice * $item['quantity'];
            }
            
            // Calculate final total with shipping and payment fees
            $finalTotal = $calculatedTotal + $shipping_cost + $payment_fee;

            $orderStmt = $this->db->prepare("
                INSERT INTO orders (user_id, order_email, total, address, shipping_id, payment_id, status)
                VALUES (:user_id, :email, :total, :address, :shipping_id, :payment_id, 'pending')
            ");

            $orderStmt->execute([
                ':user_id' => $user_id,
                ':email' => $email,
                ':total' => $finalTotal,
                ':address' => $address,
                ':shipping_id' => $shipping_id,
                ':payment_id' => $payment_id
            ]);

            $orderId = $this->db->lastInsertId();

            if (!$orderId) {
                throw new Exception("Failed to get order ID");
            }

            $detailStmt = $this->db->prepare("
                INSERT INTO order_details (order_id, product_id, quantity, price)
                VALUES (:order_id, :product_id, :quantity, :price)
            ");

            $updateStockStmt = $this->db->prepare("
                UPDATE products 
                SET stock = stock - :quantity,
                    sales_count = sales_count + :quantity 
                WHERE id = :product_id
            ");

            foreach ($updatedCartItems as $productId => $item) {
                $detailStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $productId,
                    ':quantity' => $item['quantity'],
                    ':price' => $item['price']
                ]);

                $updateStockStmt->execute([
                    ':quantity' => $item['quantity'],
                    ':product_id' => $productId
                ]);

                if ($updateStockStmt->rowCount() === 0) {
                    throw new Exception("Failed to update stock for product ID: $productId");
                }
            }

            $this->db->commit();
            $this->sendOrderConfirmationEmail($orderId, $email, $address);
            return $orderId;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new Exception("Order creation failed: " . $e->getMessage());
        }
    }

    public function getById($id)
    {
        if (!$this->db) return null;

        $stmt = $this->db->prepare("
            SELECT o.*, 
                   sm.name as shipping_name, sm.price as shipping_price, sm.description as shipping_description,
                   pm.name as payment_name, pm.price as payment_price, pm.description as payment_description
            FROM orders o
            LEFT JOIN shipping_methods sm ON sm.id = o.shipping_id
            LEFT JOIN payment_methods pm ON pm.id = o.payment_id
            WHERE o.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->populateFromRow($row);
            return $this;
        }
        return null;
    }

    public function getByUserId($user_id)
    {
        if (!$this->db) return [];

        $stmt = $this->db->prepare("
            SELECT o.*, 
                   sm.name as shipping_name, sm.price as shipping_price,
                   pm.name as payment_name, pm.price as payment_price
            FROM orders o
            LEFT JOIN shipping_methods sm ON sm.id = o.shipping_id
            LEFT JOIN payment_methods pm ON pm.id = o.payment_id
            WHERE o.user_id = :user_id 
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderDetails($order_id)
    {
        if (!$this->db) return [];

        $stmt = $this->db->prepare("
            SELECT od.*, p.name, p.image 
            FROM order_details od
            JOIN products p ON p.id = od.product_id
            WHERE od.order_id = :order_id
        ");
        $stmt->execute([':order_id' => $order_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll($limit = 50)
    {
        if (!$this->db) return [];

        $stmt = $this->db->prepare("
            SELECT o.*, 
                   u.first_name, u.last_name,
                   sm.name as shipping_name, sm.price as shipping_price,
                   pm.name as payment_name, pm.price as payment_price
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            LEFT JOIN shipping_methods sm ON sm.id = o.shipping_id
            LEFT JOIN payment_methods pm ON pm.id = o.payment_id
            ORDER BY o.created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function sendOrderConfirmationEmail($orderId, $email) {
        try {
            require_once __DIR__ . '/Email.php';
            
            // Get order details for email
            $orderStmt = $this->db->prepare("
                SELECT o.*, 
                    u.first_name, u.last_name
                FROM orders o
                LEFT JOIN users u ON u.id = o.user_id
                WHERE o.id = ?
            ");
            $orderStmt->execute([$orderId]);
            $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($orderData) {
                $name = !empty($orderData['first_name']) 
                    ? $orderData['first_name'] . ' ' . $orderData['last_name']
                    : 'Zákazník';
                
                $emailService = new Email($this->db);
                return $emailService->sendOrderConfirmation($email, $name, $orderData);
            }
        } catch (Exception $e) {
            // Log error but don't interrupt the order process
            error_log("Failed to send order confirmation email: " . $e->getMessage());
        }
        return false;
    }

    public function updateStatus($order_id, $status) {
        if (!$this->db) return false;

        $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE orders 
            SET status = :status, 
                updated_at = NOW()
            WHERE id = :order_id
        ");

        $result = $stmt->execute([
            ':status' => $status,
            ':order_id' => $order_id
        ]);
        
        if ($result) {
            $this->sendStatusUpdateEmail($order_id, $status);
            return true;
        }
        
        return false;
    }

    private function sendStatusUpdateEmail($orderId, $newStatus) {
        try {
            require_once __DIR__ . '/Email.php';
            
            // Get order details for email
            $orderStmt = $this->db->prepare("
                SELECT o.*, 
                    u.first_name, u.last_name, u.email
                FROM orders o
                LEFT JOIN users u ON u.id = o.user_id
                WHERE o.id = ?
            ");
            $orderStmt->execute([$orderId]);
            $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($orderData && !empty($orderData['email'])) {
                $name = !empty($orderData['first_name']) 
                    ? $orderData['first_name'] . ' ' . $orderData['last_name']
                    : 'Zákazník';

                // Update the status in order data for the email template
                $orderData['status'] = $newStatus;
                
                $emailService = new Email($this->db);
                return $emailService->sendOrderStatusUpdate($orderData['email'], $name, $orderData);
            }
        } catch (Exception $e) {
            // Log error but don't interrupt the status update
            error_log("Failed to send status update email: " . $e->getMessage());
        }
        return false;
    }

    private function populateFromRow($row) {
        $this->id = $row['id'];
        $this->user_id = $row['user_id'];
        $this->order_email = $row['order_email'];
        $this->status = $row['status'];
        $this->total = $row['total'];
        $this->address = $row['address'];
        $this->shipping_id = $row['shipping_id'];
        $this->payment_id = $row['payment_id'];
        $this->created_at = $row['created_at'];
        $this->updated_at = $row['updated_at'];

        $this->shipping_name = $row['shipping_name'];
        $this->shipping_price = $row['shipping_price'];
        $this->shipping_description = $row['shipping_description'];
        $this->payment_name = $row['payment_name'];
        $this->payment_price = $row['payment_price'];
        $this->payment_description = $row['payment_description'];
    }

    public function getSubtotal($order_id) {
        if (!$this->db) return 0;
        
        $stmt = $this->db->prepare("
            SELECT SUM(quantity * price) as subtotal 
            FROM order_details 
            WHERE order_id = :order_id
        ");
        $stmt->execute([':order_id' => $order_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['subtotal'] ?? 0;
    }
}