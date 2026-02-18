<?php
// Create new dispute
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/Order.php';
require_once 'models/Dispute.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();
$order = new Order($db);
$dispute = new Dispute($db);

$order_id = (int)($_GET['order_id'] ?? 0);
$order_data = $order->getOrderById($order_id);

if (!$order_data) {
    flash_message('Order not found.', 'error');
    redirect('my-orders.php');
}

$user_id = get_current_user_id();
if ($order_data['buyer_id'] != $user_id && $order_data['seller_id'] != $user_id) {
    flash_message('You are not authorized to create dispute for this order.', 'error');
    redirect('my-orders.php');
}

$errors = [];
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'order_id' => $order_id,
        'complainant_id' => $user_id,
        'respondent_id' => ($user_id == $order_data['buyer_id']) ? $order_data['seller_id'] : $order_data['buyer_id'],
        'dispute_type' => $_POST['dispute_type'] ?? '',
        'description' => sanitize_input($_POST['description'] ?? '')
    ];

    // Validation
    if (empty($form_data['dispute_type'])) $errors[] = 'Please select dispute type.';
    if (empty($form_data['description'])) $errors[] = 'Please describe the issue.';
    if (strlen($form_data['description']) < 20) $errors[] = 'Description must be at least 20 characters.';

    if (empty($errors)) {
        if ($dispute->create($form_data)) {
            // Update order status to disputed
            $order->updateOrderStatus($order_id, 'disputed');
            
            flash_message('Dispute created successfully. Admin will review it shortly.', 'success');
            redirect('my-disputes.php');
        } else {
            $errors[] = 'Failed to create dispute. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Dispute - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-book"></i> <?php echo SITE_NAME; ?>
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
                <li class="breadcrumb-item"><a href="my-orders.php">My Orders</a></li>
                <li class="breadcrumb-item active">Create Dispute</li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-exclamation-triangle text-warning"></i> Create Dispute</h4>
                    </div>
                    <div class="card-body">
                        <!-- Order Information -->
                        <div class="alert alert-info">
                            <h6>Order Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Order ID:</strong> #<?php echo $order_data['id']; ?><br>
                                    <strong>Book:</strong> <?php echo htmlspecialchars($order_data['book_title']); ?><br>
                                    <strong>Author:</strong> <?php echo htmlspecialchars($order_data['book_author']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Amount:</strong> <?php echo format_price($order_data['total_amount']); ?><br>
                                    <strong>Order Date:</strong> <?php echo date('M d, Y', strtotime($order_data['created_at'])); ?><br>
                                    <strong>Status:</strong> 
                                    <span class="badge bg-primary"><?php echo ucfirst($order_data['order_status']); ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="dispute_type" class="form-label">Dispute Type *</label>
                                <select class="form-select" id="dispute_type" name="dispute_type" required>
                                    <option value="">Select Issue Type</option>
                                    <option value="payment" <?php echo ($form_data['dispute_type'] ?? '') === 'payment' ? 'selected' : ''; ?>>
                                        Payment Issue
                                    </option>
                                    <option value="quality" <?php echo ($form_data['dispute_type'] ?? '') === 'quality' ? 'selected' : ''; ?>>
                                        Book Quality/Condition
                                    </option>
                                    <option value="shipping" <?php echo ($form_data['dispute_type'] ?? '') === 'shipping' ? 'selected' : ''; ?>>
                                        Shipping/Delivery Issue
                                    </option>
                                    <option value="description" <?php echo ($form_data['dispute_type'] ?? '') === 'description' ? 'selected' : ''; ?>>
                                        Misleading Description
                                    </option>
                                    <option value="other" <?php echo ($form_data['dispute_type'] ?? '') === 'other' ? 'selected' : ''; ?>>
                                        Other Issue
                                    </option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Describe the Issue *</label>
                                <textarea class="form-control" id="description" name="description" rows="6" 
                                          placeholder="Please provide detailed description of the issue. Include any relevant information that will help us resolve this dispute." required><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                                <small class="text-muted">Minimum 20 characters required</small>
                            </div>

                            <div class="alert alert-warning">
                                <h6><i class="fas fa-info-circle"></i> Please Note:</h6>
                                <ul class="mb-0">
                                    <li>Creating a dispute will notify the admin and the other party</li>
                                    <li>Please try to resolve the issue directly with the other party first</li>
                                    <li>Provide as much detail as possible to help with resolution</li>
                                    <li>False or frivolous disputes may result in account suspension</li>
                                </ul>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="my-orders.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Create Dispute
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>