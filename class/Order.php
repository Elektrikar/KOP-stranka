<?php

class Order {
    private $db;
    
    public $id;
    public $user_id;
    public $order_email;
    public $status;
    public $total;
    public $address;
    public $created_at;
    public $updated_at;

    public function __construct($db = null) {
        $this->db = $db;
    }

    public function create($user_id, $email, $total, $address, $cartItems) {
        if (!$this->db) {
            throw new Exception("Database connection not available");
        }
        
        if (empty($cartItems)) {
            throw new Exception("Cart is empty");
        }
        
        try {
            $this->db->beginTransaction();
            
            // Insert into orders table
            $stmt = $this->db->prepare("
                INSERT INTO orders (user_id, order_email, total, address, status)
                VALUES (:user_id, :email, :total, :address, 'pending')
            ");
            
            $stmt->execute([
                ':user_id' => $user_id,
                ':email' => $email,
                ':total' => $total,
                ':address' => $address
            ]);
            
            $orderId = $this->db->lastInsertId();
            
            if (!$orderId) {
                throw new Exception("Failed to get order ID");
            }
            
            // Insert order details
            $stmt = $this->db->prepare("
                INSERT INTO order_details (order_id, product_id, quantity, price)
                VALUES (:order_id, :product_id, :quantity, :price)
            ");
            
            foreach ($cartItems as $productId => $item) {
                if (!isset($item['quantity']) || !isset($item['price'])) {
                    throw new Exception("Invalid cart item structure for product ID: $productId");
                }
                
                $stmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $productId,
                    ':quantity' => $item['quantity'],
                    ':price' => $item['price']
                ]);
                
                // Update product stock
                $updateStmt = $this->db->prepare("
                    UPDATE products 
                    SET stock = stock - :quantity 
                    WHERE id = :product_id AND stock >= :quantity
                ");
                
                $updateStmt->execute([
                    ':quantity' => $item['quantity'],
                    ':product_id' => $productId
                ]);
                
                if ($updateStmt->rowCount() === 0) {
                    throw new Exception("Insufficient stock for product ID: $productId");
                }
            }
            
            $this->db->commit();
            return $orderId;
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new Exception("Order creation failed: " . $e->getMessage());
        }
    }

    public function getById($id) {
        if (!$this->db) return null;
        
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->populateFromRow($row);
            return $this;
        }
        return null;
    }

    public function getByUserId($user_id) {
        if (!$this->db) return [];
        
        $stmt = $this->db->prepare("
            SELECT * FROM orders 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC
        ");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderDetails($order_id) {
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

    public function getAll($limit = 50) {
        if (!$this->db) return [];
        
        $stmt = $this->db->prepare("
            SELECT o.*, u.first_name, u.last_name 
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            ORDER BY o.created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($order_id, $status) {
        if (!$this->db) return false;
        
        $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            UPDATE orders 
            SET status = :status 
            WHERE id = :order_id
        ");
        
        return $stmt->execute([
            ':status' => $status,
            ':order_id' => $order_id
        ]);
    }

    private function populateFromRow($row) {
        $this->id = $row['id'];
        $this->user_id = $row['user_id'];
        $this->order_email = $row['order_email'];
        $this->status = $row['status'];
        $this->total = $row['total'];
        $this->address = $row['address'];
        $this->created_at = $row['created_at'];
        $this->updated_at = $row['updated_at'];
    }
}