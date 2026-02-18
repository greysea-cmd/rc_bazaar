<?php
// User management
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../models/User.php';

if (!is_admin()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$page = max(1, $_GET['page'] ?? 1);
$offset = ($page - 1) * ITEMS_PER_PAGE;
$users = $user->getAllUsers(ITEMS_PER_PAGE, $offset);

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    
    if ($user->updateUserStatus($user_id, $status)) {
        flash_message('User status updated successfully!', 'success');
        redirect('users.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin - <?php echo SITE_NAME; ?></title>
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
                    <a class="nav-link active" href="users.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                    <a class="nav-link" href="books.php">
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

                <h2><i class="fas fa-users"></i> User Management</h2>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Rating</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user_item): ?>
                            <tr>
                                <td><?php echo $user_item['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user_item['first_name'] . ' ' . $user_item['last_name']); ?></strong><br>
                                    <small class="text-muted">@<?php echo htmlspecialchars($user_item['username']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo ucfirst($user_item['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="fas fa-star text-warning"></i>
                                    <?php echo number_format($user_item['rating'], 1); ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'active' => 'success',
                                        'suspended' => 'warning',
                                        'banned' => 'danger'
                                    ];
                                    $color = $status_colors[$user_item['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($user_item['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo time_ago($user_item['created_at']); ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user_item['id']; ?>">
                                                    <?php if ($user_item['status'] !== 'active'): ?>
                                                        <button type="submit" name="update_status" value="active" class="dropdown-item text-success">
                                                            <i class="fas fa-check"></i> Activate
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($user_item['status'] !== 'suspended'): ?>
                                                        <button type="submit" name="update_status" value="suspended" class="dropdown-item text-warning">
                                                            <i class="fas fa-pause"></i> Suspend
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($user_item['status'] !== 'banned'): ?>
                                                        <button type="submit" name="update_status" value="banned" class="dropdown-item text-danger">
                                                            <i class="fas fa-ban"></i> Ban
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page-1); ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item active">
                            <span class="page-link"><?php echo $page; ?></span>
                        </li>
                        
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo ($page+1); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>