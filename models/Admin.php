<?php
class Admin {
    private $conn;
    private $table = 'admins';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($email, $password) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            return $admin;
        }
        return false;
    }

    public function getAdminById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDashboardStats() {
        $stats = [];
        
        // Total users
        $query = "SELECT COUNT(*) as total_users FROM users";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
        
        // Total books
        $query = "SELECT COUNT(*) as total_books FROM books";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total_books'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_books'];
        
        // Pending approvals
        $query = "SELECT COUNT(*) as pending_books FROM books WHERE status = 'pending'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['pending_books'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_books'];
        
        // Total orders
        $query = "SELECT COUNT(*) as total_orders FROM orders";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'];
        
        // Open disputes
        $query = "SELECT COUNT(*) as open_disputes FROM disputes WHERE status IN ('open', 'under_review')";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['open_disputes'] = $stmt->fetch(PDO::FETCH_ASSOC)['open_disputes'];
        
        return $stats;
    }
}