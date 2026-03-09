<?php
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $admin_notes = sanitize_input($_POST['admin_notes'] ?? '');

        if ($action === 'approve') {
            if ($book->updateBookStatus($book_id, 'approved', $admin_notes)) {
                flash_message('Book approved successfully!', 'success');
                header('Location: books.php?status=approved');
                exit();
            }
        } elseif ($action === 'reject') {
            if ($book->updateBookStatus($book_id, 'rejected', $admin_notes)) {
                flash_message('Book rejected.', 'info');
                header('Location: books.php?status=rejected');
                exit();
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
        <style>
            .status-badge {
                font-size: 1rem;
                padding: 0.5rem 1rem;
            }

            .book-info {
                background-color: #f8f9fa;
                padding: 1rem;
                border-radius: 0.5rem;
                margin-bottom: 1.5rem;
            }

            .info-label {
                font-weight: 600;
                color: #495057;
            }
        </style>
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

            <!-- Current Status Banner -->
            <div class="alert alert-<?php
                                    echo $book_data['status'] === 'approved' ? 'success' : ($book_data['status'] === 'rejected' ? 'danger' : 'warning');
                                    ?> mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-<?php
                                        echo $book_data['status'] === 'approved' ? 'check-circle' : ($book_data['status'] === 'rejected' ? 'exclamation-circle' : 'clock');
                                        ?> fa-2x me-3"></i>
                    <div>
                        <strong>Current Status:</strong>
                        <span class="badge bg-<?php
                                                echo $book_data['status'] === 'approved' ? 'success' : ($book_data['status'] === 'rejected' ? 'danger' : 'warning');
                                                ?> status-badge ms-2">
                            <?php echo strtoupper($book_data['status']); ?>
                        </span>
                        <?php if (!empty($book_data['admin_notes'])): ?>
                            <div class="mt-2">
                                <strong>Admin Notes:</strong> <?php echo htmlspecialchars($book_data['admin_notes']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <?php if (!empty($book_data['image_url'])): ?>
                            <img src="../<?php echo UPLOAD_PATH . $book_data['image_url']; ?>" class="card-img-top" alt="Book cover" style="height: 400px; object-fit: cover;">
                        <?php else: ?>
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 400px;">
                                <i class="fas fa-book fa-5x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-footer text-center">
                            <small class="text-muted">Book ID: <?php echo $book_data['id']; ?></small>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-<?php
                                                    echo $book_data['status'] === 'approved' ? 'success' : ($book_data['status'] === 'rejected' ? 'danger' : 'warning');
                                                    ?> text-white">
                            <h4 class="mb-0">
                                <i class="fas fa-<?php
                                                    echo $book_data['status'] === 'approved' ? 'check-circle' : ($book_data['status'] === 'rejected' ? 'exclamation-circle' : 'eye');
                                                    ?> me-2"></i>
                                Book Review
                            </h4>
                        </div>
                        <div class="card-body">
                            <h3><?php echo htmlspecialchars($book_data['title']); ?></h3>
                            <p class="text-muted lead">by <?php echo htmlspecialchars($book_data['author']); ?></p>

                            <div class="book-info">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><span class="info-label">Price:</span> <?php echo format_price($book_data['price']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><span class="info-label">Condition:</span>
                                            <span class="badge bg-secondary"><?php echo ucwords(str_replace('_', ' ', $book_data['condition_type'])); ?></span>
                                        </p>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <p><span class="info-label">Category:</span> <?php echo htmlspecialchars($book_data['category_name'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><span class="info-label">ISBN:</span> <?php echo htmlspecialchars($book_data['isbn'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <p><span class="info-label">Quantity:</span> <?php echo $book_data['quantity']; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><span class="info-label">Submitted:</span> <?php echo time_ago($book_data['created_at']); ?></p>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <p><span class="info-label">Seller:</span>
                                            <?php echo htmlspecialchars($book_data['first_name'] . ' ' . $book_data['last_name']); ?>
                                            <small class="text-muted">(ID: <?php echo $book_data['seller_id']; ?>)</small>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($book_data['description'])): ?>
                                <div class="mb-4">
                                    <h5>Description:</h5>
                                    <div class="p-3 bg-light rounded">
                                        <?php echo nl2br(htmlspecialchars($book_data['description'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($book_data['status'] === 'pending'): ?>
                                <!-- Show approval form for pending books -->
                                <form method="POST" class="mt-4">
                                    <div class="mb-3">
                                        <label for="admin_notes" class="form-label">Admin Notes (optional)</label>
                                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3"
                                            placeholder="Add any notes for the seller..."></textarea>
                                        <small class="text-muted">These notes will be visible to the seller.</small>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" name="action" value="reject" class="btn btn-danger me-md-2">
                                            <i class="fas fa-times"></i> Reject Book
                                        </button>
                                        <button type="submit" name="action" value="approve" class="btn btn-success">
                                            <i class="fas fa-check"></i> Approve Book
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <!-- Show status-specific actions for non-pending books -->
                                <div class="alert alert-<?php
                                                        echo $book_data['status'] === 'approved' ? 'success' : 'danger';
                                                        ?> mt-4">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-<?php
                                                            echo $book_data['status'] === 'approved' ? 'check-circle' : 'info-circle';
                                                            ?> fa-2x me-3"></i>
                                        <div>
                                            <h5 class="alert-heading">Book is <?php echo ucfirst($book_data['status']); ?></h5>
                                            <p class="mb-0">
                                                <?php if ($book_data['status'] === 'approved'): ?>
                                                    This book has been approved and is now visible in the marketplace.
                                                <?php else: ?>
                                                    This book has been rejected and is not visible in the marketplace.
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-3">
                                    <a href="books.php?status=<?php echo $book_data['status']; ?>" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to <?php echo ucfirst($book_data['status']); ?> Books
                                    </a>

                                    <?php if ($book_data['status'] === 'approved'): ?>
                                        <a href="../book-details.php?id=<?php echo $book_data['id']; ?>" class="btn btn-primary" target="_blank">
                                            <i class="fas fa-external-link-alt"></i> View on Site
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    </body>

    </html>
<?php
}
?>