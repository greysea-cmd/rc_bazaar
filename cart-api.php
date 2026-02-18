<?php
// cart-api.php
session_start();
require_once 'config/database.php';
require_once 'models/Cart.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to modify your cart.'
    ]);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$cart = new Cart($db);

$book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$user_id = $_SESSION['user_id'];

// Validate
if ($book_id <= 0 || !in_array($action, ['add', 'remove'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

try {
    if ($action === 'add') {
        // Get book price first
        $stmt = $db->prepare("SELECT price FROM books WHERE id = ? AND status = 'approved'");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch();
        
        if (!$book) {
            throw new Exception('Book not found or not available.');
        }
        
        $result = $cart->addToCart($user_id, $book_id, $book['price']);
    } else {
        $result = $cart->removeFromCart($user_id, $book_id);
    }
    
    // Get updated cart count
    $cart_count = $cart->getCartItemCount($user_id);
    $result['cart_count'] = $cart_count;
    
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}