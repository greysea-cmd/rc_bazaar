<?php
// models/User.php
class User
{
    private $conn;
    private $table = 'users';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function register($data)
    {
        $query = "INSERT INTO " . $this->table . " 
                  (username, email, password, first_name, last_name, phone, address, city, state, zipcode, user_type) 
                  VALUES (:username, :email, :password, :first_name, :last_name, :phone, :address, :city, :state, :zipcode, :user_type)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':password', password_hash($data['password'], PASSWORD_DEFAULT)); // Fixed: Changed HASH_ALGO to PASSWORD_DEFAULT
        $stmt->bindParam(':first_name', $data['first_name']);
        $stmt->bindParam(':last_name', $data['last_name']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':city', $data['city']);
        $stmt->bindParam(':state', $data['state']);
        $stmt->bindParam(':zipcode', $data['zipcode']);
        $stmt->bindParam(':user_type', $data['user_type']);

        return $stmt->execute();
    }

    public function login($email, $password)
    {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email AND status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    public function getUserById($id)
    {
        $query = "SELECT *, 
              CASE 
                WHEN profile_picture IS NOT NULL THEN CONCAT('uploads/profiles/', profile_picture)
                ELSE NULL
              END as profile_picture_path
              FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return false if no user found, but checkout.php should handle this
        return $result ? $result : false;
    }

    // FIXED: Ensure this method always returns an array
    public function getAllUsers($limit = null, $offset = null)
    {
        $query = "SELECT id, username, email, first_name, last_name, user_type, status, rating, created_at 
                  FROM " . $this->table . " ORDER BY created_at DESC";

        if ($limit !== null) {
            $query .= " LIMIT :limit";
            if ($offset !== null) {
                $query .= " OFFSET :offset";
            }
        }

        $stmt = $this->conn->prepare($query);
        if ($limit !== null) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            if ($offset !== null) {
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            }
        }
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Always return an array, even if empty
        return is_array($result) ? $result : [];
    }

    public function updateProfile($id, $data)
    {
        $query = "UPDATE " . $this->table . " 
                  SET first_name = :first_name, last_name = :last_name, phone = :phone, 
                      address = :address, city = :city, state = :state, zipcode = :zipcode 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':first_name', $data['first_name']);
        $stmt->bindParam(':last_name', $data['last_name']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':city', $data['city']);
        $stmt->bindParam(':state', $data['state']);
        $stmt->bindParam(':zipcode', $data['zipcode']);

        return $stmt->execute();
    }

    public function updateUserStatus($id, $status)
    {
        $query = "UPDATE " . $this->table . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        return $stmt->execute();
    }

    // Update user password
    public function updatePassword($user_id, $new_password)
    {
        $query = "UPDATE " . $this->table . " 
                  SET password = :password 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Verify current password
    public function verifyPassword($user_id, $password)
    {
        $query = "SELECT password FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && isset($user['password'])) {
            return password_verify($password, $user['password']);
        }
        return false;
    }

    // Update profile picture
    public function updateProfilePicture($user_id, $filename) {
        $query = "UPDATE " . $this->table . " SET profile_picture = :filename WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }

    public function getProfilePicture($user_id) {
        $query = "SELECT profile_picture FROM " . $this->table . " WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['profile_picture'] : null;
    }

    /*Delete user's profile picture*/
    public function deleteProfilePicture($user_id)
    {
        // First get the current picture
        $current_picture = $this->getProfilePicture($user_id);

        if ($current_picture && file_exists($current_picture)) {
            unlink($current_picture);
        }

        $query = "UPDATE " . $this->table . " SET profile_picture = NULL WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }
    
}