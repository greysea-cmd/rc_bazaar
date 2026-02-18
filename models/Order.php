<?php
class Order
{
    private $conn;
    private $table = 'orders';
    private $order_items_table = 'order_items';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Create a new order (for multiple items from same seller) - WITHOUT book_id
    public function createOrder($data)
    {
        // Extract values
        $buyer_id = $data['buyer_id'] ?? null;
        $seller_id = $data['seller_id'] ?? null;
        $shipping_address = $data['shipping_address'] ?? '';
        $payment_method = $data['payment_method'] ?? '';
        $notes = $data['notes'] ?? '';
        $subtotal = $data['subtotal'] ?? 0;
        $shipping_fee = $data['shipping_fee'] ?? 0;
        $total_amount = $data['total_amount'] ?? 0;
        $order_status = $data['status'] ?? 'pending';
        $phone = $data['phone'] ?? '';

        $query = "INSERT INTO " . $this->table . " 
              (buyer_id, seller_id, shipping_address, payment_method, notes, 
               subtotal, shipping_fee, total_amount, order_status, phone, created_at) 
              VALUES 
              (:buyer_id, :seller_id, :shipping_address, :payment_method, :notes, 
               :subtotal, :shipping_fee, :total_amount, :order_status, :phone, NOW())";

        $stmt = $this->conn->prepare($query);

        // Bind parameters
        $stmt->bindValue(':buyer_id', $buyer_id, PDO::PARAM_INT);
        $stmt->bindValue(':seller_id', $seller_id, PDO::PARAM_INT);
        $stmt->bindValue(':shipping_address', $shipping_address);
        $stmt->bindValue(':payment_method', $payment_method);
        $stmt->bindValue(':notes', $notes);
        $stmt->bindValue(':subtotal', $subtotal);
        $stmt->bindValue(':shipping_fee', $shipping_fee);
        $stmt->bindValue(':total_amount', $total_amount);
        $stmt->bindValue(':order_status', $order_status);
        $stmt->bindValue(':phone', $phone);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        error_log("Order creation error: " . print_r($stmt->errorInfo(), true));
        return false;
    }

    // Add item to order
    public function addOrderItem($data)
    {
        // Extract values
        $order_id = $data['order_id'] ?? null;
        $book_id = $data['book_id'] ?? null;
        $quantity = $data['quantity'] ?? 0;
        $unit_price = $data['unit_price'] ?? 0;
        $total_price = $data['total_price'] ?? 0;

        $query = "INSERT INTO " . $this->order_items_table . " 
                  (order_id, book_id, quantity, unit_price, total_price) 
                  VALUES (:order_id, :book_id, :quantity, :unit_price, :total_price)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindValue(':unit_price', $unit_price);
        $stmt->bindValue(':total_price', $total_price);

        return $stmt->execute();
    }

    // Old method for single book orders (kept for backward compatibility)
    public function create($data)
    {
        // Extract values
        $buyer_id = $data['buyer_id'] ?? null;
        $seller_id = $data['seller_id'] ?? null;
        $book_id = $data['book_id'] ?? null;
        $quantity = $data['quantity'] ?? 0;
        $unit_price = $data['unit_price'] ?? 0;
        $total_amount = $data['total_amount'] ?? 0;
        $shipping_address = $data['shipping_address'] ?? '';
        $payment_method = $data['payment_method'] ?? '';
        $notes = $data['notes'] ?? '';

        // If your table still has book_id column, use this query
        // Otherwise, you might need to remove book_id from this query
        $query = "INSERT INTO " . $this->table . " 
                  (buyer_id, seller_id, book_id, quantity, unit_price, total_amount, 
                   shipping_address, payment_method, notes, order_status, created_at) 
                  VALUES (:buyer_id, :seller_id, :book_id, :quantity, :unit_price, :total_amount, 
                          :shipping_address, :payment_method, :notes, 'pending', NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':buyer_id', $buyer_id, PDO::PARAM_INT);
        $stmt->bindValue(':seller_id', $seller_id, PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindValue(':unit_price', $unit_price);
        $stmt->bindValue(':total_amount', $total_amount);
        $stmt->bindValue(':shipping_address', $shipping_address);
        $stmt->bindValue(':payment_method', $payment_method);
        $stmt->bindValue(':notes', $notes);

        return $stmt->execute();
    }

    public function getUserOrders($user_id, $type = 'buyer')
    {
        $field = ($type === 'buyer') ? 'buyer_id' : 'seller_id';

        // Updated query to handle both old and new structure
        $query = "SELECT o.*, 
                  buyer.username as buyer_name, 
                  seller.username as seller_name
                  FROM " . $this->table . " o
                  JOIN users buyer ON o.buyer_id = buyer.id
                  JOIN users seller ON o.seller_id = seller.id
                  WHERE o.$field = :user_id
                  ORDER BY o.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get order items for each order
        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }

        return $orders;
    }

    public function getOrderById($id)
    {
        $query = "SELECT o.*,
                         buyer.username as buyer_name, buyer.first_name as buyer_first_name, buyer.last_name as buyer_last_name,
                         seller.username as seller_name, seller.first_name as seller_first_name, seller.last_name as seller_last_name
                  FROM " . $this->table . " o
                  JOIN users buyer ON o.buyer_id = buyer.id
                  JOIN users seller ON o.seller_id = seller.id
                  WHERE o.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $order['items'] = $this->getOrderItems($id);
        }

