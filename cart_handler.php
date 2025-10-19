<?php
session_start();
require_once("db.php");
require_once("User.php");

header('Content-Type: application/json');

if (!User::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        $product_id = intval($_POST['product_id'] ?? 0);

        // Check stock from products table (replace 'quantity' with your column)
        $stmt = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result || $result['quantity'] < 1) {
            echo json_encode(['success' => false, 'message' => 'Product out of stock']);
            exit();
        }

        // Check if product already in cart
        $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $cartResult = $stmt->get_result();

        if ($cartResult->num_rows > 0) {
            $cart_item = $cartResult->fetch_assoc();
            $new_quantity = $cart_item['quantity'] + 1;

            if ($new_quantity > $result['quantity']) {
                echo json_encode(['success' => false, 'message' => 'Cannot add more than stock available']);
                exit();
            }

            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_quantity, $cart_item['id']);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
        }

        echo json_encode(['success' => true, 'message' => 'Product added to cart']);
        break;

    case 'update':
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);

        if ($cart_id <= 0 || $quantity < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit();
        }

        // Get stock for this cart item
        $stmt = $conn->prepare("
            SELECT p.quantity 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Cart item not found']);
            exit();
        }

        if ($quantity > $result['quantity']) {
            echo json_encode(['success' => false, 'message' => 'Quantity exceeds stock available']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
        $stmt->execute();

        echo json_encode(['success' => true]);
        break;

    case 'remove':
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();

        echo json_encode(['success' => true]);
        break;

    case 'count':
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        echo json_encode(['count' => $row['total'] ?? 0]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
