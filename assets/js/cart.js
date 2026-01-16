$(function () {
    function formatPrice(number) {
        return number
        .replace(/,/g, ' ')
        .replace('.', ',');
    }

    function updateRow(btnOrInput, res) {
        // Update quantity input
        var summary = btnOrInput.closest('.cart-summary');
        summary.find('.cart-qty-input').val(res.quantity);
        // Update subtotal
        var row = btnOrInput.closest('tr');
        row.find('td').eq(4).text(formatPrice(res.subtotal) + ' €');
        // Update total
        var totalCell = $('.cart-table tr:last-child td:last-child');
        totalCell.text(formatPrice(res.total) + ' €');
    }

    function showCartError(message) {
        const errorDiv = $('<div class="stock-error" style="position: fixed; top: 20px; right: 20px; background: #f44336; color: white; padding: 10px 20px; border-radius: 4px; z-index: 9999; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">' + message + '</div>');
        $('body').append(errorDiv);

        setTimeout(function() {
            errorDiv.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    $(document).on('click', '.cart-plus', function (e) {
        e.preventDefault();
        var btn = $(this);
        var productId = btn.data('id');
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
                updateRow(btn, res);
                if (window.notifyCartUpdated) {
                    window.notifyCartUpdated();
                }
            } else {
                if (res.message) {
                    showCartError(res.message);
                }
            }
        }).fail(function (xhr, status, error) {
            console.error('AJAX request failed:', status, error);
        });
    });

    $(document).on('click', '.cart-minus', function (e) {
        e.preventDefault();
        var btn = $(this);
        var productId = btn.data('id');
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
                updateRow(btn, res);
                if (res.quantity <= 0) {
                    location.reload();
                }
                if (window.notifyCartUpdated) {
                    window.notifyCartUpdated();
                }
            }
        }).fail(function (xhr, status, error) {
            console.error('AJAX request failed:', status, error);
        });
    });

    $(document).on('change', '.cart-qty-input', function (e) {
        var input = $(this);
        var productId = input.data('id');
        var newQty = parseInt(input.val(), 10);
        if (isNaN(newQty) || newQty < 1) {
            input.val(1);
            newQty = 1;
        }
        $.post('cart.php', {
            id: productId,
            action: 'set',
            quantity: newQty
        }, function (res) {
            try {
                res = JSON.parse(res);
            } catch (e) {
                console.error('Error parsing response:', e);
                return;
            }
            if (res.success) {
                input.val(res.quantity);
                var row = input.closest('tr');
                row.find('td').eq(4).text(formatPrice(res.subtotal) + ' €');
                var totalCell = $('.cart-table tr:last-child td:last-child strong');
                totalCell.text(formatPrice(res.total) + ' €');
                if (window.notifyCartUpdated) {
                    window.notifyCartUpdated();
                }
            } else {
                // Reset input to current quantity
                if (res.message) {
                    showCartError(res.message);
                    var currentRow = input.closest('tr');
                    var currentQty = currentRow.find('.cart-qty-input').val();
                    input.val(currentQty);
                }
            }
        }).fail(function (xhr, status, error) {
            console.error('AJAX request failed:', status, error);
        });
    });

    $(document).on('click', '#empty-cart-btn', function (e) {
        e.preventDefault();
        if (confirm('Naozaj chcete vyprázdniť celý košík?')) {
            $.post('cart.php', {
                empty_cart: 'true'
            }, function (res) {
                try {
                    res = JSON.parse(res);
                } catch (e) {
                    console.error('Error parsing response:', e);
                    return;
                }
                if (res.success && res.redirect) {
                    location.reload();
                }
            }).fail(function (xhr, status, error) {
                console.error('AJAX request failed:', status, error);
                location.reload();
            });
        }
    });
    
    $(document).on('keypress', '.cart-qty-input', function (e) {
        if (e.which < 48 || e.which > 57) { // Not a digit
            e.preventDefault();
        }

        if (e.which === 13) { // Enter key
            $(this).trigger('change');
        }
    });
});