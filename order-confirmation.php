<?php
// order-confirmation.php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/Order.php';
require_once 'models/User.php';
require_once 'models/Payment.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!is_logged_in()) {
    flash_message('Please log in to view order confirmation.', 'error');
    redirect('login.php');
}

// Check if we have order IDs or transaction data
if (!isset($_SESSION['order_ids']) && !isset($_SESSION['last_transaction'])) {
    flash_message('No order found.', 'error');
    redirect('dashboard.php');
}

$database = new Database();
$db = $database->getConnection();
$order_model = new Order($db);
$user_model = new User($db);
$payment_model = new Payment($db);

$user_id = get_current_user_id();
$user_info = $user_model->getUserById($user_id);

// Get orders from session or GET parameter
if (isset($_SESSION['order_ids'])) {
    $order_ids = $_SESSION['order_ids'];
    $from_session = true;
} elseif (isset($_GET['order_id'])) {
    $order_ids = [$_GET['order_id']];
    $from_session = false;
} else {
    flash_message('No order specified.', 'error');
    redirect('my-orders.php');
}

// Get transaction details
$transaction_details = null;
if (isset($_SESSION['last_transaction'])) {
    $transaction_details = $_SESSION['last_transaction'];
} elseif (isset($_SESSION['esewa_transaction'])) {
    $transaction_details = [
        'transaction_code' => $_SESSION['esewa_transaction']['transaction_code'],
        'amount' => $_SESSION['esewa_transaction']['amount'],
        'timestamp' => $_SESSION['esewa_transaction']['timestamp']
    ];
}

// Get orders - only get orders that belong to this user
$orders = [];
$total_amount = 0;

foreach ($order_ids as $order_id) {
    $order = $order_model->getOrderById($order_id);

    // Verify the order belongs to the current user
    if ($order && ($order['buyer_id'] == $user_id || $order['seller_id'] == $user_id)) {
        if (!empty($payments)) {
            $order['payments'] = $payments;
        }

        $orders[] = $order;
    }
}

// If no valid orders found
if (empty($orders)) {
    flash_message('No valid orders found.', 'error');
    redirect('my-orders.php');
}

// Clear session data if it came from session
if ($from_session) {
    unset($_SESSION['order_ids']);
    unset($_SESSION['last_transaction']);
    unset($_SESSION['esewa_transaction']);
}

// Check if payment was successful
$payment_status = 'pending';
$payment_method = 'Unknown';
$transaction_code = 'N/A';

