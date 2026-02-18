<?php
if (basename($_SERVER['PHP_SELF']) === 'books.php') {
    require_once '../config/database.php';
    require_once '../config/config.php';
    require_once '../models/Book.php';

    if (!is_admin()) {
        redirect('login.php');
    }

    $database = new Database();
    $db = $database->getConnection();
    $book = new Book($db);

    $status_filter = $_GET['status'] ?? 'pending';
    $page = max(1, $_GET['page'] ?? 1);
    $offset = ($page - 1) * ITEMS_PER_PAGE;

    // Get books based on filter
    if ($status_filter === 'pending') {
        $books = $book->getPendingBooks();
    } else {
        // You'd need to add a method to get books by status
        $books = $book->getPendingBooks(); // For now, showing pending
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Management - Admin - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background: #343a40;
        }
        .sidebar .nav-link {
            color: #adb5bd;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background-color: #495057;
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
                <a class="nav-link" href="../index.php" target="_blank">View Site</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar p-0">
                <div class="nav flex-column nav-pills p-3">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                    <a class="nav-link active" href="books.php">
                        <i class="fas fa-book"></i> Books
                    </a>
                    <a class="nav-link" href="orders.php">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </a>
                    <!-- <a class="nav-link" href="disputes.php">
                        <i class="fas fa-exclamation-triangle"></i> Disputes
                    </a> -->
                </div>
            </div>

            <div class="col-md-10 p-4">
                <?php display_flash_message(); ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-book"></i> Book Management</h2>
                    
                    <!-- Filter Tabs -->
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" 
                               href="?status=pending">
                                Pending <span class="badge bg-warning"><?php echo count($books); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter === 'approved' ? 'active' : ''; ?>" 
                               href="?status=approved">Approved</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" 
                               href="?status=rejected">Rejected</a>
                        </li>
                    </ul>
                </div>

                <?php if (empty($books)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h4>No pending books</h4>
                        <p class="text-muted">All book listings have been reviewed!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Seller</th>
                                    <th>Category</th>
                                    <th>Condition</th>
                                    <th>Price</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($books as $book_item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($book_item['image_url']): ?>
                                                <img src="../<?php echo UPLOAD_PATH . $book_item['image_url']; ?>" 
                                                     alt="Book cover" class="me-3" style="width: 50px; height: 60px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="me-3 bg-light d-flex align-items-center justify-content-center" 
                                                     style="width: 50px; height: 60px;">
                                                    <i class="fas fa-book text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($book_item['title']); ?></strong><br>
                                                <small class="text-muted">by <?php echo htmlspecialchars($book_item['author']); ?></small><br>
                                                <?php if ($book_item['isbn']): ?>
                                                    <small class="text-muted">ISBN: <?php echo htmlspecialchars($book_item['isbn']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($book_item['seller_name']); ?></td>
                                    <td><?php echo htmlspecialchars($book_item['category_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucwords(str_replace('_', ' ', $book_item['condition_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_price($book_item['price']); ?></td>
                                    <td><?php echo time_ago($book_item['created_at']); ?></td>
                                    <td>
                                        <a href="book-review.php?id=<?php echo $book_item['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> Review
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
} else {
    header('Location: dashboard.php');
    exit();
}