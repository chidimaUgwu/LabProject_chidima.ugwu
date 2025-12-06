<?php
class Database {
    private $conn;

    public function getConnection() {
        // Load credentials safely
        $config = include __DIR__ . '/secure_config.php';

        try {
            $this->conn = new PDO(
                "mysql:host={$config['host']};dbname={$config['db_name']};port={$config['port']}",
                $config['username'],
                $config['password']
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8mb4");

        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Connection failed: Please check your database configuration.");
        }

        return $this->conn;
    }
}
?>
