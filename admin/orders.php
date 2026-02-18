<?php
if (basename($_SERVER['PHP_SELF']) === 'orders.php') {
    require_once '../config/database.php';
    require_once '../config/config.php';
    require_once '../models/Order.php';

    if (!is_admin()) {
        redirect('login.php');
    }

    $database = new Database();
    $db = $database->getConnection();
    $order = new Order($db);

    $orders = $order->getAllOrders();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Admin - <?php echo SITE_NAME; ?></title>
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
        .order-details {
            font-size: 0.9em;
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
                    <a class="nav-link" href="books.php">
                        <i class="fas fa-book"></i> Books
                    </a>
                    <a class="nav-link active" href="orders.php">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </a>
                </div>
            </div>

            <div class="col-md-10 p-4">
                <h2><i class="fas fa-shopping-cart"></i> Order Management</h2>

                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h4>No orders found</h4>
                        <p class="text-muted">Orders will appear here once users start making purchases.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Book Details</th>
                                    <th>Buyer</th>
                                    <th>Seller</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order_item): ?>
                                <tr>
                                    <td>#<?php echo $order_item['id']; ?></td>
                                    <td>
                                        <?php 
                                        // Fetch book details for this order
                                        $book_stmt = $db->prepare("
                                            SELECT b.title, b.author 
                                            FROM books b 
                                            WHERE b.id = ?
                                        ");
                                        $book_stmt->execute([$order_item['id']]);
                                        $book = $book_stmt->fetch(PDO::FETCH_ASSOC);
                                        ?>
                                        <div class="order-details">
                                            <?php if ($book): ?>
                                                <strong><?php echo htmlspecialchars($book['title'] ?? 'N/A'); ?></strong><br>
                                                <small class="text-muted">by <?php echo htmlspecialchars($book['author'] ?? 'Unknown'); ?></small>
                                            <?php else: ?>
                                                <em class="text-muted">Book details not available</em>
                                            <?php endif; ?>
                                            <br>
                                            <small>Book ID: #<?php echo $order_item['id']; ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($order_item['buyer_name'] ?? 'N/A'); ?><br>
                                        <small class="text-muted">ID: <?php echo $order_item['buyer_id']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($order_item['seller_name'] ?? 'N/A'); ?><br>
                                        <small class="text-muted">ID: <?php echo $order_item['seller_id']; ?></small>
                                    </td>
                                    <td><?php echo $order_item['quantity']; ?></td>
                                    <td><?php echo format_price($order_item['total_amount']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($order_item['payment_status'] === 'paid') ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($order_item['payment_status'] ?? 'pending'); ?>
                                        </span><br>
                                        <small class="text-muted"><?php echo ucfirst($order_item['payment_method'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'placed' => 'secondary',
                                            'confirmed' => 'info',
                                            'shipped' => 'primary',
                                            'delivered' => 'success',
                                            'cancelled' => 'danger',
                                            'disputed' => 'warning'
                                        ];
                                        $color = $status_colors[$order_item['order_status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo ucfirst($order_item['order_status'] ?? 'placed'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($order_item['created_at'])); ?><br>
                                        <small class="text-muted"><?php echo time_ago($order_item['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewOrderModal<?php echo $order_item['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-info" onclick="updateStatus(<?php echo $order_item['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- View Order Modal -->
                                        <div class="modal fade" id="viewOrderModal<?php echo $order_item['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Order #<?php echo $order_item['id']; ?> Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Order Information</h6>
                                                                <p><strong>Order ID:</strong> #<?php echo $order_item['id']; ?></p>
                                                                <p><strong>Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($order_item['created_at'])); ?></p>
                                                                <p><strong>Quantity:</strong> <?php echo $order_item['quantity']; ?></p>
                                                                <p><strong>Unit Price:</strong> <?php echo format_price($order_item['unit_price']); ?></p>
                                                                <p><strong>Total Amount:</strong> <?php echo format_price($order_item['total_amount']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Payment & Status</h6>
                                                                <p><strong>Payment Method:</strong> <?php echo ucfirst($order_item['payment_method']); ?></p>
                                                                <p><strong>Payment Status:</strong> 
                                                                    <span class="badge bg-<?php echo ($order_item['payment_status'] === 'paid') ? 'success' : 'warning'; ?>">
                                                                        <?php echo ucfirst($order_item['payment_status']); ?>
                                                                    </span>
                                                                </p>
                                                                <p><strong>Order Status:</strong> 
                                                                    <span class="badge bg-<?php echo $color; ?>">
                                                                        <?php echo ucfirst($order_item['order_status']); ?>
                                                                    </span>
                                                                </p>
                                                                <?php if (!empty($order_item['shipping_address'])): ?>
                                                                    <h6 class="mt-3">Shipping Address</h6>
                                                                    <p><?php echo nl2br(htmlspecialchars($order_item['shipping_address'])); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <?php if (!empty($order_item['notes'])): ?>
                                                            <div class="row mt-3">
                                                                <div class="col-12">
                                                                    <h6>Order Notes</h6>
                                                                    <p><?php echo nl2br(htmlspecialchars($order_item['notes'])); ?></p>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
    <script>
    function updateStatus(orderId) {
        const status = prompt('Update order status (placed/confirmed/shipped/delivered/cancelled):');
        if (status) {
            if (['placed', 'confirmed', 'shipped', 'delivered', 'cancelled'].includes(status.toLowerCase())) {
                fetch('update_order_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        status: status.toLowerCase()
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Order status updated successfully!');
                        location.reload();
                    } else {
                        alert('Error updating status: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating order status');
                });
            } else {
                alert('Invalid status! Must be: placed, confirmed, shipped, delivered, or cancelled');
            }
        }
    }
    </script>
</body>
</html>

<?php
}