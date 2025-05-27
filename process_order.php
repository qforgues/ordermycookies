<?php
require_once 'db_connect.php';
require_once 'send_email.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $fullName = $data['fullName'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? '';
    $street = $data['street'] ?? '';
    $city = $data['city'] ?? '';
    $state = $data['state'] ?? '';
    $zip = $data['zip'] ?? '';
    $deliveryMethod = $data['deliveryMethod'] ?? 'pickup';
    $paymentMethod = $data['paymentMethod'] ?? 'Cash';
    $pickupTime = $data['pickupTime'] ?? '';
    $items = $data['items'] ?? '';
    $total = $data['total'] ?? 0.00;

    $stmt = $pdo->prepare("INSERT INTO orders (full_name, email, phone, street, city, state, zip, delivery_method, payment_method, pickup_time, items, total, status, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())");

    $stmt->execute([$fullName, $email, $phone, $street, $city, $state, $zip, $deliveryMethod, $paymentMethod, $pickupTime, $items, $total]);

    // Send confirmation email to customer
    $subject = "Thanks for your order from Courtneys Cookies!";
    $body = '
    <html>
    <body style="font-family: Quicksand, sans-serif; color: #3E2C1C; background-color: #FFF7ED; padding: 20px;">
        <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;padding:20px;box-shadow:0 0 10px rgba(0,0,0,0.05);">
            <img src="https://i.postimg.cc/VsHp5Dcs/logo.png" style="max-width:150px;margin:auto;display:block;" alt="Courtneys Cookies"/>
            <h2 style="color:#6B4423;text-align:center;">Thank you! Weve received your order!</h2>
            <p style="text-align:center;">Were baking up some love for you now. 🍪</p>
            <p style="text-align:center;">Please take a moment to <a href="https://facebook.com/ordermycookies" target="_blank">like and share us on Facebook</a>, and tell your friends about <strong>OrderMyCookies.com</strong>!</p>
            <p style="text-align:center;">More sweet surprises and discounts are coming soon. Keep an eye out!</p>
            <p style="text-align:center;">- Courtney</p>
        </div>
    </body>
    </html>';

    sendCustomerEmail($email, $subject, $body);

    echo json_encode(['success' => true, 'message' => 'Order placed successfully.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.', 'error' => $e->getMessage()]);
}
