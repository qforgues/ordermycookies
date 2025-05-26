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
    error_log("Dev DB connection failed: " . $e->getMessage());
    die("Dev DB connection failed.");
}
