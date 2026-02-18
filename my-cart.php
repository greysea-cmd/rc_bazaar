<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/Cart.php';
require_once 'models/User.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();
$cart = new Cart($db);
$user_model = new User($db);

$user_id = get_current_user_id();
$user_data = $user_model->getUserById($user_id);

if (!$user_data) {
    flash_message('User not found.', 'error');
    redirect('logout.php');
}

$cart_items = $cart->getUserCart($user_id);
$cart_total = 0;
$cart_count = 0;

foreach ($cart_items as $item) {
    $cart_total += $item['price'] * $item['quantity'];
    $cart_count += $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --black: #000000;
            --white: #ffffff;
            --dark-grey: #333333;
            --grey: #777777;
            --light-grey: #f5f5f5;
            --border: #e0e0e0;
            --primary: #28a745;
            --primary-hover: #218838;
            --danger: #dc3545;
            --danger-hover: #c82333;
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

        /* Cart Content */
        .cart-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
            align-items: start;
        }

        .cart-items {
            background: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
        }

        .cart-summary {
            background: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            position: sticky;
            top: 2rem;
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

        /* Cart Items */
        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 120px;
            height: 160px;
            border-radius: 6px;
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
            background: var(--light-grey);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--grey);
        }

        .item-details {
            flex: 1;
        }

        .item-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .item-author {
            color: var(--grey);
            margin-bottom: 0.5rem;
        }

        .item-price {
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .item-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .condition-badge {
            background: var(--black);
            color: var(--white);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .item-actions {
            display: flex;
            gap: 1rem;
        }

        /* Quantity Controls */
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .qty-btn {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border);
            background: var(--white);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .qty-btn:hover {
            background: var(--light-grey);
        }

        .qty-input {
            width: 50px;
            text-align: center;
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 0.5rem;
            font-weight: 600;
        }

        /* Summary */
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .summary-row.total {
            font-size: 1.2rem;
            font-weight: 700;
            border-bottom: none;
            margin-bottom: 1.5rem;
        }

        /* Buttons */
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
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-danger {
            background: var(--white);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: var(--white);
        }

        .btn-black {
            background: var(--black);
            color: var(--white);
            width: 100%;
        }

        .btn-black:hover {
            background: var(--dark-grey);
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
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
        @media (max-width: 1024px) {
            .cart-container {
                grid-template-columns: 1fr;
            }
        }

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

            .cart-item {
                flex-direction: column;
            }

            .item-image {
                width: 100%;
                height: 200px;
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
                <h1><?php echo htmlspecialchars(SITE_NAME); ?></h1>
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
                    <i class="fas fa-shopping-bag"></i>
                    <span>My Orders</span>
                </a>
                <a href="cart.php" class="nav-item active">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Shopping Cart</span>
                    <?php if ($cart_count > 0): ?>
                        <span style="background: var(--primary); color: white; padding: 2px 6px; border-radius: 10px; font-size: 12px;">
                            <?php echo $cart_count; ?>
                        </span>
                    <?php endif; ?>
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
                <h2>Shopping Cart</h2>
                <div class="user-menu">
                    <span>Welcome, <?php echo htmlspecialchars($user_data['first_name']); ?></span>
                    <div class="user-avatar" data-tooltip="<?php echo htmlspecialchars($user_data['first_name'] . ' ' . htmlspecialchars($user_data['last_name'])); ?>">
                        <span class="user-initial"><?php echo strtoupper(substr($user_data['first_name'], 0, 1)); ?></span>
                    </div>
                </div>
            </header>

            <?php display_flash_message(); ?>

            <div class="cart-container">
                <!-- Cart Items -->
                <div class="cart-items">
                    <div class="section-header">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your Cart (<?php echo $cart_count; ?> item<?php echo $cart_count != 1 ? 's' : ''; ?>)</h3>
                    </div>

                    <?php if (empty($cart_items)): ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart"></i>
                            <h4>Your cart is empty</h4>
                            <p>Add books to your cart while browsing our collection.</p>
                            <a href="browse.php" class="btn btn-primary">Browse Books</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item" id="cart-item-<?php echo $item['cart_id']; ?>">
                                <div class="item-image">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?php echo UPLOAD_PATH . htmlspecialchars($item['image_url']); ?>"
                                            alt="Book cover"
                                            onerror="handleImageError(this)">
                                    <?php else: ?>
                                        <div class="item-image-placeholder">
                                            <i class="fas fa-book fa-2x"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="item-details">
                                    <h4 class="item-title"><?php echo htmlspecialchars($item['title']); ?></h4>
                                    <p class="item-author">by <?php echo htmlspecialchars($item['author']); ?></p>
                                    <p class="item-price"><?php echo format_price($item['price']); ?></p>
                                    <div class="item-meta">
                                        <span class="condition-badge">
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['condition_type']))); ?>
                                        </span>
                                        <div class="rating">
                                            <i class="fas fa-star"></i>
                                            <?php echo number_format($item['seller_rating'], 1); ?>
                                        </div>
                                        <div>Sold by: <?php echo htmlspecialchars($item['seller_name']); ?></div>
                                    </div>
                                    <div class="item-actions">
                                        <div class="quantity-control">
                                            <button class="qty-btn" onclick="updateQuantity(<?php echo $item['cart_id']; ?>, 'decrease')">-</button>
                                            <input type="text"
                                                class="qty-input"
                                                value="<?php echo $item['quantity']; ?>"
                                                readonly>
                                            <button class="qty-btn" onclick="updateQuantity(<?php echo $item['cart_id']; ?>, 'increase')">+</button>
                                        </div>
                                        <button class="btn btn-danger btn-sm"
                                            onclick="removeFromCart(<?php echo $item['cart_id']; ?>)">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                        <a href="book-details.php?id=<?php echo $item['book_id']; ?>"
                                            class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Cart Summary -->
                <?php if (!empty($cart_items)): ?>
                    <div class="cart-summary">
                        <div class="section-header">
                            <i class="fas fa-receipt"></i>
                            <h3>Order Summary</h3>
                        </div>
                        <div class="summary-row">
                            <span>Subtotal (<?php echo $cart_count; ?> item<?php echo $cart_count != 1 ? 's' : ''; ?>)</span>
                            <span id="subtotal"><?php echo format_price($cart_total); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span>Calculated at checkout</span>
                        </div>
                        <div class="summary-row total">
                            <span>Estimated Total</span>
                            <span id="total"><?php echo format_price($cart_total); ?></span>
                        </div>
                        <button class="btn btn-black" onclick="proceedToCheckout()">
                            <i class="fas fa-lock"></i> Proceed to Checkout
                        </button>
                        <a href="browse.php" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                            <i class="fas fa-arrow-left"></i> Continue Shopping
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function handleImageError(img) {
            img.style.display = 'none';
            const placeholder = img.parentNode.querySelector('.item-image-placeholder');
            if (!placeholder) {
                const fallback = document.createElement('div');
                fallback.className = 'item-image-placeholder';
                fallback.innerHTML = '<i class="fas fa-book fa-2x"></i>';
                img.parentNode.appendChild(fallback);
            }
        }

        async function updateQuantity(cartId, action) {
            const qtyInput = document.querySelector(`#cart-item-${cartId} .qty-input`);
            let currentQty = parseInt(qtyInput.value);

            if (action === 'increase') {
                currentQty++;
            } else if (action === 'decrease' && currentQty > 1) {
                currentQty--;
            } else {
                return;
            }

            try {
                const response = await fetch('update-cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `cart_id=${cartId}&quantity=${currentQty}`
                });

                const data = await response.json();

                if (data.success) {
                    qtyInput.value = currentQty;
                    updateCartTotals(data.cart_total, data.cart_count);
                    showFlashMessage('Cart updated successfully', 'success');
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showFlashMessage(error.message, 'error');
            }
        }

        async function removeFromCart(cartId) {
            const cartItem = document.querySelector(`#cart-item-${cartId}`);

            try {
                const response = await fetch('update-cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `cart_id=${cartId}&action=remove`
                });

                const data = await response.json();

                if (data.success) {
                    // Fade out and remove the item
                    cartItem.style.transition = 'opacity 0.3s ease';
                    cartItem.style.opacity = '0';

                    setTimeout(() => {
                        cartItem.remove();

                        // Update totals
                        updateCartTotals(data.cart_total, data.cart_count);

                        // Check if cart is now empty
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            const cartContainer = document.querySelector('.cart-container');
                            cartContainer.innerHTML = `
                                <div class="empty-state">
                                    <i class="fas fa-shopping-cart"></i>
                                    <h4>Your cart is empty</h4>
                                    <p>Add books to your cart while browsing our collection.</p>
                                    <a href="browse.php" class="btn btn-primary">Browse Books</a>
                                </div>
                            `;
                        }
                    }, 300);

                    showFlashMessage('Item removed from cart', 'success');
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showFlashMessage(error.message, 'error');
            }
        }

        function updateCartTotals(total, count) {
            document.getElementById('subtotal').textContent = formatPrice(total);
            document.getElementById('total').textContent = formatPrice(total);

            const headerCount = document.querySelector('.section-header h3');
            headerCount.textContent = `Your Cart (${count} item${count !== 1 ? 's' : ''})`;

            const sidebarBadge = document.querySelector('.nav-item.active span[style*="background"]');
            if (sidebarBadge) {
                sidebarBadge.textContent = count;
                if (count === 0) {
                    sidebarBadge.style.display = 'none';
                } else {
                    sidebarBadge.style.display = 'inline-block';
                }
            }
        }

        function formatPrice(amount) {
            return 'Rs.' + parseFloat(amount).toFixed(2);
        }

        function proceedToCheckout() {
            window.location.href = 'checkout.php';
        }

        function showFlashMessage(message, type) {
            const flashContainer = document.querySelector('.main-content');
            const existingFlash = document.querySelector('.flash-message');

            if (existingFlash) {
                existingFlash.remove();
            }

            const flashMessage = document.createElement('div');
            flashMessage.className = `flash-message flash-${type}`;
            flashMessage.innerHTML = `
                <div class="flash-content">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;

            flashContainer.insertBefore(flashMessage, document.querySelector('.header').nextSibling);

            setTimeout(() => {
                flashMessage.style.transition = 'opacity 0.5s ease';
                flashMessage.style.opacity = '0';
                setTimeout(() => {
                    flashMessage.remove();
                }, 500);
            }, 5000);
        }
    </script>
</body>

</html>