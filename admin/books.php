<?php
// admin/books.php - Admin book management
if (basename($_SERVER['PHP_SELF']) === 'books.php') {
    require_once '../config/database.php';
    require_once '../config/config.php';
    require_once '../models/Book.php';
    require_once '../models/User.php';

    if (!is_admin()) {
        redirect('login.php');
    }

    $database = new Database();
    $db = $database->getConnection();
    $book = new Book($db);
    $user = new User($db);

    $status_filter = $_GET['status'] ?? 'pending';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * ITEMS_PER_PAGE;
    $books = [];
    $total_books = 0;

    // Get books based on filter with pagination
    switch ($status_filter) {
        case 'approved':
            $books = $book->getApprovedBooks($offset, ITEMS_PER_PAGE);
            $total_books = $book->countBooksByStatus('approved');
            break;
        case 'rejected':
            $books = $book->getRejectedBooks($offset, ITEMS_PER_PAGE);
            $total_books = $book->countBooksByStatus('rejected');
            break;
        case 'pending':
        default:
            $books = $book->getPendingBooks($offset, ITEMS_PER_PAGE);
            $total_books = $book->countBooksByStatus('pending');
            break;
    }

    $total_pages = ceil($total_books / ITEMS_PER_PAGE);
    
    // Get counts for badges
    $pending_count = $book->countBooksByStatus('pending');
    $approved_count = $book->countBooksByStatus('approved');
    $rejected_count = $book->countBooksByStatus('rejected');
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
        .badge-count {
            background-color: #6c757d;
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            margin-left: 5px;
            font-size: 0.75rem;
        }
        .filter-active .badge-count {
            background-color: white;
            color: #0d6efd;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
        .pagination {
            margin-top: 20px;
            justify-content: center;
        }
        .search-box {
            max-width: 300px;
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
                    
                    <!-- Search Box -->
                    <div class="search-box">
                        <form method="GET" action="books.php" class="d-flex">
                            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            <input type="text" name="search" class="form-control form-control-sm me-2" 
                                   placeholder="Search books..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <ul class="nav nav-pills mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" 
                           href="?status=pending">
                            Pending 
                            <span class="badge-count <?php echo $status_filter === 'pending' ? 'bg-light text-dark' : 'bg-secondary'; ?>">
                                <?php echo $pending_count; ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'approved' ? 'active' : ''; ?>" 
                           href="?status=approved">
                            Approved 
                            <span class="badge-count <?php echo $status_filter === 'approved' ? 'bg-light text-dark' : 'bg-secondary'; ?>">
                                <?php echo $approved_count; ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" 
                           href="?status=rejected">
                            Rejected 
                            <span class="badge-count <?php echo $status_filter === 'rejected' ? 'bg-light text-dark' : 'bg-secondary'; ?>">
                                <?php echo $rejected_count; ?>
                            </span>
                        </a>
                    </li>
                </ul>

                <?php if (empty($books)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                        <h4>No <?php echo $status_filter; ?> books found</h4>
                        <p class="text-muted">
                            <?php if ($status_filter === 'pending'): ?>
                                All book listings have been reviewed!
                            <?php elseif ($status_filter === 'approved'): ?>
                                No approved books available.
                            <?php else: ?>
                                No rejected books found.
                            <?php endif; ?>
                        </p>
                        <?php if ($status_filter !== 'pending'): ?>
                            <a href="?status=pending" class="btn btn-primary mt-2">
                                View Pending Books
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Seller</th>
                                    <th>Category</th>
                                    <th>Condition</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($books as $book_item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($book_item['image_url'])): ?>
                                                <img src="../<?php echo UPLOAD_PATH . $book_item['image_url']; ?>" 
                                                     alt="Book cover" 
                                                     class="me-3 rounded" 
                                                     style="width: 50px; height: 60px; object-fit: cover;"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="me-3 bg-light d-flex align-items-center justify-content-center rounded" 
                                                     style="width: 50px; height: 60px; display: none;">
                                                    <i class="fas fa-book text-muted"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="me-3 bg-light d-flex align-items-center justify-content-center rounded" 
                                                     style="width: 50px; height: 60px;">
                                                    <i class="fas fa-book text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($book_item['title']); ?></strong><br>
                                                <small class="text-muted">by <?php echo htmlspecialchars($book_item['author']); ?></small><br>
                                                <?php if (!empty($book_item['isbn'])): ?>
                                                    <small class="text-muted">ISBN: <?php echo htmlspecialchars($book_item['isbn']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $seller_name = htmlspecialchars($book_item['seller_name'] ?? 'Unknown');
                                        $seller_id = $book_item['seller_id'] ?? 0;
                                        ?>
                                        <a href="user-details.php?id=<?php echo $seller_id; ?>" class="text-decoration-none">
                                            <?php echo $seller_name; ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($book_item['category_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-secondary status-badge">
                                            <?php echo ucwords(str_replace('_', ' ', $book_item['condition_type'])); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo format_price($book_item['price']); ?></strong></td>
                                    <td>
                                        <?php
                                        $status = $book_item['status'] ?? 'pending';
                                        $status_colors = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger'
                                        ];
                                        $color = $status_colors[$status] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?> status-badge">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                        <?php if (!empty($book_item['quantity']) && $book_item['quantity'] > 1): ?>
                                            <br><small class="text-muted">Qty: <?php echo $book_item['quantity']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span title="<?php echo date('Y-m-d H:i:s', strtotime($book_item['created_at'])); ?>">
                                            <?php echo time_ago($book_item['created_at']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="book-review.php?id=<?php echo $book_item['id']; ?>" 
                                               class="btn btn-sm btn-primary" 
                                               title="Review">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($status_filter === 'approved'): ?>
                                                <a href="../edit-book.php?id=<?php echo $book_item['id']; ?>" 
                                                   class="btn btn-sm btn-warning"
                                                   title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    title="Delete"
                                                    onclick="confirmDelete(<?php echo $book_item['id']; ?>, '<?php echo htmlspecialchars($book_item['title']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?status=<?php echo $status_filter; ?>&page=<?php echo $page-1; ?>">
                                        Previous
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?status=<?php echo $status_filter; ?>&page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?status=<?php echo $status_filter; ?>&page=<?php echo $page+1; ?>">
                                        Next
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="bookTitle"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(bookId, bookTitle) {
            document.getElementById('bookTitle').textContent = bookTitle;
            document.getElementById('confirmDeleteBtn').href = 'delete-book.php?id=' + bookId;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>
<?php
} else {
    header('Location: dashboard.php');
    exit();
}