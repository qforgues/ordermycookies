<?php
session_start();
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['0', 'admin'])) {
    header('Location: admin.html?auth=failed');
    exit;
}

require_once 'db_connect.php';
require_once 'send_email.php';

$stmt = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatPhone($number) {
    $digits = preg_replace('/\D/', '', $number);
    return preg_match('/^(\d{3})(\d{3})(\d{4})$/', $digits, $matches) 
        ? "($matches[1]) $matches[2]-$matches[3]" 
        : $number;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $id = (int) $_POST['order_id'];
    $action = $_POST['action'];

    $newStatus = match ($action) {
        'ready' => 'ready',
        'paid' => 'paid',
        'cancelled' => 'cancelled',
        default => null
    };

    if ($newStatus) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        if ($newStatus === 'ready') {
            $stmt = $pdo->prepare("SELECT email FROM orders WHERE id = ?");
            $stmt->execute([$id]);
            $email = $stmt->fetchColumn();

            $subject = "Your Courtney’s Cookies order is on its way!";
            $body = ' <html><body style="font-family: Quicksand, sans-serif; color: #3E2C1C; background-color: #FFF7ED; padding: 20px;">
                <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;padding:20px;box-shadow:0 0 10px rgba(0,0,0,0.05);">
                    <img src="https://i.postimg.cc/VsHp5Dcs/logo.png" style="max-width:150px;margin:auto;display:block;" alt="Courtney’s Cookies"/>
                    <h2 style="color:#6B4423;text-align:center;">Your cookies are on the way! 🍪</h2>
                    <p style="text-align:center;">Thank you for ordering with us. We hope you LOVE them.</p>
                    <p style="text-align:center;">Don’t forget to <a href="https://facebook.com/ordermycookies" target="_blank">like and share us on Facebook</a> and tell friends and family about <strong>OrderMyCookies.com</strong>.</p>
                    <p style="text-align:center;">We’re rolling out fun discounts and cookie surprises soon, so stay tuned!</p>
                    <p style="text-align:center;">&ndash; Courtney</p>
                </div></body></html>';

            sendCustomerEmail($email, $subject, $body);
        }
        header('Location: admin_orders.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Orders</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .order-card { border: 1px solid #ccc; padding: 20px; margin-bottom: 20px; border-radius: 8px; background-color: #fff; }
        .order-header { font-weight: bold; margin-bottom: 10px; font-size: 1.2em; }
        .order-items { margin: 10px 0; }
        .order-actions { display: flex; gap: 10px; }
        .order-actions form { flex: 1; }
        .order-actions button { width: 100%; padding: 10px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
        .new { background-color: #e0f7fa; }
        .ready { background-color: #fff3cd; }
        .paid { background-color: #d4edda; }
        .cancelled { background-color: #f8d7da; }
    </style>
</head>
<body>
<div class="container">
    <h1>Order Management</h1>
    <?php foreach ($orders as $order): ?>
        <div class="order-card <?= $order['status'] ?>">
            <div class="order-header"> <?= htmlspecialchars($order['full_name']) ?> </div>
            <div><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($order['email']) ?>"><?= htmlspecialchars($order['email']) ?></a></div>
            <div><strong>Phone:</strong> <a href="tel:<?= htmlspecialchars($order['phone']) ?>"><?= formatPhone($order['phone']) ?></a></div>
            <div class="order-items"><strong>Items:</strong> <?= nl2br(htmlspecialchars($order['items'])) ?></div>
            <?php if ($order['status'] !== 'paid'): ?>
                <div class="order-actions">
                    <?php if ($order['status'] === 'new'): ?>
                        <form method="post"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="ready"><button style="background-color:#FFD580;">Ready</button></form>
                        <form method="post"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="cancelled"><button style="background-color:#f8d7da;">Cancelled</button></form>
                    <?php elseif ($order['status'] === 'ready'): ?>
                        <form method="post"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="paid"><button style="background-color:#d4edda;">Paid</button></form>
                        <form method="post"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="cancelled"><button style="background-color:#f8d7da;">Cancelled</button></form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
