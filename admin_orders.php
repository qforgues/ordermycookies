<?php
session_start();
echo '<pre>';
print_r($_SESSION);
echo '</pre>';
exit;

session_start();
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'keymaster'])) {
    header('Location: admin.html?auth=failed');
    exit;
}

require_once 'db_connect.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $orderId = $_POST['order_id'];
    $action = $_POST['action'];

    $newStatus = match($action) {
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
        default => null
    };

    if ($newStatus) {
        $stmt = $pdo->prepare("UPDATE cookie_orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
    }

    header("Location: admin_orders.php"); // Prevent form resubmit/white screen
    exit;
}

// Fetch all orders
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
        }
        .status-paid { background-color: #e0ffe0; }
        .status-fulfilled { background-color: #fffbe0; }
        .status-cancelled { background-color: #ffe0e0; }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .order-toggle {
            background-color: var(--accent-gold);
            border: none;
            padding: 6px 10px;
            margin-left: 5px;
            cursor: pointer;
            border-radius: 6px;
        }
        .order-toggle.cancel {
            background-color: #e67c7c;
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
                $statusClass = match($status) {
                    'paid' => 'status-paid',
                    'fulfilled' => 'status-fulfilled',
                    'cancelled' => 'status-cancelled',
                    default => ''
                };

                $itemSummary = [];
                foreach (['chocolate_chip_quantity', 'peanut_butter_quantity', 'oreomg_quantity', 'snickerdoodle_quantity', 'maplebacon_quantity'] as $flavor) {
                    if ($order[$flavor] > 0) {
                        $label = ucwords(str_replace('_', ' ', str_replace('_quantity', '', $flavor)));
                        $itemSummary[] = "{$label}: {$order[$flavor]}";
                    }
                }

                $isFulfilled = $status === 'fulfilled';
            ?>
            <div class="order-card <?= $statusClass ?> <?= $isFulfilled ? 'fulfilled-hidden' : '' ?>">
                <div class="order-header">
                    <strong>ORD-<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?> — <?= htmlspecialchars($order['full_name']) ?></strong>
                    <div>
                        <?php if (!$isFulfilled): ?>
                            <?php if ($status !== 'paid'): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="action" value="paid">
                                    <button class="order-toggle" type="submit">Mark as Paid</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <input type="hidden" name="action" value="cancelled">
                                <button class="order-toggle cancel" type="submit">Cancel Order</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <p><strong>Items:</strong> <?= implode(', ', $itemSummary) ?></p>
                <p><strong>Total | Method:</strong> <?= htmlspecialchars($order['total_amount']) ?> | <?= $order['payment_method'] ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?> | <strong>Phone:</strong> <?= htmlspecialchars($order['phone']) ?></p>
                <p><strong>Delivery:</strong> <?= $order['delivery_method'] ?> @ <?= $order['pickup_time'] ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        const toggleBox = document.getElementById('showFulfilled');
        toggleBox.addEventListener('change', () => {
            document.querySelectorAll('.fulfilled-hidden').forEach(card => {
                card.style.display = toggleBox.checked ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
