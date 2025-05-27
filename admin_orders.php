<?php
session_start();
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['0', 'admin'])) {
    header('Location: admin.html?auth=failed');
    exit;
}

require_once 'db_connect.php';
require_once 'send_email.php';

$stmt = $pdo->query("SELECT * FROM cookie_orders ORDER BY order_date DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatPhone($number) {
    $digits = preg_replace('/\D/', '', $number);
    return preg_match('/^(\d{3})(\d{3})(\d{4})$/', $digits, $matches) 
        ? "($matches[1]) $matches[2]-$matches[3]" 
        : $number;
}

function getItemSummary($order) {
    $items = [];
    if ($order['chocolate_chip_quantity'] > 0) {
        $items[] = $order['chocolate_chip_quantity'] . ' x Chocolate Chip';
    }
    if ($order['peanut_butter_quantity'] > 0) {
        $items[] = $order['peanut_butter_quantity'] . ' x Peanut Butter';
    }
    if ($order['oreomg_quantity'] > 0) {
        $items[] = $order['oreomg_quantity'] . ' x Ore-OMG';
    }
    if ($order['snickerdoodle_quantity'] > 0) {
        $items[] = $order['snickerdoodle_quantity'] . ' x Snickerdoodle';
    }
    if (!empty($order['maplebacon_quantity']) && $order['maplebacon_quantity'] > 0) {
        $items[] = $order['maplebacon_quantity'] . ' x Maple Bacon';
    }
    return implode(", ", $items);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $id = (int) $_POST['order_id'];
    $action = $_POST['action'];

    if ($action === 'ready') $newStatus = 'Ready';
    elseif ($action === 'paid') $newStatus = 'Paid';
    elseif ($action === 'cancelled') $newStatus = 'Cancelled';
    else $newStatus = null;

    if ($newStatus) {
        $stmt = $pdo->prepare("UPDATE cookie_orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        if ($newStatus === 'Ready') {
            $stmt = $pdo->prepare("SELECT email FROM cookie_orders WHERE id = ?");
            $stmt->execute([$id]);
            $email = $stmt->fetchColumn();

            $subject = "Your Courtneys Cookies order is on its way!";
            $body = '<html><body style="font-family: Quicksand, sans-serif; color: #3E2C1C; background-color: #FFF7ED; padding: 20px;">
                <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:10px;padding:20px;box-shadow:0 0 10px rgba(0,0,0,0.05);">
                    <img src="https://i.postimg.cc/VsHp5Dcs/logo.png" style="max-width:150px;margin:auto;display:block;" alt="Courtneys Cookies"/>
                    <h2 style="color:#6B4423;text-align:center;">Your cookies are on the way! 🍪</h2>
                    <p style="text-align:center;">Thank you for ordering with us. We hope you LOVE them.</p>
                    <p style="text-align:center;">Dont forget to <a href="https://facebook.com/ordermycookies" target="_blank">like and share us on Facebook</a> and tell friends and family about <strong>OrderMyCookies.com</strong>.</p>
                    <p style="text-align:center;">Were rolling out fun discounts and cookie surprises soon, so stay tuned!</p>
                    <p style="text-align:center;">- Courtney</p>
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
    <title>Admin Orders</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>All Orders</h1>
        <?php foreach ($orders as $order): ?>
            <div class="order-card <?= $order['status'] ?>">
                <p><strong>Name:</strong> <?= htmlspecialchars($order['full_name']) ?></p>
                <p><strong>Phone:</strong> <a href="tel:<?= htmlspecialchars($order['phone']) ?>"><?= formatPhone($order['phone']) ?></a></p>
                <p><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($order['email']) ?>"><?= htmlspecialchars($order['email']) ?></a></p>
                <p><strong>Address:</strong> <?= htmlspecialchars($order['street']) ?>, <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> <?= htmlspecialchars($order['zip']) ?></p>
                <p><strong>Delivery Method:</strong> <?= htmlspecialchars($order['delivery_method']) ?> | <strong>Payment:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
                <p><strong>Pickup Time:</strong> <?= htmlspecialchars($order['pickup_time']) ?></p>
                <p><strong>Items:</strong> <?= htmlspecialchars(getItemSummary($order)) ?></p>
                <p><strong>Total:</strong> $<?= htmlspecialchars($order['total_amount']) ?> + $<?= htmlspecialchars($order['delivery_fee']) ?> delivery</p>

                <?php if ($order['status'] !== 'Paid'): ?>
                    <form method="post" style="display:inline-block;">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="action" value="<?= $order['status'] === 'New' ? 'ready' : 'paid' ?>">
                        <button><?= $order['status'] === 'New' ? 'Mark Ready' : 'Mark Paid' ?></button>
                    </form>
                    <form method="post" style="display:inline-block;">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="action" value="cancelled">
                        <button>Cancel</button>
                    </form>
                <?php endif; ?>

                <hr>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>