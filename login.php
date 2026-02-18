<?php
// User login page
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/User.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $user_data = $user->login($email, $password);
        if ($user_data) {
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['user_type'] = $user_data['user_type'];
            
            flash_message('Welcome back, ' . $user_data['first_name'] . '!', 'success');
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
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 50%, #dee2e6 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(0,0,0,0.02) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(0,0,0,0.02) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(0,0,0,0.02) 0%, transparent 50%);
            animation: backgroundShift 15s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes backgroundShift {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Navigation */
        .navbar {
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 2;
            padding: 16px 0;
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
        }

        .navbar-brand {
            color: #ffffff;
            text-decoration: none;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            color: #ffffff;
            transform: translateY(-1px);
        }

        .navbar-brand i {
            font-size: 24px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 
                0 32px 64px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(0, 0, 0, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
            transform: translateY(0);
            transition: all 0.4s ease;
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
            background: linear-gradient(90deg, transparent, rgba(0,0,0,0.1), transparent);
        }

        .login-card:hover {
            transform: translateY(-8px);
            box-shadow: 
                0 48px 80px rgba(0, 0, 0, 0.2),
                0 0 0 1px rgba(0, 0, 0, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 1);
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
            background: linear-gradient(135deg, #000000 0%, #2d2d2d 100%);
            border-radius: 20px;
            margin-bottom: 24px;
            box-shadow: 
                0 16px 32px rgba(0, 0, 0, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .login-icon:hover {
            transform: scale(1.05) rotate(5deg);
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
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
                0 8px 24px rgba(0, 0, 0, 0.08);
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
            margin-bottom: 32px;
        }

        .login-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.25);
        }

        .login-button:hover::before {
            left: 100%;
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-links {
            text-align: center;
            border-top: 1px solid #e0e0e0;
            padding-top: 24px;
        }

        .login-links p {
            margin: 12px 0;
            font-size: 14px;
            color: #666666;
        }

        .login-links a {
            color: #000000;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }

        .login-links a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: #000000;
            transition: width 0.3s ease;
        }

        .login-links a:hover {
            color: #000000;
        }

        .login-links a:hover::after {
            width: 100%;
        }

        .admin-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(0, 0, 0, 0.05);
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-top: 8px;
        }

        .admin-link:hover {
            background: rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
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
            .navbar-container {
                padding: 0 16px;
            }
            
            .main-content {
                padding: 20px 16px;
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
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }

        /* Welcome animation */
        .login-card {
            animation: welcomeIn 0.6s ease-out;
        }

        @keyframes welcomeIn {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-book"></i>
                <?php echo SITE_NAME; ?>
            </a>
        </div>
    </nav>

    <div class="main-content">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <div class="login-icon">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                    <h1 class="login-title">Welcome Back</h1>
                    <p class="login-subtitle">Sign in to your account</p>
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
                            autocomplete="email"
                        >
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
                            autocomplete="current-password"
                        >
                    </div>

                    <button type="submit" class="login-button" id="loginBtn">
                        <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>
                        Sign In
                    </button>
                </form>

                <div class="login-links">
                    <p>Don't have an account? <a href="register.php">Create one here</a></p>
                    <p><a href="admin/login.php" class="admin-link">
                        <i class="fas fa-shield-alt"></i>
                        Admin Access
                    </a></p>
                </div>
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

        // Enhanced keyboard navigation
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

        // Add subtle parallax effect to background
        document.addEventListener('mousemove', function(e) {
            const moveX = (e.clientX / window.innerWidth) * 2 - 1;
            const moveY = (e.clientY / window.innerHeight) * 2 - 1;
            
            document.body.style.backgroundPosition = 
                `${moveX * 10}px ${moveY * 10}px, ${moveX * -15}px ${moveY * -15}px, ${moveX * 20}px ${moveY * 20}px`;
        });

        // Auto-focus first input on page load
        window.addEventListener('load', function() {
            const emailInput = document.getElementById('email');
            if (emailInput && !emailInput.value) {
                setTimeout(() => emailInput.focus(), 300);
            }
        });
    </script>
</body>
</html>