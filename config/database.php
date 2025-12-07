<?php
// C:\xampp\htdocs\Attandance\config\database.php

class Database {
    private $conn;

    public function getConnection() {
        // Load credentials safely
        $config = include __DIR__ . '/secure_config.php';
        
        try {
            // Use PDO for better error handling
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
            $this->conn = new PDO($dsn, $config['username'], $config['password']);
            
            // Set PDO error mode to exception
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            return $this->conn;
            
        } catch(PDOException $e) {
            // Log error but don't show details to user
            error_log("Database connection failed: " . $e->getMessage());
            
            // Show user-friendly message
            die("Database connection error. Please contact administrator.");
        }
    }
}

?>
