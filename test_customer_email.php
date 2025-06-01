<?php
// test_customer_email.php

//    Change these paths if PHPMailer is located elsewhere in your repo.
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// 2. Now load EasyMailClient (which depends on PHPMailer)
require_once __DIR__ . '/inc/EasyMailClient.php';

// Instantiate a fresh mail client
$mailClient = new EasyMailClient();

// Replace with the address you want to test (your customer/test mailbox)
$testEmail = 'quentin.forgues@gmail.com';
$testName  = 'Quentin Test';
$testSubject = 'TEST: Courtney\'s Cookies Customer-only Email';
$testHtml = '<h1>Test Only</h1><p>If you see this, the standalone customer email worked.</p>';
$testPlain = "Test Only\n\nIf you see this, the standalone customer email worked.";

// Attempt to send *only* to $testEmail
$sent = $mailClient->sendEmail(
    $testEmail,
    $testName,
    $testSubject,
    $testHtml,
    $testPlain
);

// Output success or error in plain text
if (! $sent) {
    $mailer = $mailClient->getMailerInstance();
    $err = $mailer->ErrorInfo ?: 'Unknown error';
    echo "✖ Send FAILED: {$err}\n";
} else {
    echo "✔ Send SUCCEEDED to {$testEmail}\n";
}

exit();
?>
