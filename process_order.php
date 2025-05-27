<?php
// Set header to return JSON
header('Content-Type: application/json');
error_reporting(E_ALL); // Report all errors
ini_set('display_errors', 0); // Don't display errors (breaks JSON)
ini_set('log_errors', 1); // Log errors to server log

// Include necessary files
require_once 'db_connect.php';
require_once 'send_email.php'; // <-- Added this

// --- Configuration ---
$hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
$owner_email = (str_contains($hostname, 'dev.') || str_contains($hostname, 'localhost'))
             ? 'quentin.forgues@gmail.com' // Dev owner email
             : 'courtney.forgues@gmail.com'; // Prod owner email

$fromEmail = "courtney@ordermycookies.com"; // Make sure this is a configured sending address on your server
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

// --- Sanitize and Get Data (Using Underscores) ---
$fullName = htmlspecialchars($_POST['full_name']);
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? $_POST['email'] : '';
$phone = htmlspecialchars($_POST['phone']);
$street = htmlspecialchars($_POST['street'] ?? '');
$city = htmlspecialchars($_POST['city'] ?? '');
$state = htmlspecialchars($_POST['state'] ?? '');
$zip = htmlspecialchars($_POST['zip'] ?? '');

$deliveryMethod = htmlspecialchars($_POST['delivery_method'] ?? 'pickup'); // <-- FIX
$pickupTime = htmlspecialchars($_POST['pickup_time'] ?? '');           // <-- FIX

$choco_qty = (int)($_POST['chocolate_chip_quantity'] ?? 0); // <-- FIX
$pb_qty = (int)($_POST['peanut_butter_quantity'] ?? 0);    // <-- FIX
$oreomg_qty = (int)($_POST['oreomg_quantity'] ?? 0);         // <-- FIX
$snick_qty = (int)($_POST['snickerdoodle_quantity'] ?? 0);  // <-- FIX
$maple_qty = (int)($_POST['maplebacon_quantity'] ?? 0);    // <-- FIX

$totalAmountFloat = filter_var($_POST['total_amount'], FILTER_VALIDATE_FLOAT); // <-- FIX
$totalAmountString = '$' . number_format($totalAmountFloat, 2);

$paymentMethod = htmlspecialchars($_POST['payment_method']);

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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', NOW())"); // Added 'New' status

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

// --- Send Emails (Only if DB was successful) ---
$ownerMailSuccess = false;
$customerMailSuccess = false;

if ($dbSuccess) {
    // --- 1. Send Email to Owner ---
    $to = $owner_email; // <-- FIX: Define $to
    $subjectOwner = "New Cookie Order #$orderId from $fullName";
    $headersOwner = "From: $fromName <$fromEmail>\r\n";
    $headersOwner .= "Reply-To: $email\r\n";
    $headersOwner .= "Content-Type: text/plain; charset=UTF-8";

    $messageOwner = "New cookie order (#$orderId):\n\n";
    $messageOwner .= "Name: $fullName\nEmail: $email\nPhone: $phone\n\n";
    $messageOwner .= "Address:\n$street\n$city, $state $zip\n\n";
    $messageOwner .= "Delivery Method: " . ucfirst($deliveryMethod) . "\n";
    $messageOwner .= "Preferred Time: " . ($pickupTime ?: 'N/A') . "\n\n";
    $messageOwner .= "Payment Method: " . $paymentMethod . "\n\n";
    $messageOwner .= "Order Details:\n";
    if ($choco_qty > 0) $messageOwner .= "Chocolate Chip: $choco_qty\n";
    if ($pb_qty > 0)    $messageOwner .= "Peanut Butter: $pb_qty\n";
    if ($oreomg_qty > 0) $messageOwner .= "Ore-OMG: $oreomg_qty\n";
    if ($snick_qty > 0) $messageOwner .= "Snickerdoodle: $snick_qty\n";
    if ($maple_qty > 0) $messageOwner .= "Maple Bacon: $maple_qty\n";
    $messageOwner .= "\nTotal: $totalAmountString\n";

    $ownerMailSuccess = @mail($to, $subjectOwner, $messageOwner, $headersOwner); // Use @ to suppress PHP mail errors (we log below)

    // --- 2. Send Email to Customer ---
    $host = $_SERVER['HTTP_HOST'] ?? 'dev.ordermycookies.com';
    // Use absolute URL for images in emails
    $logoUrl = "http://" . $host . "/images/logo.png";
    $subjectCustomer = "We've Received Your Courtneys Cookies Order! 🍪";
    $bodyCustomer = '<html><body style="font-family: Quicksand, sans-serif; color: #3E2C1C; background-color: #FFF7ED; padding: 20px;">
             <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;padding:20px;box-shadow:0 0 10px rgba(0,0,0,0.05);">
                 <img src="' . $logoUrl . '" style="max-width:150px;margin:auto;display:block;" alt="Courtneys Cookies"/>
                 <h2 style="color:#6B4423;text-align:center;">Thank you for your order!</h2>
                 <p style="text-align:center;">We\'ve received your delicious order (ID: ' . htmlspecialchars($orderId) . ') and will start baking soon! We\'ll send another email when it\'s ready. We hope you LOVE them!</p>
                 <p style="text-align:center;">Don\'t forget to <a href="https://facebook.com/ordermycookies" target="_blank">like and share us on Facebook</a> and tell friends and family about <strong>OrderMyCookies.com</strong>.</p>
                 <p style="text-align:center;">We\'re rolling out fun discounts and cookie surprises soon, so stay tuned!</p>
                 <p style="text-align:center;">Sweetest Regards,<br>- Courtney</p>
             </div></body></html>';

    // Use your existing sendCustomerEmail function
    $customerMailSuccess = @sendCustomerEmail($email, $subjectCustomer, $bodyCustomer);

    // --- Prepare Final Response ---
    $response['success'] = true;
    $response['message'] = 'Order placed successfully!';
    $response['orderId'] = $orderId;
    $response['totalAmount'] = $totalAmountString;
    $response['payment_method'] = $paymentMethod;

    if (!$ownerMailSuccess) error_log("Failed to send OWNER email for order $orderId");
    if (!$customerMailSuccess) error_log("Failed to send CUSTOMER email for order $orderId");

} else {
    // This part should not be reached due to exit on DB fail, but as a fallback:
    $response['message'] = "Database saving failed.";
}

echo json_encode($response);
exit;
?>