        return $order;
    }

    public function updateOrderStatus($id, $status)
    {
        $query = "UPDATE " . $this->table . " SET order_status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        return $stmt->execute();
    }

    public function getAllOrders()
    {
        $query = "SELECT o.*,
                         buyer.username as buyer_name, 
                         seller.username as seller_name
                  FROM " . $this->table . " o
                  JOIN users buyer ON o.buyer_id = buyer.id
                  JOIN users seller ON o.seller_id = seller.id
                  ORDER BY o.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get order items for each order
        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }

        return $orders;
    }

    // Alternative: If your table still has book_id, use this version
    public function createOrderWithBookId($data)
    {
        // For backward compatibility if table still has book_id
        $buyer_id = $data['buyer_id'] ?? null;
        $seller_id = $data['seller_id'] ?? null;
        $book_id = $data['book_id'] ?? null; // Added book_id
        $shipping_address = $data['shipping_address'] ?? '';
        $payment_method = $data['payment_method'] ?? '';
        $notes = $data['notes'] ?? '';
        $subtotal = $data['subtotal'] ?? 0;
        $shipping_fee = $data['shipping_fee'] ?? 0;
        $total_amount = $data['total_amount'] ?? 0;
        $order_status = $data['status'] ?? 'pending';
        $phone = $data['phone'] ?? '';

        $query = "INSERT INTO " . $this->table . " 
              (buyer_id, seller_id, book_id, shipping_address, payment_method, notes, 
               subtotal, shipping_fee, total_amount, order_status, phone, created_at) 
              VALUES 
              (:buyer_id, :seller_id, :book_id, :shipping_address, :payment_method, :notes, 
               :subtotal, :shipping_fee, :total_amount, :order_status, :phone, NOW())";

        $stmt = $this->conn->prepare($query);

        $stmt->bindValue(':buyer_id', $buyer_id, PDO::PARAM_INT);
        $stmt->bindValue(':seller_id', $seller_id, PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->bindValue(':shipping_address', $shipping_address);
        $stmt->bindValue(':payment_method', $payment_method);
        $stmt->bindValue(':notes', $notes);
        $stmt->bindValue(':subtotal', $subtotal);
        $stmt->bindValue(':shipping_fee', $shipping_fee);
        $stmt->bindValue(':total_amount', $total_amount);
        $stmt->bindValue(':order_status', $order_status);
        $stmt->bindValue(':phone', $phone);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        error_log("Order creation error: " . print_r($stmt->errorInfo(), true));
        return false;
    }

    // Helper to debug table structure
    public function debugTableStructure()
    {
        $query = "DESCRIBE " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderWithDetails($order_id) {
        $query = "SELECT o.*, 
                  b.id as buyer_id, b.first_name as buyer_first_name, b.last_name as buyer_last_name, 
                  b.email as buyer_email, b.phone as buyer_phone,
                  s.id as seller_id, s.first_name as seller_first_name, s.last_name as seller_last_name,
                  s.email as seller_email, s.phone as seller_phone,
                  p.transaction_code, p.status as payment_status, p.created_at as payment_date
                  FROM " . $this->table . " o
                  LEFT JOIN users b ON o.buyer_id = b.id
                  LEFT JOIN users s ON o.seller_id = s.id
                  LEFT JOIN payments p ON o.id = p.order_id AND p.status = 'completed'
                  WHERE o.id = :order_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getOrderPayment($order_id) {
        $query = "SELECT * FROM payments 
                  WHERE order_id = :order_id 
                  ORDER BY created_at DESC 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePaymentStatus($order_id, $status, $transaction_code = null) {
        $query = "UPDATE payments 
                  SET status = :status, updated_at = NOW() 
                  WHERE order_id = :order_id";
        
        if ($transaction_code) {
            $query .= " AND transaction_code = :transaction_code";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':order_id', $order_id);
        
        if ($transaction_code) {
            $stmt->bindParam(':transaction_code', $transaction_code);
        }
        
        return $stmt->execute();
    }

    public function getEsewaTransaction($transaction_uuid) {
        $query = "SELECT * FROM payments 
                  WHERE transaction_uuid = :transaction_uuid 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':transaction_uuid', $transaction_uuid);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get order items with book details - UPDATED with correct column name
    public function getOrderItems($order_id) {
        $query = "SELECT oi.*, b.title, b.author, b.isbn, b.price, 
                  b.image_url, b.condition_type, b.description
                  FROM order_items oi
                  LEFT JOIN books b ON oi.book_id = b.id
                  WHERE oi.order_id = :order_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Alternative method if you need to check table structure
    public function getBooksTableColumns() {
        $query = "SHOW COLUMNS FROM books";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTransactionByUuid($transaction_uuid) {
        $query = "SELECT * FROM payments 
                  WHERE transaction_uuid = :transaction_uuid 
                  ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':transaction_uuid', $transaction_uuid);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get orders by transaction UUID
     */
    public function getOrdersByTransactionUuid($transaction_uuid) {
        $query = "SELECT o.* FROM orders o
                  INNER JOIN payments p ON o.id = p.order_id
                  WHERE p.transaction_uuid = :transaction_uuid";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':transaction_uuid', $transaction_uuid);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create payment record in orders table (optional)
     */
    public function createPaymentRecord($order_id, $transaction_uuid, $transaction_code, $amount) {
        $query = "UPDATE orders 
                  SET payment_status = 'completed',
                      transaction_id = :transaction_uuid,
                      transaction_code = :transaction_code,
                      payment_amount = :amount,
                      updated_at = NOW()
                  WHERE id = :order_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':transaction_uuid', $transaction_uuid);
        $stmt->bindParam(':transaction_code', $transaction_code);
        $stmt->bindParam(':amount', $amount);
        
        return $stmt->execute();
    }
}