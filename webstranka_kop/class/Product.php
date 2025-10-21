<?php
class Product {
    public $id;
    public $name;
    public $price;
    public $image;
    public $description;

    public function __construct($data) {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->price = $data['price'];
        $this->image = $data['image'];
        $this->description = $data['description'];
    }

    public static function fetchAll($pdo) {
        $stmt = $pdo->query("SELECT * FROM products");
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = new Product($row);
        }
        return $products;
    }
}
