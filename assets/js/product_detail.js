$(function() {
    // Helper function to show error messages
    function showCartError(message) {
        const errorDiv = $('<div class="stock-error" style="position: fixed; top: 20px; right: 20px; background: #f44336; color: white; padding: 10px 20px; border-radius: 4px; z-index: 9999; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">' + message + '</div>');
        $('body').append(errorDiv);

        setTimeout(function() {
            errorDiv.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Helper function to show success message
    function showCartSuccess(message) {
        const successDiv = $('<div class="cart-success" style="position: fixed; top: 20px; right: 20px; background: #4CAF50; color: white; padding: 10px 20px; border-radius: 4px; z-index: 9999; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">' + message + '</div>');
        $('body').append(successDiv);

        setTimeout(function() {
            successDiv.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Handle add to cart button click
    $(document).on('click', '.add-to-cart', function (e) {
        e.preventDefault();
        const btn = $(this);
        const productId = btn.data('product-id');
        
        btn.prop('disabled', true).addClass('loading');
        
        $.post('cart.php', {
            id: productId,
            action: 'add'
        }, function(res) {
            try {
                res = JSON.parse(res);
            } catch (e) {
                console.error('Error parsing response:', e);
                showCartError('Chyba pri spracovaní odpovede');
                btn.prop('disabled', false).removeClass('loading');
                return;
            }
            
            if (res.success) {
                showCartSuccess('Produkt bol pridaný do košíka');

                const cartActions = btn.closest('.cart-actions');
                if (cartActions.length) {
                    cartActions.find('.add-to-cart-form').replaceWith(
                        '<div class="cart-summary" id="cart-summary-' + productId + '">' +
                        '<div class="cart-label">Produkt je v košíku</div>' +
                        '<a href="cart.php" class="btn-view-cart">' +
                        'Zobraziť košík' +
                        '</a>' +
                        '</div>'
                    );
                }

                $('.product-card[data-product-id="' + productId + '"] .product-actions').each(function() {
                    const $actions = $(this);
                    $actions.html(
                        '<div class="in-cart-small">' +
                        '<span class="cart-label">V košíku</span>' +
                        '</div>'
                    );
                });

                if (window.notifyCartUpdated) {
                    window.notifyCartUpdated();
                }
            } else {
                showCartError(res.message || 'Chyba pri pridávaní do košíka.');
            }
            btn.prop('disabled', false).removeClass('loading');
        }).fail(function() {
            showCartError('Chyba pri komunikácii so serverom');
            btn.prop('disabled', false).removeClass('loading');
        });
    });

    // Wishlist toggle animation
    $('.btn-wishlist').on('click', function(e) {
        const btn = $(this);
        btn.addClass('loading');
        
        setTimeout(() => {
            btn.removeClass('loading');
        }, 500);
    });
    
    /* NOTIFY FEATURE - currently disabled
    $('.btn-notify').on('click', function() {
        const productId = $(this).data('product-id');
        const email = prompt('Zadajte váš email pre upozornenie:');
        
        if (email && email.includes('@')) {
            alert('Ďakujeme! Upozorníme vás, keď bude produkt opäť dostupný.');
        }
    });*/

    $('.main-image img').on('click', function() {
        const src = $(this).attr('src');
        const modal = $('<div class="image-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.9); z-index: 10000; display: flex; align-items: center; justify-content: center;">' +
                       '<img src="' + src + '" style="max-width: 90%; max-height: 90%; object-fit: contain;">' +
                       '<button style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: white; font-size: 2em; cursor: pointer;">×</button>' +
                       '</div>');
        
        $('body').append(modal);
        
        modal.find('button').on('click', function() {
            modal.remove();
        });
        
        modal.on('click', function(e) {
            if (e.target === this) {
                modal.remove();
            }
        });
    });
});