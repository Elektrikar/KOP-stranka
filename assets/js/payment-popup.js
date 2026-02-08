class PaymentPopup {
    constructor() {
        this.popup = null;
        this.form = null;
        this.init();
    }

    init() {
        // popup HTML
        this.popup = document.createElement('div');
        this.popup.className = 'payment-popup-overlay';
        this.popup.innerHTML = `
            <div class="payment-popup">
                <div class="payment-popup-header">
                    <h3>Simulácia platby kartou</h3>
                    <button class="payment-popup-close">&times;</button>
                </div>
                <div class="payment-popup-content">
                    <div class="payment-method-icons">
                        <div class="payment-icon visa"></div>
                        <div class="payment-icon mastercard"></div>
                        <div class="payment-icon amex"></div>
                    </div>
                    
                    <form class="payment-form" id="paymentSimulationForm">
                        <div class="form-group">
                            <label for="cardNumber">Číslo karty</label>
                            <input type="text" id="cardNumber" name="card_number" 
                                   placeholder="1234 5678 9012 3456" 
                                   maxlength="19"
                                   required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cardExpiry">Platnosť</label>
                                <input type="text" id="cardExpiry" name="card_expiry" 
                                       placeholder="MM/RR" 
                                       maxlength="5"
                                       pattern="(0[1-9]|1[0-2])\/[0-9]{2}"
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="cardCVC">CVC</label>
                                <input type="password" id="cardCVC" name="card_cvc" 
                                       placeholder="123" 
                                       maxlength="4"
                                       pattern="[0-9]{3,4}"
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cardName">Meno na karte</label>
                            <input type="text" id="cardName" name="card_name" 
                                   placeholder="JOHN DOE" 
                                   required>
                        </div>
                        
                        <div class="payment-actions">
                            <button type="button" class="btn-payment-cancel">Zrušiť</button>
                            <button type="submit" class="btn-payment-submit">
                                <span class="btn-text">Zaplatiť</span>
                                <span class="loading-spinner" style="display: none;"></span>
                            </button>
                        </div>
                        
                        <div class="payment-disclaimer">
                            <p><strong>⚠ Dôležité upozornenie:</strong> Toto je len simulácia platby. Žiadna skutočná platba sa nevykoná. Nezadávajte skutočné údaje platobnej karty.</p>
                        </div>
                    </form>
                </div>
            </div>
        `;

        document.body.appendChild(this.popup);

        this.bindEvents();
    }

    bindEvents() {
        this.popup.querySelector('.payment-popup-close').addEventListener('click', () => this.close());
        this.popup.querySelector('.btn-payment-cancel').addEventListener('click', () => this.close());
        
        // Overlay click
        this.popup.addEventListener('click', (e) => {
            if (e.target === this.popup) this.close();
        });
        
        // Form submission
        this.form = this.popup.querySelector('#paymentSimulationForm');
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Card number formatting
        const cardNumberInput = this.popup.querySelector('#cardNumber');
        cardNumberInput.addEventListener('input', (e) => this.formatCardNumber(e));
        
        // Expiry date formatting
        const expiryInput = this.popup.querySelector('#cardExpiry');
        expiryInput.addEventListener('input', (e) => this.formatExpiryDate(e));
    }

    formatCardNumber(e) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.replace(/(\d{4})/g, '$1 ').trim();
        value = value.substring(0, 19); // Max 16 digits + 3 spaces
        e.target.value = value;
    }

    formatExpiryDate(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        e.target.value = value;
    }

    show() {
        this.popup.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Select card number automatically
        setTimeout(() => {
            this.popup.querySelector('#cardNumber').focus();
        }, 100);
    }

    close() {
        this.popup.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    async handleSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('.btn-payment-submit');
        const btnText = submitBtn.querySelector('.btn-text');
        const spinner = submitBtn.querySelector('.loading-spinner');
        
        // Show loading state
        btnText.textContent = 'Spracovávam...';
        spinner.style.display = 'inline-block';
        submitBtn.disabled = true;
        
        // Validate card number (basic Luhn algorithm check)
        const cardNumber = form.card_number.value.replace(/\s/g, '');
        if (!this.validateCardNumber(cardNumber)) {
            this.showError('Neplatné číslo karty.');
            btnText.textContent = 'Zaplatiť';
            spinner.style.display = 'none';
            submitBtn.disabled = false;
            return;
        }
        
        // Validate expiry date
        const expiry = form.card_expiry.value;
        if (!this.validateExpiryDate(expiry)) {
            this.showError('Neplatný dátum platnosti. Použite formát MM/RR');
            btnText.textContent = 'Zaplatiť';
            spinner.style.display = 'none';
            submitBtn.disabled = false;
            return;
        }
        
        // Validate CVC
        const cvc = form.card_cvc.value;
        if (cvc.length < 3) {
            this.showError('CVC musí mať aspoň 3 číslice');
            btnText.textContent = 'Zaplatiť';
            spinner.style.display = 'none';
            submitBtn.disabled = false;
            return;
        }
        
        // Simulate API call
        await this.simulatePaymentProcessing();
        
        // Success - submit the original form
        this.close();
        
        // Find and submit the checkout form
        const checkoutForm = document.getElementById('checkout-form');
        if (checkoutForm) {
            checkoutForm.submit();
        }
    }

    validateCardNumber(cardNumber) {
        // Validate using Luhn algorithm
        let sum = 0;
        let isEven = false;
        
        for (let i = cardNumber.length - 1; i >= 0; i--) {
            let digit = parseInt(cardNumber.charAt(i), 10);
            
            if (isEven) {
                digit *= 2;
                if (digit > 9) digit -= 9;
            }
            
            sum += digit;
            isEven = !isEven;
        }
        
        return (sum % 10) === 0;
    }

    validateExpiryDate(expiry) {
        const parts = expiry.split('/');
        if (parts.length !== 2) return false;
        
        const month = parseInt(parts[0], 10);
        const year = parseInt(parts[1], 10) + 2000;
        
        if (month < 1 || month > 12) return false;
        
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1;
        
        if (year < currentYear) return false;
        if (year === currentYear && month < currentMonth) return false;
        
        return true;
    }

    async simulatePaymentProcessing() {
        // Fake network delay
        return new Promise(resolve => {
            setTimeout(resolve, 2000);
        });
    }

    showError(message) {
        // Remove any existing error
        const existingError = this.popup.querySelector('.payment-error');
        if (existingError) existingError.remove();
        
        // Create error
        const errorDiv = document.createElement('div');
        errorDiv.className = 'payment-error';
        errorDiv.innerHTML = `<p>${message}</p>`;

        const form = this.popup.querySelector('.payment-form');
        form.insertBefore(errorDiv, form.firstChild);
        
        // remove after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 5000);
    }
}