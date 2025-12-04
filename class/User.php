<?php

class User {
    private $db;
    
    public $id;
    public $email;
    public $password_hash;
    public $first_name;
    public $last_name;
    public $role;

    public function __construct($db = null) {
        $this->db = $db;
    }

    public function create($email, $password, $first_name, $last_name, $role = 'user') {
        if (!$this->db) return false;
        
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $this->db->prepare("
            INSERT INTO users (email, password_hash, first_name, last_name, role)
            VALUES (:email, :password_hash, :first_name, :last_name, :role)
        ");
        
        return $stmt->execute([
            ':email' => $email,
            ':password_hash' => $password_hash,
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':role' => $role
        ]);
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
        
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute([':id' => $this->id]);
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
    }
}