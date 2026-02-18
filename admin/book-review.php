<?php

// Review and approve/reject books
if (basename($_SERVER['PHP_SELF']) === 'book-review.php') {
    require_once '../config/database.php';
    require_once '../config/config.php';
    require_once '../models/Book.php';

    if (!is_admin()) {
        redirect('login.php');
    }

    $database = new Database();
    $db = $database->getConnection();
    $book = new Book($db);

    $book_id = (int)($_GET['id'] ?? 0);
    $book_data = $book->getBookById($book_id);

    if (!$book_data) {
        flash_message('Book not found.', 'error');
        redirect('books.php');
    }

    // Handle approval/rejection
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $admin_notes = sanitize_input($_POST['admin_notes'] ?? '');
        
        if ($action === 'approve') {
            if ($book->updateBookStatus($book_id, 'approved', $admin_notes)) {
                flash_message('Book approved successfully!', 'success');
                redirect('books.php');
            }
        } elseif ($action === 'reject') {
            if ($book->updateBookStatus($book_id, 'rejected', $admin_notes)) {
                flash_message('Book rejected.', 'info');
                redirect('books.php');
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Book - Admin - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-shield-alt"></i> <?php echo SITE_NAME; ?> Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="books.php">Books</a></li>
                <li class="breadcrumb-item active">Review Book</li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <?php if ($book_data['image_url']): ?>
                        <img src="../<?php echo UPLOAD_PATH . $book_data['image_url']; ?>" class="card-img-top" alt="Book cover" style="height: 400px; object-fit: cover;">
                    <?php else: ?>
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 400px;">
                            <i class="fas fa-book fa-5x text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>Book Review</h4>
                    </div>
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($book_data['title']); ?></h3>
                        <p class="text-muted lead">by <?php echo htmlspecialchars($book_data['author']); ?></p>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Price:</strong> <?php echo format_price($book_data['price']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Condition:</strong> <?php echo ucwords(str_replace('_', ' ', $book_data['condition_type'])); ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Category:</strong> <?php echo htmlspecialchars($book_data['category_name'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>ISBN:</strong> <?php echo htmlspecialchars($book_data['isbn'] ?? 'N/A'); ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Quantity:</strong> <?php echo $book_data['quantity']; ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Submitted:</strong> <?php echo time_ago($book_data['created_at']); ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <strong>Seller:</strong> 
                            <?php echo htmlspecialchars($book_data['first_name'] . ' ' . $book_data['last_name']); ?>
                            (<?php echo htmlspecialchars($book_data['seller_name']); ?>)
                        </div>

                        <?php if ($book_data['description']): ?>
                        <div class="mb-3">
                            <strong>Description:</strong>
                            <p><?php echo nl2br(htmlspecialchars($book_data['description'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="admin_notes" class="form-label">Admin Notes</label>
                                <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                                          placeholder="Add any notes for the seller..."></textarea>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" name="action" value="reject" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                                <button type="submit" name="action" value="approve" class="btn btn-success">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
}