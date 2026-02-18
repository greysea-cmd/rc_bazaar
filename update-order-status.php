<?php
// update-order-status.php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/Order.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    flash_message('Please log in to update order status.', 'error');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_message('Invalid request method.', 'error');
    redirect('my-orders.php');
}

$order_id = (int)($_POST['order_id'] ?? 0);
$new_status = $_POST['new_status'] ?? '';

if (empty($order_id) || empty($new_status)) {
    flash_message('Invalid order data.', 'error');
    redirect('my-orders.php');
}

$database = new Database();
$db = $database->getConnection();
$order_model = new Order($db);

$user_id = get_current_user_id();
$order_data = $order_model->getOrderById($order_id);

// Check if user is the seller of this order
if (!$order_data || $order_data['seller_id'] != $user_id) {
    flash_message('You are not authorized to update this order.', 'error');
    redirect('my-orders.php');
}

// Validate status transition
$allowed_statuses = ['confirmed', 'shipped', 'delivered'];
if (!in_array($new_status, $allowed_statuses)) {
    flash_message('Invalid status.', 'error');
    redirect('order-details.php?id=' . $order_id);
}

// Update order status
if ($order_model->updateOrderStatus($order_id, $new_status)) {
    flash_message('Order status updated successfully.', 'success');
} else {
    flash_message('Failed to update order status.', 'error');
}

redirect('order-details.php?id=' . $order_id);
?>