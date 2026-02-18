<?php
if (basename($_SERVER['PHP_SELF']) === 'logout.php') {
    require_once '../config/config.php';
    
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role']);
    flash_message('Admin logged out successfully.', 'info');
    redirect('login.php');
}
?>