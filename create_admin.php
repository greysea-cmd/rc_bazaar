<?php
require_once 'config/database.php'; // Adjust path as needed

// Create DB connection
$database = new Database();
$conn = $database->getConnection();

// Admin details
$username = 'Grace';
$email = 'gracywanemphago@gmail.com';
$password = password_hash('greyseaarsi123', PASSWORD_DEFAULT);
$first_name = 'Gracy';
$last_name = 'Phago';
$role = 'super_admin';
$created_at = date('Y-m-d H:i:s');

// Insert query using PDO
$sql = "INSERT INTO admins (username, email, password, first_name, last_name, role, created_at)
        VALUES (:username, :email, :password, :first_name, :last_name, :role, :created_at)";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':username', $username);
$stmt->bindParam(':email', $email);
$stmt->bindParam(':password', $password);
$stmt->bindParam(':first_name', $first_name);
$stmt->bindParam(':last_name', $last_name);
$stmt->bindParam(':role', $role);
$stmt->bindParam(':created_at', $created_at);

if ($stmt->execute()) {
    echo "✅ Admin user created successfully.";
} else {
    echo "❌ Failed to create admin user.";
}
