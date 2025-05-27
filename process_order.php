<?php
// Set header to return JSON
header('Content-Type: application/json');
error_reporting(E_ALL); // Report all errors
ini_set('display_errors', 0); // Don't display errors (breaks JSON)
ini_set('log_errors', 1); // Log errors to server log

// Include necessary files
require_once 'db_connect.php';
require_once 'send_email.php';

// --- Configuration ---
$hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
$owner_email = (str_contains($hostname, 'dev.') || str_contains($hostname, 'localhost'))
             ? 'quentin.forgues@gmail.com' // Dev owner email
             : 'courtney.forgues@gmail.com'; // Prod owner email

$fromEmail = "courtney@ordermycookies.com";
$fromName = "Courtney's Cookies";
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// --- Input Validation ---
$required = ['full_name', 'email', 'phone', 'payment_method', 'total_amount'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        $response['message'] = "Missing required field: $field";
        echo json_encode($response);
        exit;
    }
}

// --- Sanitize and Get Data ---
$fullName = htmlspecialchars($_POST['full_name']);
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? $_POST['email'] : '';
$phone = htmlspecialchars($_POST['phone']);
$street = htmlspecialchars($_POST['street'] ?? '');
$city = htmlspecialchars($_POST['city'] ?? '');
$state = htmlspecialchars($_POST['state'] ?? '');
$zip = htmlspecialchars($_POST['zip'] ?? '');

$deliveryMethod = htmlspecialchars($_POST['delivery_method'] ?? 'pickup');
$pickupTime = htmlspecialchars($_POST['pickup_time'] ?? '');

$choco_qty = (int)($_POST['chocolate_chip_quantity'] ?? 0);
$pb_qty = (int)($_POST['peanut_butter_quantity'] ?? 0);
$oreomg_qty = (int)($_POST['oreomg_quantity'] ?? 0);
$snick_qty = (int)($_POST['snickerdoodle_quantity'] ?? 0);
$maple_qty = (int)($_POST['maplebacon_quantity'] ?? 0);

$totalAmountFloat = filter_var($_POST['total_amount'], FILTER_VALIDATE_FLOAT);
$totalAmountString = '$' . number_format($totalAmountFloat, 2); // Use this for display/email

$paymentMethod = htmlspecialchars($_POST['payment_method']);

// --- Define Payment Messages (as JS no longer sends it) ---
$paymentMessages = [
    'Cash' => 'Please have exact cash ready for pickup/delivery.',
    'CreditCard' => 'You will be sent a secure payment link via email/text shortly.',
    'Venmo' => 'Please send payment to @CourtneysCookies (Confirm name before sending!)'
];
$paymentMessage = $paymentMessages[$paymentMethod] ?? 'Payment details will be confirmed.';

if (empty($email)) {
    $response['message'] = "Invalid Email Address.";
    echo json_encode($response);
    exit;
}

// --- Database Insert ---
$dbSuccess = false;
$orderId = null;

try {
    $stmt = $pdo->prepare("INSERT INTO cookie_orders (
        full_name, email, phone, street, city, state, zip,
        chocolate_chip_quantity, peanut_butter_quantity, oreomg_quantity,
        snickerdoodle_quantity, maplebacon_quantity,
        total_amount, delivery_method, pickup_time, payment_method,
        status, order_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', NOW())");

    $stmt->execute([
        $fullName, $email, $phone, $street, $city, $state, $zip,
        $choco_qty, $pb_qty, $oreomg_qty, $snick_qty, $maple_qty,
        $totalAmountFloat, $deliveryMethod, $pickupTime, $paymentMethod
    ]);

    $orderId = $pdo->lastInsertId();
    $dbSuccess = true;

} catch (PDOException $e) {
    error_log("Database Error in process_orders.php: " . $e->getMessage());
    $response['message'] = "Database saving failed.";
    echo json_encode($response);
    exit;
}

// --- Send Emails ---
$ownerMailSuccess = false;
$customerMailSuccess = false;

if ($dbSuccess) {
    // 1. Send to Owner
    $to = $owner_email;
    $subjectOwner = "New Cookie Order #$orderId from $fullName";
    $headersOwner = "From: $fromName <$fromEmail>\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8";
    $messageOwner = "New cookie order (#$orderId):\n\nName: $fullName\nEmail: $email\nPhone: $phone\n\nAddress:\n$street\n$city, $state $zip\n\nDelivery Method: " . ucfirst($deliveryMethod) . "\nPreferred Time: " . ($pickupTime ?: 'N/A') . "\n\nPayment Method: " . $paymentMethod . "\n\nOrder Details:\n";
    if ($choco_qty > 0) $messageOwner .= "Chocolate Chip: $choco_qty\n";
    if ($pb_qty > 0)    $messageOwner .= "Peanut Butter: $pb_qty\n";
    if ($oreomg_qty > 0) $messageOwner .= "Ore-OMG: $oreomg_qty\n";
    if ($snick_qty > 0) $messageOwner .= "Snickerdoodle: $snick_qty\n";
    if ($maple_qty > 0) $messageOwner .= "Maple Bacon: $maple_qty\n";
    $messageOwner .= "\nTotal: $totalAmountString\n";
    $ownerMailSuccess = @mail($to, $subjectOwner, $messageOwner, $headersOwner);

    // 2. Send to Customer
    $host = $_SERVER['HTTP_HOST'] ?? 'dev.ordermycookies.com';
    $logoUrl = "http://" . $host . "/images/logo.png";
    $subjectCustomer = "We've Received Your Courtneys Cookies Order! 🍪";
    $bodyCustomer = '<html><body style="font-family: Quicksand, sans-serif; color: #3E2C1C; background-color: #FFF7ED; padding: 20px;"><div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;padding:20px;box-shadow:0 0 10px rgba(0,0,0,0.05);"><img src="' . $logoUrl . '" style="max-width:150px;margin:auto;display:block;" alt="Courtneys Cookies"/><h2 style="color:#6B4423;text-align:center;">Thank you for your order!</h2><p style="text-align:center;">We\'ve received your delicious order (ID: ' . htmlspecialchars($orderId) . ') and will start baking soon! We\'ll send another email when it\'s ready. We hope you LOVE them!</p><p style="text-align:center;">Don\'t forget to <a href="https://facebook.com/ordermycookies" target="_blank">like and share us on Facebook</a> and tell friends and family about <strong>OrderMyCookies.com</strong>.</p><p style="text-align:center;">We\'re rolling out fun discounts and cookie surprises soon, so stay tuned!</p><p style="text-align:center;">Sweetest Regards,<br>- Courtney</p></div></body></html>';
    $customerMailSuccess = @sendCustomerEmail($email, $subjectCustomer, $bodyCustomer);
}

// --- Prepare Final Response (Using your desired structure) ---
$mailSuccess = $ownerMailSuccess && $customerMailSuccess; // Both must succeed for overall success

if ($mailSuccess && $dbSuccess) {
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully!',
        'totalAmount' => $totalAmountString, // Use string for display
        'payment_method' => $paymentMethod,
        'paymentMessage' => $paymentMessage // Use the derived message
    ]);
} else {
    $errorMessage = "Failed to place order. ";
    // Provide more specific feedback if possible
    if (!$dbSuccess) {
        $errorMessage .= "Database saving failed. ";
    } elseif (!$mailSuccess) {
        $errorMessage .= "Email sending failed. Please check your email and contact us if you don't receive a confirmation.";
    }
    echo json_encode(['success' => false, 'message' => $errorMessage]);
}

exit; // Ensure script stops here
?>