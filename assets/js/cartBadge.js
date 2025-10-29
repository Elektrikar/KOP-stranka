$(function () {
    function updateCartBadge() {
        $.get('cart.php', { action: 'count' }, function (res) {
            var data = res;
            if (typeof res === 'string') {
                try {
                    data = JSON.parse(res);
                } catch (e) {
                    // silently ignore malformed responses
                    return;
                }
            }
            var badge = $('.cart-badge');
            if (data && data.count > 0) {
                if (badge.length) {
                    badge.text(data.count);
                } else {
                    $('.cart-icon').parent().append('<span class="cart-badge">' + data.count + '</span>');
                }
            } else {
                badge.remove();
            }
        });
    }

    updateCartBadge();

    // Remove any existing function to avoid conflicts
    if (window.notifyCartUpdated) {
        delete window.notifyCartUpdated;
    }
    
    window.notifyCartUpdated = function () {
        updateCartBadge();
        document.dispatchEvent(new CustomEvent('cartUpdated'));
    };

    document.addEventListener('cartUpdated', function () {
        updateCartBadge();
    });

    $(document).on('cartUpdated', function () {
        updateCartBadge();
    });
});