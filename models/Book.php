<?php
class Book
{
    private $conn;
    private $table = 'books';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function create($data)
    {
        // First, validate and sanitize the category_id
        $category_id = isset($data['category_id']) ? (int)$data['category_id'] : null;

        // If category_id is provided, check if it exists in the categories table
        if ($category_id) {
            $check_stmt = $this->conn->prepare("SELECT id FROM categories WHERE id = :category_id");
            $check_stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
            $check_stmt->execute();

            if (!$check_stmt->fetch()) {
                // Category doesn't exist, log error and set to null
                error_log("Warning: Invalid category_id ($category_id) provided for book creation. Setting to NULL.");
                $category_id = null;
            }
        }

        $query = "INSERT INTO " . $this->table . " 
              (seller_id, title, author, isbn, category_id, condition_type, description, price, quantity, image_url) 
              VALUES (:seller_id, :title, :author, :isbn, :category_id, :condition_type, :description, :price, :quantity, :image_url)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':seller_id', $data['seller_id'], PDO::PARAM_INT);
        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':author', $data['author']);
        $stmt->bindParam(':isbn', $data['isbn']);

        // Handle category_id - can be null if invalid or not provided
        if ($category_id) {
            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':category_id', null, PDO::PARAM_NULL);
        }

        $stmt->bindParam(':condition_type', $data['condition_type']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':quantity', $data['quantity'], PDO::PARAM_INT);
        $stmt->bindParam(':image_url', $data['image_url']);

        return $stmt->execute();
    }

    // Updated to include rating data
    public function getApprovedBooks($search = null, $category = null, $limit = null, $offset = null)
    {
        $query = "SELECT 
                    b.*, 
                    c.name as category_name, 
                    u.username as seller_name, 
                    u.rating as seller_rating,
                    COALESCE(AVG(br.rating), 0) as average_rating,
                    COUNT(br.id) as rating_count
                  FROM " . $this->table . " b 
                  LEFT JOIN categories c ON b.category_id = c.id 
                  LEFT JOIN users u ON b.seller_id = u.id 
                  LEFT JOIN book_ratings br ON b.id = br.book_id
                  WHERE b.status = 'approved' AND b.quantity > 0";

        if ($search) {
            $query .= " AND (b.title LIKE :search OR b.author LIKE :search OR b.isbn LIKE :search)";
        }

        if ($category) {
            $query .= " AND b.category_id = :category";
        }

        $query .= " GROUP BY b.id, u.id, c.id";
        $query .= " ORDER BY b.created_at DESC";

        if ($limit) {
            $query .= " LIMIT :limit";
            if ($offset) {
                $query .= " OFFSET :offset";
            }
        }

        $stmt = $this->conn->prepare($query);

        if ($search) {
            $search_param = "%$search%";
            $stmt->bindParam(':search', $search_param);
        }

        if ($category) {
            $stmt->bindParam(':category', $category);
        }

        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            if ($offset) {
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBookById($id)
    {
        $query = "SELECT 
                    b.*, 
                    c.name as category_name, 
                    u.username as seller_name, 
                    u.rating as seller_rating, 
                    u.first_name, 
                    u.last_name, 
                    u.city, 
                    u.state,
                    COALESCE(AVG(br.rating), 0) as average_rating,
                    COUNT(br.id) as rating_count
                  FROM " . $this->table . " b 
                  LEFT JOIN categories c ON b.category_id = c.id 
                  LEFT JOIN users u ON b.seller_id = u.id 
                  LEFT JOIN book_ratings br ON b.id = br.book_id
                  WHERE b.id = :id
                  GROUP BY b.id, u.id, c.id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSellerBooks($seller_id)
    {
        $query = "SELECT 
                    b.*, 
                    c.name as category_name,
                    COALESCE(AVG(br.rating), 0) as average_rating,
                    COUNT(br.id) as rating_count
                  FROM " . $this->table . " b 
                  LEFT JOIN categories c ON b.category_id = c.id 
                  LEFT JOIN book_ratings br ON b.id = br.book_id
                  WHERE b.seller_id = :seller_id 
                  GROUP BY b.id, c.id
                  ORDER BY b.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':seller_id', $seller_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingBooks()
    {
        $query = "SELECT 
                    b.*, 
                    c.name as category_name, 
                    u.username as seller_name,
                    COALESCE(AVG(br.rating), 0) as average_rating,
                    COUNT(br.id) as rating_count
                  FROM " . $this->table . " b 
                  LEFT JOIN categories c ON b.category_id = c.id 
                  LEFT JOIN users u ON b.seller_id = u.id 
                  LEFT JOIN book_ratings br ON b.id = br.book_id
                  WHERE b.status = 'pending' 
                  GROUP BY b.id, u.id, c.id
                  ORDER BY b.created_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateBookStatus($id, $status, $admin_notes = null)
    {
        $query = "UPDATE " . $this->table . " SET status = :status, admin_notes = :admin_notes WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':admin_notes', $admin_notes);
        return $stmt->execute();
    }

    public function updateQuantity($id, $quantity)
    {
        $query = "UPDATE " . $this->table . " SET quantity = :quantity WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':quantity', $quantity);
        return $stmt->execute();
    }

    public function getBooksWithFilters($filters)
    {
        $query = "SELECT 
                    b.*, 
                    c.name as category_name, 
                    u.username as seller_name, 
                    u.rating as seller_rating,
                    COALESCE(AVG(br.rating), 0) as average_rating,
                    COUNT(br.id) as rating_count
                  FROM " . $this->table . " b 
                  LEFT JOIN categories c ON b.category_id = c.id 
                  LEFT JOIN users u ON b.seller_id = u.id 
                  LEFT JOIN book_ratings br ON b.id = br.book_id
                  WHERE b.status = 'approved' AND b.quantity > 0";

        $params = [];

        // Search filter
        if (!empty($filters['search'])) {
            $query .= " AND (b.title LIKE :search OR b.author LIKE :search OR b.isbn LIKE :search)";
            $params[':search'] = "%" . $filters['search'] . "%";
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $query .= " AND b.category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }

        // Condition filter
        if (!empty($filters['condition'])) {
            $query .= " AND b.condition_type = :condition";
            $params[':condition'] = $filters['condition'];
        }

        // Price range filters
        if (!empty($filters['min_price']) && is_numeric($filters['min_price'])) {
            $query .= " AND b.price >= :min_price";
            $params[':min_price'] = $filters['min_price'];
        }

        if (!empty($filters['max_price']) && is_numeric($filters['max_price'])) {
            $query .= " AND b.price <= :max_price";
            $params[':max_price'] = $filters['max_price'];
        }

        $query .= " GROUP BY b.id, u.id, c.id";

        // Sorting
        $sort_by = $filters['sort_by'] ?? 'newest';
        switch ($sort_by) {
            case 'oldest':
                $query .= " ORDER BY b.created_at ASC";
                break;
            case 'price_low':
                $query .= " ORDER BY b.price ASC";
                break;
            case 'price_high':
                $query .= " ORDER BY b.price DESC";
                break;
            case 'title_asc':
                $query .= " ORDER BY b.title ASC";
                break;
            case 'title_desc':
                $query .= " ORDER BY b.title DESC";
                break;
            case 'rating':
                $query .= " ORDER BY average_rating DESC";
                break;
            case 'newest':
            default:
                $query .= " ORDER BY b.created_at DESC";
                break;
        }

        // Pagination
        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $query .= " LIMIT :limit";
            $params[':limit'] = (int)$filters['limit'];

            if (isset($filters['offset']) && is_numeric($filters['offset'])) {
                $query .= " OFFSET :offset";
                $params[':offset'] = (int)$filters['offset'];
            }
        }

        $stmt = $this->conn->prepare($query);

        // Bind parameters
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFilteredBooksCount($filters)
    {
        $query = "SELECT COUNT(DISTINCT b.id) as total 
                  FROM " . $this->table . " b 
                  WHERE b.status = 'approved' AND b.quantity > 0";

        $params = [];

        // Search filter
        if (!empty($filters['search'])) {
            $query .= " AND (b.title LIKE :search OR b.author LIKE :search OR b.isbn LIKE :search)";
            $params[':search'] = "%" . $filters['search'] . "%";
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $query .= " AND b.category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }

        // Condition filter
        if (!empty($filters['condition'])) {
            $query .= " AND b.condition_type = :condition";
            $params[':condition'] = $filters['condition'];
        }

        // Price range filters
        if (!empty($filters['min_price']) && is_numeric($filters['min_price'])) {
            $query .= " AND b.price >= :min_price";
            $params[':min_price'] = $filters['min_price'];
        }

        if (!empty($filters['max_price']) && is_numeric($filters['max_price'])) {
            $query .= " AND b.price <= :max_price";
            $params[':max_price'] = $filters['max_price'];
        }

        $stmt = $this->conn->prepare($query);

        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }

    // Get book rating statistics
    public function getBookRatingStats($book_id)
    {
        $query = "SELECT 
                    AVG(rating) as average_rating,
                    COUNT(*) as rating_count,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_stars,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_stars,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_stars,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_stars,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_stars
                  FROM book_ratings 
                  WHERE book_id = :book_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':book_id', $book_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get recent book ratings with user info
    public function getRecentBookRatings($book_id, $limit = 5)
    {
        $query = "SELECT 
                    br.*,
                    u.username,
                    u.profile_picture,
                    u.first_name,
                    u.last_name
                  FROM book_ratings br
                  LEFT JOIN users u ON br.user_id = u.id
                  WHERE br.book_id = :book_id
                  ORDER BY br.created_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':book_id', $book_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get books by average rating (for recommendations)
    public function getTopRatedBooks($limit = 10)
    {
        $query = "SELECT 
                    b.*,
                    c.name as category_name,
                    u.username as seller_name,
                    COALESCE(AVG(br.rating), 0) as average_rating,
                    COUNT(br.id) as rating_count
                  FROM " . $this->table . " b 
                  LEFT JOIN categories c ON b.category_id = c.id 
                  LEFT JOIN users u ON b.seller_id = u.id 
                  LEFT JOIN book_ratings br ON b.id = br.book_id
                  WHERE b.status = 'approved' AND b.quantity > 0
                  GROUP BY b.id, u.id, c.id
                  HAVING rating_count > 0
                  ORDER BY average_rating DESC, rating_count DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get user's rated books
    public function getUserRatedBooks($user_id, $limit = null, $offset = null)
    {
        $query = "SELECT 
                    b.*,
                    c.name as category_name,
                    br.rating as user_rating,
                    br.created_at as rating_date
                  FROM book_ratings br
                  INNER JOIN " . $this->table . " b ON br.book_id = b.id
                  LEFT JOIN categories c ON b.category_id = c.id
                  WHERE br.user_id = :user_id
                  ORDER BY br.created_at DESC";

        if ($limit) {
            $query .= " LIMIT :limit";
            if ($offset) {
                $query .= " OFFSET :offset";
            }
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);

        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            if ($offset) {
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // New methods that were outside the class
    public function getBooksByStatus($status, $limit = null, $offset = null)
    {
        $query = "SELECT b.*, 
                  u.first_name as seller_name,
                  c.name as category_name
                  FROM books b
                  LEFT JOIN users u ON b.seller_id = u.id
                  LEFT JOIN categories c ON b.category_id = c.id
                  WHERE b.status = :status
                  ORDER BY b.created_at DESC";

        if ($limit !== null) {
            $query .= " LIMIT :limit";
        }
        if ($offset !== null) {
            $query .= " OFFSET :offset";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);

        if ($limit !== null) {
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        }
        if ($offset !== null) {
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($book_id, $status)
    {
        $query = "UPDATE books 
                  SET status = :status, 
                      reviewed_at = NOW(),
                      reviewed_by = :reviewed_by
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);

        // Check if session exists
        $reviewed_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $stmt->bindParam(':reviewed_by', $reviewed_by);

        $stmt->bindParam(':id', $book_id);

        return $stmt->execute();
    }

    public function getBookWithDetails($book_id)
    {
        $query = "SELECT b.*, 
                  u.first_name as seller_first_name,
                  u.last_name as seller_last_name,
                  u.email as seller_email,
                  u.phone as seller_phone,
                  c.name as category_name
                  FROM books b
                  LEFT JOIN users u ON b.seller_id = u.id
                  LEFT JOIN categories c ON b.category_id = c.id
                  WHERE b.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $book_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getCountByStatus($status = null)
    {
        if ($status) {
            $query = "SELECT COUNT(*) as count FROM books WHERE status = :status";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
        } else {
            $query = "SELECT COUNT(*) as count FROM books";
            $stmt = $this->conn->prepare($query);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    public function getBooksByCategory($category_id, $limit = 20, $offset = 0)
    {
        $query = "SELECT 
                    b.*,
                    c.name as category_name,
                    u.username as seller_name,
                    COALESCE(AVG(br.rating), 0) as average_rating
                  FROM " . $this->table . " b
                  LEFT JOIN categories c ON b.category_id = c.id
                  LEFT JOIN users u ON b.seller_id = u.id
                  LEFT JOIN book_ratings br ON b.id = br.book_id
                  WHERE b.status = 'approved' 
                    AND b.quantity > 0
                    AND b.category_id = :category_id
                  GROUP BY b.id
                  ORDER BY b.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFeaturedBooks($limit = 8)
    {
        $query = "SELECT 
                    b.*,
                    c.name as category_name,
                    u.username as seller_name,
                    COALESCE(AVG(br.rating), 0) as average_rating,
                    COUNT(br.id) as rating_count
                  FROM " . $this->table . " b
                  LEFT JOIN categories c ON b.category_id = c.id
                  LEFT JOIN users u ON b.seller_id = u.id
                  LEFT JOIN book_ratings br ON b.id = br.book_id
                  WHERE b.status = 'approved' 
                    AND b.quantity > 0
                    AND b.is_featured = 1
                  GROUP BY b.id
                  ORDER BY b.created_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateBook($book_id, $data)
    {
        // Validate and sanitize category_id
        $category_id = isset($data['category_id']) ? (int)$data['category_id'] : null;

        // If category_id is provided, check if it exists
        if ($category_id) {
            $check_stmt = $this->conn->prepare("SELECT id FROM categories WHERE id = :category_id");
            $check_stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
            $check_stmt->execute();

            if (!$check_stmt->fetch()) {
                error_log("Warning: Invalid category_id ($category_id) provided for book update. Setting to NULL.");
                $category_id = null;
            }
        }

        $query = "UPDATE " . $this->table . " 
              SET title = :title,
                  author = :author,
                  isbn = :isbn,
                  category_id = :category_id,
                  condition_type = :condition_type,
                  description = :description,
                  price = :price,
                  quantity = :quantity,
                  image_url = :image_url,
                  updated_at = NOW()
              WHERE id = :id AND seller_id = :seller_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $book_id, PDO::PARAM_INT);
        $stmt->bindParam(':seller_id', $data['seller_id'], PDO::PARAM_INT);
        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':author', $data['author']);
        $stmt->bindParam(':isbn', $data['isbn']);

        // Handle category_id
        if ($category_id) {
            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':category_id', null, PDO::PARAM_NULL);
        }

        $stmt->bindParam(':condition_type', $data['condition_type']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':quantity', $data['quantity'], PDO::PARAM_INT);
        $stmt->bindParam(':image_url', $data['image_url']);

        return $stmt->execute();
    }

    public function deleteBook($book_id, $seller_id)
    {
        $query = "DELETE FROM " . $this->table . " 
                  WHERE id = :id AND seller_id = :seller_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $book_id);
        $stmt->bindParam(':seller_id', $seller_id);

        return $stmt->execute();
    }

    public function getRelatedBooks($book_id, $category_id, $limit = 4)
    {
        $query = "SELECT 
                    b.*,
                    c.name as category_name,
                    u.username as seller_name,
                    COALESCE(AVG(br.rating), 0) as average_rating
                  FROM " . $this->table . " b
                  LEFT JOIN categories c ON b.category_id = c.id
                  LEFT JOIN users u ON b.seller_id = u.id
                  LEFT JOIN book_ratings br ON b.id = br.book_id
                  WHERE b.status = 'approved' 
                    AND b.quantity > 0
                    AND b.category_id = :category_id
                    AND b.id != :book_id
                  GROUP BY b.id
                  ORDER BY b.created_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':book_id', $book_id);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBooksForAdminDashboard($limit = 10)
    {
        $query = "SELECT 
                    b.*,
                    u.username as seller_name,
                    c.name as category_name,
                    (SELECT COUNT(*) FROM order_items WHERE book_id = b.id) as total_sales
                  FROM " . $this->table . " b
                  LEFT JOIN users u ON b.seller_id = u.id
                  LEFT JOIN categories c ON b.category_id = c.id
                  WHERE b.status = 'approved'
                  ORDER BY b.created_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBookStats()
    {
        $stats = [];

        // Total books
        $query = "SELECT COUNT(*) as total FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total_books'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Approved books
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE status = 'approved'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['approved_books'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pending books
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE status = 'pending'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['pending_books'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Out of stock books
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE quantity = 0 AND status = 'approved'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['out_of_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        return $stats;
    }

public function getRejectedBooks($offset = 0, $limit = 10) {
    $query = "SELECT b.*, 
                     c.name as category_name,
                     CONCAT(u.first_name, ' ', u.last_name) as seller_name,
                     u.id as seller_id
              FROM books b
              LEFT JOIN categories c ON b.category_id = c.id
              LEFT JOIN users u ON b.seller_id = u.id
              WHERE b.status = 'rejected'
              ORDER BY b.created_at DESC
              LIMIT :offset, :limit";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function countBooksByStatus($status) {
    $query = "SELECT COUNT(*) as total FROM books WHERE status = :status";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'];
}

public function searchBooks($search_term, $status = 'pending', $offset = 0, $limit = 10) {
    $search_term = "%$search_term%";
    $query = "SELECT b.*, 
                     c.name as category_name,
                     CONCAT(u.first_name, ' ', u.last_name) as seller_name,
                     u.id as seller_id
              FROM books b
              LEFT JOIN categories c ON b.category_id = c.id
              LEFT JOIN users u ON b.seller_id = u.id
              WHERE b.status = :status 
                AND (b.title LIKE :search 
                     OR b.author LIKE :search 
                     OR b.isbn LIKE :search
                     OR u.first_name LIKE :search
                     OR u.last_name LIKE :search)
              ORDER BY b.created_at DESC
              LIMIT :offset, :limit";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':search', $search_term);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}
