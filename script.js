document.addEventListener('DOMContentLoaded', () => {
    // --- Existing variables (no change) ---
    const quantityInputs = document.querySelectorAll('.quantity-input');
    const increaseButtons = document.querySelectorAll('.increase-quantity');
    const decreaseButtons = document.querySelectorAll('.decrease-quantity');
    const orderTotalSpan = document.getElementById('orderTotal');
    const orderForm = document.getElementById('orderForm');
    const alertMessage = document.getElementById('alertMessage');
    const deliveryMethodRadios = document.querySelectorAll('input[name="deliveryMethod"]');
    const paymentMethodSelect = document.getElementById('paymentMethod');
    const deliveryFeeDisplay = document.getElementById('deliveryFeeDisplay');
    const addressSection = document.getElementById('addressSection');

    // --- NEW: Add a variable for the submit button ---
    const submitButton = orderForm.querySelector('button[type="submit"]');

    const cookiePrices = {
        'chocochip': { single: 6, pair: 10 },
        'oreomg': { single: 6, pair: 10 },
        'snickerdoodle': { single: 6, pair: 10 },
        'maplebacon': { single: 6, pair: 10 },
        'peanutbutter': { single: 6, pair: 10 }
        // Note: You had maplebacon twice, I removed the duplicate.
    };

    let currentDeliveryFee = 0;
    let paymentMessages = {};

    // --- All functions like fetchSettings() and calculateTotal() remain unchanged ---
    const fetchSettings = async () => {
        try {
            const response = await fetch('get_settings.php');
            const settings = await response.json();

            if (settings.success) {
                currentDeliveryFee = parseFloat(settings.settings.delivery_fee_amount) || 0;
                deliveryFeeDisplay.textContent = ` (+$${currentDeliveryFee.toFixed(2)})`;

                paymentMessages = {
                    'Cash': settings.settings.cash_payment_message,
                    'CreditCard': settings.settings.creditcard_payment_message,
                    'Venmo': settings.settings.venmo_payment_message,
                    'ATH Movil': settings.settings.athmovil_payment_message
                };
                calculateTotal();
            } else {
                console.error("Failed to load settings:", settings.message);
                // Fallback logic remains
                currentDeliveryFee = 2;
                deliveryFeeDisplay.textContent = ` (+$${currentDeliveryFee.toFixed(2)})`;
                paymentMessages = {
                    'Cash': 'Please have exact cash ready for pickup/delivery.',
                    'CreditCard': 'You will be sent a secure payment link via email/text shortly.',
                    'Venmo': '@CourtneysCookies',
                    'ATH Movil': 'Please send payment (818) 261-1648 Courtney Forgues'
                };
            }
        } catch (error) {
            console.error('Error fetching settings:', error);
             // Fallback logic remains
            currentDeliveryFee = 2;
            deliveryFeeDisplay.textContent = ` (+$${currentDeliveryFee.toFixed(2)})`;
             paymentMessages = {
                'Cash': 'Please have exact cash ready for pickup/delivery.',
                'CreditCard': 'You will be sent a secure payment link via email/text shortly.',
                'Venmo': '@CourtneysCookies',
                'ATH Movil': 'Please send payment (818) 261-1648 Courtney Forgues'
            };
        }
    };

    const calculateTotal = () => {
        let subtotal = 0;
        quantityInputs.forEach(input => {
            const product = input.dataset.product;
            let quantity = parseInt(input.value);

            if (quantity > 0) {
                const price = cookiePrices[product];
                const pairs = Math.floor(quantity / 2);
                const singles = quantity % 2;
                subtotal += (pairs * price.pair) + (singles * price.single);
            }
        });

        let currentOverallTotal = subtotal;
        const selectedDeliveryMethod = document.querySelector('input[name="deliveryMethod"]:checked').value;
        if (selectedDeliveryMethod === 'delivery') {
            currentOverallTotal += currentDeliveryFee;
        }

        orderTotalSpan.textContent = `$${currentOverallTotal.toFixed(2)}`;
    };
    
    // All other event listeners remain unchanged...
    increaseButtons.forEach(button => { button.addEventListener('click', () => { /* ... */ const product = button.dataset.product; const input = document.querySelector(`.quantity-input[data-product="${product}"]`); input.value = parseInt(input.value) + 1; calculateTotal(); }); });
    decreaseButtons.forEach(button => { button.addEventListener('click', () => { /* ... */ const product = button.dataset.product; const input = document.querySelector(`.quantity-input[data-product="${product}"]`); if (parseInt(input.value) > 0) { input.value = parseInt(input.value) - 1; calculateTotal(); } }); });
    quantityInputs.forEach(input => { input.addEventListener('change', () => { /* ... */ if (parseInt(input.value) < 0 || isNaN(parseInt(input.value))) { input.value = 0; } calculateTotal(); }); });
    function toggleAddressSection() { const selectedDeliveryMethod = document.querySelector('input[name="deliveryMethod"]:checked').value; if (selectedDeliveryMethod === 'delivery') { addressSection.style.display = ''; } else { addressSection.style.display = 'none'; } }
    toggleAddressSection();
    deliveryMethodRadios.forEach(radio => { radio.addEventListener('change', () => { calculateTotal(); toggleAddressSection(); }); });


    // Handle form submission
    orderForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        // --- CHANGE: Disable button and show feedback ---
        submitButton.disabled = true;
        submitButton.textContent = 'Placing Order...';

        alertMessage.classList.remove('show', 'success', 'error');
        alertMessage.textContent = '';

        const formData = new FormData(orderForm);
        quantityInputs.forEach(input => {
            const product = input.dataset.product;
            const quantity = parseInt(input.value);
            formData.append(product + 'Quantity', quantity);
        });
        formData.append('totalAmount', orderTotalSpan.textContent.replace('$', ''));
        formData.append('actualDeliveryFee', (document.querySelector('input[name="deliveryMethod"]:checked').value === 'delivery' ? currentDeliveryFee : 0).toString());
        const selectedPaymentMethod = paymentMethodSelect.value;
        const paymentMessage = paymentMessages[selectedPaymentMethod] || '';
        formData.append('selectedPaymentMethod', selectedPaymentMethod);
        formData.append('paymentMessage', paymentMessage);

        try {
            const response = await fetch('process_order.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alertMessage.classList.add('show', 'success');
                alertMessage.innerHTML = `Order placed successfully! Your total is ${result.totalAmount}.<br>Payment Method: ${result.selectedPaymentMethod}.<br><strong>${result.paymentMessage}</strong>`;
                orderForm.reset();
                quantityInputs.forEach(input => input.value = 0);
                calculateTotal();
                // --- CHANGE: Re-enable button with new text for another order ---
                submitButton.disabled = false;
                submitButton.textContent = 'Place Another Order';
            } else {
                alertMessage.classList.add('show', 'error');
                alertMessage.textContent = result.message || 'There was an error placing your order.';
                // --- CHANGE: Re-enable button on error ---
                submitButton.disabled = false;
                submitButton.textContent = 'Place Order';
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            alertMessage.classList.add('show', 'error');
            alertMessage.textContent = 'Network error or server unreachable.';
            // --- CHANGE: Re-enable button on critical error ---
            submitButton.disabled = false;
            submitButton.textContent = 'Place Order';
        }
    });

    // Initial load: Fetch settings and then calculate total
    fetchSettings();
});
// --- END OF SCRIPT ---