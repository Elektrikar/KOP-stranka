let shippingMethods = {};
let paymentMethods = {};
let baseTotal = 0;

function updateTotal() {
    const shippingId = document.querySelector('input[name="shipping_method"]:checked');
    const paymentId = document.querySelector('input[name="payment_method"]:checked');
    
    let shippingPrice = 0;
    let paymentPrice = 0;
    
    if (shippingId && shippingId.value) {
        const shipping = shippingMethods[shippingId.value];
        if (shipping) {
            shippingPrice = parseFloat(shipping.price);
            // Show shipping line
            const shippingSummary = document.getElementById('shipping-summary');
            if (shippingSummary) {
                shippingSummary.style.display = 'block';
            }
        }
    } else {
        // Hide shipping line if nothing selected
        const shippingSummary = document.getElementById('shipping-summary');
        if (shippingSummary) {
            shippingSummary.style.display = 'none';
        }
    }
    
    if (paymentId && paymentId.value) {
        const payment = paymentMethods[paymentId.value];
        if (payment) {
            paymentPrice = parseFloat(payment.price);
            // Show payment line
            const paymentSummary = document.getElementById('payment-summary');
            if (paymentSummary) {
                paymentSummary.style.display = 'block';
            }
        }
    } else {
        // Hide payment line if nothing selected
        const paymentSummary = document.getElementById('payment-summary');
        if (paymentSummary) {
            paymentSummary.style.display = 'none';
        }
    }
    
    const finalTotal = baseTotal + shippingPrice + paymentPrice;

    const shippingAmount = document.getElementById('shipping-amount');
    if (shippingAmount) {
        shippingAmount.textContent = shippingPrice.toFixed(2).replace('.', ',') + ' €';
    }
    
    const paymentAmount = document.getElementById('payment-amount');
    if (paymentAmount) {
        paymentAmount.textContent = paymentPrice.toFixed(2).replace('.', ',') + ' €';
    }
    
    const totalPrice = document.querySelector('.total-price');
    if (totalPrice) {
        totalPrice.textContent = finalTotal.toFixed(2).replace('.', ',') + ' €';
    }
}

function updateSelectionStates() {
    const shippingOptions = document.querySelectorAll('.shipping-option');
    const paymentOptions = document.querySelectorAll('.payment-option');

    shippingOptions.forEach(option => {
        const radio = option.querySelector('input[type="radio"]');
        if (radio && radio.checked) {
            option.classList.add('selected');
        } else {
            option.classList.remove('selected');
        }
    });

    paymentOptions.forEach(option => {
        const radio = option.querySelector('input[type="radio"]');
        if (radio && radio.checked) {
            option.classList.add('selected');
        } else {
            option.classList.remove('selected');
        }
    });
}

function initCheckoutShipping() {
    const orderSummary = document.querySelector('.order-summary');
    if (orderSummary && orderSummary.dataset.shippingMethods) {
        try {
            shippingMethods = JSON.parse(orderSummary.dataset.shippingMethods);
            paymentMethods = JSON.parse(orderSummary.dataset.paymentMethods);
            baseTotal = parseFloat(orderSummary.dataset.baseTotal);
        } catch (e) {
            console.error('Error parsing checkout data:', e);
        }
    }

    const shippingOptions = document.querySelectorAll('.shipping-option');
    shippingOptions.forEach(option => {
        const radio = option.querySelector('input[type="radio"]');
        if (radio) {
            option.addEventListener('click', function(e) {
                if (e.target.type !== 'radio') {
                    radio.checked = true;
                    updateSelectionStates();
                    updateTotal();
                }
            });
            
            radio.addEventListener('change', function() {
                updateSelectionStates();
                updateTotal();
            });
        }
    });

    const paymentOptions = document.querySelectorAll('.payment-option');
    paymentOptions.forEach(option => {
        const radio = option.querySelector('input[type="radio"]');
        if (radio) {
            option.addEventListener('click', function(e) {
                if (e.target.type !== 'radio') {
                    radio.checked = true;
                    updateSelectionStates();
                    updateTotal();
                }
            });
            
            radio.addEventListener('change', function() {
                updateSelectionStates();
                updateTotal();
            });
        }
    });

    updateSelectionStates();
    updateTotal();

    const initialShipping = document.querySelector('input[name="shipping_method"]:checked');
    const initialPayment = document.querySelector('input[name="payment_method"]:checked');

    if (initialShipping) {
        const shippingSummary = document.getElementById('shipping-summary');
        if (shippingSummary) {
            shippingSummary.style.display = 'block';
        }
    }
    
    if (initialPayment) {
        const paymentSummary = document.getElementById('payment-summary');
        if (paymentSummary) {
            paymentSummary.style.display = 'block';
        }
    }

    const checkoutForm = document.getElementById('checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            const terms = document.getElementById('terms');
            if (terms && !terms.checked) {
                e.preventDefault();
                alert('Pre pokračovanie musíte súhlasiť s obchodnými podmienkami.');
            }

            const shippingSelected = document.querySelector('input[name="shipping_method"]:checked');
            const paymentSelected = document.querySelector('input[name="payment_method"]:checked');
            
            if (!shippingSelected || !paymentSelected) {
                e.preventDefault();
                alert('Prosím vyberte spôsob dopravy a platby.');
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', initCheckoutShipping);

window.updateCheckoutTotal = updateTotal;
window.updateCheckoutSelectionStates = updateSelectionStates;