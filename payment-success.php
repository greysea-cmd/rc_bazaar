<?php
// payment-success.php - Payment Confirmation Page
require_once 'config/config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

// Check if we have success data
if (!isset($_SESSION['payment_success'])) {
    redirect('my-orders.php');
}

$success_data = $_SESSION['payment_success'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: pop 0.5s ease;
        }

        .success-icon i {
            color: white;
            font-size: 48px;
        }

        @keyframes pop {
            0% { transform: scale(0); }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .success-title {
            color: #28a745;
            font-size: 32px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .success-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .order-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-item:last-child {
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

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 40px;
        }

        .btn {
            flex: 1;
            padding: 16px 24px;
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
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: white;
            color: #28a745;
            border: 2px solid #28a745;
        }

        .btn-outline:hover {
            background: #28a745;
            color: white;
        }

        .note {
            margin-top: 25px;
            color: #666;
            font-size: 14px;
            font-style: italic;
        }

        @media (max-width: 576px) {
            .success-container {
                padding: 30px 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .success-title {
                font-size: 24px;
            }
            
            .success-message {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1 class="success-title">Payment Successful!</h1>
        
        <p class="success-message">
            Thank you for your purchase. Your payment has been processed successfully.
        </p>
        
        <div class="order-details">
            <div class="detail-item">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars($success_data['transaction_code']); ?></span>
            </div>
            
            <?php if (!empty($success_data['reference_id'])): ?>
            <div class="detail-item">
                <span class="detail-label">eSewa Reference:</span>
                <span class="detail-value"><?php echo htmlspecialchars($success_data['reference_id']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="detail-item">
                <span class="detail-label">Order ID(s):</span>
                <span class="detail-value">
                    <?php 
                    if (count($success_data['order_ids']) === 1) {
                        echo 'Order #' . htmlspecialchars($success_data['order_ids'][0]);
                    } else {
                        echo count($success_data['order_ids']) . ' orders';
                    }
                    ?>
                </span>
            </div>
            
            <div class="detail-item">
                <span class="detail-label">Total Paid:</span>
                <span class="detail-value amount">NPR <?php echo number_format(floatval($success_data['amount']), 2); ?></span>
            </div>
            
            <div class="detail-item">
                <span class="detail-label">Payment Date:</span>
                <span class="detail-value"><?php echo date('F j, Y, g:i a', $success_data['timestamp']); ?></span>
            </div>
        </div>
        
        <div class="action-buttons">
            <?php if (count($success_data['order_ids']) === 1): ?>
                <a href="order-details.php?id=<?php echo $success_data['order_ids'][0]; ?>" class="btn btn-primary">
                    <i class="fas fa-eye"></i> View Order Details
                </a>
            <?php else: ?>
                <a href="my-orders.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> View All Orders
                </a>
            <?php endif; ?>
            
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Continue Shopping
            </a>
        </div>
        
        <p class="note">
            <i class="fas fa-info-circle"></i>
            A confirmation email has been sent to your registered email address.
            You can also view your orders in the "My Orders" section.
        </p>
    </div>

    <script>
        // Clear success data after showing
        setTimeout(() => {
            // Optionally clear session data via AJAX
            fetch('clear-payment-session.php')
                .catch(err => console.log('Session cleanup failed'));
        }, 5000);
    </script>
</body>
</html>
<?php
// Clear session data after displaying
unset($_SESSION['payment_success']);
?>