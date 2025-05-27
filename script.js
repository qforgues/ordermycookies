document.addEventListener('DOMContentLoaded', () => {
    const quantityInputs = document.querySelectorAll('.quantity-input');
    const increaseButtons = document.querySelectorAll('.increase-quantity');
    const decreaseButtons = document.querySelectorAll('.decrease-quantity');
    const orderTotalSpan = document.getElementById('orderTotal');
    const orderForm = document.getElementById('orderForm');
    const alertMessage = document.getElementById('alertMessage');
    const deliveryMethodRadios = document.querySelectorAll('input[name="delivery_method"]');
    const paymentMethodSelect = document.getElementById('paymentMethod');
    const deliveryFeeDisplay = document.getElementById('deliveryFeeDisplay');
    const paymentMessageDisplay = document.getElementById('paymentMessageDisplay');


    const cookiePrices = {
        'chocolate_chip': { single: 6, pair: 10 },
        'oreomg': { single: 6, pair: 10 },
        'snickerdoodle': { single: 6, pair: 10 },
        'maplebacon': { single: 6, pair: 10 },
        'peanut_butter': { single: 6, pair: 10 }
    };

    // Using default/fallback values since we removed get_settings.php call
    let currentDeliveryFee = 2.00;
    deliveryFeeDisplay.textContent = ` (+$${currentDeliveryFee.toFixed(2)})`;

    const paymentMessages = {
        'Cash': 'Please have exact cash ready for pickup/delivery.',
        'CreditCard': 'You will be sent a secure payment link via email/text shortly.',
        'Venmo': 'Please send payment to @CourtneysCookies (Confirm name before sending!)'
    };

    // Function to update payment message display
    const updatePaymentMessage = () => {
        const selectedMethod = paymentMethodSelect.value;
        paymentMessageDisplay.textContent = paymentMessages[selectedMethod] || '';
    };

    // Function to calculate total price
    const calculateTotal = () => {
        let subtotal = 0;
        let cookieCount = 0;

        quantityInputs.forEach(input => {
            const quantity = parseInt(input.value, 10);
            cookieCount += quantity;
        });

        const price = cookiePrices['chocolate_chip']; // Assume all have same price structure
        const pairs = Math.floor(cookieCount / 2);
        const singles = cookieCount % 2;
        subtotal = (pairs * price.pair) + (singles * price.single);

        let currentOverallTotal = subtotal;
        const selectedDeliveryMethod = document.querySelector('input[name="delivery_method"]:checked').value;

        if (selectedDeliveryMethod === 'delivery') {
            currentOverallTotal += currentDeliveryFee;
            deliveryFeeDisplay.style.display = 'inline';
        } else {
            deliveryFeeDisplay.style.display = 'none';
        }

        orderTotalSpan.textContent = `$${currentOverallTotal.toFixed(2)}`;
        document.getElementById('total_amount').value = currentOverallTotal.toFixed(2);
        return currentOverallTotal;
    };

    // Function to update quantity
    const updateQuantity = (product, change) => {
        const input = document.querySelector(`.quantity-input[data-product="${product}"]`);
        let currentQuantity = parseInt(input.value, 10);
        currentQuantity += change;
        if (currentQuantity < 0) {
            currentQuantity = 0;
        }
        input.value = currentQuantity;
        // Update hidden field
        document.getElementById(product + '_quantity').value = currentQuantity;
        calculateTotal();
    };

    // Event listeners
    increaseButtons.forEach(button => {
        button.addEventListener('click', () => updateQuantity(button.dataset.product, 1));
    });

    decreaseButtons.forEach(button => {
        button.addEventListener('click', () => updateQuantity(button.dataset.product, -1));
    });

    quantityInputs.forEach(input => {
        input.addEventListener('change', () => { // Catch manual changes (though we set it to readonly now)
            if (parseInt(input.value) < 0 || isNaN(parseInt(input.value))) input.value = 0;
            document.getElementById(input.dataset.product + '_quantity').value = input.value;
            calculateTotal();
        });
    });

    deliveryMethodRadios.forEach(radio => {
        radio.addEventListener('change', calculateTotal);
    });

    paymentMethodSelect.addEventListener('change', updatePaymentMessage);


    // Handle form submission
    orderForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        alertMessage.classList.remove('show', 'success', 'error', 'info');
        alertMessage.textContent = '';

        let cookieCount = 0;
        quantityInputs.forEach(input => cookieCount += parseInt(input.value, 10));

        if (cookieCount === 0) {
            alertMessage.textContent = 'Please add at least one cookie!';
            alertMessage.classList.add('show', 'error');
            return;
        }

        calculateTotal(); // Ensure hidden fields are set

        alertMessage.textContent = 'Placing your order... please wait.';
        alertMessage.classList.add('show', 'info');

        const formData = new FormData(orderForm);

        try {
            const response = await fetch('process_orders.php', { // Target process_orders.php
                method: 'POST',
                body: formData
            });

            // Try to parse JSON, regardless of response.ok for more info
            let result;
            try {
                 result = await response.json();
            } catch (jsonError) {
                // If JSON parsing fails, the server likely sent HTML (PHP error)
                console.error('JSON Parsing Error:', jsonError);
                throw new Error('Server sent an invalid response (check PHP logs).');
            }


            if (response.ok && result.success) {
                alertMessage.classList.remove('info', 'error');
                alertMessage.classList.add('success');
                alertMessage.innerHTML = `Order placed successfully! (ID: ${result.orderId}).<br>We've sent a confirmation email.`;
                orderForm.reset();
                quantityInputs.forEach(input => {
                    input.value = 0;
                    document.getElementById(input.dataset.product + '_quantity').value = 0;
                });
                calculateTotal();
                updatePaymentMessage();
            } else {
                throw new Error(result.message || `Server responded with status ${response.status}`);
            }

        } catch (error) {
            console.error('Submission Error:', error);
            alertMessage.classList.remove('info', 'success');
            alertMessage.classList.add('error');
            alertMessage.textContent = `Error: ${error.message}. Please try again or contact us.`;
        }
    });

    // Initial setup on load
    calculateTotal();
    updatePaymentMessage();
});