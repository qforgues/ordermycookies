<?php
// db_connect.php
$dbHost = 'localhost';
$dbName = 'cmvtuimy_cookies';
$dbUser = 'cmvtuimy_cookieMonster';
$dbPass = 'M6B!BQ5VJLQD8%eF';

try {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Optional: Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error message (do not display it in production)
    error_log("Database connection failed: " . $e->getMessage());
    // In a real application, you might show a generic error page
    die("Database connection failed. Please try again later.");
}
?>