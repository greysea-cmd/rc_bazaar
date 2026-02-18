<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'models/Rating.php';

// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please login to submit a rating']);
    exit;
}

// Initialize database and rating model
$database = new Database();
$db = $database->getConnection();
$rating_model = new Rating($db);

// Get POST data
$book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;

header('Content-Type: application/json');

if ($book_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid book ID or rating']);
    exit;
}

$user_id = $_SESSION['user_id'];
$result = $rating_model->addRating($user_id, $book_id, $rating);

echo json_encode($result);
exit;
?>