<?php
header('Content-Type: application/json');
session_start(); // Start the session for login management
require_once 'db_connect.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $response['message'] = 'Username and password are required.';
        echo json_encode($response);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Password is correct, set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = strtolower(trim($user['role']));


            $normalizedRole = strtolower($user['role']);
            if (in_array($normalizedRole, ['admin', 'keymaster'])) {
                $_SESSION['admin_logged_in'] = true;
            }

            $response['success'] = true;
            $response['message'] = 'Login successful!';
            $response['role'] = $user['role']; // Send role back to JS
        } else {
            $response['message'] = 'Invalid username or password.';
        }
    } catch (PDOException $e) {
        error_log("Login database error: " . $e->getMessage());
        $response['message'] = 'An error occurred during login.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>