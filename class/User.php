<?php

class User {
    private $db;
    
    public $id;
    public $email;
    public $password_hash;
    public $first_name;
    public $last_name;
    public $role;
    public $address;
    public $city;
    public $zip_code;
    public $country;
    public $verification_token;
    public $is_verified;
    public $token_expires_at;
    public $created_at;

    public function __construct($db = null) {
        $this->db = $db;
    }

    public function create($email, $password, $first_name, $last_name, $role = 'user') {
        if (!$this->db) return false;
        
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $verification_token = bin2hex(random_bytes(32));
        $token_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->db->prepare("
            INSERT INTO users (email, password_hash, first_name, last_name, role, verification_token, token_expires_at)
            VALUES (:email, :password_hash, :first_name, :last_name, :role, :verification_token, :token_expires_at)
        ");
        
        return $stmt->execute([
            ':email' => $email,
            ':password_hash' => $password_hash,
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':role' => $role,
            ':verification_token' => $verification_token,
            ':token_expires_at' => $token_expires_at
        ]);
    }

    public function verify($token) {
        if (!$this->db || !$this->id) return false;
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET is_verified = TRUE, 
                verification_token = NULL,
                token_expires_at = NULL
            WHERE id = :id 
            AND verification_token = :token
            AND token_expires_at > NOW()
        ");
        
        return $stmt->execute([
            ':id' => $this->id,
            ':token' => $token
        ]);
    }

    public function getByVerificationToken($token) {
        if (!$this->db) return null;
        
        $stmt = $this->db->prepare("
            SELECT * FROM users 
            WHERE verification_token = :token 
            AND token_expires_at > NOW()
        ");
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->populateFromRow($row);
            return $this;
        }
        return null;
    }

    public function resendVerificationToken() {
        if (!$this->db || !$this->id) return false;
        
        $new_token = bin2hex(random_bytes(32));
        $new_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET verification_token = :token,
                token_expires_at = :expires_at
            WHERE id = :id
        ");
        
        $result = $stmt->execute([
            ':token' => $new_token,
            ':expires_at' => $new_expiry,
            ':id' => $this->id
        ]);
        
        if ($result) {
            $this->verification_token = $new_token;
            $this->token_expires_at = $new_expiry;
            return true;
        }
        
        return false;
    }

    public function getById($id) {
        if (!$this->db) return null;
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->populateFromRow($row);
            return $this;
        }
        return null;
    }

    public function getByEmail($email) {
        if (!$this->db) return null;
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->populateFromRow($row);
            return $this;
        }
        return null;
    }

    public function verifyPassword($password) {
        return password_verify($password, $this->password_hash);
    }

    public function update($data) {
        if (!$this->db || !$this->id) return false;
        
        $allowed = ['email', 'first_name', 'last_name', 'role'];
        $updates = [];
        $params = [':id' => $this->id];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) return false;
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    public function updatePassword($newPassword) {
        if (!$this->db || !$this->id) return false;
        
        $password_hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
        
        return $stmt->execute([
            ':password_hash' => $password_hash,
            ':id' => $this->id
        ]);
    }

    public function delete() {
        if (!$this->db || !$this->id) return false;

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $this->id]);
        $orderCount = $stmt->fetchColumn();
        
        if ($orderCount > 0) {
            // Set orders to guest orders
            $updateStmt = $this->db->prepare("UPDATE orders SET user_id = NULL WHERE user_id = :user_id");
            $updateStmt->execute([':user_id' => $this->id]);
        }
        
        // Delete user from database
        $deleteStmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        return $deleteStmt->execute([':id' => $this->id]);
    }

    public function updateAddress($address, $city, $zip_code, $country) {
        if (!$this->db || !$this->id) return false;
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET address = :address, 
                city = :city, 
                zip_code = :zip_code, 
                country = :country,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $result = $stmt->execute([
            ':address' => $address,
            ':city' => $city,
            ':zip_code' => $zip_code,
            ':country' => $country,
            ':id' => $this->id
        ]);
        
        if ($result) {
            // Update current object properties
            $this->address = $address;
            $this->city = $city;
            $this->zip_code = $zip_code;
            $this->country = $country;
            return true;
        }
        
        return false;
    }

    public function getFullAddress() {
        $parts = [];
        if (!empty($this->address)) $parts[] = $this->address;
        if (!empty($this->city) || !empty($this->zip_code)) {
            $cityPart = '';
            if (!empty($this->zip_code)) $cityPart .= $this->zip_code . ' ';
            if (!empty($this->city)) $cityPart .= $this->city;
            $parts[] = trim($cityPart);
        }
        if (!empty($this->country)) $parts[] = $this->country;
        
        return implode("\n", $parts);
    }

    public function getAll() {
        if (!$this->db) return [];
        
        $stmt = $this->db->query("SELECT * FROM users");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByRole($role) {
        if (!$this->db) return [];
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE role = :role");
        $stmt->execute([':role' => $role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function populateFromRow($row) {
        $this->id = $row['id'];
        $this->email = $row['email'];
        $this->password_hash = $row['password_hash'];
        $this->first_name = $row['first_name'];
        $this->last_name = $row['last_name'];
        $this->role = $row['role'];
        $this->address = $row['address'] ?? null;
        $this->city = $row['city'] ?? null;
        $this->zip_code = $row['zip_code'] ?? null;
        $this->country = $row['country'] ?? null;
        $this->verification_token = $row['verification_token'] ?? null;
        $this->is_verified = $row['is_verified'] ?? false;
        $this->token_expires_at = $row['token_expires_at'] ?? null;
        $this->created_at = $row['created_at'];
    }
}