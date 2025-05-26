<?php
session_start();
require_once 'db_connect.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin.html?auth=failed");
    exit;
}

// Toggle status handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $stmt = $pdo->prepare("SELECT status FROM cookie_orders WHERE id = ?");
    $stmt->execute([$_POST['order_id']]);
    $currentStatus = $stmt->fetchColumn();

    $newStatus = ($currentStatus === 'Fulfilled') ? 'New' : 'Fulfilled';
    $stmt = $pdo->prepare("UPDATE cookie_orders SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $_POST['order_id']]);
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
        .fulfilled {
            background-color: #e7f9e7;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-toggle {
            background-color: var(--accent-gold);
            border: none;
            padding: 6px 10px;
            cursor: pointer;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="orders-container">
        <h1>Orders Admin</h1>
        <label><input type="checkbox" id="showFulfilled"> Show Fulfilled Orders</label>

        <?php foreach ($orders as $order): ?>
            <?php
                $isFulfilled = $order['status'] === 'Fulfilled';
                $itemSummary = [];
                foreach (['chocolate_chip_quantity', 'peanut_butter_quantity', 'oreomg_quantity', 'snickerdoodle_quantity', 'maplebacon_quantity'] as $flavor) {
                    if ($order[$flavor] > 0) {
                        $label = ucwords(str_replace('_quantity', '', str_replace('_', ' ', $flavor)));
                        $itemSummary[] = "{$label}: {$order[$flavor]}";
                    }
                }
            ?>
            <div class="order-card<?= $isFulfilled ? ' fulfilled fulfilled-hidden' : '' ?>">
                <div class="order-header">
                    <strong>ORD-<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?> — <?= htmlspecialchars($order['full_name']) ?></strong>
                    <form method="post" onsubmit="return confirm('Change fulfillment status?')">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <button class="order-toggle" type="submit">
                            <?= $isFulfilled ? 'Mark Unfulfilled' : 'Mark Fulfilled' ?>
                        </button>
                    </form>
                </div>
                <p><strong>Items:</strong> <?= implode(', ', $itemSummary) ?></p>
                <p><strong>Total:</strong> <?= htmlspecialchars($order['total_amount']) ?> | <strong>Method:</strong> <?= $order['payment_method'] ?></p>
                <p><strong>Status:</strong> <?= $order['status'] ?></p>
                <p><strong>Pickup/Delivery:</strong> <?= $order['delivery_method'] ?> @ <?= $order['pickup_time'] ?></p>
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
