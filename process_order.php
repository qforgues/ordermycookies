<?php
header('Content-Type: application/json');
require_once 'db_connect.php'; // Include your database connection

// Include the EasyMailClient class
require_once 'inc/EasyMailClient.php';

// Instantiate the EasyMailClient
$mailClient = new EasyMailClient();

// =========================================================================
// ORIGINAL ORDER PROCESSING LOGIC (REMAINS THE SAME)
// =========================================================================

// Determine Admin Recipient Email
$adminToEmail = getenv('DEPLOY_ENV');
if (!$adminToEmail) {
    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (str_contains($hostname, 'dev.') || str_contains($hostname, 'localhost')) {
        $adminToEmail = 'quentin.forgues@gmail.com';
    } else {
        $adminToEmail = 'courtney.forgues@gmail.com';
    }
}

// Input Validation and Data Sanitization... (Code is unchanged here)
$required = ['fullName', 'email', 'phone', 'state', 'zip', 'selectedPaymentMethod'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}
$fullName = htmlspecialchars($_POST['fullName']);
$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
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
$orderId = null;

// Database Insertion... (Code is unchanged here)
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

    $orderId = $pdo->lastInsertId();
    $dbSuccess = true;

} catch (PDOException $e) {
    error_log("Database Error in process_order.php: " . $e->getMessage());
    $dbSuccess = false;
}

// Prepare Order Details HTML (used in both emails)
$orderDetailsHtml = "<div style='font-family: sans-serif; line-height: 1.6;'><h4>Your Order:</h4><ul style='list-style-type: none; padding-left: 0;'>";
if ($chocochipQuantity > 0) $orderDetailsHtml .= "<li style='margin-bottom: 5px;'>🍪 Chocolate Chip: <strong>$chocochipQuantity</strong></li>";
if ($oreomgQuantity > 0) $orderDetailsHtml .= "<li style='margin-bottom: 5px;'>🍪 Ore-OMG: <strong>$oreomgQuantity</strong></li>";
if ($snickerdoodleQuantity > 0) $orderDetailsHtml .= "<li style='margin-bottom: 5px;'>🍪 Snickerdoodle: <strong>$snickerdoodleQuantity</strong></li>";
if ($peanutbutterQuantity > 0) $orderDetailsHtml .= "<li style='margin-bottom: 5px;'>🍪 Peanut Butter: <strong>$peanutbutterQuantity</strong></li>";
if ($maplebaconQuantity > 0) $orderDetailsHtml .= "<li style='margin-bottom: 5px;'>🥓 Maple Bacon: <strong>$maplebaconQuantity</strong></li>";
$orderDetailsHtml .= "</ul>";
$orderDetailsHtml .= "<p style='font-size: 18px; font-weight: bold; color: #d43f9a; margin-top: 15px;'>Total: $totalAmount</p></div>";

$mailSuccess = false;
$customerMailSuccess = false;

