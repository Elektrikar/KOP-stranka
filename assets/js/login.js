document.querySelector('form')?.addEventListener('submit', function (e) {
    const email = this.querySelector('input[name="email"]').value.trim();
    const password = this.querySelector('input[name="password"]').value;

    if (!email || !password) {
        e.preventDefault();
        alert('Prosím vyplňte všetky polia.');
    }
});