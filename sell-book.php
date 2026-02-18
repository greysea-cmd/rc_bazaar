<?php
// sell-book.php - Add new book listing
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/Book.php';
require_once 'models/Category.php';
require_once 'models/User.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();
$book = new Book($db);
$category = new Category($db);
$user_model = new User($db);

$user_id = get_current_user_id();
$user_data = $user_model->getUserById($user_id);

if (!$user_data) {
    flash_message('User not found.', 'error');
    redirect('logout.php');
}

$categories = $category->getAllCategories();
$errors = [];
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'seller_id' => $user_id,
        'title' => sanitize_input($_POST['title'] ?? ''),
        'author' => sanitize_input($_POST['author'] ?? ''),
        'isbn' => sanitize_input($_POST['isbn'] ?? ''),
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'condition_type' => $_POST['condition_type'] ?? '',
        'description' => sanitize_input($_POST['description'] ?? ''),
        'price' => (float)($_POST['price'] ?? 0),
        'quantity' => (int)($_POST['quantity'] ?? 1),
        'image_url' => null
    ];

    // Validation
    if (empty($form_data['title'])) $errors[] = 'Title is required.';
    if (empty($form_data['author'])) $errors[] = 'Author is required.';
    if (empty($form_data['condition_type'])) $errors[] = 'Condition is required.';
    if ($form_data['price'] <= 0) $errors[] = 'Price must be greater than 0.';
    if ($form_data['quantity'] <= 0) $errors[] = 'Quantity must be greater than 0.';

    // Handle image upload
    if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === UPLOAD_ERR_OK) {
        try {
            $form_data['image_url'] = upload_image($_FILES['book_image']);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        if ($book->create($form_data)) {
            flash_message('Book listed successfully! It will be reviewed by admin before approval.', 'success');
            redirect('my-books.php');
        } else {
            $errors[] = 'Failed to list book. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell a Book - <?php echo SITE_NAME; ?></title>
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

        /* Form Section */
        .form-section {
            background: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
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

        /* Form Elements */
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--dark-grey);
        }

        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
        }

        textarea.form-control {
            min-height: 120px;
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
            background: var(--black);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--dark-grey);
        }

        .btn-secondary {
            background: var(--light-grey);
            color: var(--dark-grey);
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        /* Error Messages */
        .error-messages {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #dc3545;
        }

        .error-messages ul {
            margin-bottom: 0;
            padding-left: 1rem;
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
                <a href="sell-book.php" class="nav-item active">
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
                <h2>Sell a Book</h2>
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
            </header>

            <?php display_flash_message(); ?>

            <div class="form-section">
                <div class="section-header">
                    <i class="fas fa-plus"></i>
                    <h3>List a Book for Sale</h3>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="error-messages">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="title" class="form-label">Book Title *</label>
                            <input type="text" class="form-control" id="title" name="title"
                                value="<?php echo htmlspecialchars($form_data['title'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="isbn" class="form-label">ISBN</label>
                            <input type="text" class="form-control" id="isbn" name="isbn"
                                value="<?php echo htmlspecialchars($form_data['isbn'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="author" class="form-label">Author *</label>
                            <input type="text" class="form-control" id="author" name="author"
                                value="<?php echo htmlspecialchars($form_data['author'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"
                                        <?php echo ($form_data['category_id'] ?? 0) == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="condition_type" class="form-label">Condition *</label>
                            <select class="form-select" id="condition_type" name="condition_type" required>
                                <option value="">Select Condition</option>
                                <option value="new" <?php echo ($form_data['condition_type'] ?? '') === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="like_new" <?php echo ($form_data['condition_type'] ?? '') === 'like_new' ? 'selected' : ''; ?>>Like New</option>
                                <option value="good" <?php echo ($form_data['condition_type'] ?? '') === 'good' ? 'selected' : ''; ?>>Good</option>
                                <option value="fair" <?php echo ($form_data['condition_type'] ?? '') === 'fair' ? 'selected' : ''; ?>>Fair</option>
                                <option value="poor" <?php echo ($form_data['condition_type'] ?? '') === 'poor' ? 'selected' : ''; ?>>Poor</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="price" class="form-label">Price *</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="price" name="price"
                                value="<?php echo $form_data['price'] ?? ''; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="quantity" class="form-label">Quantity *</label>
                            <input type="number" min="1" class="form-control" id="quantity" name="quantity"
                                value="<?php echo $form_data['quantity'] ?? 1; ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"
                            placeholder="Describe the book's condition, any highlights, missing pages, etc."><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="book_image" class="form-label">Book Image</label>
                        <input type="file" class="form-control" id="book_image" name="book_image" accept="image/*">
                        <small class="text-muted">Upload a clear image of your book (JPG, PNG, GIF - Max 5MB)</small>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> List Book
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>

</html>