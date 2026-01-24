<?php

class Cart {
    public function __construct() {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }

    public function add($productId, $productData = null) {
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId]['quantity']++;
        } elseif ($productData) {
            $_SESSION['cart'][$productId] = [
                'name' => $productData['name'],
                'price' => $productData['price'],
                'quantity' => 1,
                'image' => $productData['image']
            ];
        }
    }

    public function update($productId, $quantity)
    {
        if (isset($_SESSION['cart'][$productId])) {
            if ($quantity > 0) {
                $_SESSION['cart'][$productId]['quantity'] = $quantity;
            } else {
                unset($_SESSION['cart'][$productId]);
            }
        }
    }

    public function remove($productId) {
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId]['quantity']--;
            if ($_SESSION['cart'][$productId]['quantity'] <= 0) {
                unset($_SESSION['cart'][$productId]);
            }
        }
    }

    public function clear() {
        $_SESSION['cart'] = [];
    }

    public function updatePrice($productId, $newPrice) {
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId]['price'] = $newPrice;
        }
    }

    public function getQuantity($productId) {
        return isset($_SESSION['cart'][$productId]) ? $_SESSION['cart'][$productId]['quantity'] : 0;
    }

    public function getItems() {
        return $_SESSION['cart'];
    }

    public function getTotal() {
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        return $total;
    }
}
