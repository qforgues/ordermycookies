<?php
header('Content-Type: application/json');
require_once 'db_connect.php'; // Include your database connection

$response = ['success' => false, 'message' => ''];
$settingsData = [];

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $allSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Fetches as key => value array

    // Map database keys to expected JS variable names if different
    $settingsData['delivery_fee_amount'] = $allSettings['delivery_fee_amount'] ?? '2.00';
    $settingsData['cash_payment_message'] = $allSettings['cash_payment_message'] ?? 'Please have exact cash ready for pickup/delivery.';
    $settingsData['athmovil_payment_message'] = $allSettings['athmovil_payment_message'] ?? '(818) 261-1648 Courtney Forgues';
    $settingsData['creditcard_payment_message'] = $allSettings['creditcard_payment_message'] ?? 'You will be sent a secure payment link via email/text shortly.';
    $settingsData['venmo_payment_message'] = $allSettings['venmo_payment_message'] ?? '@CourtneysCookies';
    $settingsData['allow_shipping'] = $allSettings['allow_shipping'] ?? '0';    

    $response['success'] = true;
    $response['settings'] = $settingsData; // Send all settings
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
    $response['message'] = 'Could not load settings.';
    echo json_encode($response);
}
?>