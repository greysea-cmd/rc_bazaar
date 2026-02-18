<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/Book.php';
require_once 'models/User.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

$book = new Book($db);
$user = new User($db);

$user_id = get_current_user_id();
$my_books = $book->getSellerBooks($user_id);

$user_data = $user->getUserById($user_id);

if (!$user_data) {
    flash_message('User not found. Please login again.', 'error');
    redirect('logout.php');
}

$user_data['first_name'] = $user_data['first_name'] ?? 'users';
$user_data['rating'] = $user_data['rating'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Books - <?php echo SITE_NAME; ?></title>
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

        /* Content Section */
        .content-section {
            background: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .section-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.75rem 1.25rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--black);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--dark-grey);
            text-decoration: none;
        }

        .btn-secondary {
            background: var(--light-grey);
            color: var(--dark-grey);
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
            text-decoration: none;
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            text-decoration: none;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .table th {
            font-weight: 600;
            background: var(--light-grey);
        }

        .table tr:hover {
            background: rgba(0, 0, 0, 0.02);
        }

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

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .badge-secondary {
            background-color: #e2e3e5;
            color: #383d41;
        }

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
        }

        .empty-state p {
            color: var(--grey);
            margin-bottom: 1.5rem;
        }

        /* Flash Messages */
        .flash-message {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            font-weight: 500;
        }

        .flash-success {
            background: #e6f7e6;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
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
                <a href="my-books.php" class="nav-item active">
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
                <h2>My Books</h2>
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

            <div class="content-section">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="section-header">
                        <i class="fas fa-book"></i>
                        <h3>My Book Listings</h3>
                    </div>
                    <a href="sell-book.php" class="btn btn-primary">
                        <i class="fas"></i> Add New Book
                    </a>
                </div>

                <?php if (empty($my_books)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book fa-3x"></i>
                        <h4>No books listed yet</h4>
                        <p>Start selling by adding your first book!</p>
                        <a href="sell-book.php" class="btn btn-primary">
                            <i class="fas"></i> List Your First Book
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Category</th>
                                    <th>Condition</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Listed</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_books as $book_item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($book_item['image_url']): ?>
                                                    <img src="<?php echo UPLOAD_PATH . $book_item['image_url']; ?>"
                                                        alt="Book cover" class="me-3 rounded" style="width: 50px; height: 60px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="me-3 bg-light d-flex align-items-center justify-content-center rounded"
                                                        style="width: 50px; height: 60px;">
                                                        <i class="fas fa-book text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($book_item['title']); ?></strong><br>
                                                    <small class="text-muted">by <?php echo htmlspecialchars($book_item['author']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($book_item['category_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucwords(str_replace('_', ' ', $book_item['condition_type'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_price($book_item['price']); ?></td>
                                        <td><?php echo $book_item['quantity']; ?></td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger',
                                                'sold' => 'info',
                                                'inactive' => 'secondary'
                                            ];
                                            $color = $status_colors[$book_item['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-<?php echo $color; ?>">
                                                <?php echo ucfirst($book_item['status']); ?>
                                            </span>
                                            <?php if ($book_item['admin_notes']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($book_item['admin_notes']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo time_ago($book_item['created_at']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="book-details.php?id=<?php echo $book_item['id']; ?>"
                                                    class="btn btn-secondary btn-sm" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($book_item['status'] === 'approved'): ?>
                                                    <a href="edit-book.php?id=<?php echo $book_item['id']; ?>"
                                                        class="btn btn-secondary btn-sm" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Function to handle image errors
        function handleImageError(img) {
            img.style.display = 'none';
            const fallback = document.createElement('div');
            fallback.className = 'me-3 bg-light d-flex align-items-center justify-content-center rounded';
            fallback.style.width = '50px';
            fallback.style.height = '60px';
            fallback.innerHTML = '<i class="fas fa-book text-muted"></i>';
            img.parentNode.insertBefore(fallback, img);
        }

        // Add error handlers to all book images
        document.addEventListener('DOMContentLoaded', function() {
            const bookImages = document.querySelectorAll('img[alt="Book cover"]');
            bookImages.forEach(img => {
                img.onerror = function() {
                    handleImageError(this);
                };
            });
        });
    </script>
</body>

</html>
<?php
?>