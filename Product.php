<?php
require_once("db.php");


class Product {
    private $conn;
    
    public function __construct() {
        global $conn;
        
        if (!isset($conn) || $conn === null) {
            die("Database connection not established. Please check db.php");
        }
        
        $this->conn = $conn;
    }
    
    // Add a new product
    public function addProduct($title, $price, $image, $quantity, $category = 'Bag') {
        $stmt = $this->conn->prepare("INSERT INTO products (title, price, image, quantity, category) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsis", $title, $price, $image, $quantity, $category);
        
        if ($stmt->execute()) {
            header("Location: Admin.php");
            exit();
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
    
    // Get all products - ALWAYS returns an array
    public function getAllProducts() {
        try {
            $result = $this->conn->query("SELECT * FROM products ORDER BY id DESC");
            $products = [];
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $products[] = $row;
                }
            }
            
            return $products;
        } catch (Exception $e) {
            error_log("Error fetching products: " . $e->getMessage());
            return [];
        }
    }
    
    // Get products by category (Bag or Wallet)
    public function getProductsByCategory($category) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM products WHERE category = ? ORDER BY id DESC");
            $stmt->bind_param("s", $category);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $products = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $products[] = $row;
                }
            }
            
            $stmt->close();
            return $products;
        } catch (Exception $e) {
            error_log("Error fetching products by category: " . $e->getMessage());
            return [];
        }
    }
    
    // Delete a product
    public function deleteProduct($id) {
        // Get image filename before deleting
        $stmt = $this->conn->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $imagePath = "uploads/" . $row['image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        $stmt->close();
        
        // Delete from database
        $stmt = $this->conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            header("Location: Admin.php");
            exit();
        }
        $stmt->close();
    }
    
    // Get a single product by ID
    public function getProductById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    // Update product quantity
    public function updateQuantity($id, $quantity) {
        $stmt = $this->conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
        $stmt->bind_param("ii", $quantity, $id);
        $stmt->execute();
        $stmt->close();
    }
}
?>