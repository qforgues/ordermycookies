<?php
$dbHost = 'localhost';
$dbName = 'cmvtuimy_cookies_dev';
$dbUser = 'cmvtuimy_cookieMonster';
$dbPass = 'M6B!BQ5VJLQD8%eF';

try {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the actual error for your records
    error_log("Dev DB connection failed: " . $e->getMessage());
    // Send back a JSON error if possible (though die() might prevent it)
    // It's better to let process_orders.php handle the error if $pdo isn't set
    // For now, we'll die() but know this might still break JSON in *this* specific case
    // If the connection works, this won't run.
    header('Content-Type: application/json'); // Try to set header
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Please contact support.']);
    die(); // Stop execution on DB fail
}
