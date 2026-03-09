<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = dirname($_SERVER['SCRIPT_NAME']);

    if ($script_name !== '/') {
        $script_name = rtrim($script_name, '/');
    }

    define('BASE_URL', $protocol . $host . $script_name . '/');
}

// Site configuration
define('SITE_NAME', 'RC Bazaar');
define('SITE_URL', 'http://localhost/rc_bazaar');
define('UPLOAD_PATH', 'Uploads/books/');
define('PROFILE_UPLOAD_PATH', 'Uploads/profiles/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('PROFILE_MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB for profiles

// Email configuration
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@bookbazaar.com');
define('SMTP_PASS', 'your_email_password');

// Pagination
define('ITEMS_PER_PAGE', 12);

// Security
define('HASH_ALGO', PASSWORD_DEFAULT);

// eSewa Configuration
define('ESEWA_MERCHANT_CODE', 'EPAYTEST');   // Provided by eSewa (test code)
define('ESEWA_SECRET_KEY', '8gBm/:&EnhH.1/q'); // Test secret key
define('ESEWA_API_URL', 'https://rc-epay.esewa.com.np/api/epay/main/v2/form'); // Sandbox
define('ESEWA_VERIFY_URL', 'https://rc-epay.esewa.com.np/api/epay/transaction/status'); // Verification endpoint
define('ESEWA_SUCCESS_URL', BASE_URL . 'esewa-success.php');
define('ESEWA_FAILURE_URL', BASE_URL . 'esewa-failure.php');

// Only define functions if they don't already exist (prevents redeclare errors)
if (!function_exists('sanitize_input')) {
    function sanitize_input($data)
    {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

if (!function_exists('redirect')) {
    function redirect($url)
    {
        header("Location: " . SITE_URL . '/' . ltrim($url, '/'));
        exit();
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in()
    {
        return isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
    }
}

if (!function_exists('is_admin')) {
    function is_admin()
    {
        return isset($_SESSION['admin_id']);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('get_current_admin_id')) {
    function get_current_admin_id()
    {
        return $_SESSION['admin_id'] ?? null;
    }
}

if (!function_exists('get_current_id')) {
    function get_current_id()
    {
        return is_admin() ? get_current_admin_id() : get_current_user_id();
    }
}

if (!function_exists('get_current_type')) {
    function get_current_type()
    {
        return is_admin() ? 'admin' : 'user';
    }
}

if (!function_exists('flash_message')) {
    function flash_message($message, $type = 'info')
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
}

if (!function_exists('display_flash_message')) {
    function display_flash_message()
    {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            $type = $_SESSION['flash_type'] ?? 'info';
            echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>";
            echo htmlspecialchars($message);
            echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
            echo "</div>";
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        }
    }
}

if (!function_exists('format_price')) {
    function format_price($price)
    {
        return 'NPR ' . number_format($price, 2);
    }
}

if (!function_exists('time_ago')) {
    function time_ago($datetime)
    {
        $timestamp = strtotime($datetime);
        if (!$timestamp) return 'Invalid date';

        $time = time() - $timestamp;
        if ($time < 60) return 'just now';
        if ($time < 3600) return floor($time / 60) . ' minutes ago';
        if ($time < 86400) return floor($time / 3600) . ' hours ago';
        if ($time < 2592000) return floor($time / 86400) . ' days ago';
        if ($time < 31536000) return floor($time / 2592000) . ' months ago';
        return floor($time / 31536000) . ' years ago';
    }
}

if (!function_exists('upload_image')) {
    function upload_image($file, $upload_dir = UPLOAD_PATH)
    {
        global $upload_errors;
        $upload_errors = [
            UPLOAD_ERR_OK => "No errors.",
            UPLOAD_ERR_INI_SIZE => "Larger than upload_max_filesize.",
            UPLOAD_ERR_FORM_SIZE => "Larger than form MAX_FILE_SIZE.",
            UPLOAD_ERR_PARTIAL => "Partial upload.",
            UPLOAD_ERR_NO_FILE => "No file uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder.",
            UPLOAD_ERR_CANT_WRITE => "Can't write to disk.",
            UPLOAD_ERR_EXTENSION => "File upload stopped by extension."
        ];

        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($upload_errors[$file['error']] ?? 'Unknown upload error.');
        }

        $upload_dir = rtrim($upload_dir, '/') . '/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = $upload_dir === PROFILE_UPLOAD_PATH ? PROFILE_MAX_FILE_SIZE : MAX_FILE_SIZE;

        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
        }

        if ($file['size'] > $max_size) {
            throw new Exception('File too large. Maximum size is ' . ($max_size / (1024 * 1024)) . 'MB.');
        }

        $filename = uniqid('img_') . '_' . basename($file['name']);
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $filename;
        } else {
            throw new Exception('Failed to move uploaded file.');
        }
    }
}

if (!function_exists('send_order_confirmation_email')) {
    function send_order_confirmation_email($email, $order_ids, $transaction_code = null)
    {
        // Email sending logic
        $subject = "Order Confirmation - " . SITE_NAME;
        $message = "Thank you for your order!\n\n";
        $message .= "Order ID(s): " . implode(', ', $order_ids) . "\n";

        if ($transaction_code) {
            $message .= "Transaction ID: " . $transaction_code . "\n";
        }

        $message .= "\nYou can view your order details at: " . SITE_URL . "my-orders.php\n\n";
        $message .= "Thank you,\n" . SITE_NAME . " Team";


        error_log("Email would be sent to: $email, Subject: $subject");
    }
}

if (!function_exists('generate_esewa_signature')) {
    /**
     * Generate eSewa signature for payment request
     * @param float $total_amount The total amount
     * @param string $transaction_uuid Unique transaction ID
     * @param string $product_code eSewa merchant/product code
     * @return string Base64 encoded signature
     */
    function generate_esewa_signature($total_amount, $transaction_uuid, $product_code)
    {
        $secret_key = defined('ESEWA_SECRET_KEY') ? ESEWA_SECRET_KEY : '8gBm/:&EnhH.1/q';
        
        // EXACT string eSewa expects - NO SPACES, exact order
        $data_string = "total_amount={$total_amount},transaction_uuid={$transaction_uuid},product_code={$product_code}";
        
        // Debug log
        error_log("eSewa Signature Data: " . $data_string);
        
        // Generate HMAC SHA256
        $signature = hash_hmac('sha256', $data_string, $secret_key, true);
        
        // Convert to base64
        $signature = base64_encode($signature);
        
        error_log("eSewa Generated Signature: " . $signature);
        
        return $signature;
    }
}

if (!function_exists('verify_esewa_signature')) {
    /**
     * Verify eSewa response signature
     * @param string $data_string The data string in format "total_amount=...,transaction_uuid=...,product_code=..."
     * @param string $received_signature The signature received from eSewa
     * @return bool True if signature is valid
     */
    function verify_esewa_signature($data_string, $received_signature)
    {
        $secret_key = defined('ESEWA_SECRET_KEY') ? ESEWA_SECRET_KEY : '8gBm/:&EnhH.1/q';
        
        // Parse the data string
        $pairs = explode(',', $data_string);
        $data = [];
        foreach ($pairs as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2) {
                $data[trim($parts[0])] = trim($parts[1]);
            }
        }
        
        // Generate expected signature
        $expected_signature = generate_esewa_signature(
            $data['total_amount'] ?? '',
            $data['transaction_uuid'] ?? '',
            $data['product_code'] ?? ''
        );
        
        // Compare securely
        return hash_equals($expected_signature, $received_signature);
    }
}

