

<?php
class Database {
    private $conn;

    public function getConnection() {
        // Load credentials safely
        $config = include __DIR__ . '/secure_config.php';

        $this->conn = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['port']
        );

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        return $this->conn;
    }
}

// C:\xampp\htdocs\Attandance\config\database.php

// class Database {
//     private $conn;

//     public function getConnection() {
//         $config = include __DIR__ . '/secure_config.php';

//         try {
//             $this->conn = new PDO(
//                 "mysql:host={$config['host']};dbname={$config['db_name']}",
//                 $config['username'],
//                 $config['password']
//             );
//             $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//             $this->conn->exec("set names utf8mb4");

//         } catch(PDOException $e) {
//             error_log("Database connection failed: " . $e->getMessage());
//             return null;
//         }

//         return $this->conn;
//     }
// }

?>