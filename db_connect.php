<?php
// db_connect.php

// 1. Check if environment is manually defined
$env = getenv('DEPLOY_ENV');

// 2. Or auto-detect by domain
if (!$env) {
    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (str_contains($hostname, 'dev.') || str_contains($hostname, 'localhost')) {
        $env = 'dev';
    } else {
        $env = 'prod';
    }
}

$configFile = __DIR__ . "/config/db_connect_{$env}.php";

if (!file_exists($configFile)) {
    error_log("Missing DB config for env: $env");
    die("Database environment misconfigured.");
}

require_once($configFile);
