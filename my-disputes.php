<?php
// my-disputes.php - View user's disputes
if (basename($_SERVER['PHP_SELF']) === 'my-disputes.php') {
    require_once 'config/database.php';
    require_once 'config/config.php';
    require_once 'models/Dispute.php';
    require_once 'models/User.php';

    if (!is_logged_in()) {
        redirect('login.php');
    }

    $database = new Database();
    $db = $database->getConnection();
    $dispute = new Dispute($db);
    $user = new User($db);

    $user_id = get_current_user_id();
    $disputes = $dispute->getDisputeById($user_id);
    $user_data = $user->getUserById($user_id);
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>My Disputes - <?php echo SITE_NAME; ?></title>
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

            /* Disputes Content */
            .disputes-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 1.5rem;
            }

            .dispute-card {
                background: var(--white);
                border-radius: 10px;
                padding: 1.5rem;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                border: 1px solid var(--border);
                transition: transform 0.2s ease;
            }

            .dispute-card:hover {
                transform: translateY(-2px);
            }

            .dispute-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1rem;
            }

            .dispute-card-header h3 {
                font-size: 1rem;
                font-weight: 600;
                color: var(--black);
            }

            .dispute-card-body {
                margin-bottom: 1rem;
            }

            .dispute-card-footer {
                display: flex;
                justify-content: flex-end;
            }

            .badge {
                display: inline-block;
                padding: 0.25rem 0.5rem;
                border-radius: 4px;
                font-size: 0.75rem;
                font-weight: 600;
            }

            .badge-open {
                background: #ffebee;
                color: #c62828;
            }

            .badge-under_review {
                background: #fff8e1;
                color: #ff8f00;
            }

            .badge-resolved {
                background: #e6f7e6;
                color: #2e7d32;
            }

            .badge-closed {
                background: #e0e0e0;
                color: #424242;
            }

            .btn {
                padding: 0.5rem 1rem;
                border-radius: 6px;
                font-size: 0.875rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
                text-decoration: none;
                display: inline-block;
            }

            .btn-outline {
                background: transparent;
                border: 1px solid var(--border);
                color: var(--dark-grey);
            }

            .btn-outline:hover {
                background: var(--light-grey);
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 3rem 0;
                color: var(--grey);
            }

            .empty-state i {
                font-size: 3rem;
                margin-bottom: 1rem;
                color: #2e7d32;
            }

            .empty-state h4 {
                font-size: 1.25rem;
                margin-bottom: 0.5rem;
                color: var(--dark-grey);
            }

            .empty-state p {
                margin-bottom: 1.5rem;
                color: var(--grey);
            }

            /* Modal */
            .modal-content {
                border-radius: 10px;
                border: none;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            }

            .modal-header {
                border-bottom: 1px solid var(--border);
                padding: 1.5rem;
            }

            .modal-body {
                padding: 1.5rem;
            }

            .modal-footer {
                border-top: 1px solid var(--border);
                padding: 1rem 1.5rem;
            }

            .info-block {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 1rem;
                margin-bottom: 1rem;
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
                    <a href="my-orders.php" class="nav-item">
                        <i class="fas fa-shopping-cart"></i>
                        <span>My Orders</span>
                    </a>
                    <a href="my-cart.php" class="nav-item">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Shopping Cart</span>
                    </a>
                    <a href="my-disputes.php" class="nav-item active">
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
                    <h2>My Disputes</h2>
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

                <!-- Disputes Content -->
                <?php if (empty($disputes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-handshake"></i>
                        <h4>No disputes found</h4>
                        <p>Great! You haven't had any issues with your transactions.</p>
                        <a href="my-orders.php" class="btn btn-outline">View My Orders</a>
                    </div>
                <?php else: ?>
                    <div class="disputes-grid">
                        <?php foreach ($disputes as $dispute_item): ?>
                            <div class="dispute-card">
                                <div class="dispute-card-header">
                                    <h3>Dispute #<?php echo $dispute_item['id']; ?></h3>
                                    <span class="badge badge-<?php echo str_replace(' ', '_', $dispute_item['status']); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $dispute_item['status'])); ?>
                                    </span>
                                </div>
                                <div class="dispute-card-body">
                                    <p><strong>Order:</strong> #<?php echo $dispute_item['order_number']; ?></p>
                                    <p><strong>Book:</strong> <?php echo htmlspecialchars($dispute_item['book_title']); ?></p>
                                    <p><strong>Type:</strong> <?php echo ucfirst($dispute_item['dispute_type']); ?></p>
                                    <p class="text-muted small mt-2">
                                        <?php echo nl2br(htmlspecialchars(substr($dispute_item['description'], 0, 100))); ?>
                                        <?php if (strlen($dispute_item['description']) > 100): ?>...<?php endif; ?>
                                    </p>
                                </div>
                                <div class="dispute-card-footer">
                                    <button class="btn btn-outline" data-bs-toggle="modal"
                                        data-bs-target="#disputeModal<?php echo $dispute_item['id']; ?>">
                                        View Details
                                    </button>
                                </div>
                            </div>

                            <!-- Dispute Details Modal -->
                            <div class="modal fade" id="disputeModal<?php echo $dispute_item['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                Dispute #<?php echo $dispute_item['id']; ?> Details
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <p><strong>Order ID:</strong> #<?php echo $dispute_item['order_number']; ?></p>
                                                    <p><strong>Book:</strong> <?php echo htmlspecialchars($dispute_item['book_title']); ?></p>
                                                    <p><strong>Dispute Type:</strong> <?php echo ucfirst($dispute_item['dispute_type']); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Status:</strong>
                                                        <span class="badge badge-<?php echo str_replace(' ', '_', $dispute_item['status']); ?>">
                                                            <?php echo ucwords(str_replace('_', ' ', $dispute_item['status'])); ?>
                                                        </span>
                                                    </p>
                                                    <p><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($dispute_item['created_at'])); ?></p>
                                                    <p><strong>Last Updated:</strong> <?php echo time_ago($dispute_item['updated_at']); ?></p>
                                                </div>
                                            </div>

                                            <div class="info-block mb-3">
                                                <strong>Your Description:</strong>
                                                <p><?php echo nl2br(htmlspecialchars($dispute_item['description'])); ?></p>
                                            </div>

                                            <?php if ($dispute_item['admin_notes']): ?>
                                                <div class="info-block mb-3">
                                                    <strong>Admin Notes:</strong>
                                                    <p><?php echo nl2br(htmlspecialchars($dispute_item['admin_notes'])); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($dispute_item['resolution'] && $dispute_item['status'] === 'resolved'): ?>
                                                <div class="info-block mb-3 bg-success bg-opacity-10">
                                                    <strong>Resolution:</strong>
                                                    <p><?php echo nl2br(htmlspecialchars($dispute_item['resolution'])); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($dispute_item['status'] === 'open'): ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-info-circle"></i>
                                                    Your dispute is waiting for admin review. You will be notified of any updates.
                                                </div>
                                            <?php elseif ($dispute_item['status'] === 'under_review'): ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-search"></i>
                                                    Admin is currently reviewing your dispute. Please check back for updates.
                                                </div>
                                            <?php elseif ($dispute_item['status'] === 'resolved'): ?>
                                                <div class="alert alert-success">
                                                    <i class="fas fa-check-circle"></i>
                                                    This dispute has been resolved by admin.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
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
    </body>

    </html>
<?php
}
?>