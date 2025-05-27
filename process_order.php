<?php
header('Content-Type: application/json');
require_once 'db_connect.php'; // Include your database connection

// =========================================================================
// FUNCTION TO SEND THE 'READY' EMAIL (NEW)
// =========================================================================
/**
 * Sends an email to the customer when their order is ready.
 *
 * @param int $orderId The ID of the order.
 * @param PDO $pdo The PDO database connection object.
 * @param string $fromEmail The sender's email address.
 * @param string $fromName The sender's name.
 * @return bool True if the email was sent successfully, false otherwise.
 */
function sendReadyEmail($orderId, $pdo, $fromEmail, $fromName) {
    try {
        // Fetch order details (especially customer email and name)
        $stmt = $pdo->prepare("SELECT full_name, email, delivery_method, pickup_time, street, city, state, zip FROM cookie_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            error_log("sendReadyEmail: Order ID $orderId not found.");
            return false;
        }

        $customerEmail = $order['email'];
        $fullName = $order['full_name'];
        $deliveryMethod = $order['delivery_method'];
        $pickupTime = $order['pickup_time'];
        $street = $order['street'];
        $city = $order['city'];
        $state = $order['state'];
        $zip = $order['zip'];

        $subject = "Your Courtney's Cookies Order is Ready!";
        $headers = "From: $fromName <$fromEmail>\r\n";
        $headers .= "Reply-To: $fromEmail\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8";

        $message = "Hi $fullName,\n\n";
        $message .= "Great news! Your Courtney's Cookies order (ID: $orderId) is now ready for " . ($deliveryMethod == 'pickup' ? 'pickup' : 'delivery') . ".\n\n";

        if ($deliveryMethod == 'pickup') {
            $message .= "You can pick up your order" . ($pickupTime ? " at your selected time: $pickupTime" : "") . ".\n";
            $message .= "Our pickup location is: [**Your Pickup Address Here - IMPORTANT!**].\n\n";
        } else {
            $message .= "Your order will be delivered soon to:\n$street\n$city, $state $zip\n\n";
        }

        $message .= "Thank you for your order!\n\n";
        $message .= "Best,\n";
        $message .= "Courtney's Cookies";

        return mail($customerEmail, $subject, $message, $headers);

    } catch (PDOException $e) {
        error_log("Database Error in sendReadyEmail: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("General Error in sendReadyEmail: " . $e->getMessage());
        return false;
    }
}

// =========================================================================
// CHECK IF THIS IS A CALL TO SEND 'READY' EMAIL (NEW)
// =========================================================================
// We assume you might call this script like: process_order.php?action=send_ready&order_id=123
if (isset($_GET['action']) && $_GET['action'] === 'send_ready' && isset($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];

    // Define $fromEmail and $fromName here as well for this context
    $fromEmail = "courtney@ordermycookies.com";
    $fromName = "Courtney's Cookies";

    if (sendReadyEmail($orderId, $pdo, $fromEmail, $fromName)) {
        echo json_encode(['success' => true, 'message' => "Ready email sent for order $orderId."]);
    } else {
        echo json_encode(['success' => false, 'message' => "Failed to send ready email for order $orderId."]);
    }
    exit; // Stop execution after sending the email
}


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
$required = ['fullName', 'email', 'phone', 'street', 'city', 'state', 'zip', 'selectedPaymentMethod'];
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

$message .= "Name: $fullName\nEmail: $email\nPhone: $phone\n\n";
$message .= "Address:\n$street\n$city, $state $zip\n\n";
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