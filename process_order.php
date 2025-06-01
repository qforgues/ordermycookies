<?php
header('Content-Type: application/json');
require_once 'db_connect.php'; // Include your database connection

// Include the EasyMailClient class
// Assuming EasyMailClient.php is in the 'inc' folder in your project root
require_once 'inc/EasyMailClient.php';

// Instantiate the EasyMailClient
// This will use the default SMTP settings configured within EasyMailClient.php
// (i.e., mail.ordermycookies.com, courtney@ordermycookies.com, Cookies143!)
$mailClient = new EasyMailClient();

// =========================================================================
// ORIGINAL ORDER PROCESSING LOGIC (EXISTING)
// =========================================================================

// Determine Admin Recipient Email
$adminToEmail = getenv('DEPLOY_ENV');
if (!$adminToEmail) {
    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (str_contains($hostname, 'dev.') || str_contains($hostname, 'localhost')) {
        $adminToEmail = 'quentin.forgues@gmail.com'; // Dev/localhost admin recipient
    } else {
        $adminToEmail = 'courtney.forgues@gmail.com'; // Production admin recipient
    }
}
// Note: $fromEmail and $fromName are now handled by EasyMailClient's defaults

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
$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL); // Better sanitization for email
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
$totalAmount = htmlspecialchars($_POST['totalAmount'] ?? '$0.00'); // Keep as string for display
$selectedPaymentMethod = htmlspecialchars($_POST['selectedPaymentMethod']);
$paymentMessage = htmlspecialchars($_POST['paymentMessage']); // This seems to be a success message from client-side

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
    // It's good practice to also inform the client, but avoid sending raw SQL errors.
    // The final JSON response will indicate failure if $dbSuccess remains false.
    $dbSuccess = false;
}

// Prepare Email Content (common parts)
$orderDetailsHtml = "<p><strong>Order Details:</strong></p><ul>";
if ($chocochipQuantity > 0) $orderDetailsHtml .= "<li>Chocolate Chip: $chocochipQuantity</li>";
if ($oreomgQuantity > 0) $orderDetailsHtml .= "<li>Ore-OMG: $oreomgQuantity</li>";
if ($snickerdoodleQuantity > 0) $orderDetailsHtml .= "<li>Snickerdoodle: $snickerdoodleQuantity</li>";
if ($peanutbutterQuantity > 0) $orderDetailsHtml .= "<li>Peanut Butter: $peanutbutterQuantity</li>";
if ($maplebaconQuantity > 0) $orderDetailsHtml .= "<li>Maple Bacon: $maplebaconQuantity</li>";
$orderDetailsHtml .= "</ul>";
$orderDetailsHtml .= "<p><strong>Total: $totalAmount</strong></p>";

$mailSuccess = false;
$customerMailSuccess = false;

