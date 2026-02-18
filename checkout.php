<?php
// checkout.php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/Cart.php';
require_once 'models/Order.php';
require_once 'models/Book.php';
require_once 'models/User.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!is_logged_in()) {
    flash_message('Please log in to proceed to checkout.', 'error');
    redirect('login.php?redirect=checkout.php');
}

$database = new Database();
$db = $database->getConnection();
$cart_model = new Cart($db);
$order_model = new Order($db);
$book_model = new Book($db);
$user_model = new User($db);

$user_id = get_current_user_id();

// Get cart items
$cart_items = $cart_model->getUserCart($user_id);
if (empty($cart_items)) {
    flash_message('Your cart is empty. Please add items before checkout.', 'error');
    redirect('my-cart.php');
}

// User info
$user_info = $user_model->getUserById($user_id);

// Totals
$subtotal = 0;
$shipping_fee = 5.00;
$cart_count = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $cart_count += $item['quantity'];
}
$total = $subtotal + $shipping_fee;

// Handle POST form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_address = filter_input(INPUT_POST, 'shipping_address', FILTER_SANITIZE_SPECIAL_CHARS);
    // $billing_address = filter_input(INPUT_POST, 'billing_address', FILTER_SANITIZE_SPECIAL_CHARS) ?? $shipping_address;
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS);

    $errors = [];
    if (empty($shipping_address)) $errors[] = 'Shipping address is required.';
    if (empty($payment_method)) $errors[] = 'Please select a payment method.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    elseif (!preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) $errors[] = 'Please enter a valid phone number.';

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Group cart items by seller
            $orders_by_seller = [];
            foreach ($cart_items as $item) {
                $seller_id = $item['seller_id'];
                if (!isset($orders_by_seller[$seller_id])) {
                    $orders_by_seller[$seller_id] = ['items' => [], 'subtotal' => 0];
                }
                $orders_by_seller[$seller_id]['items'][] = $item;
                $orders_by_seller[$seller_id]['subtotal'] += $item['price'] * $item['quantity'];
            }

            $order_ids = [];
            foreach ($orders_by_seller as $seller_id => $seller_order) {
                $seller_subtotal = $seller_order['subtotal'];
                $seller_shipping = $shipping_fee / count($orders_by_seller);
                $seller_total = $seller_subtotal + $seller_shipping;

                // FIXED: Make sure all required fields are present
                $first_book_id = $seller_order['items'][0]['book_id'];

                $order_data = [
                    'buyer_id' => $user_id,
                    'seller_id' => $seller_id,
                    'book_id' => $first_book_id, // Add this line
                    'shipping_address' => $shipping_address,
                    'payment_method' => $payment_method,
                    'notes' => $notes,
                    'subtotal' => $seller_subtotal,
                    'shipping_fee' => $seller_shipping,
                    'total_amount' => $seller_total,
                    'status' => $payment_method === 'cod' ? 'pending' : 'pending_payment',
                    'phone' => $phone
                ];

                // Debug: Log order data
                error_log("Creating order with data: " . print_r($order_data, true));

                $order_id = $order_model->createOrder($order_data);
                if (!$order_id) {
                    error_log("Failed to create order. Error info: " . print_r($db->errorInfo(), true));
                    throw new Exception("Failed to create order for seller ID: {$seller_id}");
                }
                $order_ids[] = $order_id;

                // Add order items
                foreach ($seller_order['items'] as $item) {
                    $order_item_data = [
                        'order_id' => $order_id,
                        'book_id' => $item['book_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'total_price' => $item['price'] * $item['quantity']
                    ];
                    if (!$order_model->addOrderItem($order_item_data)) {
                        throw new Exception("Failed to add order item for book ID: {$item['book_id']}");
                    }

                    // Update stock
                    $new_quantity = $item['book_stock'] - $item['quantity'];
                    if ($new_quantity < 0) throw new Exception("Insufficient stock for book: {$item['title']}");
                    if (!$book_model->updateQuantity($item['book_id'], $new_quantity)) {
                        throw new Exception("Failed to update book quantity for book ID: {$item['book_id']}");
                    }
                }
            }

            // Clear cart
            if (!$cart_model->clearCart($user_id)) throw new Exception("Failed to clear cart");

            $db->commit();

            // Redirect based on payment
            if ($payment_method === 'cod') {
                $_SESSION['order_ids'] = $order_ids;
                flash_message('Order placed successfully! You will pay upon delivery.', 'success');
                redirect('order-confirmation.php');
            } else {
                $_SESSION['pending_order_ids'] = $order_ids;
                $_SESSION['order_total'] = $total;
                redirect('process-esewa.php');
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Checkout error: " . $e->getMessage());
            flash_message('An error occurred while processing your order: ' . $e->getMessage(), 'error');
        }
    }

    $user_info = $user_model->getUserById($user_id);

    error_log("User info type: " . gettype($user_info));
    error_log("User info value: " . (is_string($user_info) ? $user_info : 'Not a string'));
    if (is_array($user_info)) {
        error_log("User info array keys: " . implode(', ', array_keys($user_info)));
    }

    if (!is_array($user_info)) {
        $user_info = [];
        error_log("Forced user_info to empty array");
    }

    $user_info['first_name'] = $user_info['first_name'] ?? '';
    $user_info['last_name'] = $user_info['last_name'] ?? '';
    $user_info['email'] = $user_info['email'] ?? '';
    $user_info['phone'] = $user_info['phone'] ?? '';
    $user_info['address'] = $user_info['address'] ?? '';
    $user_info['username'] = $user_info['username'] ?? 'User';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo htmlspecialchars(SITE_NAME); ?></title>
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

        .navbar-nav {
            display: flex;
            align-items: center;
            gap: 32px;
            list-style: none;
        }

        .nav-link {
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: #ffffff;
            transition: width 0.3s ease;
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

        .nav-cart {
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ffffff;
            text-decoration: none;
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #28a745;
            color: white;
            font-size: 12px;
            font-weight: 600;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dropdown {
            position: relative;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 8px 0;
            min-width: 200px;
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.2);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: all 0.3s ease;
            margin-top: 8px;
            z-index: 1001;
        }

        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: block;
            padding: 12px 20px;
            color: #000000;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .dropdown-item:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .mobile-menu {
            display: none;
            background: none;
            border: none;
            color: #ffffff;
            font-size: 20px;
            cursor: pointer;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Checkout Layout */
        .checkout-container {
            padding: 48px 0;
        }

        .checkout-title {
            font-size: 32px;
            font-weight: 800;
            color: #000000;
            margin-bottom: 32px;
            text-align: center;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 48px;
        }

        /* Checkout Steps */
        .checkout-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 48px;
            gap: 32px;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background: #000000;
            color: #ffffff;
        }

        .step-label {
            font-size: 14px;
            color: #666666;
            font-weight: 500;
        }

        .step.active .step-label {
            color: #000000;
            font-weight: 600;
        }

        /* Order Summary */
        .order-summary {
            background: #ffffff;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .order-summary h3 {
            font-size: 20px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .order-items {
            margin-bottom: 24px;
        }

        .order-item {
            display: flex;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-image-placeholder {
            width: 100%;
            height: 100%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .item-details {
            flex: 1;
        }

        .item-title {
            font-size: 14px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 4px;
            line-height: 1.4;
        }

        .item-author {
            font-size: 12px;
            color: #666666;
            margin-bottom: 8px;
        }

        .item-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-quantity {
            font-size: 13px;
            color: #666666;
        }

        .item-price {
            font-weight: 600;
            color: #000000;
        }

        /* Summary Calculations */
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
            color: #666666;
        }

        .summary-row.total {
            font-size: 18px;
            font-weight: 700;
            color: #000000;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* Checkout Form */
        .checkout-form-section {
            background: #ffffff;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 32px;
        }

        .checkout-form-section h3 {
            font-size: 20px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 8px;
        }

        .form-label .required {
            color: #dc3545;
        }

        .form-control,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #ffffff;
            color: #000000;
            font-family: inherit;
        }

        .form-control:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: #000000;
            box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.1);
            outline: none;
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            cursor: pointer;
        }

        .form-check-label {
            font-size: 14px;
            color: #666666;
            cursor: pointer;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .payment-method {
            position: relative;
        }

        .payment-method input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .payment-method label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            gap: 8px;
        }

        .payment-method label:hover {
            border-color: #000000;
            background: #f8f9fa;
        }

        .payment-method input:checked+label {
            border-color: #000000;
            background: #000000;
            color: #ffffff;
        }

        .payment-method i {
            font-size: 24px;
        }

        .payment-method span {
            font-size: 12px;
            font-weight: 500;
        }

        /* eSewa specific */
        .esewa-logo {
            width: 80px;
            height: auto;
        }

        .payment-method.esewa label {
            background: linear-gradient(135deg, #55a854 0%, #3e8e41 100%);
            color: white;
            border-color: #55a854;
        }

        .payment-method.esewa input:checked+label {
            background: linear-gradient(135deg, #55a854 0%, #3e8e41 100%);
            border-color: #55a854;
            color: white;
        }

        /* Buttons */
        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #000000 0%, #2d2d2d 100%);
            color: #ffffff;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            color: #ffffff;
        }

        .btn-secondary {
            background: #6c757d;
            color: #ffffff;
            width: 100%;
            justify-content: center;
        }

        .btn-secondary:hover {
            background: #5a6268;
            color: #ffffff;
        }

        /* Flash Messages */
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

        /* Footer */
        .footer {
            background: #000000;
            color: #ffffff;
            padding: 60px 0 40px;
            margin-top: 80px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-section h5 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .footer-section p {
            color: #cccccc;
            line-height: 1.6;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: #cccccc;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: #ffffff;
        }

        .footer-bottom {
            border-top: 1px solid #333333;
            padding-top: 30px;
            text-align: center;
            color: #cccccc;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .checkout-grid {
                grid-template-columns: 1fr;
                gap: 32px;
            }

            .order-summary {
                position: static;
                max-height: none;
            }
        }

        @media (max-width: 768px) {
            .navbar-nav {
                display: none;
            }

            .mobile-menu {
                display: block;
            }

            .container {
                padding: 0 16px;
            }

            .checkout-title {
                font-size: 24px;
            }

            .checkout-steps {
                gap: 16px;
            }

            .step-label {
                font-size: 12px;
            }

            .checkout-form-section,
            .order-summary {
                padding: 24px;
            }

            .payment-methods {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .payment-methods {
                grid-template-columns: 1fr;
            }

            .item-image {
                width: 60px;
                height: 80px;
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

            <ul class="navbar-nav">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="browse.php" class="nav-link">Browse Books</a></li>

                <?php if (is_logged_in()): ?>
                    <li>
                        <a href="my-cart.php" class="nav-cart">
                            <i class="fas fa-shopping-cart"></i>
                            Cart
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-count"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="dropdown">
                        <a href="#" class="nav-link">
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($user_info['username'] ?? 'User'); ?>
                        </a>
                        <div class="dropdown-menu">
                            <a href="dashboard.php" class="dropdown-item">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <a href="my-orders.php" class="dropdown-item">
                                <i class="fas fa-shopping-bag"></i> My Orders
                            </a>
                            <a href="my-cart.php" class="dropdown-item">
                                <i class="fas fa-shopping-cart"></i> Shopping Cart
                                <?php if ($cart_count > 0): ?>
                                    <span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 10px; font-size: 12px;">
                                        <?php echo $cart_count; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <a href="my-books.php" class="dropdown-item">
                                <i class="fas fa-book"></i> My Books
                            </a>
                            <hr style="margin: 8px 0; border: none; border-top: 1px solid #e0e0e0;">
                            <a href="logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <li><a href="login.php" class="nav-link">Login</a></li>
                    <li><a href="register.php" class="nav-link">Register</a></li>
                <?php endif; ?>
            </ul>

            <button class="mobile-menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <div class="container">
        <div class="checkout-container">
            <?php
            // Display flash messages
            if (isset($_SESSION['flash_message'])) {
                // Check if flash_message is an array
                if (is_array($_SESSION['flash_message'])) {
                    $message = $_SESSION['flash_message']['message'] ?? '';
                    $type = $_SESSION['flash_message']['type'] ?? 'info';
                } else {
                    // If it's a string, use it as the message with default type
                    $message = $_SESSION['flash_message'];
                    $type = 'info';
                }

                $bg_color = $type === 'success' ? 'flash-success' : 'flash-error';
                echo "<div class='flash-message {$bg_color}'>{$message}</div>";
                unset($_SESSION['flash_message']);
            }
            ?>

            <div class="checkout-steps">
                <div class="step active">
                    <div class="step-number">1</div>
                    <div class="step-label">Cart</div>
                </div>
                <div class="step active">
                    <div class="step-number">2</div>
                    <div class="step-label">Checkout</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-label">Confirmation</div>
                </div>
            </div>

            <h1 class="checkout-title">Checkout</h1>

            <div class="checkout-grid">
                <div class="checkout-form">
                    <div class="checkout-form-section">
                        <h3><i class="fas fa-shipping-fast"></i> Shipping Information</h3>

                        <form method="POST" id="checkoutForm">
                            <div class="form-group">
                                <label class="form-label">
                                    Full Name <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control"
                                    value="<?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?>"
                                    readonly>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Email <span class="required">*</span>
                                </label>
                                <input type="email" class="form-control"
                                    value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>"
                                    readonly>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Phone Number <span class="required">*</span>
                                </label>
                                <input type="tel" class="form-control" name="phone"
                                    value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>"
                                    placeholder="Your contact number" required
                                    pattern="[0-9+\-\s]{7,15}">
                                <small class="text-muted">Format: 98XXXXXXXX or +977-98XXXXXXXX</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Shipping Address <span class="required">*</span>
                                </label>
                                <textarea class="form-textarea" name="shipping_address"
                                    placeholder="Enter your complete shipping address (street, city, province, zip code)"
                                    required><?php echo htmlspecialchars($user_info['address'] ?? ''); ?></textarea>
                            </div>

                            <!-- <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="sameBillingAddress" checked>
                                <label class="form-check-label" for="sameBillingAddress">
                                    Billing address is the same as shipping address
                                </label>
                            </div> -->

                            <!-- <div id="billingAddressSection" style="display: none;">
                                <div class="form-group">
                                    <label class="form-label">Billing Address</label>
                                    <textarea class="form-textarea" name="billing_address"
                                        placeholder="Enter your billing address (if different from shipping)"></textarea>
                                </div>
                            </div> -->

                            <!-- Payment Method -->
                            <div class="checkout-form-section">
                                <h3><i class="fas fa-credit-card"></i> Payment Method</h3>

                                <div class="payment-methods">
                                    <div class="payment-method">
                                        <input type="radio" id="cod" name="payment_method" value="cod" checked>
                                        <label for="cod">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <span>Cash on Delivery</span>
                                        </label>
                                    </div>

                                    <div class="payment-method esewa">
                                        <input type="radio" id="esewa" name="payment_method" value="esewa">
                                        <label for="esewa">
                                            <img src="assets/images/esewa-logo.png" alt="eSewa" class="esewa-logo">
                                            <span>Pay with eSewa</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Notes -->
                            <div class="checkout-form-section">
                                <h3><i class="fas fa-sticky-note"></i> Order Notes</h3>
                                <div class="form-group">
                                    <label class="form-label">Special Instructions (Optional)</label>
                                    <textarea class="form-textarea" name="notes"
                                        placeholder="Any special instructions for delivery or packaging..."></textarea>
                                </div>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="checkout-form-section">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="checkout-form-section" style="border: none; padding: 0;">
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-lock"></i>
                                    <span id="buttonText">
                                        Place Order - <?php echo format_price($total); ?>
                                    </span>
                                </button>
                                <a href="my-cart.php" class="btn btn-secondary" style="margin-top: 16px;">
                                    <i class="fas fa-arrow-left"></i> Back to Cart
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="order-summary">
                    <h3>Order Summary</h3>

                    <div class="order-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="order-item">
                                <div class="item-image">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?php echo UPLOAD_PATH . htmlspecialchars($item['image_url']); ?>"
                                            alt="<?php echo htmlspecialchars($item['title']); ?>">
                                    <?php else: ?>
                                        <div class="item-image-placeholder">
                                            <i class="fas fa-book"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="item-details">
                                    <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    <div class="item-author">by <?php echo htmlspecialchars($item['author']); ?></div>
                                    <div class="item-meta">
                                        <div class="item-quantity">Qty: <?php echo $item['quantity']; ?></div>
                                        <div class="item-price"><?php echo format_price($item['price'] * $item['quantity']); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-row">
                        <span>Subtotal (<?php echo $cart_count; ?> items)</span>
                        <span><?php echo format_price($subtotal); ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Shipping Fee</span>
                        <span><?php echo format_price($shipping_fee); ?></span>
                    </div>


                    <div class="summary-row total">
                        <span>Total</span>
                        <span><?php echo format_price($total); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h5><?php echo htmlspecialchars(SITE_NAME); ?></h5>
                    <p>Your trusted marketplace for buying and selling books online. Connect with fellow book lovers and discover your next great read.</p>
                </div>
                <div class="footer-section">
                    <h5>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="browse.php">Browse Books</a></li>
                        <?php if (!is_logged_in()): ?>
                            <li><a href="register.php">Join Us</a></li>
                            <li><a href="login.php">Sign In</a></li>
                        <?php else: ?>
                            <li><a href="dashboard.php">Dashboard</a></li>
                            <li><a href="sell-book.php">Sell Books</a></li>
                            <li><a href="my-cart.php">Shopping Cart</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu').addEventListener('click', function() {
            const navLinks = document.querySelector('.navbar-nav');
            navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
        });

        // Toggle billing address section
        // document.getElementById('sameBillingAddress').addEventListener('change', function() {
        //     const billingSection = document.getElementById('billingAddressSection');
        //     if (this.checked) {
        //         billingSection.style.display = 'none';
        //     } else {
        //         billingSection.style.display = 'block';
        //     }
        // });

        // Update button text based on payment method
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const buttonText = document.getElementById('buttonText');
                const submitBtn = document.getElementById('submitBtn');

                if (this.value === 'esewa') {
                    buttonText.innerHTML = `<i class="fas fa-lock"></i> Pay with eSewa - <?php echo format_price($total); ?>`;
                    submitBtn.classList.add('esewa-btn');
                    submitBtn.classList.remove('cod-btn');
                } else {
                    buttonText.innerHTML = `<i class="fas fa-lock"></i> Place Order - <?php echo format_price($total); ?>`;
                    submitBtn.classList.remove('esewa-btn');
                    submitBtn.classList.add('cod-btn');
                }
            });
        });

        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const terms = document.getElementById('terms');
            if (!terms.checked) {
                e.preventDefault();
                alert('Please agree to the Terms and Conditions to proceed.');
                terms.focus();
                return false;
            }

            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;

            // Allow form to submit
            return true;
        });

        // Phone number formatting
        const phoneInput = document.querySelector('input[name="phone"]');
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');

            // If it starts with 977 (country code), handle it
            if (value.startsWith('977')) {
                value = value.substring(3);
            }

            // Format as 98X-XXX-XXXX
            if (value.length > 6) {
                value = value.substring(0, 3) + '-' + value.substring(3, 6) + '-' + value.substring(6, 10);
            } else if (value.length > 3) {
                value = value.substring(0, 3) + '-' + value.substring(3);
            }

            e.target.value = value;
        });

        // Auto-focus on first error field
        document.addEventListener('DOMContentLoaded', function() {
            const errorMessages = document.querySelectorAll('.flash-error');
            if (errorMessages.length > 0) {
                // If there's an error, scroll to the form
                const firstInput = document.querySelector('input[name="shipping_address"], input[name="phone"]');
                if (firstInput) {
                    firstInput.focus();
                }
            }
        });
    </script>
</body>

</html>