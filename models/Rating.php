<?php
// models/Rating.php
class Rating {
    private $conn;
    private $table_name = "book_ratings";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get user's rating for a specific book
    public function getUserRating($user_id, $book_id) {
        $query = "SELECT rating FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND book_id = :book_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['rating'] : null;
    }

    // Add or update a rating
    public function addRating($user_id, $book_id, $rating) {
        // Check if user already rated this book
        $existing_rating = $this->getUserRating($user_id, $book_id);
        
        if ($existing_rating) {
            // Update existing rating
            $query = "UPDATE " . $this->table_name . " 
                      SET rating = :rating, updated_at = NOW() 
                      WHERE user_id = :user_id AND book_id = :book_id";
        } else {
            // Insert new rating
            $query = "INSERT INTO " . $this->table_name . " 
                      (user_id, book_id, rating, created_at, updated_at) 
                      VALUES (:user_id, :book_id, :rating, NOW(), NOW())";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    // Get average rating for a book
    public function getAverageRating($book_id) {
        $query = "SELECT AVG(rating) as average_rating, COUNT(*) as rating_count 
                  FROM " . $this->table_name . " 
                  WHERE book_id = :book_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get all ratings for a book
    public function getBookRatings($book_id, $limit = 10) {
        $query = "SELECT r.*, u.username, u.profile_picture 
                  FROM " . $this->table_name . " r
                  JOIN users u ON r.user_id = u.id
                  WHERE r.book_id = :book_id 
                  ORDER BY r.created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>