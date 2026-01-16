<?php

class Product {
    public $id;
    public $category_id;
    public $name;
    public $price;
    public $stock;
    public $description;
    public $image;

    public function __construct($data) {
        $this->id = $data['id'];
        $this->category_id = $data['category_id'];
        $this->name = $data['name'];
        $this->price = $data['price'];
        $this->stock = $data['stock'];
        $this->description = $data['description'];
        $this->image = $data['image'];
    }

    public static function fetchAll($pdo) {
        $stmt = $pdo->query("SELECT * FROM products");
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = new Product($row);
        }
        return $products;
    }

    public static function fetchByCategory($pdo, $category_id) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = new Product($row);
        }
        return $products;
    }
}
