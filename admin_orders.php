<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 0 && $_SESSION['role'] !== 1)) {
    header('Location: admin.html?auth=failed');
    exit;
}

require_once 'db_connect.php';
require_once 'send_email.php';

function format_phone($phone) {
    $phone = preg_replace("/[^0-9]/", "", $phone);
    if (strlen($phone) == 10) {
        return "(" . substr($phone, 0, 3) . ") " . substr($phone, 3, 3) . "-" . substr($phone, 6);
    } else {
        return $phone;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $orderId = $_POST['order_id'];
    $action = $_POST['action'];

    $newStatus = match($action) {
        'ready'     => 'Ready',
        'paid'      => 'Paid',
        'cancelled' => 'Cancelled',
        default     => null
    };

    if ($newStatus) {
        $stmt = $pdo->prepare("UPDATE cookie_orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);

        $stmt = $pdo->prepare("SELECT email FROM cookie_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $email = $stmt->fetchColumn();

        if ($email) {
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
            } elseif ($newStatus === 'Paid') {
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

    header("Location: admin_orders.php");
    exit;
}

$stmt = $pdo->query("SELECT * FROM cookie_orders ORDER BY order_date DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Orders</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
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
            transition: background-color 0.3s ease;
        }
        .status-ready { background-color: #fffbe0; }
        .status-paid { background-color: #e0ffe0; }
        .status-cancelled { background-color: #ffe0e0; }
        .status-fulfilled { background-color: #e0e8ff; } /* Kept in case you use it later */

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start; /* Align to top */
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .order-name {
            font-weight: bold;
            font-size: 1.1em;
            color: var(--primary-brown);
            flex-grow: 1; /* Allow name to grow */
        }
        .order-status-display {
            font-weight: bold;
            font-size: 1em;
            text-align: right;
            color: var(--primary-brown);
            flex-shrink: 0;
            padding-left: 10px;
        }
        .button-container {
            width: 100%;
            display: flex;
            justify-content: space-between;
            gap: 2%;
            margin-top: 10px; /* Space above buttons */
        }
        .button-container form {
            width: 49%;
            display: inline-block;
        }
        .order-toggle {
            background-color: var(--accent-gold);
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 6px;
            width: 100%;
            box-sizing: border-box;
            font-size: 0.95em;
            transition: background-color 0.2s ease;
        }
        .order-toggle:hover { opacity: 0.9; }
        .order-toggle.ready { background-color: #f0ad4e; }
        .order-toggle.paid { background-color: #5cb85c; }
        .order-toggle.cancel { background-color: #d9534f; }

        .filter-controls { /* Container for checkboxes */
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .toggle-filter {
            display: inline-flex; /* Use inline-flex for alignment */
            align-items: center;
            margin-right: 20px; /* Space between checkboxes */
            font-weight: bold;
            font-size: 1em;
            color: var(--primary-brown);
        }
        .toggle-filter input {
            margin-right: 8px;
            transform: scale(1.3);
        }

        .order-card p {
            margin: 5px 0;
            line-height: 1.5;
        }
        .order-card a {
            color: var(--accent-gold);
            text-decoration: none;
        }
        .order-card a:hover { text-decoration: underline; }

        /* Compact View for Paid/Cancelled */
        .compact-view .order-header {
             margin-bottom: 5px;
             align-items: center; /* Center name and status */
        }
         .compact-view .order-name {
             margin-bottom: 0;
         }
        .compact-view p {
            margin: 3px 0;
            line-height: 1.3;
            font-size: 0.95em;
        }
    </style>
</head>
<body>
    <div class="orders-container">
        <h1>Orders Admin</h1>
        <div class="filter-controls">
            <label class="toggle-filter">
                <input type="checkbox" id="showPaid"> Show Paid Orders
            </label>
            <label class="toggle-filter">
                <input type="checkbox" id="showCancelled"> Show Cancelled Orders
            </label>
        </div>

        <?php foreach ($orders as $order): ?>
            <?php
                $status = strtolower($order['status'] ?? 'pending'); // Default to pending if null
                $statusClass = match($status) {
                    'ready'     => 'status-ready',
                    'paid'      => 'status-paid',
                    'cancelled' => 'status-cancelled',
                    'fulfilled' => 'status-fulfilled',
                    default     => 'status-pending'
                };

                $itemSummary = [];
                $flavors = [
                    'chocolate_chip_quantity', 'peanut_butter_quantity',
                    'oreomg_quantity', 'snickerdoodle_quantity', 'maplebacon_quantity'
                ];
                foreach ($flavors as $flavor) {
                    if (!empty($order[$flavor]) && $order[$flavor] > 0) {
                        $label = ucwords(str_replace(['_', ' quantity'], [' ', ''], $flavor));
                        $itemSummary[] = "$label: {$order[$flavor]}";
                    }
                }
                if (empty($itemSummary)) {
                    $itemSummary[] = "No items listed"; // Handle cases with no items
                }

                $isPaid = $status === 'paid';
                $isCancelled = $status === 'cancelled';
                $isFulfilled = $status === 'fulfilled'; // Keep for future/consistency
                $canTakeAction = !$isPaid && !$isCancelled && !$isFulfilled;
                $isCompact = $isPaid || $isCancelled || $isFulfilled;
                $compactClass = $isCompact ? 'compact-view' : '';
                $formattedPhone = format_phone($order['phone']);
            ?>
            <div class="order-card <?= $statusClass ?> <?= $compactClass ?>">
                <div class="order-header">
                    <div class="order-name"><?= htmlspecialchars($order['full_name']) ?></div>
                    <?php if (!$canTakeAction): ?>
                         <div class="order-status-display">Status: <?= htmlspecialchars(ucwords($order['status'])) ?></div>
                    <?php endif; ?>
                </div>

                <p><strong>Items:</strong> <?= implode(', ', $itemSummary) ?></p>
                <p><strong>Total | Method:</strong> $<?= htmlspecialchars(number_format($order['total_amount'], 2)) ?> | <?= htmlspecialchars($order['payment_method']) ?></p>
                <p>
                    <strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($order['email']) ?>"><?= htmlspecialchars($order['email']) ?></a> |
                    <strong>Phone:</strong> <a href="tel:<?= preg_replace("/[^0-9]/", "", $order['phone']) ?>"><?= $formattedPhone ?></a>
                </p>
                <p><strong>Delivery:</strong> <?= htmlspecialchars($order['delivery_method']) ?> @ <?= htmlspecialchars($order['pickup_time']) ?></p>

                 <?php if ($canTakeAction): ?>
                    <div class="button-container">
                        <?php if ($status === 'ready'): ?>
                            <form method="post">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <input type="hidden" name="action" value="paid">
                                <button class="order-toggle paid" type="submit">Mark as Paid</button>
                            </form>
                        <?php else: // New/Pending ?>
                            <form method="post">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <input type="hidden" name="action" value="ready">
                                <button class="order-toggle ready" type="submit">Mark as Ready</button>
                            </form>
                        <?php endif; ?>
                         <form method="post">
                             <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                             <input type="hidden" name="action" value="cancelled">
                             <button class="order-toggle cancel" type="submit">Cancel Order</button>
                         </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const showPaidBox = document.getElementById('showPaid');
            const showCancelledBox = document.getElementById('showCancelled');
            const orderCards = document.querySelectorAll('.order-card');

            function filterOrders() {
                const showPaid = showPaidBox.checked;
                const showCancelled = showCancelledBox.checked;

                orderCards.forEach(card => {
                    const isPaid = card.classList.contains('status-paid') || card.classList.contains('status-fulfilled'); // Include fulfilled with paid
                    const isCancelled = card.classList.contains('status-cancelled');

                    if (isPaid) {
                        card.style.display = showPaid ? 'block' : 'none';
                    } else if (isCancelled) {
                        card.style.display = showCancelled ? 'block' : 'none';
                    } else {
                        card.style.display = 'block'; // Always show others (New/Ready/Pending)
                    }
                });
            }

            // Add event listeners
            showPaidBox.addEventListener('change', filterOrders);
            showCancelledBox.addEventListener('change', filterOrders);

            // Run on page load to set the initial state (hide paid & cancelled)
            filterOrders();
        });
    </script>
</body>
</html>