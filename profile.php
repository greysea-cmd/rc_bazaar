<?php
if (basename($_SERVER['PHP_SELF']) === 'profile.php') {
    require_once 'config/database.php';
    require_once 'config/config.php';
    require_once 'models/User.php';

    if (!is_logged_in()) {
        flash_message('Please login to view your profile.', 'error');
        redirect('login.php');
    }

    $database = new Database();
    $db = $database->getConnection();
    $user_model = new User($db);
    $user_id = get_current_user_id();
    $user_data = $user_model->getUserById($user_id);

    if (!$user_data) {
        flash_message('User not found.', 'error');
        redirect('logout.php');
    }

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_profile'])) {
            $username = sanitize_input($_POST['username'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');

            if (empty($username) || empty($email)) {
                flash_message('Username and email are required.', 'error');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash_message('Invalid email format.', 'error');
            } else {
                if ($user_model->updateProfile($username, $email)) {
                    flash_message('Profile updated successfully!', 'success');
                    redirect('profile.php');
                } else {
                    flash_message('Failed to update profile. Please try again.', 'error');
                }
            }
        } elseif (isset($_POST['update_picture']) && isset($_FILES['profile_picture'])) {
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024; // 2MB
            $file = $_FILES['profile_picture'];

            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                flash_message('No file was uploaded.', 'error');
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                flash_message('File upload error.', 'error');
            } elseif (!in_array($file['type'], $allowed_types)) {
                flash_message('Only JPG and PNG files are allowed.', 'error');
            } elseif ($file['size'] > $max_size) {
                flash_message('File size exceeds 2MB limit.', 'error');
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                $upload_dir = 'uploads/profiles/';
                $upload_path = $upload_dir . $filename;

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $old_picture = $user_model->getProfilePicture($user_id);
                    if ($old_picture && file_exists($upload_dir . $old_picture)) {
                        unlink($upload_dir . $old_picture);
                    }

                    if ($user_model->updateProfilePicture($user_id, $filename)) {
                        flash_message('Profile picture updated successfully!', 'success');
                        redirect('profile.php');
                    } else {
                        flash_message('Failed to update profile picture in database.', 'error');
                        unlink($upload_path);
                    }
                } else {
                    flash_message('Failed to upload profile picture.', 'error');
                }
            }
        } elseif (isset($_POST['update_password'])) {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                flash_message('All password fields are required.', 'error');
            } elseif ($new_password !== $confirm_password) {
                flash_message('New password and confirmation do not match.', 'error');
            } elseif (strlen($new_password) < 6) {
                flash_message('New password must be at least 6 characters long.', 'error');
            } elseif (!$user_model->verifyPassword($user_id, $current_password)) {
                flash_message('Current password is incorrect.', 'error');
            } else {
                if ($user_model->updatePassword($user_id, $new_password)) {
                    flash_message('Password updated successfully!', 'success');
                    redirect('profile.php');
                } else {
                    flash_message('Failed to update password. Please try again.', 'error');
                }
            }
        } elseif (isset($_POST['remove_picture'])) {
            $old_picture = $user_model->getProfilePicture($user_id);
            $upload_dir = 'uploads/profiles/';

            if ($old_picture && file_exists($upload_dir . $old_picture)) {
                unlink($upload_dir . $old_picture);
            }

            if ($user_model->updateProfilePicture($user_id, null)) {
                flash_message('Profile picture removed successfully!', 'success');
            } else {
                flash_message('Failed to remove profile picture.', 'error');
            }
            redirect('profile.php');
        }
    }

    $profile_picture = $user_model->getProfilePicture($user_id);
    $profile_picture_path = $profile_picture ? 'uploads/profiles/' . $profile_picture : null;
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>My Profile - <?php echo SITE_NAME; ?></title>
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

            .user-avatar::after {
                content: attr(data-tooltip);
                position: absolute;
                bottom: -30px;
                left: 50%;
                transform: translateX(-50%);
                background: var(--black);
                color: var(--white);
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                opacity: 0;
                transition: opacity 0.2s;
                pointer-events: none;
                white-space: nowrap;
                z-index: 100;
            }

            .user-avatar:hover::after {
                opacity: 1;
            }

            /* Profile Content */
            .profile-section {
                background: var(--white);
                border-radius: 10px;
                padding: 1.5rem;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                border: 1px solid var(--border);
                margin-bottom: 1.5rem;
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

            .profile-avatar {
                width: 120px;
                height: 120px;
                border-radius: 50%;
                margin: 0 auto 1.5rem;
                background: var(--light-grey);
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                position: relative;
            }

            .profile-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .avatar-initial {
                font-size: 3rem;
                font-weight: 600;
                color: var(--grey);
            }

            .profile-info {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .info-item {
                margin-bottom: 1rem;
            }

            .info-label {
                font-size: 0.875rem;
                color: var(--grey);
                margin-bottom: 0.25rem;
            }

            .info-value {
                font-size: 1rem;
                font-weight: 500;
            }

            .form-group {
                margin-bottom: 1.25rem;
            }

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
            }

            .btn-primary {
                background: var(--black);
                color: var(--white);
            }

            .btn-primary:hover {
                background: var(--dark-grey);
            }

            .btn-danger {
                background: #ffebee;
                color: #c62828;
            }

            .btn-danger:hover {
                background: #ffcdd2;
            }

            .picture-actions {
                display: flex;
                gap: 0.75rem;
                justify-content: center;
                margin-top: 1rem;
            }

            .text-center {
                text-align: center;
            }

            .text-muted {
                color: var(--grey);
                font-size: 0.875rem;
            }

            /* Flash Messages */
            .flash-message {
                padding: 1rem;
                margin-bottom: 1.5rem;
                border-radius: 6px;
                font-weight: 500;
            }

            .flash-success {
                background: #e6f7e6;
                color: #2e7d32;
                border-left: 4px solid #2e7d32;
            }

            .flash-error {
                background: #ffebee;
                color: #c62828;
                border-left: 4px solid #c62828;
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
                    <a href="sell-book.php" class="nav-item">
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
                    <a href="profile.php" class="nav-item active">
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
                    <h2>My Profile</h2>
                    <div class="user-menu">
                        <span>Welcome, <?php echo htmlspecialchars($user_data['first_name']); ?></span>
                        <div class="user-avatar" data-tooltip="<?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>">
                            <?php if (!empty($profile_picture_path) && file_exists($profile_picture_path)): ?>
                                <img src="<?php echo htmlspecialchars($profile_picture_path); ?>"
                                    alt="Profile Picture"
                                    onerror="handleImageError(this)">
                            <?php else: ?>
                                <span class="user-initial"><?php echo strtoupper(substr($user_data['first_name'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </header>

                <?php display_flash_message(); ?>

                <!-- Profile Picture Section -->
                <div class="profile-section">
                    <div class="section-header">
                        <i class="fas fa-user-circle"></i>
                        <h3>Profile Picture</h3>
                    </div>

                    <div class="text-center">
                        <div class="profile-avatar">
                            <?php if (!empty($profile_picture_path) && file_exists($profile_picture_path)): ?>
                                <img src="<?php echo htmlspecialchars($profile_picture_path); ?>"
                                    alt="Profile Picture"
                                    onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=&quot;avatar-initial&quot;><?php echo strtoupper(substr($user_data['first_name'], 0, 1)); ?></div>'">
                            <?php else: ?>
                                <div class="avatar-initial"><?php echo strtoupper(substr($user_data['first_name'], 0, 1)); ?></div>
                            <?php endif; ?>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="profile_picture" class="form-label">Upload new picture (JPG/PNG, max 2MB)</label>
                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png">
                            </div>
                            <div class="picture-actions">
                                <button type="submit" name="update_picture" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
                                <?php if (!empty($profile_picture_path) && file_exists($profile_picture_path)): ?>
                                    <button type="submit" name="remove_picture" class="btn btn-danger"><i class="fas fa-trash"></i> Remove</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Profile Information Section -->
                <div class="profile-section">
                    <div class="section-header">
                        <i class="fas fa-info-circle"></i>
                        <h3>Profile Information</h3>
                    </div>

                    <div class="profile-info">
                        <div class="info-item">
                            <div class="info-label">Username</div>
                            <div class="info-value"><?php echo htmlspecialchars($user_data['username']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($user_data['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Member Since</div>
                            <div class="info-value"><?php echo date('F j, Y', strtotime($user_data['created_at'])); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Update Profile Section -->
                <div class="profile-section">
                    <div class="section-header">
                        <i class="fas fa-edit"></i>
                        <h3>Update Profile</h3>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    </form>
                </div>

                <!-- Change Password Section -->
                <div class="profile-section">
                    <div class="section-header">
                        <i class="fas fa-lock"></i>
                        <h3>Change Password</h3>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="update_password" value="1">
                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="text-muted">Must be at least 6 characters</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Update Password</button>
                    </form>
                </div>
            </main>
        </div>

        <script>
            function handleImageError(img) {
                // Hide the broken image
                img.style.display = 'none';

                // Get the initial from the user's first name
                const initial = '<?php echo strtoupper(substr($user_data['first_name'], 0, 1)); ?>';

                // Create a fallback display
                const fallback = document.createElement('span');
                fallback.className = 'user-initial';
                fallback.textContent = initial;

                // Insert the fallback
                img.parentNode.insertBefore(fallback, img);
            }

            // Preview image before upload
            document.getElementById('profile_picture').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const avatar = document.querySelector('.profile-avatar');
                        avatar.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    }
                    reader.readAsDataURL(file);
                }
            });
        </script>
    </body>

    </html>

<?php
}
?>