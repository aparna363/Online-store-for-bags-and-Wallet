<?php
class Database {
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $dbname = "happypouch_db";
    public $conn;

    public function __construct() {
        // Step 1: Connect to MySQL server (without DB)
        $tempConn = new mysqli($this->host, $this->user, $this->pass);
        if ($tempConn->connect_error) {
            die("MySQL connection failed: " . $tempConn->connect_error);
        }

        // Step 2: Create database if not exists
        $tempConn->query("CREATE DATABASE IF NOT EXISTS {$this->dbname}");
        $tempConn->close();

        // Step 3: Connect to the newly created database
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
        if ($this->conn->connect_error) {
            die("Database connection failed: " . $this->conn->connect_error);
        }

        // Step 4: Create products table if not exists
        $createProductsTable = "CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            image VARCHAR(255) NOT NULL,
            quantity INT DEFAULT 0,
            category VARCHAR(50) DEFAULT 'Bag'
        )";
        $this->conn->query($createProductsTable);

        // Step 4.1: Add category column if it doesn't exist (for existing tables)
        $checkColumn = $this->conn->query("SHOW COLUMNS FROM products LIKE 'category'");
        if ($checkColumn->num_rows == 0) {
            $this->conn->query("ALTER TABLE products ADD COLUMN category VARCHAR(50) DEFAULT 'Bag'");
        }

        // Step 5: Create users table if not exists
        $createUsersTable = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->conn->query($createUsersTable);

        // Step 6: Create login_attempts table (separate login tracking)
        $createLoginAttemptsTable = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('success', 'failed') NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT
        )";
        $this->conn->query($createLoginAttemptsTable);

        // Step 7: Create user_sessions table (separate session management)
        $createSessionsTable = "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(255) UNIQUE NOT NULL,
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $this->conn->query($createSessionsTable);

        // Step 8: Create cart table if not exists
        $createCartTable = "CREATE TABLE IF NOT EXISTS cart (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )";
        $this->conn->query($createCartTable);
    }
}

// Create database instance and expose connection globally
$db = new Database();
$conn = $db->conn;
?>