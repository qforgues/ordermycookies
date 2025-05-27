<?php
header('Content-Type: application/json');
require_once 'db_connect.php'; // Include your database connection
$owner_email = getenv('DEPLOY_ENV');
if (!$env) {
    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (str_contains($hostname, 'dev.') || str_contains($hostname, 'localhost')) {
        $owner_email = 'quentin.forgues@gmail.com';
    } else {
        $owner_email = 'courtney.forgues@gmail.com';
    }
}
$owner_email = "courtney.forgues@gmail.com";
$fromEmail = "courtney@courtneyscookies.com";
$fromName = "Courtney's Cookies";

$required = ['full_name', 'email', 'phone', 'street', 'city', 'state', 'zip', 'selectedPaymentMethod']; // Added selectedPaymentMethod
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$fullName = htmlspecialchars($_POST['full_name']);
$email = htmlspecialchars($_POST['email']);
$phone = htmlspecialchars($_POST['phone']);
$street = htmlspecialchars($_POST['street']);
$city = htmlspecialchars($_POST['city']);
$state = htmlspecialchars($_POST['state']);
$zip = htmlspecialchars($_POST['zip']);

$deliveryMethod = htmlspecialchars($_POST['deliveryMethod'] ?? 'pickup');
$pickupTime = htmlspecialchars($_POST['pickupTime'] ?? ''); // CORRECTED LINE 26
$actualDeliveryFee = (float)($_POST['actualDeliveryFee'] ?? 0.00); // Use the actual fee from JS

$chocochipQuantity = (int)($_POST['chocochipQuantity'] ?? 0);
$oreomgQuantity = (int)($_POST['oreomgQuantity'] ?? 0);
$snickerdoodleQuantity = (int)($_POST['snickerdoodleQuantity'] ?? 0);
$peanutbutterQuantity = (int)($_POST['peanutbutterQuantity'] ?? 0);
$maplebaconQuantity = (int)($_POST['maplebaconQuantity'] ?? 0);
$totalAmount = htmlspecialchars($_POST['totalAmount'] ?? '$0.00'); // Keep as string "$XX.XX" for email

$selectedPaymentMethod = htmlspecialchars($_POST['selectedPaymentMethod']);
$paymentMessage = htmlspecialchars($_POST['paymentMessage']);

$dbSuccess = false;
try {
    $stmt = $pdo->prepare("INSERT INTO cookie_orders (
        full_name,
        email,
        phone,
        street,
        city,
        state,
        zip,
        chocolate_chip_quantity,
        peanut_butter_quantity,
        oreomg_quantity,
        snickerdoodle_quantity,
        maplebacon_quantity,
        total_amount,
        delivery_method,
        pickup_time,
        delivery_fee,
        payment_method
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $fullName,
        $email,
        $phone,
        $street,
        $city,
        $state,
        $zip,
        $chocochipQuantity,
        $peanutbutterQuantity,
        $oreomgQuantity,
        $snickerdoodleQuantity,
        $maplebaconQuantity,
        $totalAmount,
        $deliveryMethod,
        $pickupTime,
        $actualDeliveryFee,
        $selectedPaymentMethod
    ]);

    $dbSuccess = true;
} catch (PDOException $e) {
    error_log("Database Error in process_order.php: " . $e->getMessage());
    $dbSuccess = false;
}

$subject = "New Cookie Order from $fullName";
$headers = "From: $fromName <$fromEmail>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8";

$message = "New cookie order:\n\n";
$message .= "Name: $fullName\nEmail: $email\nPhone: $phone\n\n";
$message .= "Address:\n$street\n$city, $state $zip\n\n";
$message .= "Delivery Method: " . ucfirst($deliveryMethod) . "\n";
$message .= "Preferred Time: " . ($pickupTime ?: 'N/A') . "\n";
$message .= "Delivery Fee: $" . number_format($actualDeliveryFee, 2) . "\n\n";

$message .= "Payment Method: " . $selectedPaymentMethod . "\n";
if (!empty($paymentMessage)) {
    $message .= "Payment Instructions: " . $paymentMessage . "\n\n";
}

$message .= "Order Details:\n";
if ($chocochipQuantity > 0) $message .= "Chocolate Chip: $chocochipQuantity\n";
if ($oreomgQuantity > 0) $message .= "Ore-OMG: $oreomgQuantity\n";
if ($snickerdoodleQuantity > 0) $message .= "Snickerdoodle: $snickerdoodleQuantity\n";
if ($peanutbutterQuantity > 0) $message .= "Peanut Butter: $peanutbutterQuantity\n";
$message .= "\nTotal: $totalAmount\n";

$mailSuccess = mail($to, $subject, $message, $headers);

if ($mailSuccess && $dbSuccess) {
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully!',
        'totalAmount' => $totalAmount,
        'selectedPaymentMethod' => $selectedPaymentMethod,
        'paymentMessage' => $paymentMessage
    ]);
} else {
    $errorMessage = "Failed to place order. ";
    if (!$mailSuccess) $errorMessage .= "Email sending failed. ";
    if (!$dbSuccess) $errorMessage .= "Database saving failed.";
    echo json_encode(['success' => false, 'message' => $errorMessage]);
}
?>