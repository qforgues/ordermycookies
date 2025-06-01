<?php

// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Ensure the PHPMailer library is loaded.
// If you're using Composer, the autoloader will handle this.
// If not, you'll need to require the necessary files manually.
// Example:
// require 'PHPMailer/src/Exception.php';
// require 'PHPMailer/src/PHPMailer.php';
// require 'PHPMailer/src/SMTP.php';
// It's highly recommended to use Composer for managing dependencies.

class EasyMailClient {
    private $mailer;

    // Default SMTP Configuration
    // Updated with ordermycookies.com details
    private $smtpHost = 'mail.ordermycookies.com'; // Your SMTP server
    private $smtpAuth = true;               // Enable SMTP authentication
    private $smtpUsername = 'courtney@ordermycookies.com'; // SMTP username
    private $smtpPassword = 'Cookies143!';       // SMTP password
    private $smtpSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable SMTPS encryption for port 465
    private $smtpPort = 465;                // TCP port to connect to

    // Default Sender Information
    private $defaultFromName = 'Order My Cookies'; // Updated default sender name
    private $defaultFromEmail = 'courtney@ordermycookies.com'; // Updated default sender email

    /**
     * Constructor
     * Initializes PHPMailer with default settings.
     * @param array $config Optional configuration overrides.
     */
    public function __construct(array $config = []) {
        $this->mailer = new PHPMailer(true); // Passing `true` enables exceptions

        // Apply any configuration overrides passed during instantiation
        if (!empty($config['smtpHost'])) $this->smtpHost = $config['smtpHost'];
        if (isset($config['smtpAuth'])) $this->smtpAuth = $config['smtpAuth']; // Allow disabling auth if explicitly set to false
        if (!empty($config['smtpUsername'])) $this->smtpUsername = $config['smtpUsername'];
        if (!empty($config['smtpPassword'])) $this->smtpPassword = $config['smtpPassword'];
        if (!empty($config['smtpSecure'])) $this->smtpSecure = $config['smtpSecure'];
        if (!empty($config['smtpPort'])) $this->smtpPort = $config['smtpPort'];
        if (!empty($config['defaultFromName'])) $this->defaultFromName = $config['defaultFromName'];
        if (!empty($config['defaultFromEmail'])) $this->defaultFromEmail = $config['defaultFromEmail'];

        $this->configureMailer();
    }

    /**
     * Configures the PHPMailer instance with server settings.
     */
    private function configureMailer() {
        try {
            // Server settings
            // $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output for troubleshooting
            $this->mailer->isSMTP();                               // Send using SMTP
            $this->mailer->Host       = $this->smtpHost;
            $this->mailer->SMTPAuth   = $this->smtpAuth;
            $this->mailer->Username   = $this->smtpUsername;
            $this->mailer->Password   = $this->smtpPassword;
            $this->mailer->SMTPSecure = $this->smtpSecure;
            $this->mailer->Port       = $this->smtpPort;

            // Set default "From" address
            $this->mailer->setFrom($this->defaultFromEmail, $this->defaultFromName);

        } catch (Exception $e) {
            // Handle initial configuration errors if necessary.
            // This might happen if setFrom fails, for example.
            error_log("PHPMailer configuration error: {$this->mailer->ErrorInfo}");
            // Depending on your error handling strategy, you might re-throw or handle differently.
        }
    }

