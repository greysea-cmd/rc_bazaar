<?php
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

// Initialize DB and model
$database = new Database();
$db = $database->getConnection();
$cart = new Cart($db);

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// Handle quantity update
if (isset($_POST['cart_id']) && isset($_POST['quantity'])) {
    $cart_id = (int)$_POST['cart_id'];
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity <= 0) {
        $action = 'remove';
    }
    
    $result = $cart->updateQuantity($cart_id, $user_id, $quantity);
    
    if ($result['success']) {
        $cart_count = $cart->getCartItemCount($user_id);
        $cart_total = $cart->getCartTotal($user_id);
        
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'cart_count' => $cart_count,
            'cart_total' => $cart_total
        ]);
    } else {
        echo json_encode($result);
    }
    exit;
}

// Handle remove action
if ($action === 'remove' && isset($_POST['cart_id'])) {
    // First get book_id from cart
    $cart_id = (int)$_POST['cart_id'];
    $stmt = $db->prepare("SELECT book_id FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$cart_id, $user_id]);
    $cart_item = $stmt->fetch();
    
    if (!$cart_item) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Cart item not found.'
        ]);
        exit;
    }
    
    $book_id = $cart_item['book_id'];
    $result = $cart->removeFromCart($user_id, $book_id);
    
    if ($result['success']) {
        $cart_count = $cart->getCartItemCount($user_id);
        $cart_total = $cart->getCartTotal($user_id);
        
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'cart_count' => $cart_count,
            'cart_total' => $cart_total
        ]);
    } else {
        echo json_encode($result);
    }
    exit;
}

http_response_code(400);
echo json_encode([
    'success' => false,
    'message' => 'Invalid request.'
]);