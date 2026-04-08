<?php
// ========================================
// FILE: database.php
// PURPOSE: Database connection class and handler
// ========================================

require_once 'config.php';

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    
    private $conn;
    private $error;
    
    public function __construct() {
        $this->connectDB();
    }
    
    private function connectDB() {
        try {
            $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
            
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            error_log("Database Connection Error: " . $this->error);
            die("Database connection failed. Please check your configuration.");
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
    
    public function escapeString($string) {
        return $this->conn->real_escape_string($string);
    }
    
    public function getLastInsertId() {
        return $this->conn->insert_id;
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Create global database instance
$db = new Database();
$conn = $db->getConnection();
?>