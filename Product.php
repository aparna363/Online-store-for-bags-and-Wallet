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
            $_SESSION['success_message'] = "Product added successfully!";
            header("Location: Admin.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error adding product: " . $stmt->error;
            header("Location: Admin.php");
            exit();
        }
        $stmt->close();
    }
    
    // Update an existing product
    public function updateProduct($id, $title, $price, $quantity, $category, $newImage = null) {
        try {
            if ($newImage) {
                // Get old image to delete it
                $stmt = $this->conn->prepare("SELECT image FROM products WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $oldImagePath = "uploads/" . $row['image'];
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                $stmt->close();
                
                // Update with new image
                $stmt = $this->conn->prepare("UPDATE products SET title = ?, price = ?, quantity = ?, category = ?, image = ? WHERE id = ?");
                $stmt->bind_param("sdissi", $title, $price, $quantity, $category, $newImage, $id);
            } else {
                // Update without changing image
                $stmt = $this->conn->prepare("UPDATE products SET title = ?, price = ?, quantity = ?, category = ? WHERE id = ?");
                $stmt->bind_param("sdisi", $title, $price, $quantity, $category, $id);
            }
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Product updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating product: " . $stmt->error;
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error updating product: " . $e->getMessage();
        }
        
        header("Location: Admin.php");
        exit();
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
    
    // Delete a product (with foreign key constraint handling)
    public function deleteProduct($id) {
        try {
            // Start transaction
            $this->conn->begin_transaction();
            
            // Get image filename before deleting
            $stmt = $this->conn->prepare("SELECT image FROM products WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $imagePath = "uploads/" . $row['image'];
                
                // Check if product has associated order items
                $checkStmt = $this->conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE product_id = ?");
                $checkStmt->bind_param("i", $id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $countRow = $checkResult->fetch_assoc();
                $checkStmt->close();
                
                if ($countRow['count'] > 0) {
                    // Product has order history - prevent deletion
                    $this->conn->rollback();
                    $_SESSION['error_message'] = "Cannot delete this product. It has been ordered by customers. Consider marking it as out of stock instead.";
                    header("Location: Admin.php");
                    exit();
                }
                
                // Delete from database
                $deleteStmt = $this->conn->prepare("DELETE FROM products WHERE id = ?");
                $deleteStmt->bind_param("i", $id);
                $deleteStmt->execute();
                $deleteStmt->close();
                
                // Delete image file if exists
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
                
                // Commit transaction
                $this->conn->commit();
                $_SESSION['success_message'] = "Product deleted successfully!";
                header("Location: Admin.php");
                exit();
            }
            
            $stmt->close();
            $this->conn->rollback();
            $_SESSION['error_message'] = "Product not found.";
            header("Location: Admin.php");
            exit();
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error deleting product: " . $e->getMessage());
            $_SESSION['error_message'] = "Error deleting product: " . $e->getMessage();
            header("Location: Admin.php");
            exit();
        }
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