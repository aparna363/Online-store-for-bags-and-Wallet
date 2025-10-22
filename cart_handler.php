<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once("db.php");
require_once("User.php");

header('Content-Type: application/json');

// Check if user is logged in
if (!User::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// Verify database connection exists
if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        $product_id = intval($_POST['product_id'] ?? 0);

        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID: ' . $product_id]);
            exit();
        }

        // Check if product exists and get stock
        $stmt = $conn->prepare("SELECT id, title, quantity FROM products WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
            exit();
        }
        
        $stmt->bind_param("i", $product_id);
        
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]);
            exit();
        }
        
        $productResult = $stmt->get_result();
        
        if ($productResult->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Product not found (ID: ' . $product_id . ')']);
            exit();
        }
        
        $product = $productResult->fetch_assoc();

        if ($product['quantity'] < 1) {
            echo json_encode(['success' => false, 'message' => 'Product out of stock']);
            exit();
        }

        // Check if product already in cart with pending status
        $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND status = 'pending'");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
            exit();
        }
        
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $cartResult = $stmt->get_result();

        if ($cartResult->num_rows > 0) {
            // Product already in cart - update quantity
            $cart_item = $cartResult->fetch_assoc();
            $new_quantity = $cart_item['quantity'] + 1;

            if ($new_quantity > $product['quantity']) {
                echo json_encode(['success' => false, 'message' => 'Cannot add more. Only ' . $product['quantity'] . ' items in stock']);
                exit();
            }

            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Prepare update failed: ' . $conn->error]);
                exit();
            }
            
            $stmt->bind_param("iii", $new_quantity, $cart_item['id'], $user_id);
            
            if (!$stmt->execute()) {
                echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
                exit();
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Product quantity updated in cart',
                'new_quantity' => $new_quantity
            ]);
        } else {
            // Product not in cart - insert new item with 'pending' status
            $quantity = 1;
            $status = 'pending';
            
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, status) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Prepare insert failed: ' . $conn->error]);
                exit();
            }
            
            $stmt->bind_param("iiis", $user_id, $product_id, $quantity, $status);
            
            if (!$stmt->execute()) {
                echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
                exit();
            }
            
            $insert_id = $conn->insert_id;
            
            if ($insert_id > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Product added to cart successfully',
                    'cart_id' => $insert_id
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Insert did not return ID']);
            }
        }
        break;

    case 'update':
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);

        if ($cart_id <= 0 || $quantity < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid input values']);
            exit();
        }

        // Verify cart item belongs to user, is pending, and get product stock
        $stmt = $conn->prepare("
            SELECT c.id, c.product_id, p.quantity as stock 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.id = ? AND c.user_id = ? AND c.status = 'pending'
        ");
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
            exit();
        }
        
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Cart item not found or already completed']);
            exit();
        }
        
        $cart_item = $result->fetch_assoc();

        if ($quantity > $cart_item['stock']) {
            echo json_encode(['success' => false, 'message' => 'Quantity exceeds available stock (' . $cart_item['stock'] . ')']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ? AND status = 'pending'");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
            exit();
        }
        
        $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
        
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
            exit();
        }

        echo json_encode(['success' => true, 'message' => 'Cart updated']);
        break;

    case 'remove':
        $cart_id = intval($_POST['cart_id'] ?? 0);
        
        if ($cart_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid cart ID']);
            exit();
        }
        
        // Only allow removing pending items
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ? AND status = 'pending'");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
            exit();
        }
        
        $stmt->bind_param("ii", $cart_id, $user_id);
        
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
            exit();
        }
        
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found in cart or already completed']);
        }
        break;

    case 'count':
        // Only count pending items
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ? AND status = 'pending'");
        if (!$stmt) {
            echo json_encode(['count' => 0, 'error' => $conn->error]);
            exit();
        }
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        echo json_encode(['count' => intval($row['total'] ?? 0)]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
}
?>