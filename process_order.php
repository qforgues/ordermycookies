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

// Customer Confirmation Email
$customerSubject = "Thanks for your Cookie Order, $fullName!";
$customerHeaders = "From: $fromName <$fromEmail>\r\n";
$customerHeaders .= "Reply-To: $fromEmail\r\n";
$customerHeaders .= "Content-Type: text/plain; charset=UTF-8";

$customerMessage = "Hi $fullName,\n\n";
$customerMessage .= "Thanks for placing your order with Courtney's Cookies!\n\n";
$customerMessage .= "Here's what we have for you:\n";
if ($chocochipQuantity > 0) $customerMessage .= "• Chocolate Chip: $chocochipQuantity\n";
if ($oreomgQuantity > 0) $customerMessage .= "• Ore-OMG: $oreomgQuantity\n";
if ($snickerdoodleQuantity > 0) $customerMessage .= "• Snickerdoodle: $snickerdoodleQuantity\n";
if ($peanutbutterQuantity > 0) $customerMessage .= "• Peanut Butter: $peanutbutterQuantity\n";
if ($maplebaconQuantity > 0) $customerMessage .= "• Maple Bacon: $maplebaconQuantity\n";
$customerMessage .= "\nTotal: $totalAmount\n";
$customerMessage .= "Payment Method: $selectedPaymentMethod\n\n";

if ($deliveryMethod === 'delivery') {
    $customerMessage .= "We'll deliver to:\n$street\n$city, $state $zip\nDelivery Fee: $" . number_format($actualDeliveryFee, 2) . "\n";
} else {
    $customerMessage .= "Pickup Location: Caribbean Smoothie (next to El Yate Bar, Isabel Segunda)\n";
}

$customerMessage .= "\nPreferred Time: " . ($pickupTime ?: 'N/A') . "\n";
$customerMessage .= "\nQuestions? Just reply to this email.\n\n";
$customerMessage .= "- Courtney's Cookies 🍪";

$customerMailSuccess = mail($email, $customerSubject, $customerMessage, $customerHeaders);
file_put_contents('email_debug.log', "Customer Email Sent: $customerMailSuccess\nTo: $email\nSubject: $customerSubject\nHeaders: $customerHeaders\nMessage:\n$customerMessage\n\n", FILE_APPEND);

// Final JSON Response
if ($mailSuccess && $customerMailSuccess && $dbSuccess) {
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