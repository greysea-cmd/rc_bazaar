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

    // Handle delete request
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $delete_id = (int)$_GET['delete'];
        
        // Get book details to delete image
        $book_data = $book->getBookById($delete_id);
        
        if ($book->delete($delete_id)) {
            // Delete book image if exists
            if (!empty($book_data['image_url'])) {
                $image_path = '../' . UPLOAD_PATH . $book_data['image_url'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            flash_message('Book deleted successfully!', 'success');
        } else {
            flash_message('Failed to delete book.', 'error');
        }
        
        // Redirect back to the same status filter
        $redirect_status = isset($_GET['status']) ? $_GET['status'] : 'pending';
        header('Location: books.php?status=' . $redirect_status);
        exit();
    }

    $status_filter = $_GET['status'] ?? 'pending';
    $page = max(1, $_GET['page'] ?? 1);
    $offset = ($page - 1) * ITEMS_PER_PAGE;
    
    
    $count_pending = $db->query("SELECT COUNT(*) FROM books WHERE status = 'pending'")->fetchColumn();
    $count_approved = $db->query("SELECT COUNT(*) FROM books WHERE status = 'approved'")->fetchColumn();
    $count_rejected = $db->query("SELECT COUNT(*) FROM books WHERE status = 'rejected'")->fetchColumn();
    
    $query = "SELECT b.*, 
                     c.name as category_name,
                     CONCAT(u.first_name, ' ', u.last_name) as seller_name,
                     u.id as seller_id,
                     u.email as seller_email
              FROM books b
              LEFT JOIN categories c ON b.category_id = c.id
              LEFT JOIN users u ON b.seller_id = u.id
              WHERE b.status = :status
              ORDER BY b.created_at DESC
              LIMIT :offset, :limit";
    
    $stmt = $db->prepare($query);
    
    $stmt->bindValue(':status', $status_filter, PDO::PARAM_STR);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', ITEMS_PER_PAGE, PDO::PARAM_INT);
    
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count_query = "SELECT COUNT(*) FROM books WHERE status = :status";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->bindValue(':status', $status_filter, PDO::PARAM_STR);
    $count_stmt->execute();
    $total_books = $count_stmt->fetchColumn();
    
    $total_pages = ceil($total_books / ITEMS_PER_PAGE);
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
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
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
                </div>
            </div>

            <div class="col-md-10 p-4">
                <?php display_flash_message(); ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-book"></i> Book Management</h2>
                </div>

                <!-- Filter Tabs with Counts -->
                <ul class="nav nav-pills mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" 
                           href="?status=pending">
                            Pending 
                            <span class="badge-count <?php echo $status_filter === 'pending' ? 'bg-light text-dark' : 'bg-secondary'; ?>">
                                <?php echo $count_pending; ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'approved' ? 'active' : ''; ?>" 
                           href="?status=approved">
                            Approved 
                            <span class="badge-count <?php echo $status_filter === 'approved' ? 'bg-light text-dark' : 'bg-secondary'; ?>">
                                <?php echo $count_approved; ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" 
                           href="?status=rejected">
                            Rejected 
                            <span class="badge-count <?php echo $status_filter === 'rejected' ? 'bg-light text-dark' : 'bg-secondary'; ?>">
                                <?php echo $count_rejected; ?>
                            </span>
                        </a>
                    </li>
                </ul>

                <?php if (empty($books)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                        <h4>No <?php echo htmlspecialchars($status_filter); ?> books found</h4>
                        <p class="text-muted">
                            <?php if ($status_filter === 'pending'): ?>
                                There are no pending books waiting for review.
                            <?php elseif ($status_filter === 'approved'): ?>
                                No approved books available.
                            <?php else: ?>
                                No rejected books found.
                            <?php endif; ?>
                        </p>
                        
                        <?php if ($status_filter !== 'pending' && $count_pending > 0): ?>
                            <a href="?status=pending" class="btn btn-primary mt-3">
                                View <?php echo $count_pending; ?> Pending Book(s)
                            </a>
                        <?php endif; ?>
                        
                        <!-- Quick action to add a test book -->
                        <div class="mt-4">
                            <button class="btn btn-outline-secondary" onclick="showAddTestForm()">
                                Add Test Book
                            </button>
                        </div>
                        
                        <!-- Hidden form to add test book -->
                        <div id="addTestForm" style="display: none; margin-top: 20px; max-width: 500px; margin-left: auto; margin-right: auto;">
                            <form method="POST" action="add-test-book.php" class="text-start border p-4 rounded">
                                <h5>Add Test Book</h5>
                                <div class="mb-3">
                                    <label class="form-label">Book Title</label>
                                    <input type="text" name="title" class="form-control" value="Test Book <?php echo date('Y-m-d H:i:s'); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Author</label>
                                    <input type="text" name="author" class="form-control" value="Test Author" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-control">
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Price</label>
                                    <input type="number" step="0.01" name="price" class="form-control" value="19.99" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Condition</label>
                                    <select name="condition_type" class="form-control">
                                        <option value="new">New</option>
                                        <option value="like_new">Like New</option>
                                        <option value="good" selected>Good</option>
                                        <option value="fair">Fair</option>
                                        <option value="poor">Poor</option>
                                    </select>
                                </div>
                                <input type="hidden" name="seller_id" value="1">
                                <button type="submit" class="btn btn-primary">Add Test Book</button>
                                <button type="button" class="btn btn-secondary" onclick="showAddTestForm()">Cancel</button>
                            </form>
                        </div>
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
                                    <th>Quantity</th>
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
                                                     style="width: 50px; height: 60px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="me-3 bg-light d-flex align-items-center justify-content-center rounded" 
                                                     style="width: 50px; height: 60px;">
                                                    <i class="fas fa-book text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($book_item['title']); ?></strong><br>
                                                <small class="text-muted">by <?php echo htmlspecialchars($book_item['author']); ?></small>
                                                <?php if (!empty($book_item['isbn'])): ?>
                                                    <br><small class="text-muted">ISBN: <?php echo htmlspecialchars($book_item['isbn']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        echo htmlspecialchars($book_item['seller_name'] ?? 'Unknown Seller');
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($book_item['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucwords(str_replace('_', ' ', $book_item['condition_type'] ?? 'Unknown')); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo format_price($book_item['price'] ?? 0); ?></strong></td>
                                    <td><?php echo $book_item['quantity'] ?? 1; ?></td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger'
                                        ];
                                        $color = $status_colors[$book_item['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo ucfirst($book_item['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo time_ago($book_item['created_at']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="book-review.php?id=<?php echo $book_item['id']; ?>" 
                                               class="btn btn-sm btn-primary" 
                                               title="Review">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($book_item['status'] === 'approved'): ?>
                                                <a href="../edit-book.php?id=<?php echo $book_item['id']; ?>" 
                                                   class="btn btn-sm btn-warning"
                                                   title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    title="Delete"
                                                    onclick="confirmDelete(<?php echo $book_item['id']; ?>, '<?php echo htmlspecialchars($book_item['title'], ENT_QUOTES); ?>', '<?php echo $status_filter; ?>')">
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
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?status=<?php echo urlencode($status_filter); ?>&page=<?php echo $page-1; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?status=<?php echo urlencode($status_filter); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?status=<?php echo urlencode($status_filter); ?>&page=<?php echo $page+1; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Delete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-5">Are you sure you want to delete <strong id="deleteBookTitle"></strong>?</p>
                    <p class="text-danger mb-0">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        This action cannot be undone. All data associated with this book will be permanently removed.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i> Delete Permanently
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showAddTestForm() {
            var form = document.getElementById('addTestForm');
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function confirmDelete(bookId, bookTitle, statusFilter) {
            document.getElementById('deleteBookTitle').textContent = bookTitle;
            document.getElementById('confirmDeleteBtn').href = 'books.php?delete=' + bookId + '&status=' + statusFilter;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
} else {
    header('Location: dashboard.php');
    exit();
}