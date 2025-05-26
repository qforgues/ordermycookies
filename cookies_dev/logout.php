<?php
session_start();
header('Content-Type: application/json');

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
?>