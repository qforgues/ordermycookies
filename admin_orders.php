<?php
session_start();
// Ensure user is logged in and has admin or super admin role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 0 && $_SESSION['role'] !== 1)) {
    header('Location: admin.html?auth=failed');
    exit;
}

require_once 'db_connect.php';
require_once 'send_email.php';

// Function to format phone number to (xxx) xxx-xxxx
function format_phone($phone) {
    $phone = preg_replace("/[^0-9]/", "", $phone); // Remove non-numeric characters
    if (strlen($phone) == 10) {
        return "(" . substr($phone, 0, 3) . ") " . substr($phone, 3, 3) . "-" . substr($phone, 6);
    } else {
        return $phone; // Return original or partially formatted if not 10 digits
    }
}

// Handle POST requests for updating order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $orderId = $_POST['order_id'];
    $action = $_POST['action'];

    // Determine the new status based on the action
    $newStatus = match($action) {
        'ready'     => 'Ready',
        'paid'      => 'Paid',
        'cancelled' => 'Cancelled',
        default     => null
    };

    if ($newStatus) {
        // Update the order status in the database
        $stmt = $pdo->prepare("UPDATE cookie_orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);

        // Fetch customer email for sending notifications
        $stmt = $pdo->prepare("SELECT email FROM cookie_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $email = $stmt->fetchColumn();

        if ($email) {
            // Send "Order Received" email when marked as 'Ready'
            if ($newStatus === 'Ready') {
                $subject = "We've Received Your Courtneys Cookies Order! 🍪";
                $body = '<html><body style="font-family: Quicksand, sans-serif; color: #3E2C1C; background-color: #FFF7ED; padding: 20px;">
                         <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;padding:20px;box-shadow:0 0 10px rgba(0,0,0,0.05);">
                             <img src="https://i.postimg.cc/VsHp5Dcs/logo.png" style="max-width:150px;margin:auto;display:block;" alt="Courtneys Cookies"/>
                             <h2 style="color:#6B4423;text-align:center;">Thank you for your order!</h2>
                             <p style="text-align:center;">We\'ve received your order and will start baking soon! We hope you LOVE them.</p>
                             <p style="text-align:center;">Don\'t forget to <a href="https://facebook.com/ordermycookies" target="_blank">like and share us on Facebook</a> and tell friends and family about <strong>OrderMyCookies.com</strong>.</p>
                             <p style="text-align:center;">We\'re rolling out fun discounts and cookie surprises soon, so stay tuned!</p>
                             <p style="text-align:center;">Sweetest Regards,<br>- Courtney</p>
                         </div></body></html>';
                sendCustomerEmail($email, $subject, $body);
            }
            // Send "Order Paid/Shipped" email when marked as 'Paid'
            elseif ($newStatus === 'Paid') {
                $subject = "Your Courtneys Cookies order is on its way! 🍪";
                $body = '<html><body style="font-family: Quicksand, sans-serif; color: #3E2C1C; background-color: #FFF7ED; padding: 20px;">
                         <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;padding:20px;box-shadow:0 0 10px rgba(0,0,0,0.05);">
                             <img src="https://i.postimg.cc/VsHp5Dcs/logo.png" style="max-width:150px;margin:auto;display:block;" alt="Courtneys Cookies"/>
                             <h2 style="color:#6B4423;text-align:center;">Your cookies are on the way!</h2>
                             <p style="text-align:center;">Your order has been marked as paid and will be with you soon. We hope you LOVE them!</p>
                             <p style="text-align:center;">Don\'t forget to <a href="https://facebook.com/ordermycookies" target="_blank">like and share us on Facebook</a> and tell friends and family about <strong>OrderMyCookies.com</strong>.</p>
                             <p style="text-align:center;">We\'re rolling out fun discounts and cookie surprises soon, so stay tuned!</p>
                             <p style="text-align:center;">Sweetest Regards,<br>- Courtney</p>
                         </div></body></html>';
                sendCustomerEmail($email, $subject, $body);
            }
        }
    }

    // Redirect back to the orders page to prevent form resubmission
    header("Location: admin_orders.php");
    exit;
}

// Fetch all orders, ordered by date
$stmt = $pdo->query("SELECT * FROM cookie_orders ORDER BY order_date DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Orders</title>
    <link rel="stylesheet" href="style.css"> <style>
        :root { /* Define your variables if not in style.css */
            --accent-gold: #c59d5f;
            --primary-brown: #3E2C1C;
        }
        .orders-container {
            max-width: 900px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .order-card {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            transition: background-color 0.3s ease; /* Smooth transition for color changes */
        }
        /* Status Background Colors */
        .status-ready { background-color: #fffbe0; } /* Yellow for Ready */
        .status-paid { background-color: #e0ffe0; } /* Green for Paid */
        .status-fulfilled { background-color: #e0e8ff; } /* Blue for Fulfilled */
        .status-cancelled { background-color: #ffe0e0; } /* Red for Cancelled */

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Allows items to wrap on smaller screens */
            margin-bottom: 10px; /* Space between header and details */
        }
        .order-name {
            width: 100%; /* Name takes full width initially */
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 15px; /* Space below the name */
            color: var(--primary-brown);
        }
        .button-container {
            width: 100%; /* Container takes full width */
            display: flex;
            justify-content: space-between; /* Pushes buttons to edges */
            gap: 2%; /* Adds a small gap between buttons */
        }
        .button-container form {
            width: 49%; /* Each form takes roughly half the space */
            display: inline-block;
        }
        .order-toggle {
            background-color: var(--accent-gold); /* Default button color */
            color: white; /* White text */
            border: none;
            padding: 10px 15px; /* Comfortable padding */
            cursor: pointer;
            border-radius: 6px;
            width: 100%; /* Button fills its container (form) */
            box-sizing: border-box; /* Include padding in width */
            font-size: 0.95em;
            transition: background-color 0.2s ease;
        }
        .order-toggle:hover {
            opacity: 0.9;
        }
        .order-toggle.ready { /* Specific style for Ready button */
            background-color: #f0ad4e; /* Orange/Yellow */
        }
        .order-toggle.paid { /* Style for Paid button */
             background-color: #5cb85c; /* Green */
        }
        .order-toggle.cancel { /* Style for Cancel button */
            background-color: #d9534f; /* Red */
        }
        .toggle-fulfilled {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-weight: bold;
            font-size: 1em;
            color: var(--primary-brown);
        }
        .toggle-fulfilled input {
            margin-right: 8px;
            transform: scale(1.3);
        }
        .order-card p { /* Style for order details */
            margin: 5px 0;
            line-height: 1.5;
        }
        .order-card a { /* Style for links */
            color: var(--accent-gold);
            text-decoration: none;
        }
         .order-card a:hover {
            text-decoration: underline;
        }
        .status-text { /* Style for displaying status when no buttons */
            font-weight: bold;
            font-size: 1em;
            text-align: right;
            width: 100%;
            padding: 10px 0;
        }
        /* Initially hide fulfilled orders */
        .fulfilled-hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="orders-container">
        <h1>Orders Admin</h1>
        <label class="toggle-fulfilled">
            <input type="checkbox" id="showFulfilled"> Show Fulfilled Orders
        </label>

        <?php foreach ($orders as $order): ?>
            <?php
                $status = strtolower($order['status']);
                // Determine CSS class based on status
                $statusClass = match($status) {
                    'ready'     => 'status-ready',
                    'paid'      => 'status-paid',
                    'fulfilled' => 'status-fulfilled',
                    'cancelled' => 'status-cancelled',
                    default     => '' // Default for new/pending orders
                };

                // Create a summary of ordered items
                $itemSummary = [];
                $flavors = [
                    'chocolate_chip_quantity', 'peanut_butter_quantity',
                    'oreomg_quantity', 'snickerdoodle_quantity', 'maplebacon_quantity'
                ];
                foreach ($flavors as $flavor) {
                    if ($order[$flavor] > 0) {
                        $label = ucwords(str_replace(['_', ' quantity'], [' ', ''], $flavor));
                        $itemSummary[] = "$label: {$order[$flavor]}";
                    }
                }

                // Flags for status checks
                $isFulfilled = $status === 'fulfilled';
                $isPaid = $status === 'paid';
                $isCancelled = $status === 'cancelled';
                $isReady = $status === 'ready';
                // Determine if any action can be taken (i.e., not paid, cancelled, or fulfilled)
                $canTakeAction = !$isPaid && !$isCancelled && !$isFulfilled;
                // Format phone number
                $formattedPhone = format_phone($order['phone']);
            ?>
            <div class="order-card <?= $statusClass ?> <?= $isFulfilled ? 'fulfilled-hidden' : '' ?>">
                <div class="order-header">
                    <div class="order-name"><?= htmlspecialchars($order['full_name']) ?></div>
                    <div class="button-container">
                        <?php if ($canTakeAction): ?>
                            <?php if ($isReady): ?>
                                <form method="post">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="action" value="paid">
                                    <button class="order-toggle paid" type="submit">Mark as Paid</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="action" value="cancelled">
                                    <button class="order-toggle cancel" type="submit">Cancel Order</button>
                                </form>
                            <?php else: ?>
                                <form method="post">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="action" value="ready">
                                    <button class="order-toggle ready" type="submit">Mark as Ready</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="action" value="cancelled">
                                    <button class="order-toggle cancel" type="submit">Cancel Order</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="status-text">Status: <?= htmlspecialchars(ucwords($order['status'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <p><strong>Items:</strong> <?= implode(', ', $itemSummary) ?></p>
                <p><strong>Total | Method:</strong> $<?= htmlspecialchars($order['total_amount']) ?> | <?= htmlspecialchars($order['payment_method']) ?></p>
                <p>
                    <strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($order['email']) ?>"><?= htmlspecialchars($order['email']) ?></a> |
                    <strong>Phone:</strong> <a href="tel:<?= preg_replace("/[^0-9]/", "", $order['phone']) ?>"><?= $formattedPhone ?></a>
                </p>
                <p><strong>Delivery:</strong> <?= htmlspecialchars($order['delivery_method']) ?> @ <?= htmlspecialchars($order['pickup_time']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        const toggleBox = document.getElementById('showFulfilled');
        const fulfilledCards = document.querySelectorAll('.status-fulfilled'); // Target by status class

        function toggleFulfilled() {
            fulfilledCards.forEach(card => {
                // If the checkbox is checked, always show.
                // If unchecked, hide the card (since it has 'fulfilled-hidden' by default if it was fulfilled)
                // We need to ensure that non-fulfilled cards are always shown unless they are 'fulfilled'
                // Let's adjust: We'll toggle the 'display' directly based on the 'fulfilled-hidden' class and checkbox state.
                if (card.classList.contains('fulfilled-hidden')) {
                     card.style.display = toggleBox.checked ? 'block' : 'none';
                }
            });
             // We need to ensure all non-fulfilled cards (except those initially hidden) are visible
             document.querySelectorAll('.order-card:not(.status-fulfilled)').forEach(card => {
                 card.style.display = 'block';
             });
        }

        // Add event listener
        toggleBox.addEventListener('change', toggleFulfilled);

        // Run on page load to set the initial state (hide fulfilled)
        document.addEventListener('DOMContentLoaded', () => {
             document.querySelectorAll('.fulfilled-hidden').forEach(card => {
                 card.style.display = 'none';
            });
        });
    </script>
</body>
</html>