<?php
// View book details and place order
if (basename($_SERVER['PHP_SELF']) === 'book-details.php') {
    require_once 'config/database.php';
    require_once 'config/config.php';
    require_once 'models/Book.php';
    require_once 'models/Order.php';
    require_once 'models/Rating.php';
    require_once 'models/Cart.php';

    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $database = new Database();
    $db = $database->getConnection();
    $book_model = new Book($db);
    $order_model = new Order($db);
    $rating_model = new Rating($db);
    $cart_model = new Cart($db);

    $book_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? 0;
    $book_data = $book_model->getBookById($book_id);

    if (!$book_data || $book_data['status'] !== 'approved') {
        flash_message('Book not found or not available.', 'error');
        redirect('index.php');
    }

    // Get rating data (single source of truth)
    $rating_data = [];
    $user_rating = null;
    if ($book_id) {
        $rating_stmt = $db->prepare("
            SELECT AVG(r.rating) as average_rating, COUNT(r.id) as rating_count
            FROM books b
            LEFT JOIN book_ratings r ON b.id = r.book_id
            WHERE b.id = ?
            GROUP BY b.id
        ");
        $rating_stmt->execute([$book_id]);
        $rating_data = $rating_stmt->fetch(PDO::FETCH_ASSOC) ?? [];

        // Get user's rating
        if (is_logged_in() && isset($_SESSION['user_id'])) {
            $user_stmt = $db->prepare("
                SELECT rating FROM book_ratings 
                WHERE user_id = ? AND book_id = ?
            ");
            $user_stmt->execute([$_SESSION['user_id'], $book_id]);
            $user_rating_result = $user_stmt->fetch(PDO::FETCH_ASSOC);
            $user_rating = $user_rating_result ? (int)$user_rating_result['rating'] : null;
        }
    }

    // Initialize variables for cart
    $cart_items = [];
    $cart_book_ids = [];
    $cart_count = 0;
    $is_in_cart = false;
    $display_name = 'Guest';

    if (is_logged_in()) {
        $user_id = get_current_user_id();
        $cart_items = $cart_model->getUserCart($user_id);
        $cart_book_ids = array_column($cart_items, 'book_id');
        $cart_count = count($cart_items);
        $is_in_cart = in_array($book_id, $cart_book_ids);
        
        // Get user name
        $query = "SELECT username FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
        $display_name = $current_user ? htmlspecialchars($current_user['username']) : 'User';
    }

    // Handle rating submission (SINGLE handler)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
        if (!is_logged_in()) {
            flash_message('Please login to rate books', 'error');
        } else {
            $rating = (int)$_POST['rating'];
            if ($rating < 1 || $rating > 5) {
                flash_message('Invalid rating value', 'error');
            } else {
                $check_stmt = $db->prepare("SELECT id FROM book_ratings WHERE user_id = ? AND book_id = ?");
                $check_stmt->execute([$_SESSION['user_id'], $book_id]);

                if ($check_stmt->fetch()) {
                    $update_stmt = $db->prepare("UPDATE book_ratings SET rating = ?, updated_at = NOW() WHERE user_id = ? AND book_id = ?");
                    $success = $update_stmt->execute([$rating, $_SESSION['user_id'], $book_id]);
                } else {
                    $insert_stmt = $db->prepare("INSERT INTO book_ratings (user_id, book_id, rating, created_at) VALUES (?, ?, ?, NOW())");
                    $success = $insert_stmt->execute([$_SESSION['user_id'], $book_id, $rating]);
                }

                if ($success) {
                    flash_message('Rating submitted successfully!', 'success');
                    // Refresh rating data
                    $rating_stmt->execute([$book_id]);
                    $rating_data = $rating_stmt->fetch(PDO::FETCH_ASSOC) ?? [];
                    $user_rating = $rating;
                } else {
                    flash_message('Failed to submit rating', 'error');
                }
            }
        }
    }

    // Handle cart operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_action'])) {
        if (!is_logged_in()) {
            flash_message('Please login to manage cart', 'error');
        } else {
            $action = $_POST['cart_action'];
            $user_id = get_current_user_id();
            
            if ($action === 'add') {
                $success = $cart_model->addToCart($user_id, $book_id, 1);
                if ($success) {
                    flash_message('Book added to cart!', 'success');
                    $is_in_cart = true;
                    $cart_count++;
                } else {
                    flash_message('Failed to add to cart', 'error');
                }
            } elseif ($action === 'remove') {
                $success = $cart_model->removeFromCart($user_id, $book_id);
                if ($success) {
                    flash_message('Book removed from cart', 'success');
                    $is_in_cart = false;
                    $cart_count--;
                } else {
                    flash_message('Failed to remove from cart', 'error');
                }
            }
            
            // Refresh cart data
            $cart_items = $cart_model->getUserCart($user_id);
            $cart_book_ids = array_column($cart_items, 'book_id');
        }
    }

    // Handle order placement
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_submit'])) {
        $user_id = get_current_user_id();

        if ($user_id == $book_data['seller_id']) {
            flash_message('You cannot buy your own book.', 'error');
        } else {
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));
            $shipping_address = sanitize_input($_POST['shipping_address'] ?? '');
            $payment_method = $_POST['payment_method'] ?? 'cod';
            $notes = sanitize_input($_POST['notes'] ?? '');

            if (empty($shipping_address)) {
                flash_message('Shipping address is required.', 'error');
            } elseif ($quantity > $book_data['quantity']) {
                flash_message('Requested quantity not available.', 'error');
            } else {
                $order_data = [
                    'buyer_id' => $user_id,
                    'seller_id' => $book_data['seller_id'],
                    'book_id' => $book_id,
                    'quantity' => $quantity,
                    'unit_price' => $book_data['price'],
                    'total_amount' => $book_data['price'] * $quantity,
                    'shipping_address' => $shipping_address,
                    'payment_method' => $payment_method,
                    'notes' => $notes
                ];

                if ($order_model->create($order_data)) {
                    // Update book quantity
                    $new_quantity = $book_data['quantity'] - $quantity;
                    $book_model->updateQuantity($book_id, $new_quantity);
                    
                    // If book was in cart, remove it
                    if ($is_in_cart) {
                        $cart_model->removeFromCart($user_id, $book_id);
                    }

                    flash_message('Order placed successfully!', 'success');
                    
                    // If payment is eSewa, redirect to process payment
                    if ($payment_method === 'esewa') {
                        // Store order ID in session for payment processing
                        $_SESSION['pending_order_ids'] = [$order_id];
                        $_SESSION['order_total'] = $order_data['total_amount'];
                        redirect('process-esewa.php');
                    } else {
                        redirect('my-orders.php');
                    }
                } else {
                    flash_message('Failed to place order. Please try again.', 'error');
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book_data['title']); ?> - <?php echo SITE_NAME; ?></title>
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

        /* Book Details Layout */
        .book-details-container {
            padding: 48px 0;
        }

        .breadcrumb {
            margin-bottom: 32px;
        }

        .breadcrumb a {
            color: #666666;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: #000000;
        }

        .book-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            margin-bottom: 48px;
        }

        /* Book Image Section */
        .book-image-section {
            position: relative;
        }

        .book-image-container {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
        }

        .book-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .book-image-placeholder {
            width: 100%;
            height: 500px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .condition-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        /* Book Info Section */
        .book-info-section {
            padding: 24px 0;
        }

        .book-title {
            font-size: 32px;
            font-weight: 800;
            color: #000000;
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .book-author {
            font-size: 18px;
            color: #666666;
            margin-bottom: 24px;
        }

        .book-price-section {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 32px;
        }

        .book-price {
            font-size: 36px;
            font-weight: 800;
            color: #000000;
        }

        .stock-badge {
            padding: 8px 16px;
            background: #28a745;
            color: #ffffff;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .stock-badge.out-of-stock {
            background: #dc3545;
        }

        /* Book Meta */
        .book-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 32px;
            padding: 24px;
            background: #f8f9fa;
            border-radius: 16px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .meta-label {
            font-size: 12px;
            color: #666666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-value {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
        }

        /* Rating Section */
        .rating-section {
            margin-bottom: 32px;
            padding: 24px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .rating-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .average-rating {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stars {
            display: flex;
            gap: 4px;
        }

        .stars i {
            color: #ffc107;
            font-size: 18px;
        }

        .rating-count {
            color: #666666;
            font-size: 14px;
        }

        .rating-form {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .star-select {
            display: flex;
            flex-direction: row-reverse;
            gap: 8px;
            margin: 12px 0;
        }

        .star-select input {
            display: none;
        }

        .star-select label {
            font-size: 24px;
            color: #e0e0e0;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .star-select input:checked~label,
        .star-select label:hover,
        .star-select label:hover~label {
            color: #ffc107;
        }

        /* Description */
        .description-section {
            margin-bottom: 32px;
            padding: 24px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .description-section h3 {
            font-size: 18px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 16px;
        }

        .description-text {
            color: #666666;
            line-height: 1.8;
        }

        /* Actions */
        .actions-section {
            display: flex;
            gap: 16px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }

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
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
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

        /* Order Form */
        .order-form-section {
            margin-top: 48px;
            padding: 32px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .order-form-section h3 {
            font-size: 24px;
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

        .form-control,
        .form-select {
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
        .form-select:focus {
            border-color: #000000;
            box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.1);
            outline: none;
        }

        /* Payment Methods - FIXED eSewa */
        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 8px;
        }

        .payment-method {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-method input[type="radio"] {
            width: 18px;
            height: 18px;
            margin: 0;
            flex-shrink: 0;
        }

        .esewa-logo {
            width: 45px !important;
            height: 45px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .payment-method.esewa label {
            background: white;
            color: #55a854;
            border: 2px solid #55a854;
            border-radius: 8px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            flex: 1;
            max-width: 180px;
        }

        .payment-method.esewa input:checked+label {
            background: white;
            border-color: #55a854;
            box-shadow: 0 0 0 3px rgba(85, 168, 84, 0.2);
        }

        .payment-method.cod label,
        .payment-method.bank label {
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            flex: 1;
            max-width: 180px;
        }

        .payment-method input:checked+label:not(.esewa label) {
            border-color: #007bff;
            background: #f8f9ff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
        }

        /* Alerts */
        .alert {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
        }

        .alert-info {
            background: rgba(0, 123, 255, 0.1);
            border: 1px solid rgba(0, 123, 255, 0.2);
            color: #004085;
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

        /* Responsive */
        @media (max-width: 1024px) {
            .book-details-grid {
                grid-template-columns: 1fr;
                gap: 32px;
            }

            .book-image {
                height: 400px;
            }

            .book-image-placeholder {
                height: 400px;
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

            .book-title {
                font-size: 24px;
            }

            .book-price {
                font-size: 28px;
            }

            .book-meta-grid {
                grid-template-columns: 1fr;
            }

            .actions-section {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .book-image {
                height: 300px;
            }

            .book-image-placeholder {
                height: 300px;
            }

            .order-form-section {
                padding: 20px;
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
                            <?php echo htmlspecialchars($display_name); ?>
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
                            <a href="sell-book.php" class="dropdown-item">
                                <i class="fas fa-plus"></i> Sell a Book
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
        <div class="book-details-container">
            <?php display_flash_message(); ?>

            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php">Home</a> >
                <a href="browse.php">Browse Books</a> >
                <span><?php echo htmlspecialchars($book_data['title']); ?></span>
            </div>

            <!-- Book Details Grid -->
            <div class="book-details-grid">
                <!-- Book Image -->
                <div class="book-image-section">
                    <div class="book-image-container">
                        <?php if (!empty($book_data['image_url'])): ?>
                            <img src="<?php echo UPLOAD_PATH . htmlspecialchars($book_data['image_url']); ?>"
                                class="book-image" alt="Book cover">
                        <?php else: ?>
                            <div class="book-image-placeholder">
                                <i class="fas fa-book fa-5x" style="color: #6c757d;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="condition-badge">
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $book_data['condition_type']))); ?>
                        </div>
                    </div>
                </div>

                <!-- Book Info -->
                <div class="book-info-section">
                    <h1 class="book-title"><?php echo htmlspecialchars($book_data['title']); ?></h1>
                    <p class="book-author">by <?php echo htmlspecialchars($book_data['author']); ?></p>

                    <!-- Price and Stock -->
                    <div class="book-price-section">
                        <div class="book-price"><?php echo format_price($book_data['price']); ?></div>
                        <?php if ($book_data['quantity'] > 0): ?>
                            <span class="stock-badge">
                                <i class="fas fa-check-circle"></i>
                                In Stock (<?php echo $book_data['quantity']; ?> available)
                            </span>
                        <?php else: ?>
                            <span class="stock-badge out-of-stock">
                                <i class="fas fa-times-circle"></i>
                                Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Book Meta -->
                    <div class="book-meta-grid">
                        <div class="meta-item">
                            <span class="meta-label">Category</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book_data['category_name'] ?? 'Uncategorized'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">ISBN</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book_data['isbn'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Seller</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book_data['seller_name'] ?? 'Unknown'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Published</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book_data['published_year'] ?? 'Not specified'); ?></span>
                        </div>
                    </div>

                    <!-- Rating Section -->
                    <div class="rating-section">
                        <div class="rating-header">
                            <div class="average-rating">
                                <div class="stars" id="average-stars">
                                    <?php
                                    $average_rating = $rating_data['average_rating'] ?? 0;
                                    $rating_count = $rating_data['rating_count'] ?? 0;
                                    $fullStars = floor($average_rating);
                                    $hasHalfStar = ($average_rating - $fullStars) >= 0.5;

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
                                </div>
                                <span class="rating-count" id="rating-count">
                                    <?php
                                    echo number_format($average_rating, 1);
                                    echo ' (' . $rating_count . ' review' . ($rating_count != 1 ? 's' : '') . ')';
                                    ?>
                                </span>
                            </div>
                        </div>

                        <?php if (is_logged_in()): ?>
                            <div class="rating-form">
                                <div class="form-group">
                                    <label class="form-label">Your Rating</label>
                                    <form method="POST" style="display: inline;">
                                        <div class="star-select" id="star-select">
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>"
                                                    <?php echo ($user_rating == $i) ? 'checked' : ''; ?>>
                                                <label for="star<?php echo $i; ?>">★</label>
                                            <?php endfor; ?>
                                        </div>
                                        <button type="submit" class="btn btn-primary" style="margin-top: 16px;">
                                            <i class="fas fa-star"></i> Submit Rating
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                Please <a href="login.php" style="color: #004085; text-decoration: underline;">login</a> to rate this book.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($book_data['description'])): ?>
                        <div class="description-section">
                            <h3><i class="fas fa-align-left"></i> Description</h3>
                            <div class="description-text">
                                <?php echo nl2br(htmlspecialchars($book_data['description'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="actions-section">
                        <?php if ($book_data['quantity'] > 0 && is_logged_in() && get_current_user_id() != $book_data['seller_id']): ?>
                            <a href="#order-form" class="btn btn-primary">
                                <i class="fas fa-shopping-cart"></i> Buy Now
                            </a>
                            <?php if (is_logged_in()): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="cart_action" value="<?php echo $is_in_cart ? 'remove' : 'add'; ?>">
                                    <button type="submit" class="btn btn-cart <?php echo $is_in_cart ? 'active' : ''; ?>">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span><?php echo $is_in_cart ? 'Remove from Cart' : 'Add to Cart'; ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php elseif ($book_data['quantity'] <= 0): ?>
                            <button class="btn btn-primary" disabled>
                                <i class="fas fa-times-circle"></i> Out of Stock
                            </button>
                        <?php elseif (!is_logged_in()): ?>
                            <a href="login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Login to Purchase
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Order Form -->
            <?php if ($book_data['quantity'] > 0 && is_logged_in() && get_current_user_id() != $book_data['seller_id']): ?>
                <div class="order-form-section" id="order-form">
                    <h3><i class="fas fa-credit-card"></i> Complete Your Order</h3>
                    <form method="POST">
                        <input type="hidden" name="order_submit" value="1">

                        <div class="form-group">
                            <label class="form-label">Quantity</label>
                            <select name="quantity" class="form-select" required>
                                <?php for ($i = 1; $i <= $book_data['quantity']; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> <?php echo $i == 1 ? 'copy' : 'copies'; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Shipping Address <span style="color: #dc3545;">*</span></label>
                            <textarea name="shipping_address" class="form-control" rows="3"
                                placeholder="Enter your full shipping address" required></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Method</label>
                            <div class="payment-methods">
                                <div class="payment-method cod">
                                    <input type="radio" id="cod" name="payment_method" value="cod" checked>
                                    <label for="cod">
                                        <i class="fas fa-money-bill-wave" style="color: #28a745; font-size: 18px;"></i>
                                        Cash on Delivery
                                    </label>
                                </div>
                                <div class="payment-method esewa">
                                    <input type="radio" id="esewa" name="payment_method" value="esewa">
                                    <label for="esewa">
                                        <img src="assets/images/esewa-logo.png" alt="eSewa" class="esewa-logo">
                                        eSewa
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Order Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2"
                                placeholder="Any special instructions for delivery..."></textarea>
                        </div>

                        <div style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin-top: 24px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span>Total: <strong><?php echo format_price($book_data['price']); ?></strong></span>
                                <span style="font-size: 24px; font-weight: 800; color: #000;" id="total-price">
                                    <?php echo format_price($book_data['price']); ?>
                                </span>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-lock"></i> Place Order - <?php echo format_price($book_data['price']); ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Update total price when quantity changes
        const quantitySelect = document.querySelector('select[name="quantity"]');
        const totalPriceElement = document.getElementById('total-price');
        const placeOrderButton = document.querySelector('button[type="submit"]');
        const unitPrice = <?php echo $book_data['price']; ?>;

        if (quantitySelect && totalPriceElement && placeOrderButton) {
            function updateTotalPrice() {
                const quantity = parseInt(quantitySelect.value);
                const totalPrice = unitPrice * quantity;
                
                // Format price with 2 decimal places
                const formattedPrice = 'NPR ' + totalPrice.toFixed(2);
                
                totalPriceElement.textContent = formattedPrice;
                placeOrderButton.innerHTML = `<i class="fas fa-lock"></i> Place Order - ${formattedPrice}`;
            }

            quantitySelect.addEventListener('change', updateTotalPrice);
            
            // Initialize on page load
            updateTotalPrice();
        }

        // Payment method selection handling
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
        paymentMethods.forEach(method => {
            method.addEventListener('change', function() {
                // Add visual feedback for selected payment method
                document.querySelectorAll('.payment-method label').forEach(label => {
                    label.style.boxShadow = 'none';
                    label.style.borderColor = '#e0e0e0';
                });
                
                const selectedLabel = this.closest('.payment-method').querySelector('label');
                selectedLabel.style.borderColor = this.value === 'esewa' ? '#55a854' : '#007bff';
                selectedLabel.style.boxShadow = this.value === 'esewa' 
                    ? '0 0 0 3px rgba(85, 168, 84, 0.2)' 
                    : '0 0 0 3px rgba(0, 123, 255, 0.2)';
            });
        });

        // Mobile menu toggle
        const mobileMenuButton = document.querySelector('.mobile-menu');
        const navbarNav = document.querySelector('.navbar-nav');
        
        if (mobileMenuButton && navbarNav) {
            mobileMenuButton.addEventListener('click', function() {
                navbarNav.style.display = navbarNav.style.display === 'flex' ? 'none' : 'flex';
                if (navbarNav.style.display === 'flex') {
                    navbarNav.style.position = 'absolute';
                    navbarNav.style.top = '100%';
                    navbarNav.style.left = '0';
                    navbarNav.style.right = '0';
                    navbarNav.style.background = 'rgba(0, 0, 0, 0.95)';
                    navbarNav.style.flexDirection = 'column';
                    navbarNav.style.padding = '20px';
                    navbarNav.style.gap = '20px';
                }
            });
        }
    </script>
</body>

</html>

</html>