if ($dbSuccess && $orderId) { // Only attempt to send emails if DB insert was successful and we have an order ID

    // Admin Notification Email
    $adminSubject = "New Cookie Order from $fullName (#$orderId)";
    
    $adminMessageHtml = "<h1>New Cookie Order! (ID: $orderId)</h1>";
    $adminMessageHtml .= "<p><strong>Name:</strong> $fullName<br>";
    $adminMessageHtml .= "<strong>Email:</strong> $email<br>";
    $adminMessageHtml .= "<strong>Phone:</strong> $phone</p>";
    $adminMessageHtml .= "<p><strong>Address:</strong><br>$street - $city, $zip</p>";
    $adminMessageHtml .= "<p><strong>Delivery Method:</strong> " . ucfirst($deliveryMethod) . "<br>";
    $adminMessageHtml .= "<strong>Preferred Time:</strong> " . ($pickupTime ?: 'N/A') . "<br>";
    $adminMessageHtml .= "<strong>Delivery Fee:</strong> $" . number_format($actualDeliveryFee, 2) . "</p>";
    $adminMessageHtml .= "<p><strong>Payment Method:</strong> " . $selectedPaymentMethod . "</p>";
    $adminMessageHtml .= $orderDetailsHtml;

    // Plain text version for admin (optional, but good practice)
    $adminMessagePlainText = "New Cookie Order! (ID: $orderId)\n\n";
    $adminMessagePlainText .= "Name: $fullName\nEmail: $email\nPhone: $phone\n\n";
    $adminMessagePlainText .= "Address:\n$street - $city, $zip\n\n";
    $adminMessagePlainText .= "Delivery Method: " . ucfirst($deliveryMethod) . "\n";
    $adminMessagePlainText .= "Preferred Time: " . ($pickupTime ?: 'N/A') . "\n";
    $adminMessagePlainText .= "Delivery Fee: $" . number_format($actualDeliveryFee, 2) . "\n\n";
    $adminMessagePlainText .= "Payment Method: " . $selectedPaymentMethod . "\n\n";
    $adminMessagePlainText .= "Order Details:\n";
    if ($chocochipQuantity > 0) $adminMessagePlainText .= "Chocolate Chip: $chocochipQuantity\n";
    if ($oreomgQuantity > 0) $adminMessagePlainText .= "Ore-OMG: $oreomgQuantity\n";
    if ($snickerdoodleQuantity > 0) $adminMessagePlainText .= "Snickerdoodle: $snickerdoodleQuantity\n";
    if ($peanutbutterQuantity > 0) $adminMessagePlainText .= "Peanut Butter: $peanutbutterQuantity\n";
    if ($maplebaconQuantity > 0) $adminMessagePlainText .= "Maple Bacon: $maplebaconQuantity\n";
    $adminMessagePlainText .= "\nTotal: $totalAmount\n";

    // The EasyMailClient uses its configured "From" address by default.
    // The $adminToEmail is the recipient.
    // The $fullName can be used as the recipient name for the admin email if desired, or leave blank.
    $mailSuccess = $mailClient->sendEmail($adminToEmail, 'Admin', $adminSubject, $adminMessageHtml, $adminMessagePlainText);
    if (!$mailSuccess) {
        error_log("Failed to send admin notification email for order #$orderId. PHPMailer Error: " . $mailClient->getMailerInstance()->ErrorInfo);
    }


    // Customer Confirmation Email
    $customerSubject = "Thanks for your Cookie Order, $fullName! (#$orderId)";
    
    $customerMessageHtml = "<h1>Thanks for your Cookie Order, $fullName!</h1>";
    $customerMessageHtml .= "<p>Hi $fullName,</p>";
    $customerMessageHtml .= "<p>Thanks for placing your order with Courtney's Cookies! Your order ID is #$orderId.</p>";
    $customerMessageHtml .= "<p>Here's what we have for you:</p>";
    $customerMessageHtml .= $orderDetailsHtml; // Re-use the order details HTML
    $customerMessageHtml .= "<p><strong>Payment Method:</strong> $selectedPaymentMethod</p>";

    if ($deliveryMethod === 'delivery') {
        $customerMessageHtml .= "<p><strong>We'll deliver to:</strong><br>$street<br>$city, $state $zip<br>";
        $customerMessageHtml .= "<strong>Delivery Fee:</strong> $" . number_format($actualDeliveryFee, 2) . "</p>";
    } else {
        $customerMessageHtml .= "<p><strong>Pickup Location:</strong> Caribbean Smoothie (next to El Yate Bar, Isabel Segunda)</p>";
    }

    $customerMessageHtml .= "<p><strong>Preferred Time:</strong> " . ($pickupTime ?: 'N/A') . "</p>";
    $customerMessageHtml .= "<p>Questions? Just reply to this email.</p>";
    $customerMessageHtml .= "<p>- Courtney's Cookies 🍪</p>";

    // Plain text version for customer
    $customerMessagePlainText = "Hi $fullName,\n\n";
    $customerMessagePlainText .= "Thanks for placing your order with Courtney's Cookies! Your order ID is #$orderId.\n\n";
    $customerMessagePlainText .= "Here's what we have for you:\n";
    if ($chocochipQuantity > 0) $customerMessagePlainText .= "• Chocolate Chip: $chocochipQuantity\n";
    if ($oreomgQuantity > 0) $customerMessagePlainText .= "• Ore-OMG: $oreomgQuantity\n";
    if ($snickerdoodleQuantity > 0) $customerMessagePlainText .= "• Snickerdoodle: $snickerdoodleQuantity\n";
    if ($peanutbutterQuantity > 0) $customerMessagePlainText .= "• Peanut Butter: $peanutbutterQuantity\n";
    if ($maplebaconQuantity > 0) $customerMessagePlainText .= "• Maple Bacon: $maplebaconQuantity\n";
    $customerMessagePlainText .= "\nTotal: $totalAmount\n";
    $customerMessagePlainText .= "Payment Method: $selectedPaymentMethod\n\n";

    if ($deliveryMethod === 'delivery') {
        $customerMessagePlainText .= "We'll deliver to:\n$street\n$city, $state $zip\nDelivery Fee: $" . number_format($actualDeliveryFee, 2) . "\n";
    } else {
        $customerMessagePlainText .= "Pickup Location: Caribbean Smoothie (next to El Yate Bar, Isabel Segunda)\n";
    }
    $customerMessagePlainText .= "\nPreferred Time: " . ($pickupTime ?: 'N/A') . "\n";
    $customerMessagePlainText .= "\nQuestions? Just reply to this email.\n\n";
    $customerMessagePlainText .= "- Courtney's Cookies 🍪";

    // Send to the customer's email address ($email)
    $customerMailSuccess = $mailClient->sendEmail($email, $fullName, $customerSubject, $customerMessageHtml, $customerMessagePlainText);
    if (!$customerMailSuccess) {
        error_log("Failed to send confirmation email to $email for order #$orderId. PHPMailer Error: " . $mailClient->getMailerInstance()->ErrorInfo);
    }

} // End if ($dbSuccess && $orderId)

// Final JSON Response
if ($mailSuccess && $customerMailSuccess && $dbSuccess) {
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully! We\'ve sent you a confirmation email.', // Updated success message
        'totalAmount' => $totalAmount,
        'selectedPaymentMethod' => $selectedPaymentMethod,
        'paymentMessage' => $paymentMessage, // This seems to be a client-side message, pass it through
        'orderId' => $orderId
    ]);
} else {
    $errorMessages = [];
    if (!$dbSuccess) $errorMessages[] = "There was an issue saving your order to our system.";
    if ($dbSuccess && !$mailSuccess) $errorMessages[] = "Order saved, but failed to send admin notification email.";
    if ($dbSuccess && !$customerMailSuccess) $errorMessages[] = "Order saved, but failed to send you a confirmation email. Please contact us if you don't receive it shortly.";
    
    // If DB failed, that's the primary error.
    $clientErrorMessage = $dbSuccess ? implode(" ", $errorMessages) : "Failed to place order. Please try again or contact us directly.";

    echo json_encode(['success' => false, 'message' => $clientErrorMessage, 'orderId' => $orderId]);
}
?>
