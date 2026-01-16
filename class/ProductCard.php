<?php

class ProductCard {
    private $product;
    private $cartQuantity;
    private $context;

    public function __construct($product, $cartQuantity = 0, $context = 'index') {
        $this->product = $product;
        $this->cartQuantity = $cartQuantity;
        $this->context = $context;
    }

    public function render() {
        $product = $this->product;
        $cartQty = $this->cartQuantity;
        $context = $this->context;

        list($stockClass, $stockText) = $this->getStockStatus($product->stock);

        $inCart = $cartQty > 0;

        $html = '<div class="product-card" data-product-id="' . htmlspecialchars($product->id) . '">';
        $html .= '<div class="top">';
        $html .= '<a href="product_detail.php?id=' . $product->id . '" class="product-image-link">';
        $html .= '<img src="img/product/' . htmlspecialchars($product->image) . '" alt="' . htmlspecialchars($product->name) . '" class="product-image">';
        $html .= '</a>';
        $html .= '<div class="product-info">';
        $html .= '<h3 class="product-title"><a href="product_detail.php?id=' . $product->id . '">' . htmlspecialchars($product->name) . '</a></h3>';

        if ($context === 'index' || $context === 'related') {
            $html .= '<p class="product-description product-description-ellipsis">' . htmlspecialchars($product->description) . '</p>';
        } else if ($context === 'detail') {
            $html .= '<div class="product-description-full">' . nl2br(htmlspecialchars($product->description)) . '</div>';
        }

        $html .= '<div class="product-stock-container">';
        $html .= '<div class="product-stock ' . $stockClass . '">' . $stockText . '</div>';
        $html .= '</div>';

        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="bottom">';
        $html .= '<div class="product-price">' . number_format($product->price, 2, ',', ' ') . ' €</div>';

        if ($product->stock <= 0) {
            // No button if out of stock
        } else if ($inCart) {
            $html .= $this->renderCartSummary($cartQty);
        } else {
            $html .= '<button class="btn btn-cart add-to-cart" data-product-id="' . htmlspecialchars($product->id) . '">Do košíka</button>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private function getStockStatus($stock) {
        if ($stock <= 0) {
            return ['out-of-stock', 'Momentálne nedostupné'];
        } elseif ($stock <= 5) {
            return ['low-stock', 'Posledných ' . $stock . ' ks'];
        } else {
            return ['in-stock', 'Skladom ' . $stock . ' ks'];
        }
    }

    private function renderCartSummary($qty) {
        return '<div class="cart-summary">
            <div class="cart-label">V košíku</div>
            <div class="cart-container">
                <button class="cart-minus">-</button>
                <div class="cart-qty">' . $qty . '</div>
                <button class="cart-plus">+</button>
            </div>
        </div>';
    }

    public static function renderGrid($products, $cart, $context = 'index') {
        $html = '<div class="products">';
        foreach ($products as $product) {
            $cartQty = $cart->getQuantity($product->id);
            $card = new self($product, $cartQty, $context);
            $html .= $card->render();
        }
        $html .= '</div>';
        return $html;
    }

    public static function renderRelatedProducts($products, $cart, $limit = 3) {
        if (empty($products)) {
            return '';
        }

        $products = array_slice($products, 0, $limit);

        $html = '<div class="products">';

        foreach ($products as $product) {
            $cartQty = $cart->getQuantity($product['id']);
            $productObj = new stdClass();
            foreach ($product as $key => $value) {
                $productObj->$key = $value;
            }
            $card = new self($productObj, $cartQty, 'related');
            $html .= $card->render();
        }

        $html .= '</div>';
        return $html;
    }
}