<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/User.php';

if (!is_logged_in()) {
    flash_message('Please login to upload profile picture', 'error');
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_picture'])) {
        // Handle picture deletion
        if ($user->deleteProfilePicture($user_id)) {
            flash_message('Profile picture removed successfully', 'success');
        } else {
            flash_message('Failed to remove profile picture', 'error');
        }
        redirect('profile.php');
    }
    
    if (isset($_FILES['profile_picture'])) {
        $upload_dir = 'uploads/profile/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            flash_message('File upload error', 'error');
            redirect('profile.php');
        }
        
        if ($_FILES['profile_picture']['size'] > $max_size) {
            flash_message('File is too large (max 2MB)', 'error');
            redirect('profile.php');
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['profile_picture']['tmp_name']);
        
        if (!in_array($mime, $allowed_types)) {
            flash_message('Invalid file type (only JPG, PNG, GIF allowed)', 'error');
            redirect('profile.php');
        }
        
        // Generate unique filename
        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
        $target_path = $upload_dir . $filename;
        
        // Delete old picture if exists
        $old_picture = $user->getProfilePicture($user_id);
        if ($old_picture && file_exists($old_picture)) {
            unlink($old_picture);
        }
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
            // Update database
            if ($user->updateProfilePicture($user_id, $filename)) {
                flash_message('Profile picture updated successfully', 'success');
            } else {
                // Delete the uploaded file if DB update failed
                unlink($target_path);
                flash_message('Failed to update profile in database', 'error');
            }
        } else {
            flash_message('Failed to save uploaded file', 'error');
        }
        
        redirect('profile.php');
    }
}

// If not a POST request or no file uploaded
flash_message('Invalid request', 'error');
redirect('profile.php');