<?php
if (basename($_SERVER['PHP_SELF']) === 'dashboard.php') {
    require_once 'config/database.php';
    require_once 'config/config.php';
    require_once 'models/User.php';
    require_once 'models/Book.php';
    require_once 'models/Order.php';

    if (!is_logged_in()) {
        redirect('login.php');
    }

    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    $book = new Book($db);
    $order = new Order($db);

    $user_id = get_current_user_id();
    $user_data = $user->getUserById($user_id);

    if (!$user_data) {
        flash_message('User not found. Please login again.', 'error');
        redirect('logout.php');
    }

    $user_data['first_name'] = $user_data['first_name'] ?? 'users';
    $user_data['rating'] = $user_data['rating'] ?? 0;

    $my_books = $book->getSellerBooks($user_id);
    $my_purchases = $order->getUserOrders($user_id, 'buyer');
    $my_sales = $order->getUserOrders($user_id, 'seller');
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard - <?php echo SITE_NAME; ?></title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            :root {
                --black: #000000;
                --white: #ffffff;
                --dark-grey: #333333;
                --grey: #777777;
                --light-grey: #f5f5f5;
                --border: #e0e0e0;
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            }

            body {
                background-color: var(--white);
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
                border-radius: 10px;
                padding: 1.5rem;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                border: 1px solid var(--border);
            }

            .stat-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1rem;
            }

            .stat-card h3 {
                font-size: 0.875rem;
                font-weight: 500;
                color: var(--grey);
            }

            .stat-card i {
                font-size: 1.25rem;
                color: var(--grey);
            }

            .stat-card h2 {
                font-size: 1.75rem;
                font-weight: 600;
                color: var(--black);
            }

            /* Activity Cards */
            .activity-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 1.5rem;
            }

            .activity-card {
                background: var(--white);
                border-radius: 10px;
                padding: 1.5rem;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                border: 1px solid var(--border);
            }

            .activity-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1.25rem;
            }

            .activity-card-header h3 {
                font-size: 1rem;
                font-weight: 600;
                color: var(--black);
            }

            .activity-card-header i {
                color: var(--grey);
            }

            .activity-item {
                display: flex;
                justify-content: space-between;
                padding: 0.75rem 0;
                border-bottom: 1px solid var(--border);
            }

            .activity-item:last-child {
                border-bottom: none;
            }

            .activity-info {
                flex: 1;
            }

            .activity-info h4 {
                font-size: 0.875rem;
                font-weight: 600;
                margin-bottom: 0.25rem;
            }

            .activity-info p {
                font-size: 0.75rem;
                color: var(--grey);
            }

            .activity-meta {
                text-align: right;
            }

            .badge {
                display: inline-block;
                padding: 0.25rem 0.5rem;
                border-radius: 4px;
                font-size: 0.75rem;
                font-weight: 600;
            }

            .badge-success {
                background: #e6f7e6;
                color: #2e7d32;
            }

            .badge-primary {
                background: #e3f2fd;
                color: #1565c0;
            }

            .badge-warning {
                background: #fff8e1;
                color: #ff8f00;
            }

            .badge-danger {
                background: #ffebee;
                color: #c62828;
            }

            .view-all {
                display: inline-block;
                margin-top: 1rem;
                color: var(--grey);
                font-size: 0.875rem;
                text-decoration: none;
                transition: color 0.2s ease;
            }

            .view-all:hover {
                color: var(--black);
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 2rem 0;
                color: var(--grey);
            }

            .empty-state i {
                font-size: 2rem;
                margin-bottom: 1rem;
            }

            /* Flash Messages */
            .flash-message {
                padding: 1rem;
                margin-bottom: 1.5rem;
                border-radius: 6px;
                font-weight: 500;
            }

            .flash-error {
                background: #ffebee;
                color: #c62828;
                border-left: 4px solid #c62828;
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
                }

                .nav-item {
                    padding: 0.5rem;
                }
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

            .user-avatar::after {
                content: attr(data-tooltip);
                position: absolute;
                bottom: -30px;
                left: 50%;
                transform: translateX(-50%);
                background: var(--black);
                color: var(--white);
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                opacity: 0;
                transition: opacity 0.2s;
                pointer-events: none;
                white-space: nowrap;
                z-index: 100;
            }

            .user-avatar:hover::after {
                opacity: 1;
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
                    <a href="dashboard.php" class="nav-item active">
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
                    <a href="my-orders.php" class="nav-item">
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
                    <h2>Dashboard</h2>
                    <div class="user-menu">
                        <span>Welcome, <?php echo htmlspecialchars($user_data['first_name']); ?></span>
                        <div class="user-avatar" data-tooltip="<?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>">
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

                <?php display_flash_message(); ?>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h3>My Books</h3>
                            <i class="fas fa-book"></i>
                        </div>
                        <h2><?php echo count($my_books); ?></h2>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h3>Purchases</h3>
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h2><?php echo count($my_purchases); ?></h2>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h3>Sales</h3>
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h2><?php echo count($my_sales); ?></h2>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h3>Rating</h3>
                            <i class="fas fa-star"></i>
                        </div>
                        <h2><?php echo number_format($user_data['rating'], 1); ?></h2>
                    </div>
                </div>

                <!-- Activity Cards -->
                <div class="activity-grid">
                    <div class="activity-card">
                        <div class="activity-card-header">
                            <h3>Recent Purchases</h3>
                            <i class="fas fa-clock"></i>
                        </div>

                        <?php if (empty($my_purchases)): ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-cart"></i>
                                <p>No purchases yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($my_purchases, 0, 5) as $purchase): ?>
                                <div class="activity-item">
                                    <div class="activity-info">
                                        <h4>
                                            <?php echo htmlspecialchars($purchase['items'][0]['title'] ?? 'Multiple items'); ?>
                                        </h4>
                                        <p>Order #<?php echo $purchase['id']; ?></p>
                                    </div>
                                    <div class="activity-meta">
                                        <span class="badge badge-<?php echo $purchase['order_status'] === 'delivered' ? 'success' : 'primary'; ?>">
                                            <?php echo ucfirst($purchase['order_status']); ?>
                                        </span>
                                        <p><?php echo format_price($purchase['total_amount']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <a href="my-orders.php" class="view-all">View all purchases</a>
                        <?php endif; ?>
                    </div>

                    <div class="activity-card">
                        <div class="activity-card-header">
                            <h3>My Recent Books</h3>
                            <i class="fas fa-book"></i>
                        </div>

                        <?php if (empty($my_books)): ?>
                            <div class="empty-state">
                                <i class="fas fa-book-open"></i>
                                <p>No books listed yet</p>
                                <a href="sell-book.php" class="view-all">Sell your first book</a>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($my_books, 0, 5) as $my_book): ?>
                                <div class="activity-item">
                                    <div class="activity-info">
                                        <h4><?php echo htmlspecialchars($my_book['title']); ?></h4>
                                        <p><?php echo htmlspecialchars($my_book['author']); ?></p>
                                    </div>
                                    <div class="activity-meta">
                                        <span class="badge badge-<?php
                                                                    echo $my_book['status'] === 'approved' ? 'success' : ($my_book['status'] === 'pending' ? 'warning' : 'danger');
                                                                    ?>">
                                            <?php echo ucfirst($my_book['status']); ?>
                                        </span>
                                        <p><?php echo format_price($my_book['price']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <a href="my-books.php" class="view-all">View all books</a>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </body>
    <script>
        function handleImageError(img) {
            // Hide the broken image
            img.style.display = 'none';

            // Get the initial from the user's first name
            const initial = '<?php echo strtoupper(substr($user_data['first_name'], 0, 1)); ?>';

            // Create a fallback display
            const fallback = document.createElement('span');
            fallback.className = 'user-initial';
            fallback.textContent = initial;

            // Insert the fallback
            img.parentNode.insertBefore(fallback, img);
        }
    </script>

    </html>

<?php
}
?>