<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and has keymaster (0) or owner (1) role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 0 && $_SESSION['role'] !== 1)) {
    header('Location: admin.html?auth=failed'); // Redirect to login page if not authorized
    exit;
}

// Fetch current settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Error fetching admin settings: " . $e->getMessage());
    $settings['delivery_fee_amount'] = 'Error loading';
    // Set other messages to error or default as well
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Courtney's Caribbean Cookies</title>
    <link rel="stylesheet" href="style.css"> <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;700&family=Pacifico&display=swap" rel="stylesheet">
    <style>
        /* Specific admin panel styles */
        .admin-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .admin-container h1 {
            text-align: center;
            color: var(--primary-brown);
            font-family: 'Pacifico', cursive;
            margin-bottom: 30px;
        }
        .admin-form .form-group {
            margin-bottom: 20px;
        }
        .admin-form label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .admin-form input[type="text"],
        .admin-form input[type="number"],
        .admin-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--highlight);
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .admin-form textarea {
            min-height: 80px;
            resize: vertical;
        }
        .admin-form button {
            background: var(--primary-brown);
            color: white;
            font-size: 1.1em;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 15px;
            width: auto; /* Allow button to size to content */
            transition: background-color 0.2s ease;
        }
        .admin-form button:hover {
            background: #523119;
        }
        .logout-btn {
            background: #dc3545;
            float: right;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        .admin-alert {
            margin-top: 20px;
            padding: 10px;
            border-radius: 6px;
            display: none;
            text-align: center;
        }
        .admin-alert.show {
            display: block;
        }
        .admin-alert.success {
            background-color: #d4edda;
            color: #155724;
        }
        .admin-alert.error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>Admin Panel</h1>
        <h2><a href="admin_orders.php">View Orders</a></h2>
        <button class="logout-btn" onclick="logout()">Logout</button>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (Role: <?php
            if ($_SESSION['role'] === 0) echo 'Keymaster';
            else if ($_SESSION['role'] === 1) echo 'Owner';
            else echo 'Customer';
        ?>)</p>

        <form id="settingsForm" class="admin-form">
            <h2>Delivery & Payment Settings</h2>

            <div class="form-group">
                <label for="deliveryFee">Delivery Fee Amount:</label>
                <input type="number" id="deliveryFee" name="delivery_fee_amount" step="0.01" min="0"
                       value="<?php echo htmlspecialchars($settings['delivery_fee_amount'] ?? '2.00'); ?>" required>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="allow_shipping" name="allow_shipping">
                    Enable Shipping to Mainland PR
                </label>
            </div>


            <h3>Payment Method Messages:</h3>
            <div class="form-group">
                <label for="cashMessage">Cash Payment Message:</label>
                <textarea id="cashMessage" name="cash_payment_message"><?php echo htmlspecialchars($settings['cash_payment_message'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="athmovilMessage">ATH Móvil Payment Message:</label>
                <textarea id="athmovilMessage" name="athmovil_payment_message"><?php echo htmlspecialchars($settings['athmovil_payment_message'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="creditCardMessage">Credit Card Payment Message:</label>
                <textarea id="creditCardMessage" name="creditcard_payment_message"><?php echo htmlspecialchars($settings['creditcard_payment_message'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="venmoMessage">Venmo Payment Message:</label>
                <textarea id="venmoMessage" name="venmo_payment_message"><?php echo htmlspecialchars($settings['venmo_payment_message'] ?? ''); ?></textarea>
            </div>

            <button type="submit">Update Settings</button>
            <div id="adminAlert" class="admin-alert"></div>
        </form>
    </div>

    <script>
        document.getElementById('settingsForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const adminAlert = document.getElementById('adminAlert');
            adminAlert.classList.remove('show', 'success', 'error');
            adminAlert.textContent = '';

            const formData = new FormData(this);

            try {
                const response = await fetch('update_settings.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    adminAlert.classList.add('show', 'success');
                    adminAlert.textContent = result.message;
                } else {
                    adminAlert.classList.add('show', 'error');
                    adminAlert.textContent = result.message || 'An unknown error occurred.';
                }
            } catch (error) {
                console.error('Error updating settings:', error);
                adminAlert.classList.add('show', 'error');
                adminAlert.textContent = 'Network error: Could not connect to server.';
            }
        });

        document.getElementById('allow_shipping').checked = settings.allow_shipping == 1;

        function logout() {
            fetch('logout.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'admin.html'; // Redirect to login page
                    } else {
                        alert('Logout failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Logout error:', error);
                    alert('Logout failed due to network error.');
                });
        }
    </script>
</body>
</html>