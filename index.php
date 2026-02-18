<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/Book.php';
require_once 'models/Category.php';
require_once 'models/Cart.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();
$book = new Book($db);
$category = new Category($db);

// Get search parameters with validation
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$category_id = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?? '';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
    'options' => ['default' => 1, 'min_range' => 1]
]);
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Get cart items only if logged in
$cart_items = [];
$cart_book_ids = [];
$display_name = 'User'; // default

if (is_logged_in()) {
    $cart_model = new Cart($db);
    $user_id = get_current_user_id();
    $admin_id = get_current_admin_id();

    // Fetch cart only for users (not admins)
    if ($user_id) {
        $cart_items = $cart_model->getUserCart($user_id);
        $cart_book_ids = array_column($cart_items, 'book_id');
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

// Get books and categories
$books = $book->getApprovedBooks($search, $category_id, ITEMS_PER_PAGE, $offset);
$categories = $category->getAllCategories();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(SITE_NAME); ?> - Buy and Sell Books Online</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #ffffff;
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
            transition: all 0.3s ease;
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
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            color: #ffffff;
            transform: translateY(-1px);
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

        .nav-link:hover {
            color: #ffffff;
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
            color: #000000;
        }

        .mobile-menu {
            display: none;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 50%, #dee2e6 100%);
            background-image: url('uploads/books/hero-bookstore.jpg');
            background-size: cover;
            background-position: center;
            position: relative;
            padding: 80px 0;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, rgba(255, 255, 255, 0.85) 20%, rgba(255, 255, 255, 0.4) 70%, rgba(255, 255, 255, 0.1) 100%);
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .hero-title {
            font-size: 48px;
            font-weight: 800;
            color: #000000;
            margin-bottom: 24px;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 20px;
            color: #666666;
            margin-bottom: 40px;
            font-weight: 400;
            line-height: 1.5;
        }

        .hero-search {
            display: flex;
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 8px;
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .hero-search:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .hero-search input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 16px 20px;
            font-size: 16px;
            outline: none;
            color: #000000;
        }

        .hero-search input::placeholder {
            color: #999999;
        }

        .hero-search button {
            background: linear-gradient(135deg, #000000 0%, #2d2d2d 100%);
            border: none;
            color: #ffffff;
            padding: 16px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .hero-search button:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Search Bar */
        .search-section {
            padding: 40px 0;
            background: #ffffff;
        }

        .search-bar {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 14px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            outline: none;
        }

        .search-input:focus {
            border-color: #000000;
            box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.1);
        }

        .search-select {
            padding: 14px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            background: #ffffff;
            min-width: 180px;
            transition: all 0.3s ease;
            outline: none;
        }

        .search-select:focus {
            border-color: #000000;
            box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.1);
        }

        .btn {
            padding: 14px 24px;
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
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            color: #ffffff;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: #ffffff;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(108, 117, 125, 0.3);
            color: #ffffff;
        }

        .btn-cart {
            background: transparent;
            border: 2px solid #e0e0e0;
            color: #666666;
        }

        .btn-cart:hover {
            background: #f8f9fa;
            border-color: #000000;
            color: #000000;
        }

        .btn-cart.active {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            border-color: #28a745;
            color: #ffffff;
        }

        .btn-cart.active:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            border-color: #1e7e34;
        }

        /* Categories */
        .categories-section {
            margin-bottom: 40px;
        }

        .categories-title {
            font-size: 18px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 16px;
        }

        .category-badge {
            display: inline-block;
            padding: 8px 16px;
            margin: 4px 8px 4px 0;
            background: rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 20px;
            color: #000000;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .category-badge:hover {
            background: #000000;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Books Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px;
            margin-bottom: 60px;
        }

        .book-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
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
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 60px;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar-nav {
                display: none;
            }

            .mobile-menu {
                display: block;
                background: none;
                border: none;
                color: #ffffff;
                font-size: 20px;
                cursor: pointer;
            }

            .hero-title {
                font-size: 32px;
            }

            .hero-subtitle {
                font-size: 18px;
            }

            .hero-search {
                flex-direction: column;
                gap: 8px;
            }

            .search-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .books-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 24px;
            }

            .book-content {
                padding: 20px;
            }

            .book-actions {
                flex-direction: column;
            }

            .book-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 16px;
            }

            .hero-section {
                padding: 60px 0;
            }

            .search-section {
                padding: 30px 0;
            }

            .books-grid {
                grid-template-columns: 1fr;
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
                <li><a href="index.php" class="nav-link active">Home</a></li>
                <li><a href="browse.php" class="nav-link">Browse Books</a></li>

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
                            <?php echo $display_name; ?>
                        </a>
                        <div class="dropdown-menu">
                            <a href="dashboard.php" class="dropdown-item">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <a href="my-orders.php" class="dropdown-item">
                                <i class="fas fa-shopping-bag"></i> My Orders
                            </a>
                            <a href="cart.php" class="dropdown-item">
                                <i class="fas fa-shopping-cart"></i> Shopping Cart
                                <?php if (!empty($cart_items)): ?>
                                    <span style="background: #dc3545; color: white; padding: 2px 6px; border-radius: 10px; font-size: 12px;">
                                        <?php echo count($cart_items); ?>
                                    </span>
                                <?php endif; ?>
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

    <!-- Hero Section -->
    <?php if (empty($search) && empty($category_id)): ?>
        <section class="hero-section">
            <div class="hero-content">
                <h1 class="hero-title">Buy and Sell Books Online</h1>
                <p class="hero-subtitle">Connect with book lovers in your community. Find rare books, textbooks, and bestsellers at great prices.</p>

                <!-- <form method="GET" action="index.php" class="hero-search">
                    <input type="text" name="search" placeholder="Search books, authors, ISBN..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form> -->
            </div>
        </section>
    <?php endif; ?>

    <div class="container">
        <div class="search-section">
            <?php display_flash_message(); ?>

            <!-- Search and Filter Bar -->
            <form method="GET" action="index.php" class="search-bar">
                <input type="text" name="search" class="search-input" placeholder="Search books..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="category" class="search-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo (int)$cat['id']; ?>" <?php echo ($category_id == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Search
                </button>
                <?php if (is_logged_in()): ?>
                    <a href="sell-book.php" class="btn btn-secondary">
                        <i class="fas fa-plus"></i>
                        Sell a Book
                    </a>
                <?php endif; ?>
            </form>

            <!-- Categories -->
            <?php if (empty($search) && empty($category_id)): ?>
                <div class="categories-section">
                    <h5 class="categories-title">Browse by Category:</h5>
                    <div>
                        <?php foreach ($categories as $cat): ?>
                            <a href="?category=<?php echo (int)$cat['id']; ?>" class="category-badge">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Books Grid -->
        <?php if (empty($books)): ?>
            <div class="empty-state">
                <i class="fas fa-book"></i>
                <h3>No books found</h3>
                <p>Try adjusting your search criteria or browse different categories.</p>
            </div>
        <?php else: ?>
            <div class="books-grid">
                <?php foreach ($books as $book_item): ?>
                    <div class="book-card">
                        <div class="book-image-container">
                            <?php if ($book_item['image_url']): ?>
                                <img src="<?php echo UPLOAD_PATH . htmlspecialchars($book_item['image_url']); ?>" class="book-image" alt="Book cover">
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
                                <a href="book-details.php?id=<?php echo (int)$book_item['id']; ?>" class="btn btn-primary">
                                    View Details
                                </a>
                                <?php if (is_logged_in()): ?>
                                    <button class="btn btn-cart <?php echo in_array($book_item['id'], $cart_book_ids) ? 'active' : ''; ?>"
                                        data-book-id="<?php echo (int)$book_item['id']; ?>"
                                        onclick="toggleCart(<?php echo (int)$book_item['id']; ?>, this)">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span><?php echo in_array($book_item['id'], $cart_book_ids) ? 'Remove from Cart' : 'Add to Cart'; ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if (count($books) >= ITEMS_PER_PAGE): ?>
            <div class="pagination-container">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <span class="page-link active"><?php echo $page; ?></span>

                    <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>" class="page-link">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
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

        // Add scroll effect to navbar
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(0, 0, 0, 0.98)';
                navbar.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
            } else {
                navbar.style.background = 'rgba(0, 0, 0, 0.95)';
                navbar.style.boxShadow = 'none';
            }
        });

        // Book card hover effects
        document.querySelectorAll('.book-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Cart functionality
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

                const response = await fetch('cart.php', {
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
                showFlashMessage(data.message, 'success');

            } catch (error) {
                console.error('Cart error:', error);
                showFlashMessage(error.message || 'Failed to update cart. Please try again.', 'error');
            }
        }
        // Function to update cart count
        // Function to update cart count
        function updateCartCount(count) {
            // Update cart count in navbar
            const cartCountElements = document.querySelectorAll('.cart-count');
            const cartNav = document.querySelector('.nav-cart');

            // If cart count element doesn't exist, create it
            if (count > 0) {
                cartCountElements.forEach(el => {
                    el.textContent = count;
                    el.style.display = 'flex';
                });

                // Ensure cart count exists in navbar if not already present
                if (cartNav && !cartNav.querySelector('.cart-count')) {
                    const countBadge = document.createElement('span');
                    countBadge.className = 'cart-count';
                    countBadge.textContent = count;
                    cartNav.appendChild(countBadge);
                }
            } else {
                // Hide cart count badges
                cartCountElements.forEach(el => {
                    el.style.display = 'none';
                });

                // Remove from dropdown menu too
                const dropdownBadges = document.querySelectorAll('.dropdown-item .cart-count');
                dropdownBadges.forEach(badge => {
                    badge.style.display = 'none';
                });
            }
        }
        // Function to show flash messages
        function showFlashMessage(message, type) {
            const flashContainer = document.querySelector('.search-section') || document.querySelector('.container');
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
        document.querySelectorAll('.btn').forEach(button => {
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