if (
    isset($orders[0]['payments']) &&
    is_array($orders[0]['payments']) &&
    isset($orders[0]['payments'][0])
) {
    $latest_payment = $orders[0]['payments'][0];
    $payment_status = $latest_payment['status'];
    $transaction_code = $latest_payment['transaction_code'];

    // Determine payment method based on transaction code
    if (strpos($transaction_code, 'ESEWA-') === 0 || strpos($transaction_code, 'TXN-') === 0) {
        $payment_method = 'esewa';
    }
} elseif (is_array($transaction_details)) {
    $transaction_code = $transaction_details['transaction_code'] ?? 'N/A';
    $payment_method = 'esewa';
    $payment_status = 'completed';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f8f9fa;
            color: #000000;
            line-height: 1.6;
        }

        /* Navigation */
        .navbar {
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 16px 0;
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .navbar-brand {
            color: #ffffff;
            text-decoration: none;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .navbar-brand:hover {
            color: #ffffff;
        }

        .navbar-brand i {
            font-size: 24px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .confirmation-container {
            padding: 60px 0;
            max-width: 800px;
            margin: 0 auto;
        }

        .confirmation-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: white;
            font-size: 36px;
        }

        .confirmation-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 16px;
            color: #28a745;
        }

        .confirmation-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 32px;
        }

        .order-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 24px;
            margin: 32px 0;
            text-align: left;
        }

        .order-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .order-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .order-label {
            color: #666;
            font-weight: 500;
        }

        .order-value {
            color: #333;
            font-weight: 600;
        }

        .order-value.amount {
            color: #28a745;
            font-size: 24px;
        }

        .order-ids {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .order-id-badge {
            background: #e9ecef;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            color: #495057;
        }

        .payment-details {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .payment-label {
            color: #2e7d32;
            font-weight: 500;
        }

        .payment-value {
            color: #1b5e20;
            font-weight: 600;
        }

        .esewa-badge {
            background: #55a854;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .action-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 40px;
        }

        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #000000 0%, #2d2d2d 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            color: white;
        }

        .email-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
            color: #856404;
            font-size: 14px;
        }

        .print-receipt {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .print-receipt:hover {
            background: #138496;
        }

        .footer {
            background: #000000;
            color: white;
            padding: 40px 0;
            margin-top: 80px;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #333;
            margin-top: 40px;
        }

        @media print {

            .navbar,
            .action-buttons,
            .footer,
            .print-receipt {
                display: none !important;
            }

            .confirmation-container {
                padding: 0;
            }

            .confirmation-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }

        @media (max-width: 768px) {
            .confirmation-card {
                padding: 24px;
            }

            .confirmation-title {
                font-size: 24px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Flash Message */
        .flash-message {
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        .flash-success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: #155724;
        }

        .flash-error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: #721c24;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-book"></i>
                <?php echo htmlspecialchars(SITE_NAME); ?>
            </a>
            <div style="display: flex; align-items: center; gap: 20px;">
                <a href="dashboard.php" style="color: white; text-decoration: none;">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="my-orders.php" style="color: white; text-decoration: none;">
                    <i class="fas fa-shopping-cart"></i> My Orders
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="confirmation-container">
            <!-- Flash Messages -->
            <?php
            if (isset($_SESSION['flash_message'])) {
                $message = $_SESSION['flash_message']['message'];
                $type = $_SESSION['flash_message']['type'];

                $bg_color = $type === 'success' ? 'flash-success' : 'flash-error';
                echo "<div class='flash-message {$bg_color}'>{$message}</div>";
                unset($_SESSION['flash_message']);
            }
            ?>

            <div class="confirmation-card">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>

                <h1 class="confirmation-title">
                    <?php
                    if ($payment_status === 'completed') {
                        echo 'Payment Successful!';
                    } else {
                        echo count($orders) > 1 ? 'Orders Confirmed!' : 'Order Confirmed!';
                    }
                    ?>
                </h1>

                <p class="confirmation-message">
                    <?php if ($payment_status === 'completed'): ?>
                        <i class="fas fa-check-circle" style="color: #28a745; margin-right: 8px;"></i>
                        Your payment has been successfully processed.
                    <?php endif; ?>
                    Thank you for your purchase, <?php echo htmlspecialchars($user_info['first_name']); ?>!
                    Your <?php echo count($orders) > 1 ? 'orders have' : 'order has'; ?> been successfully placed.
                </p>

                <!-- Payment Details Section -->
                <?php if ($payment_method === 'esewa'): ?>
                    <div class="payment-details">
                        <h3 style="color: #2e7d32; margin-bottom: 16px;">
                            <i class="fas fa-credit-card"></i> Payment Information
                        </h3>

                        <div class="payment-row">
                            <span class="payment-label">Payment Method:</span>
                            <span class="esewa-badge">
                                <i class="fas fa-check-circle"></i> eSewa Payment
                            </span>
                        </div>

                        <div class="payment-row">
                            <span class="payment-label">Transaction ID:</span>
                            <span class="payment-value"><?php echo htmlspecialchars($transaction_code); ?></span>
                        </div>

                        <div class="payment-row">
                            <span class="payment-label">Payment Status:</span>
                            <span class="payment-value" style="color: #28a745;">
                                <i class="fas fa-check-circle"></i> Completed
                            </span>
                        </div>

                        <div class="payment-row">
                            <span class="payment-label">Payment Date:</span>
                            <span class="payment-value">
                                <?php
                                $payment_time = time();

                                if (is_array($transaction_details) && isset($transaction_details['timestamp'])) {
                                    $payment_time = $transaction_details['timestamp'];
                                }

                                echo date('F j, Y, g:i a', $payment_time);
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="order-details">
                    <h3 style="color: #333; margin-bottom: 16px;">
                        <i class="fas fa-shopping-bag"></i> Order Information
                    </h3>

                    <div class="order-row">
                        <span class="order-label">Order Date:</span>
                        <span class="order-value"><?php echo date('F j, Y, g:i a'); ?></span>
                    </div>

                    <div class="order-row">
                        <span class="order-label">Order <?php echo count($orders) > 1 ? 'Numbers' : 'Number'; ?>:</span>
                        <div class="order-ids">
                            <?php foreach ($orders as $order): ?>
                                <span class="order-id-badge">#<?php echo $order['id']; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="order-row">
                        <span class="order-label">Order Status:</span>
                        <span class="order-value" style="color: #28a745;">
                            <i class="fas fa-check-circle"></i>
                            <?php
                            if (!empty($orders)) {
                                $status = $orders[0]['status'];
                                echo ucwords(str_replace('_', ' ', $status));
                            }
                            ?>
                        </span>
                    </div>

                    <?php if (count($orders) > 1): ?>
                        <div class="order-row">
                            <span class="order-label">Number of Orders:</span>
                            <span class="order-value"><?php echo count($orders); ?> separate orders</span>
                        </div>
                    <?php endif; ?>

                    <div class="order-row">
                        <span class="order-label">Total Amount:</span>
                        <span class="order-value amount">NPR <?php echo number_format($total_amount, 2); ?></span>
                    </div>
                </div>

                <button onclick="window.print()" class="print-receipt">
                    <i class="fas fa-print"></i> Print Receipt
                </button>

                <div class="email-note">
                    <i class="fas fa-envelope"></i>
                    <?php if (count($orders) > 1): ?>
                        Order confirmation emails have been sent to <?php echo htmlspecialchars($user_info['email']); ?>.
                        Please check your inbox and spam folder.
                    <?php else: ?>
                        An order confirmation email has been sent to <?php echo htmlspecialchars($user_info['email']); ?>.
                        Please check your inbox and spam folder.
                    <?php endif; ?>
                </div>

                <div class="action-buttons">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="my-orders.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> View My Orders
                    </a>
                    <?php if (count($orders) === 1): ?>
                        <a href="order-details.php?id=<?php echo $orders[0]['id']; ?>" class="btn btn-success">
                            <i class="fas fa-eye"></i> View Order Details
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?>. All rights reserved.</p>
                <p style="margin-top: 10px; font-size: 14px; color: #aaa;">
                    For any queries, please contact our support team at support@<?php echo strtolower(str_replace(' ', '', SITE_NAME)); ?>.com
                </p>
            </div>
        </div>
    </footer>

    <script>
        // Auto-redirect after 15 seconds
        setTimeout(() => {
            window.location.href = 'my-orders.php';
        }, 15000);

        // Print receipt function
        function printReceipt() {
            window.print();
        }

        // Add receipt print event listener
        document.addEventListener('keydown', function(e) {
            // Ctrl+P for print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });

        // Save receipt as PDF (basic implementation)
        function saveAsPDF() {
            alert('To save as PDF, please use the print dialog and select "Save as PDF" as your printer.');
        }
    </script>
</body>

</html>