if (!function_exists('verify_esewa_response')) {
    /**
     * Verify eSewa callback/response data
     * @param array $data The POST data from eSewa
     * @return bool True if signature is valid
     */
    function verify_esewa_response($data)
    {
        // Extract required fields
        $total_amount = $data['total_amount'] ?? '';
        $transaction_uuid = $data['transaction_uuid'] ?? '';
        $product_code = $data['product_code'] ?? '';
        $received_signature = $data['signature'] ?? '';
        
        // Create data string in same format as sent
        $data_string = "total_amount={$total_amount},transaction_uuid={$transaction_uuid},product_code={$product_code}";
        
        return verify_esewa_signature($data_string, $received_signature);
    }
}

if (!function_exists('generate_esewa_payment_url')) {
    /**
     * Generate eSewa payment URL with parameters
     * @param float $amount Payment amount
     * @param string $transaction_uuid Unique transaction ID
     * @return string Complete eSewa payment URL
     */
    function generate_esewa_payment_url($amount, $transaction_uuid)
    {
        $product_code = ESEWA_MERCHANT_CODE;
        
        // Format amount to 2 decimal places
        $formatted_amount = number_format(floatval($amount), 2, '.', '');
        
        // Generate signature
        $signature = generate_esewa_signature($formatted_amount, $transaction_uuid, $product_code);
        
        // Build query parameters
        $params = [
            'amount' => $formatted_amount,
            'tax_amount' => '0',
            'total_amount' => $formatted_amount,
            'transaction_uuid' => $transaction_uuid,
            'product_code' => $product_code,
            'product_service_charge' => '0',
            'product_delivery_charge' => '0',
            'success_url' => ESEWA_SUCCESS_URL,
            'failure_url' => ESEWA_FAILURE_URL,
            'signed_field_names' => 'total_amount,transaction_uuid,product_code',
            'signature' => $signature
        ];
        
        // Return complete URL
        return ESEWA_API_URL . '?' . http_build_query($params);
    }
}