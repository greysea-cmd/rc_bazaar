<?php
require_once 'config/database.php';

class Payment
{
    private $conn;
    private $table_name = "payments";

    public $id;
    public $order_id;
    public $transaction_uuid;
    public $transaction_code;
    public $amount;
    public $status;
    public $created_at;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function updatePaymentStatus($transaction_uuid, $status)
    {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status 
                  WHERE transaction_uuid = :transaction_uuid";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':transaction_uuid', $transaction_uuid);

        return $stmt->execute();
    }


    public function getOrderPayments($order_id)
    {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE order_id = :order_id 
                  ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPaymentByTransactionId($transaction_uuid)
    {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE transaction_uuid = :transaction_uuid 
                  ORDER BY created_at DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':transaction_uuid', $transaction_uuid);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPaymentByTransactionCode($transaction_code)
    {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE transaction_code = :transaction_code 
                  ORDER BY created_at DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':transaction_code', $transaction_code);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createPaymentRecord($order_id, $transaction_uuid, $transaction_code, $amount)
    {
        $query = "INSERT INTO " . $this->table_name . " 
                  (order_id, transaction_uuid, transaction_code, amount, status, created_at) 
                  VALUES (:order_id, :transaction_uuid, :transaction_code, :amount, 'pending', NOW())
                  ON DUPLICATE KEY UPDATE 
                  amount = VALUES(amount), 
                  status = VALUES(status),
                  updated_at = NOW()";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':transaction_uuid', $transaction_uuid);
        $stmt->bindParam(':transaction_code', $transaction_code);
        $stmt->bindParam(':amount', $amount);

        return $stmt->execute();
    }

    public function updatePaymentReference($transaction_uuid, $reference_id)
    {
        $query = "UPDATE " . $this->table_name . " 
                  SET reference_id = :reference_id, 
                      updated_at = NOW() 
                  WHERE transaction_uuid = :transaction_uuid";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':reference_id', $reference_id);
        $stmt->bindParam(':transaction_uuid', $transaction_uuid);

        return $stmt->execute();
    }

    public function getPaymentHistory($order_id)
    {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE order_id = :order_id 
                  ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTransactionByUuid($transaction_uuid)
    {
        $query = "SELECT * FROM " . $this->table_name . " 
              WHERE transaction_uuid = :transaction_uuid 
              ORDER BY created_at DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':transaction_uuid', $transaction_uuid);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $query = "INSERT INTO " . $this->table_name . " 
                  (order_id, transaction_uuid, transaction_code, amount, status, created_at) 
                  VALUES (:order_id, :transaction_uuid, :transaction_code, :amount, :status, NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $data['order_id']);
        $stmt->bindParam(':transaction_uuid', $data['transaction_uuid']);
        $stmt->bindParam(':transaction_code', $data['transaction_code']);
        $stmt->bindParam(':amount', $data['amount']);
        $stmt->bindParam(':status', $data['status']);

        return $stmt->execute();
    }
}
