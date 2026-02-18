<?php
session_start();
require_once 'config/database.php';
require_once 'models/Cart.php';

header('Content-Type: application/json');

// Debug logging
error_log("Cart API called with: " . print_r($_POST, true));

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in for cart operation");
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to modify your cart.'
    ]);
    exit;
}

// Initialize DB and model
$database = new Database();
$db = $database->getConnection();
$cart = new Cart($db);

// Get and sanitize input
$book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

error_log("Parsed parameters - book_id: $book_id, action: $action");

// Validate request
if ($book_id <= 0) {
    error_log("Invalid book_id: $book_id");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid book ID. Please try again.'
    ]);
    exit;
}

if (!in_array($action, ['add', 'remove'])) {
    error_log("Invalid action: $action");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action. Please try again.'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
error_log("User ID: $user_id");

// Check if book exists and is approved
$stmt = $db->prepare("SELECT id, status, price, quantity FROM books WHERE id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    error_log("Book not found with ID: $book_id");
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Book not found.'
    ]);
    exit;
}

if ($book['status'] !== 'approved') {
    error_log("Book not approved. Status: " . $book['status']);
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'This book is not available.'
    ]);
    exit;
}

// Check stock availability for adding to cart
if ($action === 'add' && $book['quantity'] <= 0) {
    error_log("Book out of stock. Quantity: " . $book['quantity']);
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'This book is out of stock.'
    ]);
    exit;
}

// Prevent adding own book to cart
$stmt = $db->prepare("SELECT id FROM books WHERE id = ? AND seller_id = ?");
$stmt->execute([$book_id, $user_id]);
if ($stmt->fetch()) {
    error_log("User trying to add own book. User ID: $user_id, Book ID: $book_id");
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You cannot add your own book to the cart.'
    ]);
    exit;
}

// Perform action
try {
    if ($action === 'add') {
        error_log("Adding book $book_id to cart for user $user_id");
        $result = $cart->addToCart($user_id, $book_id, $book['price']);
    } else {
        error_log("Removing book $book_id from cart for user $user_id");
        $result = $cart->removeFromCart($user_id, $book_id);
    }
    
    error_log("Cart operation result: " . print_r($result, true));
    
    // Get updated cart count
    $cart_count = $cart->getCartItemCount($user_id);
    $result['cart_count'] = $cart_count;
    
    echo json_encode($result);
} catch (Exception $e) {
    error_log("Cart error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}