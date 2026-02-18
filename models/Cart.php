<?php
class Cart
{
    private $conn;
    private $table_name = "cart";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Get all cart items for a user with additional book details
    // Get all cart items for a user with additional book details
    public function getUserCart($user_id)
    {
        $query = "SELECT 
                c.id AS cart_id,
                c.quantity,
                b.id AS book_id,
                b.title, 
                b.author, 
                b.price, 
                b.image_url, 
                b.condition_type,
                b.description,
                b.quantity AS book_stock,
                u.username AS seller_name,
                u.id AS seller_id,
                COALESCE(AVG(r.rating), 0) + 0 AS seller_rating,
                COUNT(r.id) AS rating_count
              FROM " . $this->table_name . " c
              INNER JOIN books b ON c.book_id = b.id
              INNER JOIN users u ON b.seller_id = u.id
              LEFT JOIN book_ratings r ON b.id = r.book_id
              WHERE c.user_id = :user_id
              AND b.status = 'approved'
              AND b.quantity > 0
              GROUP BY c.id, b.id, u.id
              ORDER BY c.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        try {
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!is_array($results)) {
                return [];
            }

            $cart = [];
            foreach ($results as $item) {
                if (!is_array($item)) continue;

                $price = isset($item['price']) ? (float)$item['price'] : 0.0;
                $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;

                $cart[] = [
                    'cart_id' => isset($item['cart_id']) ? (int)$item['cart_id'] : 0,
                    'book_id' => isset($item['book_id']) ? (int)$item['book_id'] : 0,
                    'title' => $item['title'] ?? '',
                    'author' => $item['author'] ?? '',
                    'price' => $price,
                    'image_url' => $item['image_url'] ?? '',
                    'condition_type' => $item['condition_type'] ?? '',
                    'description' => $item['description'] ?? '',
                    'book_stock' => isset($item['book_stock']) ? (int)$item['book_stock'] : 0,
                    'seller_name' => $item['seller_name'] ?? '',
                    'seller_id' => isset($item['seller_id']) ? (int)$item['seller_id'] : 0,
                    'seller_rating' => isset($item['seller_rating']) ? round((float)$item['seller_rating'], 1) : 0.0,
                    'rating_count' => isset($item['rating_count']) ? (int)$item['rating_count'] : 0,
                    'quantity' => $quantity,
                    'subtotal' => $price * $quantity
                ];
            }

            return $cart;
        } catch (PDOException $e) {
            error_log("Cart fetch error: " . $e->getMessage());
            return [];
        }
    }



    // Add a book to cart
    public function addToCart($user_id, $book_id, $price)
    {
        // First check if already exists
        if ($this->isInCart($user_id, $book_id)) {
            return [
                'success' => false,
                'message' => 'This book is already in your cart.'
            ];
        }

        // Check book availability
        $stmt = $this->conn->prepare("SELECT quantity FROM books WHERE id = ?");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch();

        if (!$book || $book['quantity'] <= 0) {
            return [
                'success' => false,
                'message' => 'This book is out of stock.'
            ];
        }

        $query = "INSERT INTO " . $this->table_name . " (user_id, book_id, quantity, price, created_at) 
                  VALUES (:user_id, :book_id, 1, :price, NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->bindParam(':price', $price, PDO::PARAM_STR);

        try {
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Book added to your cart!',
                    'cart_id' => $this->conn->lastInsertId()
                ];
            }
        } catch (PDOException $e) {
            error_log("Cart add error: " . $e->getMessage());

            if ($e->getCode() == 23000) { // Duplicate entry
                return [
                    'success' => false,
                    'message' => 'This book is already in your cart.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Database error. Please try again later.'
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to add book to cart.'
        ];
    }

    // Remove a book from cart
    public function removeFromCart($user_id, $book_id)
    {
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND book_id = :book_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);

        try {
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Book removed from your cart.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Book not found in your cart.'
            ];
        } catch (PDOException $e) {
            error_log("Cart remove error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to remove book from cart.'
            ];
        }
    }

    // Update cart item quantity
    public function updateQuantity($cart_id, $user_id, $quantity)
    {
        if ($quantity <= 0) {
            return $this->removeFromCart($user_id, $cart_id);
        }

        // Check book stock availability
        $stmt = $this->conn->prepare("
            SELECT b.quantity as book_stock 
            FROM cart c 
            JOIN books b ON c.book_id = b.id 
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$cart_id, $user_id]);
        $result = $stmt->fetch();

        if (!$result) {
            return [
                'success' => false,
                'message' => 'Cart item not found.'
            ];
        }

        if ($quantity > $result['book_stock']) {
            return [
                'success' => false,
                'message' => 'Requested quantity exceeds available stock.'
            ];
        }

        $query = "UPDATE " . $this->table_name . " 
                  SET quantity = :quantity, updated_at = NOW() 
                  WHERE id = :cart_id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        try {
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Cart updated successfully.'
                ];
            }
        } catch (PDOException $e) {
            error_log("Cart update error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update cart.'
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to update cart.'
        ];
    }

    // Check if a book is in user's cart
    public function isInCart($user_id, $book_id)
    {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND book_id = :book_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);

        try {
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['count'] > 0;
        } catch (PDOException $e) {
            error_log("isInCart error: " . $e->getMessage());
            return false;
        }
    }

    // Get cart item count for a user
    public function getCartItemCount($user_id)
    {
        $query = "SELECT SUM(quantity) as total_count FROM " . $this->table_name . " 
                  WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        try {
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['total_count'] ?? 0;
        } catch (PDOException $e) {
            error_log("getCartItemCount error: " . $e->getMessage());
            return 0;
        }
    }

    // Get cart total for a user
    public function getCartTotal($user_id)
    {
        $query = "SELECT SUM(c.quantity * c.price) as total FROM " . $this->table_name . " c
                  INNER JOIN books b ON c.book_id = b.id
                  WHERE c.user_id = :user_id AND b.status = 'approved'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        try {
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (float)$result['total'] ?? 0.00;
        } catch (PDOException $e) {
            error_log("getCartTotal error: " . $e->getMessage());
            return 0.00;
        }
    }

    // Clear user's cart
    public function clearCart($user_id)
    {
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("clearCart error: " . $e->getMessage());
            return false;
        }
    }
}
