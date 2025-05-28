<?php
header('Content-Type: application/json');
require_once 'db_connect.php'; // Include your database connection

// =========================================================================
// ORIGINAL ORDER PROCESSING LOGIC (EXISTING)
// =========================================================================

// Email Setup (Admin Notification)
$to = getenv('DEPLOY_ENV');
if (!$to) {
    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (str_contains($hostname, 'dev.') || str_contains($hostname, 'localhost')) {
        $to = 'quentin.forgues@gmail.com';
    } else {
        $to = 'courtney.forgues@gmail.com';
    }
}
$fromEmail = "courtney@ordermycookies.com";
$fromName = "Courtney's Cookies";

// Input Validation
$required = ['fullName', 'email', 'phone', 'state', 'zip', 'selectedPaymentMethod'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Sanitize & Process Input Data
$fullName = htmlspecialchars($_POST['fullName']);
$email = htmlspecialchars($_POST['email']);
$phone = htmlspecialchars($_POST['phone']);
$street = htmlspecialchars($_POST['street']);
$city = htmlspecialchars($_POST['city']);
$state = htmlspecialchars($_POST['state']);
$zip = htmlspecialchars($_POST['zip']);
$deliveryMethod = htmlspecialchars($_POST['deliveryMethod'] ?? 'pickup');
$pickupTime = htmlspecialchars($_POST['pickupTime'] ?? '');
$actualDeliveryFee = (float)($_POST['actualDeliveryFee'] ?? 0.00);
$chocochipQuantity = (int)($_POST['chocochipQuantity'] ?? 0);
$oreomgQuantity = (int)($_POST['oreomgQuantity'] ?? 0);
$snickerdoodleQuantity = (int)($_POST['snickerdoodleQuantity'] ?? 0);
$peanutbutterQuantity = (int)($_POST['peanutbutterQuantity'] ?? 0);
$maplebaconQuantity = (int)($_POST['maplebaconQuantity'] ?? 0);
$totalAmount = htmlspecialchars($_POST['totalAmount'] ?? '$0.00');
$selectedPaymentMethod = htmlspecialchars($_POST['selectedPaymentMethod']);
$paymentMessage = htmlspecialchars($_POST['paymentMessage']);

$dbSuccess = false;
$orderId = null; // Variable to hold the new order ID

// Database Insertion
try {
    $stmt = $pdo->prepare("INSERT INTO cookie_orders (
        full_name, email, phone, street, city, state, zip,
        chocolate_chip_quantity, peanut_butter_quantity, oreomg_quantity, snickerdoodle_quantity,maplebacon_quantity,
        total_amount, delivery_method, pickup_time, delivery_fee, payment_method
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $fullName, $email, $phone, $street, $city, $state, $zip,
        $chocochipQuantity, $peanutbutterQuantity, $oreomgQuantity, $snickerdoodleQuantity,$maplebaconQuantity,
        $totalAmount, $deliveryMethod, $pickupTime, $actualDeliveryFee, $selectedPaymentMethod
    ]);

    $orderId = $pdo->lastInsertId(); // Get the ID of the order just inserted
    $dbSuccess = true;

} catch (PDOException $e) {
    error_log("Database Error in process_order.php: " . $e->getMessage());
    $dbSuccess = false;
}

// Admin Notification Email
$subject = "New Cookie Order from $fullName (#$orderId)"; // Added Order ID
$headers = "From: $fromName <$fromEmail>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8";

$message = "New Cookie Order! (ID: $orderId)\n\n";
$message .= "Name: $fullName\nEmail: $email\nPhone: $phone\n\n";
$message .= "Address:\n$street - $city, $zip\n\n";
$message .= "Delivery Method: " . ucfirst($deliveryMethod) . "\n";
$message .= "Preferred Time: " . ($pickupTime ?: 'N/A') . "\n";
$message .= "Delivery Fee: $" . number_format($actualDeliveryFee, 2) . "\n\n";
$message .= "Payment Method: " . $selectedPaymentMethod . "\n\n";

$message .= "Order Details:\n";
if ($chocochipQuantity > 0) $message .= "Chocolate Chip: $chocochipQuantity\n";
if ($oreomgQuantity > 0) $message .= "Ore-OMG: $oreomgQuantity\n";
if ($snickerdoodleQuantity > 0) $message .= "Snickerdoodle: $snickerdoodleQuantity\n";
if ($peanutbutterQuantity > 0) $message .= "Peanut Butter: $peanutbutterQuantity\n";
if ($maplebaconQuantity > 0) $message .= "Maple Bacon: $maplebaconQuantity\n";
$message .= "\nTotal: $totalAmount\n";

$mailSuccess = mail($to, $subject, $message, $headers);

// --- Customer Confirmation Email (HTML Version) ---
$host = $_SERVER['HTTP_HOST'] ?? 'dev.ordermycookies.com';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$logoUrl = $protocol . $host . "/images/logo.png"; // Use HTTPS if available
$subjectCustomer = "We've Received Your Courtneys Cookies Order! 🍪";
$bodyCustomer = '<html><body style="font-family: Quicksand, sans-serif; color: #3E2C1C; background-color: #FFF7ED; padding: 20px;"><div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;padding:20px;box-shadow:0 0 10px rgba(0,0,0,0.05);"><img src="' . $logoUrl . '" style="max-width:150px;margin:auto;display:block;" alt="Courtneys Cookies"/><h2 style="color:#6B4423;text-align:center;">Thank you for your order!</h2><p style="text-align:center;">We\'ve received your delicious order (ID: ' . htmlspecialchars($orderId) . ') and will start baking soon! We\'ll send another email when it\'s ready. We hope you LOVE them!</p><p style="text-align:center;">Don\'t forget to <a href="https://facebook.com/ordermycookies" target="_blank">like and share us on Facebook</a> and tell friends and family about <strong>OrderMyCookies.com</strong>.</p><p style="text-align:center;">We\'re rolling out fun discounts and cookie surprises soon, so stay tuned!</p><p style="text-align:center;">Sweetest Regards,<br>- Courtney</p></div></body></html>';

// Use correct headers for HTML email
$customerHeaders = "MIME-Version: 1.0\r\n";
$customerHeaders .= "Content-type: text/html; charset=UTF-8\r\n";
$customerHeaders .= "From: $fromName <$fromEmail>\r\n";
$customerHeaders .= "Reply-To: $fromEmail\r\n";

$customerMailSuccess = mail($email, $subjectCustomer, $bodyCustomer, $customerHeaders);

// Update mailSuccess to require both emails to succeed if sending both
$mailSuccess = $mailSuccess && $customerMailSuccess;

// For testing, you can echo the HTML:
// echo $customerMessage;

// Final JSON Response
if ($mailSuccess && $dbSuccess) {
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully!',
        'totalAmount' => $totalAmount,
        'selectedPaymentMethod' => $selectedPaymentMethod,
        'paymentMessage' => $paymentMessage,
        'orderId' => $orderId // Optionally return the order ID
    ]);
} else {
    $errorMessage = "Failed to place order. ";
    if (!$mailSuccess) $errorMessage .= "Email sending failed. ";
    if (!$dbSuccess) $errorMessage .= "Database saving failed.";
    echo json_encode(['success' => false, 'message' => $errorMessage]);
}
?>