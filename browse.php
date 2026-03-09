<?php
// Advanced browse page with filtering and sorting
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/Book.php';
require_once 'models/Category.php';
require_once 'models/Cart.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();
$book = new Book($db);
$category = new Category($db);

// Get filter and search parameters with validation
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$category_id = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?? '';
$condition = filter_input(INPUT_GET, 'condition', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$min_price = filter_input(INPUT_GET, 'min_price', FILTER_VALIDATE_FLOAT) ?? '';
$max_price = filter_input(INPUT_GET, 'max_price', FILTER_VALIDATE_FLOAT) ?? '';
$sort_by = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'newest';
$view_mode = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'grid';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
    'options' => ['default' => 1, 'min_range' => 1]
]);

$offset = ($page - 1) * ITEMS_PER_PAGE;

// Get cart items only if logged in
$cart_items = []; // Fixed variable name
$cart_book_ids = [];
$display_name = 'User'; // default
$cart_count = 0; // Added cart count

if (is_logged_in()) {
    $cart_model = new Cart($db);
    $user_id = get_current_user_id();
    $admin_id = get_current_admin_id();

    // Fetch cart only for users (not admins)
    if ($user_id) {
        $cart_items = $cart_model->getUserCart($user_id); // Fixed method name
        $cart_book_ids = array_column($cart_items, 'book_id');
        $cart_count = count($cart_items); // Count cart items
    }

    // Get user/admin name
    $current_user = null;
    if ($user_id) {
        $query = "SELECT username FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($admin_id) {
        $query = "SELECT username FROM admins WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$admin_id]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $display_name = $current_user ? htmlspecialchars($current_user['username']) : 'users';
}

// Get books with advanced filtering
$books = $book->getBooksWithFilters([
    'search' => $search,
    'category_id' => $category_id,
    'condition' => $condition,
    'min_price' => $min_price,
    'max_price' => $max_price,
    'sort_by' => $sort_by,
    'limit' => ITEMS_PER_PAGE,
    'offset' => $offset
]);

$categories = $category->getAllCategories();

// Get total count for pagination
$total_books = $book->getFilteredBooksCount([
    'search' => $search,
    'category_id' => $category_id,
    'condition' => $condition,
    'min_price' => $min_price,
    'max_price' => $max_price
]);

$total_pages = ceil($total_books / ITEMS_PER_PAGE);

// Condition options
$conditions = [
    'new' => 'New',
    'like_new' => 'Like New',
    'very_good' => 'Very Good',
    'good' => 'Good',
    'acceptable' => 'Acceptable'
];

// Sort options
$sort_options = [
    'newest' => 'Newest First',
    'oldest' => 'Oldest First',
    'price_low' => 'Price: Low to High',
    'price_high' => 'Price: High to Low',
    'title_asc' => 'Title: A to Z',
    'title_desc' => 'Title: Z to A',
    'rating' => 'Seller Rating'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Books - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            color: #000000;
            line-height: 1.6;
            min-height: 100vh;
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
            background: #dc3545;
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

        /* Main Layout */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px;
            display: flex;
            gap: 32px;
            min-height: calc(100vh - 80px);
        }

        /* Left Sidebar */
        .sidebar {
            width: 280px;
            flex-shrink: 0;
            position: sticky;
            top: 96px;
            height: fit-content;
            max-height: calc(100vh - 128px);
            overflow-y: auto;
        }

        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Filter Card */
        .filter-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .filter-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #000000;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .clear-filters {
            background: none;
            border: none;
            color: #666666;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.3s ease;
        }

        .clear-filters:hover {
            color: #000000;
        }

        .filter-section {
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .filter-section h4 {
            font-size: 14px;
            font-weight: 600;
            color: #666666;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control,
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #ffffff;
            color: #000000;
            font-family: inherit;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #000000;
            box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.1);
            outline: none;
        }

        .price-range {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 12px;
            align-items: center;
        }

        .price-range span {
            color: #666666;
            font-weight: 500;
            text-align: center;
        }

        .apply-filters {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, #000000 0%, #2d2d2d 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            margin-top: 24px;
        }

        .apply-filters:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        /* Main Content Area */
        .content-area {
            flex: 1;
            min-width: 0;
            /* Prevents flex item from overflowing */
        }

        /* Results Header */
        .results-header {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 32px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .results-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .results-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: #000000;
        }

        .results-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #666666;
            font-size: 14px;
        }

        .controls {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .view-toggle {
            display: flex;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 4px;
        }

        .view-toggle-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            color: #666666;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .view-toggle-btn.active {
            background: #000000;
            color: #ffffff;
        }

        .sell-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #000000 0%, #2d2d2d 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .sell-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            color: #ffffff;
        }

        /* Books Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 48px;
        }

        .list-view .books-grid {
            grid-template-columns: 1fr;
        }

        /* Book Card */
        .book-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 100%;
        }

        .book-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .book-image-container {
            position: relative;
            height: 240px;
            overflow: hidden;
        }

        .book-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .book-card:hover .book-image {
            transform: scale(1.05);
        }

        .book-image-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .condition-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(0, 0, 0, 0.8);
            color: #ffffff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .book-content {
            padding: 24px;
        }

        .book-title {
            font-size: 16px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .book-author {
            font-size: 14px;
            color: #666666;
            margin-bottom: 16px;
        }

        .book-price {
            font-size: 20px;
            font-weight: 800;
            color: #000000;
            margin-bottom: 16px;
        }

        .book-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #666666;
            margin-bottom: 20px;
        }

        .rating {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .rating i {
            color: #ffc107;
        }

        .book-actions {
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding-top: 20px;
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #000000 0%, #2d2d2d 100%);
            color: #ffffff;
            flex: 1;
            justify-content: center;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .btn-cart {
            background: transparent;
            border: 2px solid #e0e0e0;
            color: #666666;
            flex: 1;
            justify-content: center;
        }

        .btn-cart:hover {
            background: #f8f9fa;
            border-color: #000000;
            color: #000000;
        }

        .btn-cart.active {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border-color: #dc3545;
            color: #ffffff;
        }

        .btn-cart.active:hover {
            background: linear-gradient(135deg, #c82333 0%, #b21f2d 100%);
            border-color: #b21f2d;
        }

        /* List View Specific */
        .list-view .book-card {
            display: flex;
            height: auto;
        }

        .list-view .book-image-container {
            width: 200px;
            height: auto;
            flex-shrink: 0;
        }

        .list-view .book-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .list-view .book-actions {
            margin-top: auto;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 48px;
        }

        .pagination {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .page-link {
            padding: 12px 20px;
            background: #ffffff;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            color: #000000;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }

        .page-link.active {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 0;
        }

        .empty-state i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 24px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #000000;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: #666666;
            font-size: 16px;
            margin-bottom: 24px;
        }

        /* Footer */
        .footer {
            background: #000000;
            color: #ffffff;
            padding: 60px 0 40px;
            margin-top: 80px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
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
            max-width: 1200px;
            margin: 0 auto;
            padding-left: 24px;
            padding-right: 24px;
        }

        /* Flash Messages */
        .flash-message {
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
            max-width: 1200px;
            margin: 0 auto 24px;
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

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-container {
                flex-direction: column;
                gap: 24px;
            }

            .sidebar {
                width: 100%;
                position: static;
                max-height: none;
            }

            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .navbar-nav {
                display: none;
            }

            .mobile-menu {
                display: block;
            }

            .main-container {
                padding: 24px 16px;
            }

            .results-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .controls {
                width: 100%;
                justify-content: space-between;
            }

            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }

            .list-view .book-card {
                flex-direction: column;
            }

            .list-view .book-image-container {
                width: 100%;
                height: 200px;
            }

            .book-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .books-grid {
                grid-template-columns: 1fr;
            }

            .price-range {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .price-range span {
                display: none;
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
                <li><a href="browse.php" class="nav-link active">Browse Books</a></li>

                <?php if (is_logged_in()): ?>
                    <li>
                        <a href="my-cart.php" class="nav-cart">
                            <i class="fas fa-shopping-cart"></i>
                            Cart
                            <?php if (!empty($cart_items)): ?>
                                <span class="cart-count"><?php echo count($cart_items); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="dropdown">
                        <a href="#" class="nav-link">
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($display_name); ?>
                        </a>
                        <div class="dropdown-menu">
                            <a href="dashboard.php" class="dropdown-item">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <a href="my-cart.php" class="dropdown-item">
                                <i class="fas fa-heart"></i> My cart
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

    <?php display_flash_message(); ?>

    <div class="main-container">
        <!-- Left Sidebar with Filters -->
        <aside class="sidebar">
            <div class="filter-card">
                <div class="filter-header">
                    <h3><i class="fas fa-filter"></i> Filters</h3>
                    <a href="browse.php" class="clear-filters">
                        <i class="fas fa-times"></i>
                        Clear All
                    </a>
                </div>

                <form method="GET" action="browse.php" id="filterForm">
                    <!-- Search -->
                    <div class="filter-section">
                        <h4>Search</h4>
                        <input type="text" name="search" class="form-control"
                            placeholder="Title, author, ISBN..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <!-- Category -->
                    <div class="filter-section">
                        <h4>Category</h4>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>"
                                    <?php echo ($category_id == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Condition -->
                    <div class="filter-section">
                        <h4>Condition</h4>
                        <select name="condition" class="form-select">
                            <option value="">Any Condition</option>
                            <?php foreach ($conditions as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>"
                                    <?php echo ($condition == $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Price Range -->
                    <div class="filter-section">
                        <h4>Price Range</h4>
                        <div class="price-range">
                            <input type="number" name="min_price" class="form-control"
                                placeholder="Min" value="<?php echo htmlspecialchars($min_price); ?>"
                                min="0" step="0.01">
                            <span>to</span>
                            <input type="number" name="max_price" class="form-control"
                                placeholder="Max" value="<?php echo htmlspecialchars($max_price); ?>"
                                min="0" step="0.01">
                        </div>
                    </div>

                    <!-- Sort By -->
                    <div class="filter-section">
                        <h4>Sort By</h4>
                        <select name="sort_by" class="form-select">
                            <?php foreach ($sort_options as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>"
                                    <?php echo ($sort_by == $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">

                    <button type="submit" class="apply-filters">
                        <i class="fas fa-search"></i>
                        Apply Filters
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="content-area">
            <!-- Results Header -->
            <div class="results-header">
                <div class="results-title">
                    <h1>Browse Books</h1>
                    <div class="controls">
                        <div class="view-toggle">
                            <button class="view-toggle-btn <?php echo $view_mode == 'grid' ? 'active' : ''; ?>"
                                onclick="setViewMode('grid')">
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button class="view-toggle-btn <?php echo $view_mode == 'list' ? 'active' : ''; ?>"
                                onclick="setViewMode('list')">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>

                        <?php if (is_logged_in()): ?>
                            <a href="sell-book.php" class="sell-btn">
                                <i class="fas fa-plus"></i>
                                Sell a Book
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="results-info">
                    <div>
                        <strong><?php echo number_format($total_books); ?></strong>
                        book<?php echo $total_books != 1 ? 's' : ''; ?> found
                        <?php if ($total_pages > 1): ?>
                            • Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        <?php endif; ?>
                        <?php if ($search || $category_id || $condition || $min_price || $max_price): ?>
                            • Filtered Results
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Books Display -->
            <div class="<?php echo $view_mode == 'list' ? 'list-view' : ''; ?>">
                <?php if (empty($books)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search fa-3x"></i>
                        <h3>No books found</h3>
                        <p>Try adjusting your filters or search terms.</p>
                        <a href="browse.php" class="btn btn-primary">
                            <i class="fas fa-book"></i>
                            View All Books
                        </a>
                    </div>
                <?php else: ?>
                    <div class="books-grid">
                        <?php foreach ($books as $book_item): ?>
                            <div class="book-card">
                                <div class="book-image-container">
                                    <?php if ($book_item['image_url']): ?>
                                        <img src="<?php echo UPLOAD_PATH . htmlspecialchars($book_item['image_url']); ?>"
                                            class="book-image" alt="Book cover">
                                    <?php else: ?>
                                        <div class="book-image-placeholder">
                                            <i class="fas fa-book fa-3x"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="condition-badge">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $book_item['condition_type']))); ?>
                                    </div>
                                </div>

                                <div class="book-content">
                                    <h3 class="book-title"><?php echo htmlspecialchars($book_item['title']); ?></h3>
                                    <p class="book-author">by <?php echo htmlspecialchars($book_item['author']); ?></p>
                                    <div class="book-price"><?php echo format_price($book_item['price']); ?></div>
                                    <div class="book-meta">
                                        <div class="rating">
                                            <?php
                                            $rating = $book_item['average_rating'] ?? 0;
                                            $rating_count = $book_item['rating_count'] ?? 0;
                                            $fullStars = floor($rating);
                                            $hasHalfStar = ($rating - $fullStars) >= 0.5;

                                            // Display stars
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $fullStars) {
                                                    echo '<i class="fas fa-star"></i>';
                                                } elseif ($i == $fullStars + 1 && $hasHalfStar) {
                                                    echo '<i class="fas fa-star-half-alt"></i>';
                                                } else {
                                                    echo '<i class="far fa-star"></i>';
                                                }
                                            }
                                            ?>
                                            <span class="rating-text">
                                                <?php echo number_format($rating, 1); ?>
                                                <?php if ($rating_count > 0): ?>
                                                    <small>(<?php echo $rating_count; ?>)</small>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div><?php echo htmlspecialchars($book_item['seller_name']); ?></div>
                                    </div>
                                    <div class="book-actions">
                                        <a href="book-details.php?id=<?php echo (int)$book_item['id']; ?>"
                                            class="btn btn-primary">
                                            View Details
                                        </a>
                                        <?php if (is_logged_in()): ?>
                                            <button class="btn btn-cart <?php echo in_array($book_item['id'], $cart_book_ids) ? 'active' : ''; ?>"
                                                data-book-id="<?php echo (int)$book_item['id']; ?>"
                                                onclick="toggleCart(<?php echo (int)$book_item['id']; ?>, this)">
                                                <i class="fas fa-heart"></i>
                                                <span><?php echo in_array($book_item['id'], $cart_book_ids) ? 'Remove' : 'cart'; ?></span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo build_pagination_url($page - 1); ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a href="?<?php echo build_pagination_url($i); ?>"
                                class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo build_pagination_url($page + 1); ?>" class="page-link">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Footer -->
    <footer class="footer">
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
                        <li><a href="my-cart.php">My cart</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?>. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu').addEventListener('click', function() {
            const navLinks = document.querySelector('.navbar-nav');
            navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
        });

        // Set view mode
        function setViewMode(mode) {
            const form = document.getElementById('filterForm');
            const viewInput = form.querySelector('input[name="view"]');
            viewInput.value = mode;
            form.submit();
        }

        // Auto-submit form when sort or category changes
        document.querySelectorAll('select[name="sort_by"], select[name="category"], select[name="condition"]').forEach(select => {
            select.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });

        // Price range validation
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            const minPrice = parseFloat(this.elements['min_price'].value);
            const maxPrice = parseFloat(this.elements['max_price'].value);

            if (minPrice && maxPrice && minPrice > maxPrice) {
                alert('Minimum price cannot be greater than maximum price');
                e.preventDefault();
            }
        });

        // Cart functionality
        async function toggleCart(bookId, button) {
            // Check if user is logged in
            const isLoggedIn = <?php echo (is_logged_in() && get_current_user_id()) ? 'true' : 'false'; ?>;
            if (!isLoggedIn) {
                showFlashMessage('Please log in to add books to your cart.', 'error');
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname);
                return;
            }

            const isAdding = !button.classList.contains('active');
            const icon = button.querySelector('i');
            const span = button.querySelector('span');

            // Immediately toggle the UI state
            button.classList.toggle('active');
            if (isAdding) {
                icon.className = 'fas fa-shopping-cart';
                span.textContent = 'Remove from Cart';
            } else {
                icon.className = 'fas fa-cart-plus';
                span.textContent = 'Add to Cart';
            }

            try {
                const formData = new FormData();
                formData.append('book_id', bookId);
                formData.append('action', isAdding ? 'add' : 'remove');

                const response = await fetch('cart-api.php', { // Make sure this file exists
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Cart API response:', data);

                if (!data.success) {
                    // If the request failed, revert the UI state
                    button.classList.toggle('active');
                    if (isAdding) {
                        icon.className = 'fas fa-cart-plus';
                        span.textContent = 'Add to Cart';
                    } else {
                        icon.className = 'fas fa-shopping-cart';
                        span.textContent = 'Remove from Cart';
                    }
                    throw new Error(data.message || 'Action failed');
                }

                // Update cart count in navbar
                updateCartCount(data.cart_count || 0);

                // Show success message
                if (isAdding) {
                    showFlashMessageWithAction(
                        data.message,
                        'success',
                        'View Cart',
                        'my-cart.php'
                    );
                } else {
                    showFlashMessage(data.message, 'success');
                }

            } catch (error) {
                console.error('Cart error:', error);
                showFlashMessage(error.message || 'Failed to update cart. Please try again.', 'error');
            }
        }

        // Function to update cart count
        function updateCartCount(count) {
            console.log('Updating cart count to:', count);

            // Update cart count in navbar
            const cartCountElements = document.querySelectorAll('.cart-count');
            const cartNav = document.querySelector('.nav-cart');
            const dropdownBadge = document.querySelector('.dropdown-item .cart-count');

            if (count > 0) {
                // Update or create badge in navbar
                if (cartNav) {
                    let badge = cartNav.querySelector('.cart-count');
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'cart-count';
                        cartNav.appendChild(badge);
                    }
                    badge.textContent = count;
                    badge.style.display = 'flex';
                }

                // Update dropdown badge
                if (dropdownBadge) {
                    dropdownBadge.textContent = count;
                    dropdownBadge.style.display = 'inline-block';
                }
            } else {
                // Hide cart count badges
                cartCountElements.forEach(el => {
                    el.style.display = 'none';
                });

                if (dropdownBadge) {
                    dropdownBadge.style.display = 'none';
                }
            }
        }

        // Function to show flash messages with action button
        function showFlashMessageWithAction(message, type, actionText, actionUrl) {
            const flashContainer = document.querySelector('.main-content') || document.querySelector('.container');
            const flashMessage = document.createElement('div');
            flashMessage.className = `flash-message flash-${type}`;
            flashMessage.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span>${message}</span>
                    <a href="${actionUrl}" class="btn btn-primary btn-sm" style="margin-left: 16px;">
                        ${actionText}
                    </a>
                </div>
            `;
            flashContainer.insertBefore(flashMessage, flashContainer.firstChild);

            setTimeout(() => {
                flashMessage.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                flashMessage.style.opacity = '0';
                flashMessage.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    flashMessage.remove();
                }, 500);
            }, 5000);
        }

        // Function to show flash messages
        function showFlashMessage(message, type) {
            const flashContainer = document.querySelector('.main-content') || document.querySelector('.container');
            const flashMessage = document.createElement('div');
            flashMessage.className = `flash-message flash-${type}`;
            flashMessage.textContent = message;
            flashContainer.insertBefore(flashMessage, flashContainer.firstChild);

            setTimeout(() => {
                flashMessage.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                flashMessage.style.opacity = '0';
                flashMessage.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    flashMessage.remove();
                }, 500);
            }, 5000);
        }

        // Add ripple effect to buttons
        document.querySelectorAll('.btn, .apply-filters, .sell-btn, .view-toggle-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;

                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.3)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.pointerEvents = 'none';

                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);

                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>

<?php
// Helper function for condition badge colors
function getConditionColor($condition)
{
    switch ($condition) {
        case 'new':
            return 'dark';
        case 'like_new':
            return 'secondary';
        case 'very_good':
            return 'secondary';
        case 'good':
            return 'light text-dark';
        case 'acceptable':
            return 'light text-dark';
        default:
            return 'secondary';
    }
}

// Helper function to build pagination URLs
function build_pagination_url($page)
{
    $params = $_GET;
    $params['page'] = $page;
    return http_build_query($params);
}

// Helper function to build URLs with view mode
function build_query_with_view($view)
{
    $params = $_GET;
    $params['view'] = $view;
    return http_build_query($params);
}

// Helper function to format price
if (!function_exists('format_price')) {
    function format_price($price)
    {
        return 'NPR' . number_format($price, 2);
    }
}

// Helper function to display flash messages
if (!function_exists('display_flash_message')) {
    function display_flash_message()
    {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            echo '<div class="flash-message flash-' . htmlspecialchars($flash['type']) . '">'
                . htmlspecialchars($flash['message']) . '</div>';
            unset($_SESSION['flash']);
        }
    }
}
?>