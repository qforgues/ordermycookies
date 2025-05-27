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

    let currentDeliveryFee = 2.00;
    deliveryFeeDisplay.textContent = ` (+$${currentDeliveryFee.toFixed(2)})`;

    const paymentMessages = {
        'Cash': 'Please have exact cash ready for pickup/delivery.',
        'CreditCard': 'You will be sent a secure payment link via email/text shortly.',
        'Venmo': 'Please send payment to @CourtneysCookies (Confirm name before sending!)'
    };

    const updatePaymentMessage = () => {
        const selectedMethod = paymentMethodSelect.value;
        paymentMessageDisplay.textContent = paymentMessages[selectedMethod] || '';
    };

    const calculateTotal = () => {
        let subtotal = 0;
        let cookieCount = 0;

        quantityInputs.forEach(input => {
            const quantity = parseInt(input.value, 10);
            cookieCount += quantity;
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

    // Handle Form Submission - UPDATED FOR DEBUGGING
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

            // --- DEBUGGING CHANGE: Get response as text first ---
            const responseText = await response.text();
            console.log("Server Response Text:", responseText); // already present
            document.body.innerHTML += `<pre style="color:red;">${responseText}</pre>`; // temporarily add to page

            // --- END DEBUGGING CHANGE ---

            let result;
            try {
                 result = JSON.parse(responseText); // Try to parse the text
            } catch (jsonError) {
                console.error('JSON Parsing Error:', jsonError);
                // Show the raw text if JSON parsing fails
                throw new Error(`Server sent an invalid response. See console (F12) for "Server Response Text". It likely contains a PHP error.`);
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

    calculateTotal();
    updatePaymentMessage();
});