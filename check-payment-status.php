<?php
// check-payment-status.php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/Payment.php';
require_once 'models/Order.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    die(json_encode(['error' => 'Not logged in']));
}

if (!isset($_GET['order_id'])) {
    die(json_encode(['error' => 'No order ID']));
}

$order_id = (int)$_GET['order_id'];
$user_id = get_current_user_id();

$database = new Database();
$db = $database->getConnection();
$order_model = new Order($db);
$payment_model = new Payment($db);

// Verify order belongs to user
$order = $order_model->getOrderById($order_id);
if (!$order || ($order['buyer_id'] != $user_id && $order['seller_id'] != $user_id)) {
    die(json_encode(['error' => 'Unauthorized']));
}

// Get payment status
$payments = $payment_model->getOrderPayments($order_id);
$latest_payment = !empty($payments) ? $payments[0] : null;

header('Content-Type: application/json');
echo json_encode([
    'order_status' => $order['status'],
    'payment_status' => $latest_payment ? $latest_payment['status'] : 'none',
    'payment_method' => $order['payment_method'],
    'amount' => $order['total_amount'],
    'last_updated' => $order['updated_at']
]);
?>