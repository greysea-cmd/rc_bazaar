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

// Get cart items - with proper error handling
$cart_items = $cart_model->getUserCart($user_id);

// DEBUG: Check what getUserCart returns
error_log("Cart items type: " . gettype($cart_items));
if (is_string($cart_items)) {
    error_log("Cart items is string: " . $cart_items);
    $cart_items = [];
} elseif (!is_array($cart_items)) {
    error_log("Cart items is not array, type: " . gettype($cart_items));
    $cart_items = [];
}

if (empty($cart_items)) {
    flash_message('Your cart is empty. Please add items before checkout.', 'error');
    redirect('my-cart.php');
}

// User info - with proper error handling
$user_info = $user_model->getUserById($user_id);

// DEBUG: Check what getUserById returns
if (is_string($user_info)) {
    error_log("getUserById() returned string: " . $user_info);
    $user_info = [];
} elseif (!is_array($user_info)) {
    error_log("getUserById() returned non-array, type: " . gettype($user_info));
    $user_info = [];
}

// Ensure all required keys exist with default values
$default_user_info = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'username' => 'User'
];

$user_info = array_merge($default_user_info, (array)$user_info);

// Totals
$subtotal = 0;
$shipping_fee = 5.00;
$cart_count = 0;

// Make sure cart_items is an array before iterating
if (is_array($cart_items)) {
    foreach ($cart_items as $item) {
        // Ensure each item is an array
        if (is_array($item) && isset($item['price'], $item['quantity'])) {
            $subtotal += $item['price'] * $item['quantity'];
            $cart_count += $item['quantity'];
        } else {
            error_log("Invalid cart item structure: " . print_r($item, true));
        }
    }
}

$total = $subtotal + $shipping_fee;

// Handle POST form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_address = filter_input(INPUT_POST, 'shipping_address', FILTER_SANITIZE_SPECIAL_CHARS);
    $billing_address = filter_input(INPUT_POST, 'billing_address', FILTER_SANITIZE_SPECIAL_CHARS) ?? $shipping_address;
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
                // Skip if item is not valid
                if (!is_array($item) || !isset($item['seller_id'])) {
                    continue;
                }
                
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

                $order_data = [
                    'buyer_id' => $user_id,
                    'seller_id' => $seller_id,
                    'shipping_address' => $shipping_address,
                    'billing_address' => $billing_address,
                    'payment_method' => $payment_method,
                    'notes' => $notes,
                    'subtotal' => $seller_subtotal,
                    'shipping_fee' => $seller_shipping,
                    'tax_amount' => 0,
                    'total_amount' => $seller_total,
                    'status' => $payment_method === 'cod' ? 'pending' : 'pending_payment',
                    'phone' => $phone
                ];

                $order_id = $order_model->createOrder($order_data);
                if (!$order_id) throw new Exception("Failed to create order for seller ID: {$seller_id}");
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
    } else {
        foreach ($errors as $error) flash_message($error, 'error');
    }
}

// Helper function to safely get user info
function get_user_info($key, $default = '') {
    global $user_info;
    return isset($user_info[$key]) ? $user_info[$key] : $default;
}

// Helper function to safely get cart item
function get_cart_item_value($item, $key, $default = '') {
    return (is_array($item) && isset($item[$key])) ? $item[$key] : $default;
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
        /* [Keep all your CSS styles as they are] */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        /* ... rest of your CSS ... */
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
                            <?php echo htmlspecialchars(get_user_info('username', 'User')); ?>
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
                $message = $_SESSION['flash_message']['message'];
                $type = $_SESSION['flash_message']['type'];
                
                $bg_color = $type === 'success' ? 'flash-success' : 'flash-error';
                echo "<div class='flash-message {$bg_color}'>{$message}</div>";
                unset($_SESSION['flash_message']);
            }
            ?>

            <!-- Checkout Steps -->
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
                <!-- Checkout Form -->
                <div class="checkout-form">
                    <!-- Shipping Information -->
                    <div class="checkout-form-section">
                        <h3><i class="fas fa-shipping-fast"></i> Shipping Information</h3>
                        
                        <form method="POST" id="checkoutForm">
                            <div class="form-group">
                                <label class="form-label">
                                    Full Name <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars(get_user_info('first_name') . ' ' . get_user_info('last_name')); ?>"
                                       readonly>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Email <span class="required">*</span>
                                </label>
                                <input type="email" class="form-control" 
                                       value="<?php echo htmlspecialchars(get_user_info('email')); ?>"
                                       readonly>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Phone Number <span class="required">*</span>
                                </label>
                                <input type="tel" class="form-control" name="phone"
                                       value="<?php echo htmlspecialchars(get_user_info('phone')); ?>"
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
                                          required><?php echo htmlspecialchars(get_user_info('address')); ?></textarea>
                            </div>

                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="sameBillingAddress" checked>
                                <label class="form-check-label" for="sameBillingAddress">
                                    Billing address is the same as shipping address
                                </label>
                            </div>

                            <div id="billingAddressSection" style="display: none;">
                                <div class="form-group">
                                    <label class="form-label">Billing Address</label>
                                    <textarea class="form-textarea" name="billing_address" 
                                              placeholder="Enter your billing address (if different from shipping)"></textarea>
                                </div>
                            </div>

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
                        <?php if (is_array($cart_items) && count($cart_items) > 0): ?>
                            <?php foreach ($cart_items as $item): ?>
                                <?php if (is_array($item)): ?>
                                    <div class="order-item">
                                        <div class="item-image">
                                            <?php if (!empty(get_cart_item_value($item, 'image_url'))): ?>
                                                <img src="<?php echo UPLOAD_PATH . htmlspecialchars(get_cart_item_value($item, 'image_url')); ?>" 
                                                     alt="<?php echo htmlspecialchars(get_cart_item_value($item, 'title', 'Book')); ?>">
                                            <?php else: ?>
                                                <div class="item-image-placeholder">
                                                    <i class="fas fa-book"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="item-details">
                                            <div class="item-title"><?php echo htmlspecialchars(get_cart_item_value($item, 'title', 'Unknown Book')); ?></div>
                                            <div class="item-author">by <?php echo htmlspecialchars(get_cart_item_value($item, 'author', 'Unknown Author')); ?></div>
                                            <div class="item-meta">
                                                <div class="item-quantity">Qty: <?php echo get_cart_item_value($item, 'quantity', 0); ?></div>
                                                <div class="item-price"><?php echo format_price(get_cart_item_value($item, 'price', 0) * get_cart_item_value($item, 'quantity', 0)); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No items in cart</p>
                        <?php endif; ?>
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
        // [Keep all your JavaScript as it is]
        // ... (your JavaScript code remains the same)
    </script>
</body>
</html>