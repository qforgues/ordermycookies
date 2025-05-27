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

    // --- Defaults / Placeholders ---
    let currentDeliveryFee = 2.00; // Default until loaded
    let paymentMessages = {
        'Cash': 'Loading payment info...',
        'CreditCard': 'Loading payment info...',
        'Venmo': 'Loading payment info...'
    };

    // --- Function to fetch settings from the server ---
    const fetchSettings = async () => {
        try {
            const response = await fetch('get_settings.php'); // Call your settings script
            if (!response.ok) {
                throw new Error(`Server returned status ${response.status}`);
            }
            const data = await response.json();

            if (data.success && data.settings) {
                console.log("Settings loaded:", data.settings);
                // Use loaded settings or keep default if a specific setting is missing
                currentDeliveryFee = parseFloat(data.settings.delivery_fee_amount) || 2.00;
                paymentMessages = {
                    'Cash': data.settings.cash_payment_message || 'Please have exact cash ready.',
                    'CreditCard': data.settings.creditcard_payment_message || 'Payment link will be sent.',
                    'Venmo': data.settings.venmo_payment_message || 'Venmo @CourtneysCookies.'
                };
            } else {
                 throw new Error(data.message || "Failed to load settings (invalid format).");
            }
        } catch (error) {
            console.error('Error fetching settings:', error);
            // Use hardcoded defaults on any fetch error
            currentDeliveryFee = 2.00;
            paymentMessages = {
                'Cash': 'Please have exact cash ready (default).',
                'CreditCard': 'Payment link will be sent (default).',
                'Venmo': 'Venmo @CourtneysCookies (default).'
            };
            // Optionally show a non-blocking message
            // alertMessage.textContent = 'Could not load custom payment messages.';
            // alertMessage.classList.add('show', 'error');
        } finally {
             // Always update display and total after trying to fetch
             deliveryFeeDisplay.textContent = ` (+$${currentDeliveryFee.toFixed(2)})`;
             updatePaymentMessage();
             calculateTotal();
        }
    };


    const updatePaymentMessage = () => {
        const selectedMethod = paymentMethodSelect.value;
        paymentMessageDisplay.textContent = paymentMessages[selectedMethod] || '';
    };

    const calculateTotal = () => {
        let subtotal = 0;
        let cookieCount = 0;

        quantityInputs.forEach(input => {
            cookieCount += parseInt(input.value, 10);
        });

        const price = cookiePrices['chocolate_chip'];
        const pairs = Math.floor(cookieCount / 2);
        const singles = cookieCount % 2;
        subtotal = (pairs * price.pair) + (singles * price.single);

        let currentOverallTotal = subtotal;
        const selectedDeliveryRadio = document.querySelector('input[name="delivery_method"]:checked');
        const selectedDeliveryMethod = selectedDeliveryRadio ? selectedDeliveryRadio.value : 'pickup';

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

    const updateQuantity = (product, change) => {
        const input = document.querySelector(`.quantity-input[data-product="${product}"]`);
        let currentQuantity = parseInt(input.value, 10);
        currentQuantity += change;
        if (currentQuantity < 0) currentQuantity = 0;
        input.value = currentQuantity;
        document.getElementById(product + '_quantity').value = currentQuantity;
        calculateTotal();
    };

    increaseButtons.forEach(button => button.addEventListener('click', () => updateQuantity(button.dataset.product, 1)));
    decreaseButtons.forEach(button => button.addEventListener('click', () => updateQuantity(button.dataset.product, -1)));
    deliveryMethodRadios.forEach(radio => radio.addEventListener('change', calculateTotal));
    paymentMethodSelect.addEventListener('change', updatePaymentMessage);

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

        calculateTotal();

        alertMessage.textContent = 'Placing your order... please wait.';
        alertMessage.classList.add('show', 'info');

        const formData = new FormData(orderForm);

        try {
            const response = await fetch('process_orders.php', {
                method: 'POST',
                body: formData
            });

            const responseText = await response.text();
            console.log("Server Response Text:", responseText);

            let result;
            try {
                 result = JSON.parse(responseText);
            } catch (jsonError) {
                console.error('JSON Parsing Error:', jsonError);
                throw new Error('Server sent an invalid response. See console (F12) for "Server Response Text". It likely contains a PHP error.');
            }

            if (response.ok && result.success) {
                alertMessage.classList.remove('info', 'error');
                alertMessage.classList.add('success');
                // Use the message and data coming back from PHP
                alertMessage.innerHTML = `Order placed successfully! (ID: ${result.orderId}).<br>Total: ${result.totalAmount}.<br><strong>${result.paymentMessage}</strong>`;
                orderForm.reset();
                quantityInputs.forEach(input => {
                    input.value = 0;
                    document.getElementById(input.dataset.product + '_quantity').value = 0;
                });
                calculateTotal();
                updatePaymentMessage(); // Reset payment message display
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

    // --- Initial load: Fetch settings, which will then call update/calculate ---
    fetchSettings();
});