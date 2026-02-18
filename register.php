<?php
// User registration page
if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
    require_once 'config/database.php';
    require_once 'config/config.php';
    require_once 'models/User.php';

    if (is_logged_in()) {
        redirect('dashboard.php');
    }

    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);

    $errors = [];
    $form_data = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form_data = [
            'username' => sanitize_input($_POST['username'] ?? ''),
            'email' => sanitize_input($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'first_name' => sanitize_input($_POST['first_name'] ?? ''),
            'last_name' => sanitize_input($_POST['last_name'] ?? ''),
            'phone' => sanitize_input($_POST['phone'] ?? ''),
            'address' => sanitize_input($_POST['address'] ?? ''),
            'city' => sanitize_input($_POST['city'] ?? ''),
            'state' => sanitize_input($_POST['state'] ?? ''),
            'zipcode' => sanitize_input($_POST['zipcode'] ?? ''),
            'user_type' => $_POST['user_type'] ?? 'both'
        ];

        // Validation
        if (empty($form_data['username'])) $errors[] = 'Username is required.';
        if (empty($form_data['email'])) $errors[] = 'Email is required.';
        if (empty($form_data['password'])) $errors[] = 'Password is required.';
        if ($form_data['password'] !== $form_data['confirm_password']) $errors[] = 'Passwords do not match.';
        if (strlen($form_data['password']) < 6) $errors[] = 'Password must be at least 6 characters.';
        if (empty($form_data['first_name'])) $errors[] = 'First name is required.';
        if (empty($form_data['last_name'])) $errors[] = 'Last name is required.';

        if (empty($errors)) {
            try {
                if ($user->register($form_data)) {
                    flash_message('Registration successful! Please login.', 'success');
                    redirect('login.php');
                } else {
                    $errors[] = 'Registration failed. Username or email may already exist.';
                }
            } catch (Exception $e) {
                $errors[] = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-book"></i> <?php echo SITE_NAME; ?>
            </a>
        </div>
    </nav>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">
                            <i class="fas fa-user-plus"></i> Create Account
                        </h3>

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
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($form_data['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?php echo htmlspecialchars($form_data['city'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="state" class="form-label">State</label>
                                    <input type="text" class="form-control" id="state" name="state" 
                                           value="<?php echo htmlspecialchars($form_data['state'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="zipcode" class="form-label">ZIP Code</label>
                                    <input type="text" class="form-control" id="zipcode" name="zipcode" 
                                           value="<?php echo htmlspecialchars($form_data['zipcode'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="user_type" class="form-label">Account Type</label>
                                <select class="form-select" id="user_type" name="user_type">
                                    <option value="both" <?php echo ($form_data['user_type'] ?? 'both') === 'both' ? 'selected' : ''; ?>>
                                        Both Buyer & Seller
                                    </option>
                                    <option value="buyer" <?php echo ($form_data['user_type'] ?? '') === 'buyer' ? 'selected' : ''; ?>>
                                        Buyer Only
                                    </option>
                                    <option value="seller" <?php echo ($form_data['user_type'] ?? '') === 'seller' ? 'selected' : ''; ?>>
                                        Seller Only
                                    </option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-user-plus"></i> Create Account
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
} else {
    // Redirect to homepage if accessed directly
    redirect('index.php');
}