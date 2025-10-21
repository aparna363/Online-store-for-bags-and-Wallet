<?php
require_once("db.php");

class User {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    // Register new user
    public function register($name, $email, $password) {
        // Check if email already exists
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return "Email already registered!";
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $this->conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hashedPassword);
        
        if ($stmt->execute()) {
            return true;
        } else {
            return "Registration failed!";
        }
    }
    
    // Login user with tracking
    public function login($email, $password) {
        $stmt = $this->conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Log successful login
                $this->logLoginAttempt($email, 'success', $ip_address, $user_agent);
                
                // Create session
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                
                // Create session token
                $session_token = bin2hex(random_bytes(32));
                $_SESSION['session_token'] = $session_token;
                
                // Store session in database
                $this->createUserSession($user['id'], $session_token, $ip_address, $user_agent);
                
                return true;
            } else {
                // Log failed login
                $this->logLoginAttempt($email, 'failed', $ip_address, $user_agent);
            }
        } else {
            // Log failed login (email not found)
            $this->logLoginAttempt($email, 'failed', $ip_address, $user_agent);
        }
        
        return "Invalid email or password!";
    }
    
    // Log login attempt to separate table
    private function logLoginAttempt($email, $status, $ip_address, $user_agent) {
        $stmt = $this->conn->prepare("INSERT INTO login_attempts (email, status, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $email, $status, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
    
    // Create user session in separate table
    private function createUserSession($user_id, $session_token, $ip_address, $user_agent) {
        $stmt = $this->conn->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $session_token, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
    
    // Check if user is logged in
    public static function isLoggedIn() {
        if (!isset($_SESSION)) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }
    
    // Logout user and deactivate session
    public function logout() {
        session_start();
        
        if (isset($_SESSION['session_token'])) {
            // Deactivate session in database
            $stmt = $this->conn->prepare("UPDATE user_sessions SET is_active = FALSE WHERE session_token = ?");
            $stmt->bind_param("s", $_SESSION['session_token']);
            $stmt->execute();
            $stmt->close();
        }
        
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit();
    }
    
    // Get current user data
    public static function getCurrentUser() {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email']
            ];
        }
        return null;
    }

    // Get current user ID
    public static function getCurrentUserId() {
        $user = self::getCurrentUser();
        return $user ? $user['id'] : null;
    }

    // Get current user full name
    public static function getCurrentUserFullName() {
        $user = self::getCurrentUser();
        return $user ? $user['name'] : null;
    }

    // Get current username (same as full name for now)
    public static function getCurrentUsername() {
        return self::getCurrentUserFullName();
    }
    
    // Get user's login history
    public function getLoginHistory($email, $limit = 10) {
        $stmt = $this->conn->prepare("SELECT * FROM login_attempts WHERE email = ? ORDER BY attempt_time DESC LIMIT ?");
        $stmt->bind_param("si", $email, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        return $history;
    }
    
    // Get user's active sessions
    public function getActiveSessions($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM user_sessions WHERE user_id = ? AND is_active = TRUE ORDER BY login_time DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sessions = [];
        while ($row = $result->fetch_assoc()) {
            $sessions[] = $row;
        }
        
        return $sessions;
    }
}
?>