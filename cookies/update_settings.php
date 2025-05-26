<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'message' => ''];

// Check if user is logged in and has keymaster (0) or owner (1) role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 0 && $_SESSION['role'] !== 1)) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = [
        'delivery_fee_amount' => $_POST['delivery_fee_amount'] ?? '',
        'cash_payment_message' => $_POST['cash_payment_message'] ?? '',
        'athmovil_payment_message' => $_POST['athmovil_payment_message'] ?? '',
        'creditcard_payment_message' => $_POST['creditcard_payment_message'] ?? '',
        'venmo_payment_message' => $_POST['venmo_payment_message'] ?? '',
        'allow_shipping' => isset($_POST['allow_shipping']) ? '1' : '0'
    ];


    try {
        $pdo->beginTransaction(); // Start a transaction for multiple updates

        foreach ($updates as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                                   ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }

        $pdo->commit(); // Commit the transaction
        $response['success'] = true;
        $response['message'] = 'Settings updated successfully!';

    } catch (PDOException $e) {
        $pdo->rollBack(); // Rollback on error
        error_log("Error updating settings: " . $e->getMessage());
        $response['message'] = 'Database error: Could not update settings.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>