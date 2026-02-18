<?php
// admin/login.php - Admin login page
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../models/Admin.php';

// Add proper session start if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is admin
function is_admin()
{
    return isset($_SESSION['admin_id']);
}

// Improved redirect function
function redirect($url)
{
    // Determine base path for admin section
    $admin_base = dirname($_SERVER['PHP_SELF']);
    $redirect_url = $admin_base . '/' . $url;

    // Ensure we don't have double slashes
    $redirect_url = str_replace('//', '/', $redirect_url);

    header("Location: $redirect_url");
    exit();
}

if (is_admin()) {
    redirect('dashboard.php');
}

$database = new Database();
$db = $database->getConnection();
$admin = new Admin($db);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $admin_data = $admin->login($email, $password);
        if ($admin_data) {
            $_SESSION['admin_id'] = $admin_data['id'];
            $_SESSION['admin_username'] = $admin_data['username'];
            $_SESSION['admin_role'] = $admin_data['role'];

            flash_message('Welcome to admin panel!', 'success');
            redirect('dashboard.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 50%, #2d2d2d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s linear infinite;
            z-index: 0;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) rotate(0deg);
            }

            100% {
                transform: translate(-50px, -50px) rotate(360deg);
            }
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow:
                0 32px 64px rgba(0, 0, 0, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            transform: translateY(0);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(0, 0, 0, 0.1), transparent);
        }

        .login-card:hover {
            transform: translateY(-8px);
            box-shadow:
                0 48px 80px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(255, 255, 255, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #2d2d2d 0%, #000000 100%);
            border-radius: 20px;
            margin-bottom: 24px;
            box-shadow:
                0 16px 32px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .login-icon:hover {
            transform: scale(1.05);
            box-shadow:
                0 20px 40px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .login-icon i {
            font-size: 28px;
            color: #ffffff;
        }

        .login-title {
            font-size: 28px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            font-size: 16px;
            color: #666666;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333333;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }

        .form-input {
            width: 100%;
            padding: 16px 20px;
            font-size: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            background: #ffffff;
            color: #000000;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: #000000;
            box-shadow:
                0 0 0 4px rgba(0, 0, 0, 0.1),
                0 8px 24px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .form-input::placeholder {
            color: #999999;
        }

        .login-button {
            width: 100%;
            padding: 18px 24px;
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
            background: linear-gradient(135deg, #000000 0%, #2d2d2d 100%);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-transform: none;
            letter-spacing: 0.5px;
        }

        .login-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.3);
        }

        .login-button:hover::before {
            left: 100%;
        }

        .login-button:active {
            transform: translateY(0);
        }

        .back-link {
            text-align: center;
            margin-top: 32px;
        }

        .back-link a {
            color: #666666;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-link a:hover {
            color: #000000;
            transform: translateX(-4px);
        }

        .error-alert {
            background: linear-gradient(135deg, #f8f8f8 0%, #eeeeee 100%);
            border: 1px solid #d0d0d0;
            color: #333333;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            position: relative;
            animation: slideIn 0.3s ease;
        }

        .error-alert::before {
            content: '⚠';
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #666666;
        }

        .error-alert {
            padding-left: 50px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .login-container {
                padding: 16px;
            }

            .login-card {
                padding: 32px 24px;
                border-radius: 20px;
            }

            .login-title {
                font-size: 24px;
            }

            .login-icon {
                width: 64px;
                height: 64px;
            }

            .login-icon i {
                font-size: 24px;
            }
        }

        /* Loading state */
        .login-button.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .login-button.loading::after {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        @keyframes spin {
            0% {
                transform: translateY(-50%) rotate(0deg);
            }

            100% {
                transform: translateY(-50%) rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="login-title">Admin Access</h1>
                <p class="login-subtitle"><?php echo SITE_NAME; ?> Administration</p>
            </div>

            <?php if ($error): ?>
                <div class="error-alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input
                        type="email"
                        class="form-input"
                        id="email"
                        name="email"
                        value="<?php echo htmlspecialchars($email ?? ''); ?>"
                        placeholder="Enter your email"
                        required
                        autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        class="form-input"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password">
                </div>

                <button type="submit" class="login-button" id="loginBtn">
                    <i class="fas"></i>
                    Access Admin Panel
                </button>
            </form>

            <div class="back-link">
                <a href="../index.php">
                    <i class="fas"></i>
                    Back to Main Site
                </a>
            </div>
        </div>
    </div>

    <script>
        // Add loading state to button on form submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>Signing In...';
        });

        // Add subtle animations on input focus
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });

            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Keyboard navigation enhancement
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
                const form = document.getElementById('loginForm');
                const inputs = form.querySelectorAll('input[required]');
                const currentIndex = Array.from(inputs).indexOf(document.activeElement);

                if (currentIndex !== -1 && currentIndex < inputs.length - 1) {
                    e.preventDefault();
                    inputs[currentIndex + 1].focus();
                }
            }
        });
    </script>
</body>

</html>