    /**
     * Sends an email.
     *
     * @param string $toEmail Recipient's email address.
     * @param string $toName Recipient's name (optional).
     * @param string $subject Email subject.
     * @param string $htmlBody HTML content for the email.
     * @param string $plainTextBody Plain text alternative body (optional, for non-HTML clients).
     * @param array $attachments Array of file paths for attachments (optional). Example: [['path' => '/path/to/file.pdf', 'name' => 'Document.pdf']]
     * @param array $ccEmails Array of CC email addresses (optional).
     * @param array $bccEmails Array of BCC email addresses (optional).
     * @return bool True on success, false on failure.
     */
    public function sendEmail(string $toEmail, string $toName = '', string $subject, string $htmlBody, string $plainTextBody = '', array $attachments = [], array $ccEmails = [], array $bccEmails = []) {
        try {
            // Recipients
            $this->mailer->addAddress($toEmail, $toName);

            // CC and BCC
            foreach ($ccEmails as $cc) {
                $this->mailer->addCC($cc);
            }
            foreach ($bccEmails as $bcc) {
                $this->mailer->addBCC($bcc);
            }

            // Attachments
            foreach ($attachments as $attachment) {
                if (isset($attachment['path'])) {
                    $name = isset($attachment['name']) ? $attachment['name'] : ''; // Use provided name or derive from path
                    $this->mailer->addAttachment($attachment['path'], $name);
                }
            }

            // Content
            $this->mailer->isHTML(true); // Set email format to HTML
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $htmlBody;
            if (!empty($plainTextBody)) {
                $this->mailer->AltBody = $plainTextBody;
            } else {
                // Basic auto plain text body from HTML
                $this->mailer->AltBody = strip_tags($htmlBody);
            }

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            // Log the error or handle it as per your application's needs
            error_log("Message could not be sent. Mailer Error: {$this->mailer->ErrorInfo}");
            // For debugging, you might want to echo or return the error message.
            // In a production app, log this to a file or error tracking service.
            // echo "Message could not be sent. Mailer Error: {$this->mailer->ErrorInfo}"; // For debugging
            return false;
        } finally {
            // Clear addresses and attachments for the next email
            // This is important if you reuse the same EasyMailClient instance for multiple emails.
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->clearCCs();
            $this->mailer->clearBCCs();
            // Subject and body are typically set per email, so clearing them here might be
            // overly aggressive unless you have a specific use case.
            // $this->mailer->Subject = '';
            // $this->mailer->Body = '';
            // $this->mailer->AltBody = '';
        }
    }

    /**
     * Sets a custom "From" address for a specific email.
     * This overrides the default "From" address set in the constructor or by resetFromToDefault().
     * @param string $fromEmail Sender's email address.
     * @param string $fromName Sender's name (optional).
     */
    public function setCustomFrom(string $fromEmail, string $fromName = '') {
        try {
            // If no name is provided, PHPMailer will use the email address part as the name.
            // You can decide if you want to pass an empty string or the email itself if $fromName is empty.
            $this->mailer->setFrom($fromEmail, $fromName);
        } catch (Exception $e) {
            // PHPMailer's setFrom can throw an exception for invalid email.
            error_log("Failed to set custom From address: {$e->getMessage()}");
        }
    }

    /**
     * Resets the "From" address to the default values specified in the class properties.
     */
    public function resetFromToDefault() {
        try {
            $this->mailer->setFrom($this->defaultFromEmail, $this->defaultFromName);
        } catch (Exception $e) {
            error_log("Failed to reset From address to default: {$e->getMessage()}");
        }
    }

    /**
     * For advanced use cases, you might want to get the underlying PHPMailer instance
     * to access methods not exposed by this wrapper.
     * @return PHPMailer
     */
    public function getMailerInstance(): PHPMailer {
        return $this->mailer;
    }
}

// --- HOW TO USE THE EasyMailClient ---

// Assuming PHPMailer is loaded (e.g., via Composer's autoload.php)
// require_once 'vendor/autoload.php'; // If using Composer

// ** 1. Create an instance of the EasyMailClient **
// $mailClient = new EasyMailClient(); // Uses the default ordermycookies.com settings now

// ** Example: Sending an email **
/*
$recipientEmail = 'recipient@example.net';
$recipientName = 'John Doe'; // Optional
$emailSubject = 'Order Confirmation from OrderMyCookies.com';
$emailHtmlBody = '<h1>Thank You!</h1><p>Your cookie order has been received.</p>';
$emailPlainTextBody = "Thank You!\nYour cookie order has been received."; // Optional

// Send the email using the default "From" address (courtney@ordermycookies.com)
if ($mailClient->sendEmail($recipientEmail, $recipientName, $emailSubject, $emailHtmlBody, $emailPlainTextBody)) {
    echo "Email sent successfully to $recipientEmail!<br>";
} else {
    echo "Email sending failed.<br>";
    // For debugging:
    // echo "Mailer Error: " . $mailClient->getMailerInstance()->ErrorInfo;
}
*/

// --- IMPORTANT SECURITY NOTE ---
// The password 'Cookies143!' is now hardcoded in this script.
// For production environments, this is NOT recommended.
// You should store sensitive credentials like passwords in environment variables
// or a secure configuration management system, and load them at runtime.
// Example using getenv():
// private $smtpPassword = getenv('SMTP_PASSWORD');
// And then set the SMTP_PASSWORD environment variable in your server configuration.

?>
