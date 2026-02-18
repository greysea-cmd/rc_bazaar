<?php
if (basename($_SERVER['PHP_SELF']) === 'logout.php') {
    require_once 'config/config.php';
    
    session_destroy();
    flash_message('You have been logged out successfully.', 'info');
    redirect('index.php');
}