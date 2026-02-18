<?php
// clear-payment-session.php
require_once 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear payment-related session data
unset($_SESSION['payment_success']);
unset($_SESSION['esewa_transaction']);
unset($_SESSION['pending_order_ids']);
unset($_SESSION['order_total']);

echo json_encode(['success' => true]);
?>