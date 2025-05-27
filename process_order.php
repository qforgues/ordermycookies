<?php
// process_orders.php - Handles NEW Customer Order Submissions & Returns JSON
ob_start(); // <-- Add this line FIRST

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// --- Set Header for JSON Response ---
header('Content-Type: application/json');

session_start();
require_once 'db_connect.php';
require_once 'send_email.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['full_name'], $_POST['email'], $_POST['phone'])) {

    // --- 1. Sanitize and Validate Input ---
    $fullName = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);

    $choco_qty = (int)($_POST['chocolate_chip_quantity'] ?? 0);
    $pb_qty = (int)($_POST['peanut_butter_quantity'] ?? 0);
    $oreomg_qty = (int)($_POST['oreomg_quantity'] ?? 0);
    $snick_qty = (int)($_POST['snickerdoodle_quantity'] ?? 0);
    $maple_qty = (int)($_POST['maplebacon_quantity'] ?? 0);

    $totalAmount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_FLOAT);
    $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    $deliveryMethod = filter_input(INPUT_POST, 'delivery_method', FILTER_SANITIZE_STRING);
    $pickupTime = filter_input(INPUT_POST, 'pickup_time', FILTER_SANITIZE_STRING);

    // Basic check
    if (!$email || !$fullName || ($choco_qty + $pb_qty + $oreomg_qty + $snick_qty + $maple_qty) <= 0) {
        $response['message'] = 'Invalid input. Please check your name, email, and add at least one cookie.';
        echo json_encode($response);
        exit;
    }

    // --- 2. Insert into Database ---
    $sql = "INSERT INTO cookie_orders (
                full_name, email, phone,
                chocolate_chip_quantity, peanut_butter_quantity, oreomg_quantity,
                snickerdoodle_quantity, maplebacon_quantity,
                total_amount, payment_method, delivery_method, pickup_time,
                status, order_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', NOW())";

    try {
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $fullName, $email, $phone,
            $choco_qty, $pb_qty, $oreomg_qty,
            $snick_qty, $maple_qty,
            $totalAmount, $paymentMethod, $deliveryMethod, $pickupTime
        ]);
        $orderId = $pdo->lastInsertId();

        if ($success && $orderId) {
            // --- 3. Send "New Order" (Thank You) Email ---
            $subject = "We've Received Your Courtneys Cookies Order! 🍪";
            // Use local images/logo.png
            $body = '<html><body style="font-family: Quicksand, sans-serif; color: #3E2C1C; background-color: #FFF7ED; padding: 20px;">
                     <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;padding:20px;box-shadow:0 0 10px rgba(0,0,0,0.05);">
                         <img src="images/logo.png" style="max-width:150px;margin:auto;display:block;" alt="Courtneys Cookies"/>
                         <h2 style="color:#6B4423;text-align:center;">Thank you for your order!</h2>
                         <p style="text-align:center;">We\'ve received your delicious order (ID: ' . htmlspecialchars($orderId) . ') and will start baking soon! We\'ll send another email when it\'s ready. We hope you LOVE them!</p>
                         <p style="text-align:center;">Don\'t forget to <a href="https://facebook.com/ordermycookies" target="_blank">like and share us on Facebook</a> and tell friends and family about <strong>OrderMyCookies.com</strong>.</p>
                         <p style="text-align:center;">We\'re rolling out fun discounts and cookie surprises soon, so stay tuned!</p>
                         <p style="text-align:center;">Sweetest Regards,<br>- Courtney</p>
                     </div></body></html>';

            $emailSent = sendCustomerEmail($email, $subject, $body);

            $response['success'] = true;
            $response['message'] = 'Order placed successfully!';
            $response['orderId'] = $orderId;
            if (!$emailSent) {
                $response['message'] .= ' (Warning: Confirmation email could not be sent)';
            }
        } else {
             $response['message'] = 'Database error: Could not save order.';
        }

    } catch (PDOException $e) {
        error_log("Order submission failed: " . $e->getMessage());
        $response['message'] = 'Database error. Please try again later.';
    }

} else {
    $response['message'] = 'Invalid request method or missing data.';
}

// --- 4. Echo JSON Response ---
echo json_encode($response);
ob_end_clean(); // Prevents stray output before JSON

exit;
?>