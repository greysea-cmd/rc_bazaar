<?php
// process-esewa.php - Complete Payment Processing Page
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/Order.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!is_logged_in()) {
    flash_message('Please log in to proceed with payment.', 'error');
    redirect('login.php');
}

// Check if we have pending orders
if (!isset($_SESSION['pending_order_ids']) || empty($_SESSION['pending_order_ids'])) {
    flash_message('No pending orders found.', 'error');
    redirect('checkout.php');
}

// Get pending order details
$order_ids = $_SESSION['pending_order_ids'];
$total_amount = $_SESSION['order_total'] ?? 0;

if ($total_amount <= 0) {
    flash_message('Invalid order amount.', 'error');
    redirect('checkout.php');
}

// Generate unique transaction ID
$transaction_uuid = uniqid('TXN-');
$transaction_code = 'TXN-' . time();

// Format amount for eSewa (must have exactly 2 decimal places)
$amount = number_format(floatval($total_amount), 2, '.', '');

// Generate eSewa signature
$total_amount = $amount;

$signature = generate_esewa_signature(
    $total_amount,
    $transaction_uuid,
    ESEWA_MERCHANT_CODE
);

// Store transaction data in session
$_SESSION['esewa_transaction'] = [
    'transaction_uuid' => $transaction_uuid,
    'transaction_code' => $transaction_code,
    'amount' => $amount,
    'order_ids' => $order_ids,
    'timestamp' => time(),
    'signature' => $signature // Store for verification
];

// Log for debugging
error_log("eSewa Payment Initiated:");
error_log("Transaction UUID: {$transaction_uuid}");
error_log("Amount: {$amount}");
error_log("Signature: {$signature}");
error_log("Order IDs: " . implode(', ', $order_ids));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing eSewa Payment - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .payment-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .payment-header {
            margin-bottom: 30px;
        }

        .payment-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .payment-header p {
            color: #666;
            font-size: 16px;
        }

        .payment-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            color: #333;
            font-weight: 600;
        }

        .detail-value.amount {
            color: #28a745;
            font-size: 24px;
        }

        .esewa-logo {
            width: 150px;
            height: auto;
            margin: 20px 0;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .loading-spinner {
            display: inline-block;
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #55a854;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px 0;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .redirect-message {
            color: #666;
            margin: 20px 0;
            font-size: 14px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #55a854 0%, #3e8e41 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(85, 168, 84, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .hidden-form {
            display: none;
        }

        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 10px;
            margin-top: 20px;
            font-size: 12px;
            color: #856404;
            text-align: left;
            display: none; 
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h1><i class="fas fa-lock"></i> Secure Payment</h1>
            <p>You will be redirected to eSewa to complete your payment</p>
        </div>

        <div class="payment-details">
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars($transaction_code); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Order IDs:</span>
                <span class="detail-value"><?php echo htmlspecialchars(implode(', ', $order_ids)); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Total Amount:</span>
                <span class="detail-value amount">NPR <?php echo number_format($total_amount, 2); ?></span>
            </div>
        </div>

        <div style="color: #55a854; font-size: 24px; font-weight: bold; margin: 20px 0;">
            <i class="fas fa-wallet"></i> eSewa
        </div>

        <div class="loading-spinner"></div>

        <p class="redirect-message">
            <i class="fas fa-info-circle"></i>
            Please wait while we redirect you to eSewa...
        </p>

        <!-- eSewa Payment Form (auto-submits) -->
        <form id="esewaForm" action="<?php echo ESEWA_API_URL; ?>" method="POST" class="hidden-form">
            <input type="text" id="amount" name="amount" value="<?php echo $amount; ?>" required>
            <input type="text" id="tax_amount" name="tax_amount" value="0" required>
            <input type="text" id="total_amount" name="total_amount" value="<?php echo $amount; ?>" required>
            <input type="text" id="transaction_uuid" name="transaction_uuid" value="<?php echo $transaction_uuid; ?>" required>
            <input type="text" id="product_code" name="product_code" value="<?php echo ESEWA_MERCHANT_CODE; ?>" required>
            <input type="text" id="product_service_charge" name="product_service_charge" value="0" required>
            <input type="text" id="product_delivery_charge" name="product_delivery_charge" value="0" required>
            <input type="text" id="success_url" name="success_url" value="<?php echo ESEWA_SUCCESS_URL; ?>" required>
            <input type="text" id="failure_url" name="failure_url" value="<?php echo ESEWA_FAILURE_URL; ?>" required>
            <input type="text" id="signed_field_names" name="signed_field_names" value="total_amount,transaction_uuid,product_code" required>
            <input type="text" id="signature" name="signature" value="<?php echo $signature; ?>" required>
        </form>

        <div class="action-buttons">
            <a href="checkout.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Checkout
            </a>
            <button type="button" class="btn btn-primary" id="proceedBtn">
                <i class="fas fa-arrow-right"></i> Proceed to eSewa
            </button>
        </div>

        <!-- Debug info (remove in production) -->
        <div class="debug-info">
            <strong>Debug Info:</strong><br>
            Data String: total_amount=<?php echo $amount; ?>,transaction_uuid=<?php echo $transaction_uuid; ?>,product_code=<?php echo ESEWA_MERCHANT_CODE; ?><br>
            Signature: <?php echo $signature; ?>
        </div>
    </div>

    <script>
        // Auto-submit form after 3 seconds
        setTimeout(() => {
            document.getElementById('esewaForm').submit();
        }, 3000);

        // Manual submit button
        document.getElementById('proceedBtn').addEventListener('click', function() {
            document.getElementById('esewaForm').submit();
        });

        // Show debug info on Ctrl+Shift+D
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                const debug = document.querySelector('.debug-info');
                debug.style.display = debug.style.display === 'block' ? 'none' : 'block';
            }
        });
    </script>
</body>
</html>