if ($dbSuccess && $orderId) { // Only attempt to send emails if DB insert was successful

    // Admin Notification Email... (Code is unchanged here)
    $adminSubject = "New Cookie Order from $fullName (#$orderId)";
    $adminMessageHtml = "<h1>New Cookie Order! (ID: $orderId)</h1><p><strong>Name:</strong> $fullName<br><strong>Email:</strong> $email<br><strong>Phone:</strong> $phone</p>";
    $adminMessageHtml .= "<p><strong>Address:</strong><br>$street - $city, $zip</p>";
    $adminMessageHtml .= "<p><strong>Delivery Method:</strong> " . ucfirst($deliveryMethod) . "<br><strong>Preferred Time:</strong> " . ($pickupTime ?: 'N/A') . "<br><strong>Delivery Fee:</strong> $" . number_format($actualDeliveryFee, 2) . "</p>";
    $adminMessageHtml .= "<p><strong>Payment Method:</strong> " . $selectedPaymentMethod . "</p>";
    $adminMessageHtml .= str_replace("<h4>Your Order:</h4>", "<h4>Admin Order Details:</h4>", $orderDetailsHtml); // Reuse order details
    $mailSuccess = $mailClient->sendEmail($adminToEmail, 'Admin', $adminSubject, $adminMessageHtml, ''); // Plain text version for admin can be skipped for brevity
    if (!$mailSuccess) {
        error_log("Failed to send admin notification email for order #$orderId. PHPMailer Error: " . $mailClient->getMailerInstance()->ErrorInfo);
    }
    
    // =========================================================================
    // NEW CUSTOMER CONFIRMATION EMAIL
    // =========================================================================
    $mailClient = new EasyMailClient();
    $customerSubject = "Your Courtney's Cookies Order is Confirmed! (#$orderId)";
    
    // --- START OF HTML EMAIL TEMPLATE ---
    $customerMessageHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Courtney's Cookies Order Confirmation</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Comic Sans MS', 'Chalkboard SE', 'Marker Felt', sans-serif; background-color: #f9f6f2;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; border-collapse: collapse; background-color: #ffffff;">
        <tr>
            <td align="center" style="background-color: #fcefee; padding: 20px 0;">
                <img src="https://ordermycookies.com/images/logo.png" alt="Courtney's Cookies Logo" width="150" style="display: block;">
            </td>
        </tr>
        <tr>
            <td style="padding: 40px 30px; color: #5b3a29;">
                <h1 style="color: #d43f9a; font-size: 28px; margin: 0 0 20px 0;">We've Got Your Order!</h1>
                <p style="font-size: 18px; line-height: 1.6; margin: 0 0 25px 0;">Hi $fullName,</p>
                <p style="font-size: 16px; line-height: 1.6; margin: 0 0 25px 0;">
                    Thank you so much for your order! I'm so excited to bake up these fresh, delicious cookies just for you. Your support means the world to me! We're getting everything ready for you now.
                </p>
                
                <div style="border-top: 2px solid #fcefee; border-bottom: 2px solid #fcefee; padding: 20px 0;">
                    $orderDetailsHtml
                    <p style="font-family: sans-serif; margin-bottom: 10px;"><strong>Payment Method:</strong> $selectedPaymentMethod</p>
HTML;

    // Add Delivery/Pickup Info
    if ($deliveryMethod === 'delivery') {
        $customerMessageHtml .= "<p style='font-family: sans-serif; line-height: 1.6;'><strong>We'll deliver to:</strong><br>$street<br>$city, $state $zip<br><strong>Delivery Fee:</strong> $" . number_format($actualDeliveryFee, 2) . "</p>";
    } else {
        $customerMessageHtml .= "<p style='font-family: sans-serif; line-height: 1.6;'><strong>Pickup Location:</strong><br>Caribbean Smoothie (next to El Yate Bar, Isabel Segunda)</p>";
    }
    $customerMessageHtml .= "<p style='font-family: sans-serif; line-height: 1.6;'><strong>Requested Time:</strong> " . ($pickupTime ?: 'N/A') . "</p>";

    $customerMessageHtml .= <<<HTML
                </div>
                
                <p style="font-size: 16px; line-height: 1.6; margin: 30px 0 15px 0;">
                    While you wait, check out our latest creations and updates on our Facebook page! And if you love your cookies, telling your friends and family on Vieques is the sweetest compliment you can give!
                </p>
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td align="center">
                            <a href="https://www.facebook.com/ordermycookies" target="_blank" style="background-color: #d43f9a; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
                                Visit our Facebook Page
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="background-color: #fcefee; padding: 20px 30px; color: #5b3a29;">
                <p style="margin: 0; font-size: 14px;">Courtney's Cookies &bull; Vieques, PR</p>
                <p style="margin: 5px 0 0 0; font-size: 12px;">Order ID: #$orderId</p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    // --- END OF HTML EMAIL TEMPLATE ---
    
    // --- Plain Text Version ---
    $customerMessagePlainText = "Hi $fullName,\n\n";
    $customerMessagePlainText .= "Thank you so much for your order! I'm so excited to bake up these fresh, delicious cookies just for you. Your support means the world to me!\n\n";
    $customerMessagePlainText .= "ORDER SUMMARY (ID: #$orderId)\n";
    $customerMessagePlainText .= "--------------------------------\n";
    if ($chocochipQuantity > 0) $customerMessagePlainText .= "• Chocolate Chip: $chocochipQuantity\n";
    if ($oreomgQuantity > 0) $customerMessagePlainText .= "• Ore-OMG: $oreomgQuantity\n";
    if ($snickerdoodleQuantity > 0) $customerMessagePlainText .= "• Snickerdoodle: $snickerdoodleQuantity\n";
    if ($peanutbutterQuantity > 0) $customerMessagePlainText .= "• Peanut Butter: $peanutbutterQuantity\n";
    if ($maplebaconQuantity > 0) $customerMessagePlainText .= "• Maple Bacon: $maplebaconQuantity\n";
    $customerMessagePlainText .= "\nTotal: $totalAmount\n";
    $customerMessagePlainText .= "Payment Method: $selectedPaymentMethod\n\n";
    if ($deliveryMethod === 'delivery') {
        $customerMessagePlainText .= "Delivery To:\n$street\n$city, $state $zip\nDelivery Fee: $" . number_format($actualDeliveryFee, 2) . "\n";
    } else {
        $customerMessagePlainText .= "Pickup Location: Caribbean Smoothie (next to El Yate Bar, Isabel Segunda)\n";
    }
    $customerMessagePlainText .= "\nRequested Time: " . ($pickupTime ?: 'N/A') . "\n";
    $customerMessagePlainText .= "--------------------------------\n\n";
    $customerMessagePlainText .= "While you wait, check out our Facebook page: https://www.facebook.com/ordermycookies\n\n";
    $customerMessagePlainText .= "Questions? Just reply to this email.\n\n- Courtney's Cookies 🍪";
    
    // Send to the customer's email address
    $customerMailSuccess = $mailClient->sendEmail($email, $fullName, $customerSubject, $customerMessageHtml, $customerMessagePlainText);
    if (!$customerMailSuccess) {
        error_log("Failed to send confirmation email to $email for order #$orderId. PHPMailer Error: " . $mailClient->getMailerInstance()->ErrorInfo);
    }
}

// Final JSON Response... (Code is unchanged here)
if ($mailSuccess && $customerMailSuccess && $dbSuccess) {
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully! We\'ve sent you a confirmation email.',
        'totalAmount' => $totalAmount,
        'selectedPaymentMethod' => $selectedPaymentMethod,
        'paymentMessage' => $paymentMessage,
        'orderId' => $orderId
    ]);
} else {
    $errorMessages = [];
    if (!$dbSuccess) $errorMessages[] = "There was an issue saving your order to our system.";
    if ($dbSuccess && !$mailSuccess) $errorMessages[] = "Order saved, but failed to send admin notification email.";
    if ($dbSuccess && !$customerMailSuccess) $errorMessages[] = "Order saved, but we failed to send your confirmation email. Please contact us if you don't receive it shortly.";
    $clientErrorMessage = $dbSuccess ? implode(" ", $errorMessages) : "Failed to place order. Please try again or contact us directly.";
    echo json_encode(['success' => false, 'message' => $clientErrorMessage, 'orderId' => $orderId]);
}
?>
