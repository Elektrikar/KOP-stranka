$(function () {
    function cartSummaryHtmlJs(qty) {
        return '<div class="cart-summary">' +
            '<div class="cart-label">V košíku</div>' +
            '<div class="cart-container">' +
            '<button class="cart-minus">-</button>' +
            '<div class="cart-qty">' + qty + '</div>' +
            '<button class="cart-plus">+</button>' +
            '</div>' +
            '</div>';
    }

    function updateCartSummary(card, qty) {
        if (qty > 0) {
            card.find('.cart-summary .cart-qty').text(qty);
        } else {
            card.find('.cart-summary').remove();
            card.find('.bottom').append('<button class="btn btn-cart add-to-cart" data-product-id="' + card.data('product-id') + '">Do košíka</button>');
        }
    }

    $(document).on('click', '.add-to-cart', function (e) {
        e.preventDefault();
        var btn = $(this);
        var productId = btn.data('product-id');
        $.post('cart.php', {
            id: productId,
            action: 'add'
        }, function (res) {
            try {
                res = JSON.parse(res);
            } catch (e) {
                console.error('Error parsing response:', e);
                return;
            }
            if (res.success) {
                var card = btn.closest('.product-card');
                btn.remove();
                card.find('.bottom').append(cartSummaryHtmlJs(res.quantity));
            }
        }).fail(function (xhr, status, error) {
            console.error('AJAX request failed:', status, error);
        });
    });

    $(document).on('click', '.cart-plus', function (e) {
        e.preventDefault();
        var card = $(this).closest('.product-card');
        var productId = card.data('product-id');
        $.post('cart.php', {
            id: productId,
            action: 'add'
        }, function (res) {
            try {
                res = JSON.parse(res);
            } catch (e) {
                console.error('Error parsing response:', e);
                return;
            }
            if (res.success) {
                updateCartSummary(card, res.quantity);
            }
        }).fail(function (xhr, status, error) {
            console.error('AJAX request failed:', status, error);
        });
    });

    $(document).on('click', '.cart-minus', function (e) {
        e.preventDefault();
        var card = $(this).closest('.product-card');
        var productId = card.data('product-id');
        $.post('cart.php', {
            id: productId,
            action: 'remove'
        }, function (res) {
            try {
                res = JSON.parse(res);
            } catch (e) {
                console.error('Error parsing response:', e);
                return;
            }
            if (res.success) {
                updateCartSummary(card, res.quantity);
            }
        }).fail(function (xhr, status, error) {
            console.error('AJAX request failed:', status, error);
        });
    });
});