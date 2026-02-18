<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../models/User.php';

// Restrict access to admins
if (!is_admin()) {
    flash_message('Access denied. Admins only.', 'error');
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();
$user_model = new User($db);
$current_id = get_current_admin_id();

// Get all users for dropdown
$all_users = $user_model->getAllUsers();

// Determine which profile to display
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $current_id;
$user_type = isset($_GET['user_id']) ? 'user' : 'admin';
$user_data = $user_model->getUserById($user_id);

if (!$user_data) {
    flash_message('User not found.', 'error');
    redirect('../logout.php');
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $data = [
        'username' => sanitize_input($_POST['username'] ?? ''),
        'email' => sanitize_input($_POST['email'] ?? ''),
        'first_name' => sanitize_input($_POST['first_name'] ?? ''),
        'last_name' => sanitize_input($_POST['last_name'] ?? ''),
        'phone' => $user_type === 'user' ? sanitize_input($_POST['phone'] ?? '') : null,
        'address' => $user_type === 'user' ? sanitize_input($_POST['address'] ?? '') : null,
        'city' => $user_type === 'user' ? sanitize_input($_POST['city'] ?? '') : null,
        'state' => $user_type === 'user' ? sanitize_input($_POST['state'] ?? '') : null,
        'zipcode' => $user_type === 'user' ? sanitize_input($_POST['zipcode'] ?? '') : null
    ];

    if (empty($data['username']) || empty($data['email']) || empty($data['first_name']) || empty($data['last_name']) ||
        ($user_type === 'user' && (empty($data['phone']) || empty($data['address']) || empty($data['city']) || empty($data['state']) || empty($data['zipcode'])))) {
        flash_message('All required fields must be filled.', 'error');
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        flash_message('Invalid email format.', 'error');
    } elseif ($user_type === 'user' && !preg_match('/^\d{10}$/', $data['phone'])) {
        flash_message('Phone number must be 10 digits.', 'error');
    } elseif ($user_type === 'user' && !preg_match('/^\d{5}$/', $data['zipcode'])) {
        flash_message('Zipcode must be 5 digits.', 'error');
    } else {
        if ($user_model->updateProfile($user_id, $data)) {
            flash_message('Profile updated successfully!', 'success');
            redirect('profile.php' . ($user_type === 'user' ? '?user_id=' . $user_id : ''));
        } else {
            flash_message('Failed to update profile. Please try again.', 'error');
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_picture']) && isset($_FILES['profile_picture'])) {
    try {
        $filename = upload_image($_FILES['profile_picture'], PROFILE_UPLOAD_PATH);
        $old_picture = $user_model->getProfilePicture($user_id);
        if ($old_picture && file_exists(PROFILE_UPLOAD_PATH . $old_picture)) {
            unlink(PROFILE_UPLOAD_PATH . $old_picture);
        }
        if ($user_model->updateProfilePicture($user_id, $filename)) {
            flash_message('Profile picture updated successfully!', 'success');
            redirect('profile.php' . ($user_type === 'user' ? '?user_id=' . $user_id : ''));
        } else {
            flash_message('Failed to update profile picture in database.', 'error');
            unlink(PROFILE_UPLOAD_PATH . $filename);
        }
    } catch (Exception $e) {
        flash_message($e->getMessage(), 'error');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user_id === $current_id ? 'Admin Profile' : 'User Profile'; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-card {
            max-width: 800px;
            margin: 0 auto;
        }
        .profile-card .card-header {
            background-color: #f8f9fa;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
        }
        .profile-picture-placeholder {
            width: 150px;
            height: 150px;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-book"></i> <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="../index.php">Home</a>
                    <a class="nav-link" href="../browse.php">Browse Books</a>
                    <a class="nav-link" href="dashboard.php">Admin Dashboard</a>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item active" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php display_flash_message(); ?>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item active"><?php echo $user_id === $current_id ? 'Admin Profile' : 'User Profile'; ?></li>
            </ol>
        </nav>

        <!-- User selection dropdown -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-users"></i> Select User to Edit</h5>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Select User</label>
                        <select class="form-select" id="user_id" name="user_id" onchange="this.form.submit()">
                            <option value="">-- My Profile --</option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $user_id === $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($user_id !== $current_id): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> You are editing the profile of <?php echo htmlspecialchars($user_data['username']); ?>.
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <!-- Profile Picture -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-user-circle"></i> Profile Picture</h5>
                </div>
                <div class="card-body text-center">
                    <?php if ($user_data['profile_picture'] && file_exists(PROFILE_UPLOAD_PATH . $user_data['profile_picture'])): ?>
                        <img src="<?php echo PROFILE_UPLOAD_PATH . htmlspecialchars($user_data['profile_picture']); ?>" 
                             class="profile-picture mb-3" alt="Profile Picture">
                    <?php else: ?>
                        <div class="profile-picture-placeholder mb-3">
                            <i class="fas fa-user fa-3x text-muted"></i>
                        </div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_picture" value="1">
                        <div class="mb-3">
                            <label for="profile_picture" class="form-label">Upload New Picture (JPG/PNG/GIF, Max 2MB)</label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-upload"></i> Upload Picture
                        </button>
                    </form>
                </div>
            </div>

            <!-- Profile Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-user"></i> Profile Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Username</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['username']); ?></dd>
                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['email']); ?></dd>
                        <dt class="col-sm-4">First Name</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['first_name']); ?></dd>
                        <dt class="col-sm-4">Last Name</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['last_name']); ?></dd>
                        <?php if ($user_type === 'user'): ?>
                            <dt class="col-sm-4">Phone</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['phone'] ?? 'N/A'); ?></dd>
                            <dt class="col-sm-4">Address</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['address'] ?? 'N/A'); ?></dd>
                            <dt class="col-sm-4">City</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['city'] ?? 'N/A'); ?></dd>
                            <dt class="col-sm-4">State</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['state'] ?? 'N/A'); ?></dd>
                            <dt class="col-sm-4">Zipcode</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['zipcode'] ?? 'N/A'); ?></dd>
                            <dt class="col-sm-4">User Type</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['user_type']); ?></dd>
                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['status']); ?></dd>
                            <dt class="col-sm-4">Rating</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['rating'] ?? 'N/A'); ?></dd>
                        <?php else: ?>
                            <dt class="col-sm-4">Role</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($user_data['role']); ?></dd>
                        <?php endif; ?>
                        <dt class="col-sm-4">Joined</dt>
                        <dd class="col-sm-8"><?php echo date('F j, Y', strtotime($user_data['created_at'])); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Update Profile Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-edit"></i> Update Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                            </div>
                            <?php if ($user_type === 'user'): ?>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" required pattern="\d{10}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="address" name="address" 
                                           value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?php echo htmlspecialchars($user_data['city'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="state" class="form-label">State</label>
                                    <input type="text" class="form-control" id="state" name="state" 
                                           value="<?php echo htmlspecialchars($user_data['state'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="zipcode" class="form-label">Zipcode</label>
                                    <input type="text" class="form-control" id="zipcode" name="zipcode" 
                                           value="<?php echo htmlspecialchars($user_data['zipcode'] ?? ''); ?>" required pattern="\d{5}">
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        function showFlashMessage(message, type) {
            const flashContainer = document.querySelector('.container.mt-4');
            const flashMessage = document.createElement('div');
            flashMessage.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
            flashMessage.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            flashContainer.insertBefore(flashMessage, flashContainer.firstChild);
            setTimeout(() => {
                flashMessage.classList.remove('show');
                setTimeout(() => {
                    flashMessage.remove();
                }, 150);
            }, 5000);
        }
    </script>
</body>
</html>