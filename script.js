document.addEventListener('DOMContentLoaded', () => {
    const quantityInputs = document.querySelectorAll('.quantity-input');
    const increaseButtons = document.querySelectorAll('.increase-quantity');
    const decreaseButtons = document.querySelectorAll('.decrease-quantity');
    const orderTotalSpan = document.getElementById('orderTotal');
    const orderForm = document.getElementById('orderForm');
    const alertMessage = document.getElementById('alertMessage');
    const deliveryMethodRadios = document.querySelectorAll('input[name="deliveryMethod"]');
    const paymentMethodSelect = document.getElementById('paymentMethod');
    const deliveryFeeDisplay = document.getElementById('deliveryFeeDisplay');

    const cookiePrices = {
        'chocochip': { single: 6, pair: 10 },
        'oreomg': { single: 6, pair: 10 },
        'snickerdoodle': { single: 6, pair: 10 },
        'peanutbutter': { single: 6, pair: 10 }
    };

    let currentDeliveryFee = 0;
    let paymentMessages = {};

    // Function to fetch settings from the server
    const fetchSettings = async () => {
        try {
            const response = await fetch('get_settings.php');
            const settings = await response.json();

            if (settings.success) {
                currentDeliveryFee = parseFloat(settings.settings.delivery_fee_amount) || 0; // Access .settings.
                deliveryFeeDisplay.textContent = ` (+$${currentDeliveryFee.toFixed(2)})`;

                paymentMessages = {
                    'Cash': settings.settings.cash_payment_message,
                    'CreditCard': settings.settings.creditcard_payment_message,
                    'Venmo': settings.settings.venmo_payment_message
                    // Removed ATHMovil
                };
                calculateTotal(); // Recalculate total after settings load
            } else {
                console.error("Failed to load settings:", settings.message);
                // Fallback to default if loading fails
                currentDeliveryFee = 2;
                deliveryFeeDisplay.textContent = ` (+$${currentDeliveryFee.toFixed(2)})`;
                paymentMessages = {
                    'Cash': 'Please have exact cash ready for pickup/delivery.',
                    'CreditCard': 'You will be sent a secure payment link via email/text shortly.',
                    'Venmo': '@CourtneysCookies'
                };
            }
        } catch (error) {
            console.error('Error fetching settings:', error);
            // Fallback to default if network error
            currentDeliveryFee = 2;
            deliveryFeeDisplay.textContent = ` (+$${currentDeliveryFee.toFixed(2)})`;
            paymentMessages = {
                'Cash': 'Please have exact cash ready for pickup/delivery.',
                'CreditCard': 'You will be sent a secure payment link via email/text shortly.',
                'Venmo': '@CourtneysCookies'
            };
        }
    };

    // Function to calculate total price based on quantities and delivery method
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

    // Event listeners for quantity buttons
    increaseButtons.forEach(button => {
        button.addEventListener('click', () => {
            const product = button.dataset.product;
            const input = document.querySelector(`.quantity-input[data-product="${product}"]`);
            input.value = parseInt(input.value) + 1;
            calculateTotal();
        });
    });

    decreaseButtons.forEach(button => {
        button.addEventListener('click', () => {
            const product = button.dataset.product;
            const input = document.querySelector(`.quantity-input[data-product="${product}"]`);
            if (parseInt(input.value) > 0) {
                input.value = parseInt(input.value) - 1;
                calculateTotal();
            }
        });
    });

    // Event listener for manual input changes
    quantityInputs.forEach(input => {
        input.addEventListener('change', () => {
            if (parseInt(input.value) < 0 || isNaN(parseInt(input.value))) {
                input.value = 0;
            }
            calculateTotal();
        });
    });

    // Event listener for delivery method change
    deliveryMethodRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            calculateTotal();
        });
    });

    // Handle form submission
    orderForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        alertMessage.classList.remove('show', 'success', 'error');
        alertMessage.textContent = '';

        const formData = new FormData(orderForm);

        // Append cookie quantities to form data
        quantityInputs.forEach(input => {
            const product = input.dataset.product;
            const quantity = parseInt(input.value);
            formData.append(product + 'Quantity', quantity);
        });

        // Append calculated total amount
        formData.append('totalAmount', orderTotalSpan.textContent);
        // Append current delivery fee (as a number)
        formData.append('actualDeliveryFee', document.querySelector('input[name="deliveryMethod"]:checked').value === 'delivery' ? currentDeliveryFee : 0);

        // Get selected payment method and its message
        const selectedPaymentMethod = paymentMethodSelect.value;
        const paymentMessage = paymentMessages[selectedPaymentMethod] || '';
        formData.append('selectedPaymentMethod', selectedPaymentMethod);
        formData.append('paymentMessage', paymentMessage); // Pass the message to the backend

        // For all payment methods (Cash, Credit Card, Venmo), proceed directly with form submission
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
            } else {
                alertMessage.classList.add('show', 'error');
                alertMessage.textContent = result.message || 'There was an error placing your order.';
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            alertMessage.classList.add('show', 'error');
            alertMessage.textContent = 'Network error or server unreachable.';
        }
    });

    // Initial load: Fetch settings and then calculate total
    fetchSettings();
});