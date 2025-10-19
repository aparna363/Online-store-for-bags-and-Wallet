<?php
session_start();
require_once("db.php");
require_once("User.php");

// PHPMailer for email notifications
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!User::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: checkout.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$currentUser = User::getCurrentUser();

// Get checkout data from session
if (!isset($_SESSION['checkout_data'])) {
    header("Location: checkout.php");
    exit();
}

$checkout_data = $_SESSION['checkout_data'];
$payment_method = $_POST['payment_method'] ?? '';

// Validate payment method
$valid_methods = ['card', 'upi', 'netbanking', 'cod'];
if (!in_array($payment_method, $valid_methods)) {
    $_SESSION['error'] = "Invalid payment method selected";
    header("Location: payment.php");
    exit();
}

// Get cart items
$stmt = $conn->prepare("
    SELECT c.id as cart_id, c.quantity, c.product_id, p.* 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
}

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Generate order number
    $order_number = 'HP' . date('Ymd') . strtoupper(substr(uniqid(), -6));
    
    // Determine payment status
    $payment_status = ($payment_method === 'cod') ? 'pending' : 'completed';
    $order_status = 'processing';
    
    // Insert order
    $stmt = $conn->prepare("
        INSERT INTO orders (
            user_id, order_number, payment_method, payment_status, order_status,
            full_name, email, phone, address_line1, address_line2, 
            city, state, pincode, country, order_notes,
            subtotal, tax, total, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param(
        "issssssssssssssddd",
        $user_id,
        $order_number,
        $payment_method,
        $payment_status,
        $order_status,
        $checkout_data['full_name'],
        $checkout_data['email'],
        $checkout_data['phone'],
        $checkout_data['address_line1'],
        $checkout_data['address_line2'],
        $checkout_data['city'],
        $checkout_data['state'],
        $checkout_data['pincode'],
        $checkout_data['country'],
        $checkout_data['order_notes'],
        $checkout_data['subtotal'],
        $checkout_data['tax'],
        $checkout_data['total']
    );
    
    $stmt->execute();
    $order_id = $conn->insert_id;
    
    // Insert order items
    $stmt = $conn->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($cart_items as $item) {
        $item_subtotal = $item['price'] * $item['quantity'];
        $stmt->bind_param(
            "iiidd",
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item_subtotal
        );
        $stmt->execute();
        
        // Update product stock (only if stock column exists)
        $check_column = $conn->query("SHOW COLUMNS FROM products LIKE 'stock'");
        if ($check_column->num_rows > 0) {
            $update_stock = $conn->prepare("
                UPDATE products SET stock = stock - ? WHERE id = ?
            ");
            $update_stock->bind_param("ii", $item['quantity'], $item['product_id']);
            $update_stock->execute();
        }
    }
    
    // Clear cart
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Send confirmation email
    sendOrderConfirmationEmail(
        $checkout_data['email'],
        $checkout_data['full_name'],
        $order_number,
        $cart_items,
        $checkout_data,
        $payment_method
    );
    
    // Clear checkout data from session
    unset($_SESSION['checkout_data']);
    
    // Set success message
    $_SESSION['success'] = "Order placed successfully!";
    $_SESSION['order_number'] = $order_number;
    
    // Redirect to success page
    header("Location: order_success.php?order=" . $order_number);
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    $_SESSION['error'] = "Payment processing failed. Please try again.";
    header("Location: payment.php");
    exit();
}

function sendOrderConfirmationEmail($email, $name, $order_number, $items, $checkout_data, $payment_method) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'aparnaprasad363@gmail.com';
        $mail->Password = 'wbnh wldc yeqo sqzi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('aparnaprasad363@gmail.com', 'HappyPouch');
        $mail->addAddress($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Order Confirmation - ' . $order_number;
        
        // Build items HTML
        $itemsHTML = '';
        foreach ($items as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $itemsHTML .= '
                <tr>
                    <td style="padding:10px; border-bottom:1px solid #eee;">' . htmlspecialchars($item['title']) . '</td>
                    <td style="padding:10px; border-bottom:1px solid #eee; text-align:center;">' . $item['quantity'] . '</td>
                    <td style="padding:10px; border-bottom:1px solid #eee; text-align:right;">₹' . number_format($item['price'], 2) . '</td>
                    <td style="padding:10px; border-bottom:1px solid #eee; text-align:right;">₹' . number_format($itemTotal, 2) . '</td>
                </tr>
            ';
        }
        
        $paymentMethodLabel = [
            'card' => 'Credit/Debit Card',
            'upi' => 'UPI',
            'netbanking' => 'Net Banking',
            'cod' => 'Cash on Delivery'
        ];
        
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #f4a261; padding: 20px; text-align: center;">
                <h1 style="color: white; margin: 0;">Order Confirmed!</h1>
            </div>
            
            <div style="padding: 30px; background: #fff;">
                <p>Dear ' . htmlspecialchars($name) . ',</p>
                <p>Thank you for your order! Your order has been received and is being processed.</p>
                
                <div style="background: #f8f8f8; padding: 15px; margin: 20px 0; border-radius: 5px;">
                    <h3 style="margin-top: 0; color: #f4a261;">Order Details</h3>
                    <p><strong>Order Number:</strong> ' . $order_number . '</p>
                    <p><strong>Payment Method:</strong> ' . $paymentMethodLabel[$payment_method] . '</p>
                    <p><strong>Order Date:</strong> ' . date('F d, Y') . '</p>
                </div>
                
                <h3 style="color: #f4a261;">Items Ordered</h3>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <thead>
                        <tr style="background: #f8f8f8;">
                            <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Product</th>
                            <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Qty</th>
                            <th style="padding: 10px; text-align: right; border-bottom: 2px solid #ddd;">Price</th>
                            <th style="padding: 10px; text-align: right; border-bottom: 2px solid #ddd;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $itemsHTML . '
                    </tbody>
                </table>
                
                <div style="text-align: right; margin-top: 20px;">
                    <p><strong>Subtotal:</strong> ₹' . number_format($checkout_data['subtotal'], 2) . '</p>
                    <p><strong>Tax (18%):</strong> ₹' . number_format($checkout_data['tax'], 2) . '</p>
                    <p style="font-size: 1.2em; color: #f4a261;"><strong>Total:</strong> ₹' . number_format($checkout_data['total'], 2) . '</p>
                </div>
                
                <h3 style="color: #f4a261; margin-top: 30px;">Shipping Address</h3>
                <p>
                    ' . htmlspecialchars($checkout_data['address_line1']) . '<br>
                    ' . ($checkout_data['address_line2'] ? htmlspecialchars($checkout_data['address_line2']) . '<br>' : '') . '
                    ' . htmlspecialchars($checkout_data['city']) . ', ' . htmlspecialchars($checkout_data['state']) . ' - ' . htmlspecialchars($checkout_data['pincode']) . '<br>
                    ' . htmlspecialchars($checkout_data['country']) . '<br>
                    Phone: ' . htmlspecialchars($checkout_data['phone']) . '
                </p>
                
                <p style="margin-top: 30px;">We will send you a shipping confirmation email with tracking details once your order is shipped.</p>
                
                <p style="margin-top: 20px;">If you have any questions, please contact our support team.</p>
            </div>
            
            <div style="background: #f8f8f8; padding: 20px; text-align: center; color: #666;">
                <p style="margin: 0;">Thank you for shopping with HappyPouch!</p>
                <p style="margin: 5px 0 0 0;">© ' . date('Y') . ' HappyPouch. All rights reserved.</p>
            </div>
        </div>
        ';
        
        $mail->send();
    } catch (Exception $e) {
        // Log error but don't stop the order process
        error_log("Email sending failed: " . $mail->ErrorInfo);
    }
}
?>