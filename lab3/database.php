<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Tạm thời comment require_once này lại nếu không dùng đến biến kết nối mysqli thuần tại đây
// require_once 'config.php'; 

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    public $connection;
    
    public function __construct()
    {
        // Gán chuỗi cấu hình tường minh, không sử dụng lại biến hệ thống trùng tên
        $this->host = "localhost";
        $this->username = "root";
        $this->password = "";
        $this->database = "lab3";
    }

    public function getConnection() {
        try {
            $conn_pdo = new PDO("mysql:host={$this->host};dbname={$this->database};charset=utf8", $this->username, $this->password);
            $conn_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection = $conn_pdo;
            return $this->connection;
        } catch(PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
            return null;
        }
    }
}