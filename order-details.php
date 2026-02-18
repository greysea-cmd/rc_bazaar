<?php
// Detailed order view
if (basename($_SERVER['PHP_SELF']) === 'order-details.php') {
    require_once 'config/database.php';
    require_once 'config/config.php';
    require_once 'models/Order.php';
    require_once 'models/User.php';

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!is_logged_in()) {
        flash_message('Please log in to view order details.', 'error');
        redirect('login.php');
    }

    $database = new Database();
    $db = $database->getConnection();
    $order_model = new Order($db);
    $user_model = new User($db);

    $order_id = (int)($_GET['id'] ?? 0);
    $user_id = get_current_user_id();

    // Get order with full details including buyer and seller info
    $order_data = $order_model->getOrderWithDetails($order_id);

    if (!$order_data) {
        flash_message('Order not found.', 'error');
        redirect('my-orders.php');
    }

    // Check if user is authorized to view this order
    if ($order_data['buyer_id'] != $user_id && $order_data['seller_id'] != $user_id) {
        flash_message('You are not authorized to view this order.', 'error');
        redirect('my-orders.php');
    }

    $is_buyer = ($order_data['buyer_id'] == $user_id);
    $user_data = $user_model->getUserById($user_id);

    // Helper functions
    if (!function_exists('time_ago')) {
        function time_ago($datetime, $full = false)
        {
            $now = new DateTime;
            $ago = new DateTime($datetime);
            $diff = $now->diff($ago);

            // Calculate weeks
            $weeks = floor($diff->d / 7);
            $remaining_days = $diff->d % 7;

            $string = array();

            if ($diff->y > 0) {
                $string[] = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
            }
            if ($diff->m > 0) {
                $string[] = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
            }
            if ($weeks > 0) {
                $string[] = $weeks . ' week' . ($weeks > 1 ? 's' : '');
            }
            if ($remaining_days > 0) {
                $string[] = $remaining_days . ' day' . ($remaining_days > 1 ? 's' : '');
            }
            if ($diff->h > 0) {
                $string[] = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
            }
            if ($diff->i > 0) {
                $string[] = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
            }
            if ($diff->s > 0) {
                $string[] = $diff->s . ' second' . ($diff->s > 1 ? 's' : '');
            }

            if (!$full) {
                $string = array_slice($string, 0, 1);
            }

            return $string ? implode(', ', $string) . ' ago' : 'just now';
        }
    }

    // Get order items
    $order_items = $order_model->getOrderItems($order_id);
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order #<?php echo $order_data['id']; ?> - <?php echo SITE_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            :root {
                --black: #000000;
                --white: #ffffff;
                --dark-grey: #333333;
                --grey: #777777;
                --light-grey: #f5f5f5;
                --border: #e0e0e0;
                --success: #28a745;
                --danger: #dc3545;
                --warning: #ffc107;
                --info: #17a2b8;
                --primary: #007bff;
            }

            body {
                background-color: var(--light-grey);
                font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            }

            .navbar {
                background: var(--black) !important;
            }

            .navbar-brand {
                color: var(--white) !important;
                font-weight: 600;
            }

            .navbar-brand i {
                margin-right: 8px;
            }

            .breadcrumb {
                background-color: transparent;
                padding: 0.75rem 1rem;
                border-radius: 0.375rem;
                margin-bottom: 2rem;
            }

            .breadcrumb-item a {
                color: var(--dark-grey);
                text-decoration: none;
            }

            .breadcrumb-item.active {
                color: var(--grey);
            }

            .card {
                border: 1px solid var(--border);
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                margin-bottom: 1.5rem;
            }

            .card-header {
                background-color: var(--white);
                border-bottom: 1px solid var(--border);
                padding: 1rem 1.25rem;
            }

            .card-body {
                padding: 1.25rem;
            }

            .timeline {
                position: relative;
                padding-left: 2rem;
            }

            .timeline::before {
                content: '';
                position: absolute;
                left: 7px;
                top: 0;
                bottom: 0;
                width: 2px;
                background-color: var(--border);
            }

            .timeline-item {
                position: relative;
                margin-bottom: 1.5rem;
            }

            .timeline-item:last-child {
                margin-bottom: 0;
            }

            .timeline-marker {
                position: absolute;
                left: -2rem;
                top: 0;
                width: 16px;
                height: 16px;
                border-radius: 50%;
                background-color: var(--white);
                border: 2px solid var(--border);
                z-index: 1;
            }

            .timeline-content {
                background: var(--white);
                border: 1px solid var(--border);
                border-radius: 6px;
                padding: 1rem;
                margin-left: 1rem;
            }

            .timeline-item.completed .timeline-marker {
                border-color: var(--success);
                background-color: var(--success);
            }

            .timeline-item.active .timeline-marker {
                border-color: var(--primary);
                background-color: var(--primary);
            }

            .timeline-item.pending .timeline-marker {
                border-color: var(--warning);
                background-color: var(--warning);
            }

            .address-box {
                background: var(--light-grey);
                border: 1px solid var(--border);
                border-radius: 6px;
                padding: 1rem;
                white-space: pre-line;
            }

            .badge {
                font-size: 0.75em;
                font-weight: 600;
                padding: 0.35em 0.65em;
            }

            .btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-weight: 500;
            }

            .btn i {
                font-size: 0.875em;
            }

            .book-image {
                width: 120px;
                height: 160px;
                object-fit: cover;
                border-radius: 4px;
                border: 1px solid var(--border);
            }

            .book-image-placeholder {
                width: 120px;
                height: 160px;
                background: var(--light-grey);
                border: 1px solid var(--border);
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--grey);
            }

            .status-badge {
                font-size: 0.875rem;
                padding: 0.5rem 1rem;
            }

            @media (max-width: 768px) {
                .container {
                    padding: 0 1rem;
                }

                .row {
                    margin: 0 -1rem;
                }

                .col-md-8,
                .col-md-4 {
                    padding: 0 1rem;
                }

                .card {
                    margin-bottom: 1rem;
                }
            }
        </style>
    </head>

    <body>
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-book"></i> <?php echo SITE_NAME; ?>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my-orders.php">
                                <i class="fas fa-shopping-cart"></i> My Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container mt-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="my-orders.php">My Orders</a></li>
                    <li class="breadcrumb-item active">Order #<?php echo $order_data['id']; ?></li>
                </ol>
            </nav>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['flash_message']['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mb-0">Order #<?php echo $order_data['id']; ?></h4>
                                <?php
                                $status_colors = [
                                    'pending' => 'secondary',
                                    'pending_payment' => 'warning',
                                    'paid' => 'info',
                                    'confirmed' => 'primary',
                                    'shipped' => 'info',
                                    'delivered' => 'success',
                                    'cancelled' => 'danger',
                                    'disputed' => 'warning'
                                ];
                                $color = $status_colors[$order_data['order_status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?> status-badge">
                                    <?php echo ucwords(str_replace('_', ' ', $order_data['order_status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Order Items -->
                            <?php foreach ($order_items as $item): ?>
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="<?php echo UPLOAD_PATH . $item['image_url']; ?>"
                                                alt="Book cover" class="book-image">
                                        <?php else: ?>
                                            <div class="book-image-placeholder">
                                                <i class="fas fa-book fa-2x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-9">
                                        <h5><?php echo htmlspecialchars($item['title']); ?></h5>
                                        <p class="text-muted">by <?php echo htmlspecialchars($item['author']); ?></p>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>Quantity:</strong> <?php echo $item['quantity']; ?><br>
                                                <strong>Unit Price:</strong> <?php echo format_price($item['unit_price']); ?><br>
                                                <strong>Item Total:</strong> <?php echo format_price($item['total_price']); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>ISBN:</strong> <?php echo htmlspecialchars($item['isbn'] ?? 'N/A'); ?><br>
                                                <strong>Condition:</strong> <?php echo ucfirst($item['book_condition'] ?? 'good'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Order Summary -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Order Summary</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Subtotal:</span>
                                                <span><?php echo format_price($order_data['subtotal']); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Shipping Fee:</span>
                                                <span><?php echo format_price($order_data['shipping_fee']); ?></span>
                                            </div>

                                            <hr>
                                            <div class="d-flex justify-content-between fw-bold">
                                                <span>Total Amount:</span>
                                                <span class="text-success"><?php echo format_price($order_data['total_amount']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Payment Information -->
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Payment Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <strong>Payment Method:</strong><br>
                                            <?php
                                            // Get payment details from database
                                            require_once 'models/Payment.php';
                                            $payment_model = new Payment($db);
                                            $payment_details = $payment_model->getOrderPayments($order_data['id']);

                                            $payment_method = $order_data['payment_method'] ?? 'unknown';
                                            $payment_status = 'pending';

                                            if (!empty($payment_details)) {
                                                $latest_payment = $payment_details[0];
                                                $payment_status = $latest_payment['status'];
                                                $transaction_code = $latest_payment['transaction_code'];
                                                $transaction_uuid = $latest_payment['transaction_uuid'];

                                                // Determine if it's eSewa
                                                if (strpos($transaction_code, 'TXN-') === 0 || !empty($transaction_uuid)) {
                                                    $payment_method = 'esewa';
                                                }
                                            }

                                            if ($payment_method === 'esewa') {
                                                echo '<div class="alert alert-success mb-2">';
                                                echo '<i class="fas fa-check-circle"></i> <strong>eSewa Payment</strong>';
                                                echo '</div>';

                                                // Show eSewa transaction details
                                                if (!empty($payment_details)): ?>
                                                    <div class="payment-details mt-3 p-3 bg-light rounded">
                                                        <h6 class="mb-3">Transaction Details:</h6>

                                                        <?php foreach ($payment_details as $payment): ?>
                                                            <?php if ($payment['status'] === 'completed'): ?>
                                                                <div class="row mb-2">
                                                                    <div class="col-6">
                                                                        <small class="text-muted">Transaction ID:</small><br>
                                                                        <code><?php echo htmlspecialchars($payment['transaction_code']); ?></code>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <small class="text-muted">Amount:</small><br>
                                                                        <strong class="text-success">NPR <?php echo number_format($payment['amount'], 2); ?></strong>
                                                                    </div>
                                                                </div>

                                                                <?php if (!empty($payment['transaction_uuid'])): ?>
                                                                    <div class="row mb-2">
                                                                        <div class="col-12">
                                                                            <small class="text-muted">eSewa Transaction UUID:</small><br>
                                                                            <small><code><?php echo htmlspecialchars($payment['transaction_uuid']); ?></code></small>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <div class="row mb-2">
                                                                    <div class="col-6">
                                                                        <small class="text-muted">Payment Date:</small><br>
                                                                        <small><?php echo date('F j, Y, g:i a', strtotime($payment['created_at'])); ?></small>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <small class="text-muted">Status:</small><br>
                                                                        <span class="badge bg-success">Completed</span>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </div>
                                            <?php endif;
                                            } elseif ($payment_method === 'cod') {
                                                echo '<span class="badge bg-info">Cash on Delivery</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">' . ucwords(str_replace('_', ' ', $payment_method)) . '</span>';
                                            }
                                            ?>
                                        </div>

                                        <div class="mb-2">
                                            <strong>Payment Status:</strong><br>
                                            <?php
                                            if ($payment_status === 'completed') {
                                                echo '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Paid</span>';
                                            } elseif ($payment_status === 'pending') {
                                                if ($order_data['order_status'] === 'paid') {
                                                    echo '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Paid</span>';
                                                } else {
                                                    echo '<span class="badge bg-warning"><i class="fas fa-clock"></i> Pending</span>';
                                                }
                                            } elseif ($payment_status === 'failed') {
                                                echo '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> Failed</span>';
                                            }
                                            ?>
                                        </div>

                                        <?php if ($payment_method === 'esewa' && $payment_status === 'completed'): ?>
                                            <div class="alert alert-success mt-3">
                                                <i class="fas fa-shield-alt"></i>
                                                <strong>Payment Verified</strong>
                                                <p class="mb-0 mt-1 small">This payment has been successfully verified through eSewa.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Timeline -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Order Timeline</h6>
                                </div>
                                <div class="card-body">
                                    <div class="timeline">
                                        <?php
                                        $timeline_steps = [
                                            'placed' => [
                                                'title' => 'Order Placed',
                                                'description' => 'Your order has been placed successfully.',
                                                'date' => $order_data['created_at']
                                            ],
                                            'confirmed' => [
                                                'title' => 'Order Confirmed',
                                                'description' => 'Seller has confirmed your order.',
                                                'date' => $order_data['order_status'] === 'confirmed' || $order_data['order_status'] === 'shipped' || $order_data['order_status'] === 'delivered' ? $order_data['updated_at'] : null
                                            ],
                                            'shipped' => [
                                                'title' => 'Order Shipped',
                                                'description' => 'Your order is on its way!',
                                                'date' => $order_data['order_status'] === 'shipped' || $order_data['order_status'] === 'delivered' ? $order_data['updated_at'] : null
                                            ],
                                            'delivered' => [
                                                'title' => 'Order Delivered',
                                                'description' => 'Your order has been delivered successfully.',
                                                'date' => $order_data['order_status'] === 'delivered' ? $order_data['updated_at'] : null
                                            ]
                                        ];

                                        $current_step = $order_data['order_status'];
                                        foreach ($timeline_steps as $step => $step_data):
                                            $is_completed = false;
                                            $is_active = false;
                                            $is_pending = false;

                                            if (in_array($current_step, ['paid', 'confirmed', 'shipped', 'delivered'])) {
                                                if ($step === 'placed') $is_completed = true;
                                                if ($step === 'confirmed' && in_array($current_step, ['confirmed', 'shipped', 'delivered'])) $is_completed = true;
                                                if ($step === 'shipped' && in_array($current_step, ['shipped', 'delivered'])) $is_completed = true;
                                                if ($step === 'delivered' && $current_step === 'delivered') $is_completed = true;

                                                if ($step === 'placed' && $current_step === 'pending') $is_active = true;
                                                if ($step === 'confirmed' && $current_step === 'confirmed') $is_active = true;
                                                if ($step === 'shipped' && $current_step === 'shipped') $is_active = true;
                                                if ($step === 'delivered' && $current_step === 'delivered') $is_active = true;
                                            }

                                            if (!$is_completed && !$is_active) {
                                                $is_pending = true;
                                            }
                                        ?>
                                            <div class="timeline-item <?php echo $is_completed ? 'completed' : ($is_active ? 'active' : 'pending'); ?>">
                                                <div class="timeline-marker"></div>
                                                <div class="timeline-content">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1"><?php echo $step_data['title']; ?></h6>
                                                            <p class="mb-1 text-muted"><?php echo $step_data['description']; ?></p>
                                                        </div>
                                                        <?php if ($step_data['date']): ?>
                                                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($step_data['date'])); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Address -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Shipping Address</h6>
                                </div>
                                <div class="card-body">
                                    <div class="address-box">
                                        <?php echo nl2br(htmlspecialchars($order_data['shipping_address'])); ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($order_data['notes'])): ?>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0">Order Notes</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="address-box">
                                            <?php echo nl2br(htmlspecialchars($order_data['notes'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Contact Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><?php echo $is_buyer ? 'Seller' : 'Buyer'; ?> Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <?php
                                    $contact_user_id = $is_buyer ? $order_data['seller_id'] : $order_data['buyer_id'];
                                    $contact_user = $user_model->getUserById($contact_user_id);
                                    ?>
                                    <?php if (!empty($contact_user['profile_picture_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($contact_user['profile_picture_path']); ?>"
                                            alt="Profile" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--light-grey); display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($contact_user['first_name'] . ' ' . $contact_user['last_name']); ?></strong><br>
                                    <small class="text-muted">@<?php echo htmlspecialchars($contact_user['username']); ?></small>
                                </div>
                            </div>

                            <?php if (!empty($contact_user['email'])): ?>
                                <div class="mb-2">
                                    <i class="fas fa-envelope me-2 text-muted"></i>
                                    <span><?php echo htmlspecialchars($contact_user['email']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($contact_user['phone'])): ?>
                                <div class="mb-2">
                                    <i class="fas fa-phone me-2 text-muted"></i>
                                    <span><?php echo htmlspecialchars($contact_user['phone']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($contact_user['address'])): ?>
                                <div class="mb-2">
                                    <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                                    <span><?php echo htmlspecialchars($contact_user['address']); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="mt-3">
                                <small class="text-muted">
                                    Member since <?php echo date('M Y', strtotime($contact_user['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if ($order_data['order_status'] === 'delivered' && $is_buyer): ?>
                                    <a href="create-dispute.php?order_id=<?php echo $order_data['id']; ?>" class="btn btn-warning">
                                        <i class="fas fa-exclamation-triangle"></i> Report Issue
                                    </a>
                                <?php endif; ?>

                                <?php if ($order_data['order_status'] === 'pending' && $is_buyer): ?>
                                    <a href="checkout.php?order_id=<?php echo $order_data['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-credit-card"></i> Complete Payment
                                    </a>
                                <?php endif; ?>

                                <?php if (!$is_buyer && in_array($order_data['order_status'], ['paid', 'confirmed'])): ?>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                        <i class="fas fa-sync-alt"></i> Update Status
                                    </button>
                                <?php endif; ?>

                                <a href="my-orders.php" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left"></i> Back to Orders
                                </a>

                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Order Metadata -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">Order Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <small class="text-muted">Order ID:</small><br>
                                <strong>#<?php echo $order_data['id']; ?></strong>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Order Date:</small><br>
                                <strong><?php echo date('F j, Y, g:i a', strtotime($order_data['created_at'])); ?></strong>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Last Updated:</small><br>
                                <strong><?php echo time_ago($order_data['updated_at']); ?></strong>
                            </div>
                            <?php if ($order_data['estimated_delivery']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Estimated Delivery:</small><br>
                                    <strong><?php echo date('F j, Y', strtotime($order_data['estimated_delivery'])); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Update Status Modal -->
        <?php if (!$is_buyer && in_array($order_data['order_status'], ['paid', 'confirmed'])): ?>
            <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="update-order-status.php">
                            <input type="hidden" name="order_id" value="<?php echo $order_data['id']; ?>">
                            <div class="modal-header">
                                <h5 class="modal-title">Update Order Status</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="new_status" class="form-label">New Status</label>
                                    <select class="form-select" id="new_status" name="new_status" required>
                                        <option value="confirmed" <?php echo $order_data['order_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="shipped" <?php echo $order_data['order_status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $order_data['order_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Status</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Auto-dismiss alerts after 5 seconds
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    setTimeout(() => {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }, 5000);
                });

                // Handle image errors
                const images = document.querySelectorAll('img');
                images.forEach(img => {
                    img.onerror = function() {
                        this.style.display = 'none';
                        const placeholder = document.createElement('div');
                        placeholder.className = 'book-image-placeholder';
                        placeholder.innerHTML = '<i class="fas fa-book fa-2x"></i>';
                        this.parentNode.appendChild(placeholder);
                    };
                });

                // Print order details
                document.getElementById('printOrder').addEventListener('click', function() {
                    window.print();
                });
            });
        </script>
    </body>

    </html>
<?php
} else {
    // Redirect to homepage if accessed directly
    redirect('index.php');
}
?>