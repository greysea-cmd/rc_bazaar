<?php
// View user's orders (both purchases and sales)
if (basename($_SERVER['PHP_SELF']) === 'my-orders.php') {
    require_once 'config/database.php';
    require_once 'config/config.php';
    require_once 'models/Order.php';
    require_once 'models/User.php';

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!is_logged_in()) {
        flash_message('Please log in to view your orders.', 'error');
        redirect('login.php');
    }

    $database = new Database();
    $db = $database->getConnection();
    $order_model = new Order($db);
    $user_model = new User($db);

    $user_id = get_current_user_id();
    $user_data = $user_model->getUserById($user_id);

    if (!$user_data) {
        flash_message('User not found.', 'error');
        redirect('logout.php');
    }

    $tab = $_GET['tab'] ?? 'purchases';
    $orders = [];
    $page_title = '';

    // Get orders based on tab
    if ($tab === 'sales') {
        $orders = $order_model->getUserOrders($user_id, 'seller');
        $page_title = 'My Sales';
    } else {
        $orders = $order_model->getUserOrders($user_id, 'buyer');
        $page_title = 'My Purchases';
    }

    // Handle order status updates (for sellers)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';

        // Verify the seller owns this order
        $order_data = $order_model->getOrderById($order_id);
        if ($order_data && $order_data['seller_id'] == $user_id) {
            if ($order_model->updateOrderStatus($order_id, $new_status)) {
                flash_message('Order status updated successfully.', 'success');
                // Refresh the page
                redirect('my-orders.php?tab=' . $tab);
            } else {
                flash_message('Failed to update order status.', 'error');
            }
        } else {
            flash_message('You are not authorized to update this order.', 'error');
        }
    }

    // Helper function for time ago - FIXED VERSION
    // function time_ago($datetime, $full = false)
    // {
    //     if (empty($datetime)) return 'Recently';

    //     $now = new DateTime();
    //     $ago = new DateTime($datetime);
    //     $diff = $now->diff($ago);

    //     // Calculate weeks from days
    //     $weeks = floor($diff->d / 7);
    //     $days = $diff->d % 7;

    //     $string = array(
    //         'y' => 'year',
    //         'm' => 'month',
    //         'w' => 'week',
    //         'd' => 'day',
    //         'h' => 'hour',
    //         'i' => 'minute',
    //         's' => 'second',
    //     );

    //     $values = array(
    //         'y' => $diff->y,
    //         'm' => $diff->m,
    //         'w' => $weeks,
    //         'd' => $days,
    //         'h' => $diff->h,
    //         'i' => $diff->i,
    //         's' => $diff->s,
    //     );

    //     foreach ($string as $k => &$v) {
    //         if (!empty($values[$k])) {
    //             $v = $values[$k] . ' ' . $v . ($values[$k] > 1 ? 's' : '');
    //         } else {
    //             unset($string[$k]);
    //         }
    //     }

    //     if (!$full) $string = array_slice($string, 0, 1);

    //     return $string ? implode(', ', $string) . ' ago' : 'just now';
    // }


    // Alternative simpler time_ago function
    if (!function_exists('simple_time_ago')) {
        function simple_time_ago($datetime)
        {
            if (empty($datetime)) return 'Recently';

            $now = time();
            $timestamp = strtotime($datetime);
            $diff = $now - $timestamp;

            if ($diff < 60) {
                return 'just now';
            } elseif ($diff < 3600) {
                $mins = floor($diff / 60);
                return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
            } elseif ($diff < 604800) {
                $days = floor($diff / 86400);
                return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
            } elseif ($diff < 2592000) {
                $weeks = floor($diff / 604800);
                return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
            } elseif ($diff < 31536000) {
                $months = floor($diff / 2592000);
                return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
            } else {
                $years = floor($diff / 31536000);
                return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
            }
        }
    }

    // Helper function for flash message
    if (!function_exists('display_flash_message')) {
        function display_flash_message()
        {
            if (isset($_SESSION['flash_message'])) {
                $message = $_SESSION['flash_message']['message'];
                $type = $_SESSION['flash_message']['type'];

                $bg_color = $type === 'success' ? 'alert-success' : ($type === 'error' ? 'alert-danger' : 'alert-info');
                $icon = $type === 'success' ? 'fa-check-circle' : ($type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');

                echo <<<HTML
                <div class="alert {$bg_color} alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="fas {$icon} me-2"></i>
                    <div>{$message}</div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
HTML;
                unset($_SESSION['flash_message']);
            }
        }
    }
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
                color: var(--dark-grey);
                line-height: 1.6;
            }

            /* Layout */
            .dashboard-container {
                display: grid;
                grid-template-columns: 240px 1fr;
                min-height: 100vh;
            }

            /* Sidebar */
            .sidebar {
                background: var(--black);
                color: var(--white);
                padding: 2rem 1rem;
                position: sticky;
                top: 0;
                height: 100vh;
                border-right: 1px solid var(--border);
            }

            .brand {
                display: inline-flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 2rem;
                padding: 0 0.5rem;
                text-decoration: none;
                color: var(--white);
            }

            .brand:hover {
                color: var(--white);
                opacity: 0.9;
            }

            .brand i {
                font-size: 1.5rem;
            }

            .brand h1 {
                font-size: 1.25rem;
                font-weight: 600;
                display: inline;
                margin: 0;
            }

            .nav-menu {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }

            .nav-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 0.75rem 1rem;
                border-radius: 6px;
                color: var(--white);
                text-decoration: none;
                transition: all 0.2s ease;
                border: none;
                background: none;
                width: 100%;
                text-align: left;
                font-size: 1rem;
                cursor: pointer;
            }

            .nav-item:hover,
            .nav-item.active {
                background: var(--dark-grey);
            }

            .nav-item i {
                width: 24px;
                text-align: center;
            }

            /* Main Content */
            .main-content {
                padding: 2rem;
                background: var(--light-grey);
            }

            /* Header */
            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 2rem;
                flex-wrap: wrap;
                gap: 1rem;
            }

            .user-menu {
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: var(--grey);
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--white);
                font-weight: 600;
                overflow: hidden;
                position: relative;
                cursor: pointer;
            }

            .user-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .user-initial {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                height: 100%;
                font-size: 1.2rem;
            }

            /* Content Section */
            .content-section {
                background: var(--white);
                border-radius: 10px;
                padding: 1.5rem;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                border: 1px solid var(--border);
                margin-bottom: 1.5rem;
            }

            /* Tabs */
            .nav-tabs {
                display: flex;
                gap: 1px;
                background: var(--border);
                border-radius: 6px;
                padding: 4px;
                margin-bottom: 2rem;
                flex-wrap: wrap;
            }

            .tab-button {
                padding: 0.75rem 1.5rem;
                border: none;
                background: transparent;
                color: var(--grey);
                font-weight: 500;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s ease;
                text-decoration: none;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .tab-button:hover {
                background: rgba(0, 0, 0, 0.05);
            }

            .tab-button.active {
                background: var(--white);
                color: var(--black);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            /* Cards */
            .order-card {
                border: 1px solid var(--border);
                border-radius: 8px;
                overflow: hidden;
                margin-bottom: 1.5rem;
                background: var(--white);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }

            .order-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            .order-card-header {
                padding: 1rem;
                background: var(--light-grey);
                border-bottom: 1px solid var(--border);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .order-card-body {
                padding: 1rem;
            }

            .order-card-footer {
                padding: 1rem;
                border-top: 1px solid var(--border);
                background: var(--light-grey);
            }

            /* Badges */
            .badge {
                display: inline-block;
                padding: 0.35em 0.65em;
                font-size: 0.75em;
                font-weight: 600;
                line-height: 1;
                text-align: center;
                white-space: nowrap;
                vertical-align: baseline;
                border-radius: 0.25rem;
            }

            .badge-secondary {
                background-color: #e2e3e5;
                color: #383d41;
            }

            .badge-info {
                background-color: #d1ecf1;
                color: #0c5460;
            }

            .badge-primary {
                background-color: #cfe2ff;
                color: #084298;
            }

            .badge-success {
                background-color: #d4edda;
                color: #155724;
            }

            .badge-danger {
                background-color: #f8d7da;
                color: #721c24;
            }

            .badge-warning {
                background-color: #fff3cd;
                color: #856404;
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 3rem;
                background: var(--white);
                border-radius: 10px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                border: 1px solid var(--border);
            }

            .empty-state i {
                font-size: 3rem;
                color: var(--grey);
                margin-bottom: 1rem;
            }

            .empty-state h4 {
                margin-bottom: 0.5rem;
                color: var(--dark-grey);
            }

            .empty-state p {
                color: var(--grey);
                margin-bottom: 1.5rem;
            }

            /* Form Elements */
            .form-select {
                padding: 0.5rem;
                border: 1px solid var(--border);
                border-radius: 6px;
                font-size: 0.875rem;
                background: var(--white);
            }

            /* Responsive */
            @media (max-width: 768px) {
                .dashboard-container {
                    grid-template-columns: 1fr;
                }

                .sidebar {
                    height: auto;
                    position: relative;
                    padding: 1rem;
                }

                .brand {
                    margin-bottom: 1rem;
                }

                .nav-menu {
                    flex-direction: row;
                    flex-wrap: wrap;
                    gap: 8px;
                }

                .nav-item {
                    padding: 0.5rem;
                    font-size: 0.875rem;
                    width: auto;
                }

                .main-content {
                    padding: 1rem;
                }

                .header {
                    flex-direction: column;
                    gap: 1rem;
                    align-items: flex-start;
                }

                .nav-tabs {
                    flex-direction: column;
                }

                .tab-button {
                    width: 100%;
                    justify-content: center;
                }

                .order-card-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 0.5rem;
                }

                .order-card-footer .d-flex {
                    flex-direction: column;
                    gap: 1rem;
                }

                .order-card-footer .btn-group {
                    width: 100%;
                }

                .order-card-footer .btn {
                    width: 100%;
                    justify-content: center;
                }
            }

            /* Stats Cards */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
                margin-bottom: 2rem;
            }

            .stat-card {
                background: var(--white);
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 1.5rem;
                text-align: center;
            }

            .stat-icon {
                font-size: 2rem;
                color: var(--primary);
                margin-bottom: 1rem;
            }

            .stat-value {
                font-size: 2rem;
                font-weight: 700;
                color: var(--black);
                margin-bottom: 0.5rem;
            }

            .stat-label {
                color: var(--grey);
                font-size: 0.875rem;
            }

            /* Fix for image display */
            .book-image {
                width: 60px;
                height: 80px;
                object-fit: cover;
                border-radius: 4px;
            }

            .book-image-placeholder {
                width: 60px;
                height: 80px;
                background: var(--light-grey);
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--grey);
            }
        </style>
    </head>

    <body>
        <div class="dashboard-container">
            <!-- Sidebar -->
            <aside class="sidebar">
                <a href="index.php" class="brand">
                    <i class="fas fa-book"></i>
                    <h1><?php echo SITE_NAME; ?></h1>
                </a>

                <nav class="nav-menu">
                    <a href="dashboard.php" class="nav-item">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="my-books.php" class="nav-item">
                        <i class="fas fa-book"></i>
                        <span>My Books</span>
                    </a>
                    <a href="sell-book.php" class="nav-item">
                        <i class="fas fa-plus"></i>
                        <span>Sell a Book</span>
                    </a>
                    <a href="my-orders.php" class="nav-item active">
                        <i class="fas fa-shopping-cart"></i>
                        <span>My Orders</span>
                    </a>
                    <a href="my-cart.php" class="nav-item">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Shopping Cart</span>
                    </a>
                    <a href="my-disputes.php" class="nav-item">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Disputes</span>
                    </a>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                    <a href="logout.php" class="nav-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <!-- Header -->
                <header class="header">
                    <h2><?php echo $page_title; ?></h2>
                    <div class="user-menu">
                        <span>Welcome, <?php echo htmlspecialchars($user_data['first_name']); ?></span>
                        <div class="user-avatar" title="<?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>">
                            <?php if (!empty($user_data['profile_picture_path']) && file_exists($user_data['profile_picture_path'])): ?>
                                <img src="<?php echo htmlspecialchars($user_data['profile_picture_path']); ?>"
                                    alt="Profile Picture"
                                    onerror="handleImageError(this)">
                            <?php else: ?>
                                <span class="user-initial"><?php echo strtoupper(substr($user_data['first_name'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </header>

                <!-- Flash Messages -->
                <?php display_flash_message(); ?>

                <?php if ($tab === 'sales'): ?>
                    <!-- Sales Stats -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-value">
                                <?php
                                // Count pending orders
                                $pending_count = 0;
                                foreach ($orders as $order) {
                                    if (in_array($order['order_status'] ?? $order['status'] ?? '', ['pending', 'pending_payment', 'paid', 'confirmed'])) {
                                        $pending_count++;
                                    }
                                }
                                echo $pending_count;
                                ?>
                            </div>
                            <div class="stat-label">Pending Orders</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-value">
                                <?php
                                // Calculate total sales
                                $total_sales = 0;
                                foreach ($orders as $order) {
                                    if (in_array($order['order_status'] ?? $order['status'] ?? '', ['delivered'])) {
                                        $total_sales += $order['total_amount'];
                                    }
                                }
                                echo format_price($total_sales);
                                ?>
                            </div>
                            <div class="stat-label">Total Sales</div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="content-section">
                    <!-- Tabs -->
                    <div class="nav-tabs">
                        <a href="?tab=purchases" class="tab-button <?php echo $tab === 'purchases' ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-bag"></i> My Purchases
                        </a>
                        <a href="?tab=sales" class="tab-button <?php echo $tab === 'sales' ? 'active' : ''; ?>">
                            <i class="fas fa-dollar-sign"></i> My Sales
                        </a>
                    </div>

                    <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart"></i>
                            <h4>No orders found</h4>
                            <p>
                                <?php echo $tab === 'purchases' ? 'You haven\'t made any purchases yet.' : 'You haven\'t made any sales yet.'; ?>
                            </p>
                            <?php if ($tab === 'purchases'): ?>
                                <a href="browse.php" class="btn btn-primary">Browse Books</a>
                            <?php else: ?>
                                <a href="sell-book.php" class="btn btn-primary">List a Book</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="orders-grid">
                            <?php foreach ($orders as $order_item): ?>
                                <div class="order-card">
                                    <div class="order-card-header">
                                        <div>
                                            <h6 style="margin: 0;">Order #<?php echo $order_item['id']; ?></h6>
                                            <small class="text-muted">
                                                <?php
                                                // Check if it's a multi-item order
                                                if (isset($order_item['quantity'])) {
                                                    echo $order_item['quantity'] . ' item' . ($order_item['quantity'] > 1 ? 's' : '');
                                                } else {
                                                    echo '1 item';
                                                }
                                                ?>
                                            </small>
                                        </div>
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

                                        // Get status - check both order_status and status fields
                                        $status = $order_item['order_status'] ?? $order_item['status'] ?? 'pending';
                                        $color = $status_colors[$status] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?php echo $color; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                        </span>
                                    </div>
                                    <div class="order-card-body">
                                        <div class="d-flex">
                                            <?php if (!empty($order_item['image_url'])): ?>
                                                <img src="<?php echo UPLOAD_PATH . $order_item['image_url']; ?>"
                                                    alt="Book cover" class="book-image me-3"
                                                    onerror="handleImageError(this)">
                                            <?php else: ?>
                                                <div class="book-image-placeholder me-3">
                                                    <i class="fas fa-book"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <h6><?php echo htmlspecialchars($order_item['book_title'] ?? 'Multiple Items'); ?></h6>
                                                <p class="text-muted small mb-1">by <?php echo htmlspecialchars($order_item['book_author'] ?? 'Unknown Author'); ?></p>
                                                <p class="mb-1">
                                                    <strong>Total:</strong> <?php echo format_price($order_item['total_amount'] ?? 0); ?>
                                                </p>
                                                <p class="small text-muted mb-1">
                                                    <?php if ($tab === 'purchases'): ?>
                                                        <strong>Seller:</strong> <?php echo htmlspecialchars($order_item['seller_name'] ?? 'Unknown Seller'); ?>
                                                    <?php else: ?>
                                                        <strong>Buyer:</strong> <?php echo htmlspecialchars($order_item['buyer_name'] ?? 'Unknown Buyer'); ?>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="small text-muted">
                                                    <strong>Ordered:</strong> <?php echo simple_time_ago($order_item['created_at'] ?? date('Y-m-d H:i:s')); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="order-card-footer">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                                            <div>
                                                <?php if ($tab === 'sales' && in_array($status, ['paid', 'confirmed'])): ?>
                                                    <form method="POST" class="d-inline-flex align-items-center gap-2 flex-wrap">
                                                        <input type="hidden" name="order_id" value="<?php echo $order_item['id']; ?>">
                                                        <select name="new_status" class="form-select">
                                                            <?php if ($status === 'paid'): ?>
                                                                <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirm Order</option>
                                                            <?php endif; ?>
                                                            <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Mark as Shipped</option>
                                                            <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Mark as Delivered</option>
                                                        </select>
                                                        <button type="submit" name="update_status" class="btn btn-sm btn-primary">Update</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                            <div class="btn-group mt-2 mt-md-0">
                                                <a href="order-details.php?id=<?php echo $order_item['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> Details
                                                </a>
                                                <?php if ($status === 'delivered' && $tab === 'purchases'): ?>
                                                    <a href="create-dispute.php?order_id=<?php echo $order_item['id']; ?>" class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-exclamation-triangle"></i> Report
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Function to handle image errors
            function handleImageError(img) {
                img.style.display = 'none';
                const parent = img.parentNode;
                const fallback = document.createElement('div');

                if (img.classList.contains('user-avatar')) {
                    fallback.className = 'user-initial';
                    fallback.style.width = '40px';
                    fallback.style.height = '40px';
                    fallback.style.fontSize = '1.2rem';
                    fallback.textContent = '<?php echo strtoupper(substr($user_data["first_name"], 0, 1)); ?>';
                } else {
                    fallback.className = 'book-image-placeholder me-3';
                    fallback.style.width = '60px';
                    fallback.style.height = '80px';
                    fallback.innerHTML = '<i class="fas fa-book"></i>';
                }

                parent.appendChild(fallback);
            }

            // Add error handlers to all images
            document.addEventListener('DOMContentLoaded', function() {
                const bookImages = document.querySelectorAll('img[alt="Book cover"]');
                bookImages.forEach(img => {
                    img.onerror = function() {
                        handleImageError(this);
                    };
                });

                const profileImages = document.querySelectorAll('.user-avatar img');
                profileImages.forEach(img => {
                    img.onerror = function() {
                        handleImageError(this);
                    };
                });

                // Auto-dismiss flash messages after 5 seconds
                const flashMessages = document.querySelectorAll('.alert');
                flashMessages.forEach(message => {
                    setTimeout(() => {
                        const bsAlert = new bootstrap.Alert(message);
                        bsAlert.close();
                    }, 5000);
                });
            });

            // Confirm status update
            document.addEventListener('submit', function(e) {
                if (e.target.matches('form[method="POST"]')) {
                    if (!confirm('Are you sure you want to update the order status?')) {
                        e.preventDefault();
                    }
                }
            });

            // Mobile menu toggle for sidebar
            function toggleMobileMenu() {
                const sidebar = document.querySelector('.sidebar');
                const mainContent = document.querySelector('.main-content');

                if (window.innerWidth <= 768) {
                    sidebar.style.display = 'none';
                    mainContent.style.gridColumn = '1 / -1';
                } else {
                    sidebar.style.display = 'block';
                    mainContent.style.gridColumn = '2 / -1';
                }
            }

            // Initial check
            toggleMobileMenu();

            // Check on resize
            window.addEventListener('resize', toggleMobileMenu);
        </script>
    </body>

    </html>

<?php
} else {
    // Redirect to homepage if accessed directly
    redirect('index.php');
}
?>