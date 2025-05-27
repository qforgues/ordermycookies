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
// --- Prepare variables for the email ---
$pickupTimeDisplay = $pickupTime ?: 'N/A'; // Use null coalescing or ternary
$deliveryMethodDisplay = ucfirst($deliveryMethod); // Prepare the capitalized version

$customerSubject = "Thank you for your order! (#$orderId)";
$customerHeaders = "From: $fromName <$fromEmail>\r\n";
$customerHeaders .= "Reply-To: $fromEmail\r\n";
$customerHeaders .= "MIME-Version: 1.0\r\n"; // Added for HTML
$customerHeaders .= "Content-Type: text/html; charset=UTF-8\r\n"; // Set content type to HTML

// Define some variables for links/URLs (replace with your actual URLs)
$facebookLink = "https://www.facebook.com/yourpage";
$websiteUrl = "OrderMyCookies.com";
$logoPlaceholderText = "[Your Logo Here]"; // Or use an <img src="URL_TO_LOGO">

// Build the HTML Message using HEREDOC syntax for readability
$customerMessage = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank you for your order!</title>
    <style>
        /* Basic styles - more robust styling would be needed for full cross-client compatibility */
        body { margin: 0; padding: 0; background-color: #FDF5E6; font-family: Arial, sans-serif; }
        .container { background-color: #FFFFFF; max-width: 600px; margin: 20px auto; border: 1px solid #EADDC7; border-radius: 8px; overflow: hidden; }
        .content { padding: 20px 40px; }
        .logo { text-align: center; padding: 20px 0; border-bottom: 1px solid #EADDC7; font-size: 24px; color: #cccccc; background-color: #f8f8f8; }
        h1 { color: #8B4513; text-align: center; font-family: Georgia, serif; font-size: 28px; margin-top: 30px; margin-bottom: 20px; }
        p { color: #5C5C5C; text-align: center; font-family: Arial, sans-serif; line-height: 1.6; font-size: 16px; margin-bottom: 20px; }
        a { color: #3b5998; text-decoration: underline; }
        strong { color: #8B4513; }
        .signature { color: #5C5C5C; text-align: center; font-family: Georgia, serif; font-style: italic; margin-top: 40px; font-size: 16px; }
        .details-section { margin-top: 40px; padding-top: 20px; border-top: 1px solid #EADDC7; }
        .details-section h2 { color: #8B4513; text-align: center; font-family: Georgia, serif; font-size: 20px; margin-bottom: 15px;}
        .details-table { width: 100%; margin: 20px 0; border-collapse: collapse; }
        .details-table th, .details-table td { padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C; }
        .details-table th { background-color: #f9f9f9; font-weight: bold; }
        .details-table .total td { font-weight: bold; font-size: 1.1em; border-top: 2px solid #ccc; }

    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #FDF5E6; font-family: Arial, sans-serif;">
    <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#FDF5E6">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table class="container" width="600" border="0" cellpadding="0" cellspacing="0" style="background-color: #FFFFFF; border: 1px solid #EADDC7; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td class="logo" style="text-align: center; padding: 20px 0; border-bottom: 1px solid #EADDC7; font-size: 24px; color: #cccccc; background-color: #f8f8f8;">
                            {$logoPlaceholderText}
                        </td>
                    </tr>
                    <tr>
                        <td class="content" style="padding: 20px 40px;">
                            <h1 style="color: #8B4513; text-align: center; font-family: Georgia, serif; font-size: 28px; margin-top: 30px; margin-bottom: 20px;">Thank you for your order!</h1>

                            <p style="color: #5C5C5C; text-align: center; font-family: Arial, sans-serif; line-height: 1.6; font-size: 16px; margin-bottom: 20px;">
                                We've received your delicious order (ID: {$orderId}) and will start baking soon! We'll send another email when it's ready. We hope you LOVE them!
                            </p>

                            <p style="color: #5C5C5C; text-align: center; font-family: Arial, sans-serif; line-height: 1.6; font-size: 16px; margin-bottom: 20px;">
                                Don't forget to <a href="{$facebookLink}" style="color: #3b5998; text-decoration: underline;">like and share us on Facebook</a> and tell friends and family about <strong style="color: #8B4513;">{$websiteUrl}</strong>.
                            </p>

                            <p style="color: #5C5C5C; text-align: center; font-family: Arial, sans-serif; line-height: 1.6; font-size: 16px; margin-bottom: 20px;">
                                We're rolling out fun discounts and cookie surprises soon, so stay tuned!
                            </p>

                            <div class="details-section" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #EADDC7;">
                                <h2 style="color: #8B4513; text-align: center; font-family: Georgia, serif; font-size: 20px; margin-bottom: 15px;">Your Order Summary</h2>
                                <table class="details-table" width="100%" cellpadding="8" cellspacing="0" style="width: 100%; margin: 20px 0; border-collapse: collapse;">
                                    <tr><td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;"><strong>Name:</strong></td><td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;">{$fullName}</td></tr>
                                    <tr><td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;"><strong>Email:</strong></td><td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;">{$email}</td></tr>
                                    <tr><td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;"><strong>Address:</strong></td><td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;">{$street} - {$city}, {$zip}</td></tr>
                                    <?php $pickupTimeDisplay = $pickupTime ? $pickupTime : 'N/A'; ?>
                                    <tr><td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;"><strong>Delivery:</strong></td><td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;">{ucfirst($deliveryMethod)} ({$pickupTimeDisplay})</td></tr>
                                    <tr><td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;"><strong>Payment:</strong></td><td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;">{$selectedPaymentMethod}</td></tr>
                                    <tr><th colspan="2" style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C; background-color: #f9f9f9; font-weight: bold; text-align:center;">Items</th></tr>
HTML;

// Add cookie items conditionally
if ($chocochipQuantity > 0) $customerMessage .= "<tr><td style='padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;'>Chocolate Chip:</td><td style='padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;'>{$chocochipQuantity}</td></tr>";
if ($oreomgQuantity > 0) $customerMessage .= "<tr><td style='padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;'>Ore-OMG:</td><td style='padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;'>{$oreomgQuantity}</td></tr>";
if ($snickerdoodleQuantity > 0) $customerMessage .= "<tr><td style='padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;'>Snickerdoodle:</td><td style='padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;'>{$snickerdoodleQuantity}</td></tr>";
if ($peanutbutterQuantity > 0) $customerMessage .= "<tr><td style='padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;'>Peanut Butter:</td><td style='padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;'>{$peanutbutterQuantity}</td></tr>";
if ($maplebaconQuantity > 0) $customerMessage .= "<tr><td style='padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;'>Maple Bacon:</td><td style='padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C;'>{$maplebaconQuantity}</td></tr>";
$customerMessage .= <<<HTML
                                    <tr class="total"><td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C; font-weight: bold; font-size: 1.1em; border-top: 2px solid #ccc;"><strong>Total:</strong></td><td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee; color: #5C5C5C; font-weight: bold; font-size: 1.1em; border-top: 2px solid #ccc;"><strong>{$totalAmount}</strong></td></tr>
                                </table>
                            </div>

                            <p class="signature" style="color: #5C5C5C; text-align: center; font-family: Georgia, serif; font-style: italic; margin-top: 40px; font-size: 16px;">
                                Sweetest Regards,<br>- Courtney
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

// Send Customer Email (Uncomment to send)
$customerMailSuccess = mail($email, $customerSubject, $customerMessage, $customerHeaders);

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