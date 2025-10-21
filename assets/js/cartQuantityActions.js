$(function () {
    function updateRow(btnOrInput, res) {
        // Update quantity input
        var summary = btnOrInput.closest('.cart-summary');
        summary.find('.cart-qty-input').val(res.quantity);
        // Update subtotal
        var row = btnOrInput.closest('tr');
        row.find('td').eq(4).text(res.subtotal + ' €');
        // Update total
        var totalCell = $('.cart-table tr:last-child td:last-child strong');
        totalCell.text(res.total + ' €');
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
                row.find('td').eq(4).text(res.subtotal + ' €');
                var totalCell = $('.cart-table tr:last-child td:last-child strong');
                totalCell.text(res.total + ' €');
            }
        }).fail(function (xhr, status, error) {
            console.error('AJAX request failed:', status, error);
        });
    });
    document.querySelector("input").addEventListener("keypress", function (evt) {
        if (evt.which < 48 || evt.which > 57) { // Not a digit
            evt.preventDefault();
        }
    });
    $(document).on('keypress', '.cart-qty-input', function(e) {
        if (e.which === 13) { // Enter key
            $(this).trigger('change');
        }
    });
});