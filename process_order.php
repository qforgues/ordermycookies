<?php
// process_orders.php - Handles NEW Customer Order Submissions

session_start(); // Start session if needed (e.g., for captcha or user tracking)

require_once 'db_connect.php';
require_once 'send_email.php';

// --- Check if it's a POST request (Order Submission) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['full_name'], $_POST['email'], $_POST['phone'])) {

    // --- 1. Sanitize and Validate Input ---
    // (This is basic - consider more robust validation)
    $fullName = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);

    $choco_qty = (int)($_POST['chocolate_chip_quantity'] ?? 0);
    $pb_qty = (int)($_POST['peanut_butter_quantity'] ?? 0);
    $oreomg_qty = (int)($_POST['oreomg_quantity'] ?? 0);
    $snick_qty = (int)($_POST['snickerdoodle_quantity'] ?? 0);
    $maple_qty = (int)($_POST['maplebacon_quantity'] ?? 0);

    // Ensure quantities are not negative
    $choco_qty = max(0, $choco_qty);
    $pb_qty = max(0, $pb_qty);
    $oreomg_qty = max(0, $oreomg_qty);
    $snick_qty = max(0, $snick_qty);
    $maple_qty = max(0, $maple_qty);

    $totalAmount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_FLOAT); // Ensure this is sent from your form
    $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    $deliveryMethod = filter_input(INPUT_POST, 'delivery_method', FILTER_SANITIZE_STRING);
    $pickupTime = filter_input(INPUT_POST, 'pickup_time', FILTER_SANITIZE_STRING);

    // Basic check - need at least an email, name, and one cookie
    if (!$email || !$fullName || ($choco_qty + $pb_qty + $oreomg_qty + $snick_qty + $maple_qty) <= 0) {
        // Redirect back with an error message (adjust your form page name)
        header('Location: order_form.html?error=invalid_input');
        exit;
    }

    // --- 2. Insert into Database ---
    $sql = "INSERT INTO cookie_orders (
                full_name, email, phone,
                chocolate_chip_quantity, peanut_butter_quantity, oreomg_quantity,
                snickerdoodle_quantity, maplebacon_quantity,
                total_amount, payment_method, delivery_method, pickup_time,
                status, order_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', NOW())"; // Set status to 'New'

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $fullName, $email, $phone,
            $choco_qty, $pb_qty, $oreomg_qty,
            $snick_qty, $maple_qty,
            $totalAmount, $paymentMethod, $deliveryMethod, $pickupTime
        ]);
        $orderId = $pdo->lastInsertId(); // Get the ID of the new order

    } catch (PDOException $e) {
        error_log("Order submission failed: " . $e->getMessage());
        header('Location: order_form.html?error=db_error');
        exit;
    }

    // --- 3. Send "New Order" (Thank You) Email ---
    if ($email && $orderId) {
        $subject = "We've Received Your Courtneys Cookies Order! 🍪";
        $body = '<html><body style="font-family: Quicksand, sans-serif; color: #3E2C1C; background-color: #FFF7ED; padding: 20px;">
                 <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;padding:20px;box-shadow:0 0 10px rgba(0,0,0,0.05);">
                     <img src="images/logo.png" style="max-width:150px;margin:auto;display:block;" alt="Courtneys Cookies"/>
                     <h2 style="color:#6B4423;text-align:center;">Thank you for your order!</h2>
                     <p style="text-align:center;">We\'ve received your delicious order (ID: ' . htmlspecialchars($orderId) . ') and will start baking soon! We\'ll send another email when it\'s ready. We hope you LOVE them!</p>
                     <p style="text-align:center;">Don\'t forget to <a href="https://facebook.com/ordermycookies" target="_blank">like and share us on Facebook</a> and tell friends and family about <strong>OrderMyCookies.com</strong>.</p>
                     <p style="text-align:center;">We\'re rolling out fun discounts and cookie surprises soon, so stay tuned!</p>
                     <p style="text-align:center;">Sweetest Regards,<br>- Courtney</p>
                 </div></body></html>';

        sendCustomerEmail($email, $subject, $body);
    }

    // --- 4. Redirect to a Thank You Page ---
    header('Location: thank_you.html'); // Create a thank_you.html page!
    exit;

} else {
    // If not a valid POST submission, redirect to the order form
    header('Location: order_form.html'); // Adjust if your form has a different name
    exit;
}
?>