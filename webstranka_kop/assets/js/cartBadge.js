$(function () {
    function updateCartBadge() {
        $.get('cart.php', {
            action: 'count'
        }, function (res) {
            try {
                res = JSON.parse(res);
            } catch (e) {
                return;
            }
            var badge = $('.cart-badge');
            if (res.count > 0) {
                if (badge.length) {
                    badge.text(res.count);
                } else {
                    $('.cart-icon').parent().append('<span class="cart-badge">' + res.count + '</span>');
                }
            } else {
                badge.remove();
            }
        });
    }
    setInterval(updateCartBadge, 1000);
});