<?php
// esewa-failure.php - Complete Failure Handler
require_once 'config/config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get failure reason from eSewa
$failure_reason = $_GET['reason'] ?? 'Payment cancelled or failed';
$transaction_uuid = $_GET['transaction_uuid'] ?? '';

// Log the failure
error_log("eSewa Payment Failed:");
error_log("Reason: {$failure_reason}");
error_log("Transaction UUID: {$transaction_uuid}");

// Update payment status to failed if we have transaction_uuid
if (!empty($transaction_uuid)) {
    try {
        require_once 'config/database.php';
        require_once 'models/Payment.php';
        
        $database = new Database();
        $db = $database->getConnection();
        $payment_model = new Payment($db);
        
        $payment_model->updatePaymentStatus($transaction_uuid, 'failed');
        
        error_log("Updated payment status to failed for: {$transaction_uuid}");
    } catch (Exception $e) {
        error_log("Failed to update payment status: " . $e->getMessage());
    }
}

// Clear session data
unset($_SESSION['esewa_transaction']);
unset($_SESSION['pending_order_ids']);
unset($_SESSION['order_total']);

// Set appropriate message
$message = 'Payment was cancelled or failed.';
if ($failure_reason === 'user_cancel') {
    $message = 'Payment was cancelled by user.';
} elseif ($failure_reason === 'timeout') {
    $message = 'Payment timed out. Please try again.';
} elseif (!empty($failure_reason)) {
    $message = 'Payment failed: ' . htmlspecialchars($failure_reason);
}

flash_message($message, 'error');

// Redirect back to checkout
redirect('checkout.php');
?>