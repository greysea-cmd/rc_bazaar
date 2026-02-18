<?php
// esewa-success.php - SIMPLIFIED VERSION
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
    flash_message('Please log in to view payment status.', 'error');
    redirect('login.php');
}

// Get eSewa response data
$status = $_GET['status'] ?? '';
$transaction_uuid = $_GET['transaction_uuid'] ?? '';
$transaction_code = $_GET['transaction_code'] ?? '';
$total_amount = $_GET['total_amount'] ?? '';
$product_code = $_GET['product_code'] ?? '';
$signature = $_GET['signature'] ?? '';
$reference_id = $_GET['reference_id'] ?? '';

// Log the response for debugging
error_log("eSewa Success Response:");
error_log(print_r($_GET, true));

// Check for required parameters
if (empty($status) || empty($transaction_uuid) || empty($total_amount)) {
    flash_message('Invalid payment response from eSewa.', 'error');
    redirect('checkout.php');
}

// Verify signature
$verification_data = [
    'total_amount' => $total_amount,
    'transaction_uuid' => $transaction_uuid,
    'product_code' => $product_code
];

$is_signature_valid = verify_esewa_signature($verification_data, $signature);

if (!$is_signature_valid) {
    error_log("Invalid eSewa signature!");
    flash_message('Payment verification failed: Invalid signature.', 'error');
    redirect('checkout.php');
}

// Check if transaction is complete
if ($status !== 'COMPLETE') {
    flash_message('Payment was not completed. Status: ' . $status, 'error');
    redirect('checkout.php');
}

// Retrieve stored transaction data from session
if (!isset($_SESSION['esewa_transaction'])) {
    // Try to find transaction in database using Payment model
    try {
        $database = new Database();
        $db = $database->getConnection();
        $payment_model = new Payment($db);
        
        // Use Payment model's method instead
        $transaction_data = $payment_model->getPaymentByTransactionId($transaction_uuid);
        
        if (!$transaction_data) {
            flash_message('Payment session expired or transaction not found.', 'error');
            redirect('my-orders.php');
        }
        
        // Get order IDs from payment records
        $order_ids_query = "SELECT order_id FROM payments 
                           WHERE transaction_uuid = :transaction_uuid";
        $stmt = $db->prepare($order_ids_query);
        $stmt->bindParam(':transaction_uuid', $transaction_uuid);
        $stmt->execute();
        $order_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $order_ids = array_column($order_records, 'order_id');
        
        $stored_transaction = [
            'transaction_uuid' => $transaction_data['transaction_uuid'],
            'transaction_code' => $transaction_data['transaction_code'],
            'amount' => $transaction_data['amount'],
            'order_ids' => $order_ids,
            'timestamp' => strtotime($transaction_data['created_at'])
        ];
    } catch (Exception $e) {
        error_log("Error retrieving transaction: " . $e->getMessage());
        flash_message('Unable to retrieve transaction details.', 'error');
        redirect('checkout.php');
    }
} else {
    $stored_transaction = $_SESSION['esewa_transaction'];
    
    // Verify transaction matches
    if ($stored_transaction['transaction_uuid'] !== $transaction_uuid) {
        flash_message('Transaction verification failed.', 'error');
        redirect('checkout.php');
    }
    
    // Verify amount matches
    $stored_amount = floatval($stored_transaction['amount']);
    $received_amount = floatval($total_amount);
    
    if (abs($stored_amount - $received_amount) > 0.01) {
        error_log("Amount mismatch: Stored={$stored_amount}, Received={$received_amount}");
        flash_message('Transaction amount mismatch.', 'error');
        redirect('checkout.php');
    }
}

// Process successful payment
try {
    $database = new Database();
    $db = $database->getConnection();
    $order_model = new Order($db);
    $payment_model = new Payment($db);
    
    // Update all pending orders
    foreach ($stored_transaction['order_ids'] as $order_id) {
        try {
            // Update order status to 'paid' or 'completed'
            // First, check if order exists
            $check_query = "SELECT id FROM orders WHERE id = :order_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':order_id', $order_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                // Update order status
                $update_query = "UPDATE orders SET 
                                status = 'paid', 
                                payment_status = 'completed',
                                updated_at = NOW()
                                WHERE id = :order_id";
                
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':order_id', $order_id);
                $update_stmt->execute();
                
                error_log("Updated order {$order_id} to paid status");
            }
            
            // Check if payment record already exists
            $existing_payment = $payment_model->getPaymentByTransactionId($transaction_uuid);
            
            if (!$existing_payment) {
                // Create new payment record
                $payment_model->createPaymentRecord(
                    $order_id,
                    $transaction_uuid,
                    $stored_transaction['transaction_code'] ?? 'TXN-' . time(),
                    $total_amount
                );
                error_log("Created payment record for order {$order_id}");
            }
            
            // Update payment with eSewa reference ID if available
            if (!empty($reference_id)) {
                $payment_model->updatePaymentReference($transaction_uuid, $reference_id);
            }
            
            // Update payment status
            $payment_model->updatePaymentStatus($transaction_uuid, 'completed');
            
        } catch (Exception $e) {
            error_log("Error processing order {$order_id}: " . $e->getMessage());
            // Continue with other orders even if one fails
        }
    }
    
    // Clear session data
    unset($_SESSION['esewa_transaction']);
    unset($_SESSION['pending_order_ids']);
    unset($_SESSION['order_total']);
    
    // Store success data for confirmation page
    $_SESSION['payment_success'] = [
        'order_ids' => $stored_transaction['order_ids'],
        'transaction_code' => $stored_transaction['transaction_code'] ?? 'TXN-' . time(),
        'amount' => $total_amount,
        'reference_id' => $reference_id,
        'timestamp' => time()
    ];
    
    // Send confirmation email (if function exists)
    if (isset($_SESSION['user_email']) && function_exists('send_order_confirmation_email')) {
        send_order_confirmation_email(
            $_SESSION['user_email'],
            $stored_transaction['order_ids'],
            $stored_transaction['transaction_code'] ?? 'TXN-' . time()
        );
    }
    
    // Log successful payment
    error_log("PAYMENT SUCCESSFUL - Transaction: {$transaction_uuid}, Amount: {$total_amount}");
    
    // Redirect to confirmation page
    if (count($stored_transaction['order_ids']) === 1) {
        redirect('order-details.php?id=' . $stored_transaction['order_ids'][0]);
    } else {
        redirect('payment-success.php');
    }
    
} catch (Exception $e) {
    error_log("Error processing payment: " . $e->getMessage());
    
    // Update payment status to failed
    if (isset($payment_model) && isset($transaction_uuid)) {
        try {
            $payment_model->updatePaymentStatus($transaction_uuid, 'failed');
        } catch (Exception $e2) {
            error_log("Failed to update payment status: " . $e2->getMessage());
        }
    }
    
    flash_message('Payment processed but there was an error updating orders. Please contact support.', 'error');
    redirect('checkout.php');
}
?>