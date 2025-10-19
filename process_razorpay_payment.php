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

// ==================== EXCEPTION CLASSES ====================
class PaymentProcessingException extends Exception {}
class InvalidPaymentDataException extends Exception {}
class CartEmptyException extends Exception {}
class DatabaseException extends Exception {}

// ==================== VALUE OBJECT ====================
class OrderResult {
    private $success;
    private $orderNumber;
    private $message;
    
    public function __construct(bool $success, string $orderNumber, string $message) {
        $this->success = $success;
        $this->orderNumber = $orderNumber;
        $this->message = $message;
    }
    
    public function isSuccess(): bool { return $this->success; }
    public function getOrderNumber(): string { return $this->orderNumber; }
    public function getMessage(): string { return $this->message; }
}

// ==================== MODEL CLASSES ====================
class Order {
    private $id;
    private $userId;
    private $orderNumber;
    private $paymentMethod;
    private $paymentStatus;
    private $orderStatus;
    private $fullName;
    private $email;
    private $phone;
    private $addressLine1;
    private $addressLine2;
    private $city;
    private $state;
    private $pincode;
    private $country;
    private $orderNotes;
    private $subtotal;
    private $tax;
    private $total;
    private $razorpayPaymentId;
    private $razorpayOrderId;
    private $items = [];
    
    // Getters
    public function getId(): ?int { return $this->id; }
    public function getOrderNumber(): string { return $this->orderNumber; }
    public function getEmail(): string { return $this->email; }
    public function getFullName(): string { return $this->fullName; }
    public function getTotal(): float { return $this->total; }
    public function getSubtotal(): float { return $this->subtotal; }
    public function getTax(): float { return $this->tax; }
    public function getItems(): array { return $this->items; }
    public function getPhone(): string { return $this->phone; }
    public function getAddressLine1(): string { return $this->addressLine1; }
    public function getAddressLine2(): string { return $this->addressLine2; }
    public function getCity(): string { return $this->city; }
    public function getState(): string { return $this->state; }
    public function getPincode(): string { return $this->pincode; }
    public function getCountry(): string { return $this->country; }
    public function getRazorpayPaymentId(): ?string { return $this->razorpayPaymentId; }
    
    // Setters
    public function setId(int $id): void { $this->id = $id; }
    public function setUserId(int $userId): void { $this->userId = $userId; }
    public function setOrderNumber(string $orderNumber): void { $this->orderNumber = $orderNumber; }
    public function setPaymentMethod(string $method): void { $this->paymentMethod = $method; }
    public function setPaymentStatus(string $status): void { $this->paymentStatus = $status; }
    public function setOrderStatus(string $status): void { $this->orderStatus = $status; }
    public function setItems(array $items): void { $this->items = $items; }
    
    public function setCustomerDetails(array $data): void {
        $this->fullName = $data['full_name'];
        $this->email = $data['email'];
        $this->phone = $data['phone'];
        $this->addressLine1 = $data['address_line1'];
        $this->addressLine2 = $data['address_line2'] ?? '';
        $this->city = $data['city'];
        $this->state = $data['state'];
        $this->pincode = $data['pincode'];
        $this->country = $data['country'];
        $this->orderNotes = $data['order_notes'] ?? '';
    }
    
    public function setPaymentDetails(array $data): void {
        $this->razorpayPaymentId = $data['razorpay_payment_id'] ?? null;
        $this->razorpayOrderId = $data['razorpay_order_id'] ?? null;
    }
    
    public function calculateTotals(array $items): void {
        $this->subtotal = array_reduce($items, function($sum, $item) {
            return $sum + ($item['price'] * $item['quantity']);
        }, 0);
        
        $this->tax = $this->subtotal * 0.18;
        $this->total = $this->subtotal + $this->tax;
    }
    
    public function toArray(): array {
        return [
            'user_id' => $this->userId,
            'order_number' => $this->orderNumber,
            'payment_method' => $this->paymentMethod,
            'payment_status' => $this->paymentStatus,
            'order_status' => $this->orderStatus,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'country' => $this->country,
            'order_notes' => $this->orderNotes,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'razorpay_payment_id' => $this->razorpayPaymentId,
            'razorpay_order_id' => $this->razorpayOrderId
        ];
    }
}

class OrderItem {
    private $orderId;
    private $productId;
    private $quantity;
    private $price;
    private $subtotal;
    
    public function setOrderId(int $orderId): void { $this->orderId = $orderId; }
    public function setProductId(int $productId): void { $this->productId = $productId; }
    public function setQuantity(int $quantity): void { $this->quantity = $quantity; }
    public function setPrice(float $price): void { $this->price = $price; }
    
    public function calculateSubtotal(): void {
        $this->subtotal = $this->price * $this->quantity;
    }
    
    public function toArray(): array {
        return [
            'order_id' => $this->orderId,
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'subtotal' => $this->subtotal
        ];
    }
}

// ==================== REPOSITORY CLASSES ====================
class OrderRepository {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function save(Order $order): int {
        $data = $order->toArray();
        $hasRazorpayColumns = $this->hasRazorpayColumns();
        
        if ($hasRazorpayColumns) {
            $sql = "INSERT INTO orders (
                user_id, order_number, payment_method, payment_status, order_status,
                full_name, email, phone, address_line1, address_line2, 
                city, state, pincode, country, order_notes,
                subtotal, tax, total, razorpay_payment_id, razorpay_order_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param(
                "issssssssssssssdddss",
                $data['user_id'], $data['order_number'], $data['payment_method'],
                $data['payment_status'], $data['order_status'], $data['full_name'],
                $data['email'], $data['phone'], $data['address_line1'], $data['address_line2'],
                $data['city'], $data['state'], $data['pincode'], $data['country'],
                $data['order_notes'], $data['subtotal'], $data['tax'], $data['total'],
                $data['razorpay_payment_id'], $data['razorpay_order_id']
            );
        } else {
            $sql = "INSERT INTO orders (
                user_id, order_number, payment_method, payment_status, order_status,
                full_name, email, phone, address_line1, address_line2, 
                city, state, pincode, country, order_notes,
                subtotal, tax, total, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param(
                "issssssssssssssddd",
                $data['user_id'], $data['order_number'], $data['payment_method'],
                $data['payment_status'], $data['order_status'], $data['full_name'],
                $data['email'], $data['phone'], $data['address_line1'], $data['address_line2'],
                $data['city'], $data['state'], $data['pincode'], $data['country'],
                $data['order_notes'], $data['subtotal'], $data['tax'], $data['total']
            );
            
            error_log("Order {$data['order_number']} - Razorpay Payment ID: {$data['razorpay_payment_id']}");
        }
        
        if (!$stmt->execute()) {
            throw new DatabaseException("Failed to save order: " . $stmt->error);
        }
        
        return $this->conn->insert_id;
    }
    
    public function saveOrderItem(OrderItem $item): bool {
        $data = $item->toArray();
        $sql = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "iiidd",
            $data['order_id'], $data['product_id'], $data['quantity'],
            $data['price'], $data['subtotal']
        );
        
        if (!$stmt->execute()) {
            throw new DatabaseException("Failed to save order item: " . $stmt->error);
        }
        
        return true;
    }
    
    private function hasRazorpayColumns(): bool {
        $result = $this->conn->query("SHOW COLUMNS FROM orders LIKE 'razorpay_payment_id'");
        return ($result && $result->num_rows > 0);
    }
}

class CartRepository {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getCartItems(int $userId): array {
        $sql = "SELECT c.id as cart_id, c.quantity, c.product_id, p.* 
                FROM cart c 
                JOIN products p ON c.product_id = p.id 
                WHERE c.user_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        return $items;
    }
    
    public function clearCart(int $userId): bool {
        $sql = "DELETE FROM cart WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        
        if (!$stmt->execute()) {
            throw new DatabaseException("Failed to clear cart: " . $stmt->error);
        }
        
        return true;
    }
}

class ProductRepository {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function decreaseStock(int $productId, int $quantity): bool {
        if (!$this->hasStockColumn()) {
            return true;
        }
        
        $sql = "UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iii", $quantity, $productId, $quantity);
        
        return $stmt->execute();
    }
    
    private function hasStockColumn(): bool {
        $result = $this->conn->query("SHOW COLUMNS FROM products LIKE 'stock'");
        return ($result && $result->num_rows > 0);
    }
}

// ==================== SERVICE CLASSES ====================
class EmailService {
    private $config;
    
    public function __construct(array $config) {
        $this->config = $config;
    }
    
    public function sendOrderConfirmation(Order $order): bool {
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->config['port'];
            
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($order->getEmail(), $order->getFullName());
            
            $mail->isHTML(true);
            $mail->Subject = 'Order Confirmation - ' . $order->getOrderNumber();
            $mail->Body = $this->buildOrderEmailHtml($order);
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function buildOrderEmailHtml(Order $order): string {
        $itemsHTML = '';
        foreach ($order->getItems() as $item) {
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
        
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd;">
            <div style="background: #f4a261; padding: 20px; text-align: center;">
                <h1 style="color: white; margin: 0;">Order Confirmed!</h1>
            </div>
            
            <div style="padding: 30px; background: #fff;">
                <p style="font-size: 16px;">Dear ' . htmlspecialchars($order->getFullName()) . ',</p>
                <p>Thank you for your order! Your payment has been received and your order is being processed.</p>
                
                <div style="background: #e8f5e9; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #4caf50;">
                    <p style="margin: 0; color: #2e7d32;"><strong>✓ Payment Successful</strong></p>
                </div>
                
                <div style="background: #f8f8f8; padding: 20px; margin: 20px 0; border-radius: 5px;">
                    <h3 style="margin-top: 0; color: #f4a261;">Order Details</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 0;"><strong>Order Number:</strong></td>
                            <td style="padding: 8px 0; text-align: right;">' . $order->getOrderNumber() . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong>Payment ID:</strong></td>
                            <td style="padding: 8px 0; text-align: right; font-size: 12px;">' . $order->getRazorpayPaymentId() . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong>Payment Method:</strong></td>
                            <td style="padding: 8px 0; text-align: right;">Razorpay</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong>Order Date:</strong></td>
                            <td style="padding: 8px 0; text-align: right;">' . date('F d, Y') . '</td>
                        </tr>
                    </table>
                </div>
                
                <h3 style="color: #f4a261; margin-top: 30px;">Items Ordered</h3>
                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <thead>
                        <tr style="background: #f8f8f8;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Product</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd;">Qty</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #ddd;">Price</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #ddd;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $itemsHTML . '
                    </tbody>
                </table>
                
                <div style="text-align: right; margin-top: 20px; padding: 20px; background: #f8f8f8; border-radius: 5px;">
                    <p style="margin: 8px 0;"><strong>Subtotal:</strong> ₹' . number_format($order->getSubtotal(), 2) . '</p>
                    <p style="margin: 8px 0;"><strong>Shipping:</strong> <span style="color: #4caf50;">Free</span></p>
                    <p style="margin: 8px 0;"><strong>Tax (18%):</strong> ₹' . number_format($order->getTax(), 2) . '</p>
                    <p style="margin: 15px 0 0 0; font-size: 20px; color: #f4a261;"><strong>Total Paid:</strong> ₹' . number_format($order->getTotal(), 2) . '</p>
                </div>
                
                <h3 style="color: #f4a261; margin-top: 30px;">Shipping Address</h3>
                <div style="background: #f8f8f8; padding: 15px; border-radius: 5px;">
                    <p style="margin: 5px 0;">' . htmlspecialchars($order->getAddressLine1()) . '</p>
                    ' . ($order->getAddressLine2() ? '<p style="margin: 5px 0;">' . htmlspecialchars($order->getAddressLine2()) . '</p>' : '') . '
                    <p style="margin: 5px 0;">' . htmlspecialchars($order->getCity()) . ', ' . htmlspecialchars($order->getState()) . ' - ' . htmlspecialchars($order->getPincode()) . '</p>
                    <p style="margin: 5px 0;">' . htmlspecialchars($order->getCountry()) . '</p>
                    <p style="margin: 10px 0 5px 0;"><strong>Phone:</strong> ' . htmlspecialchars($order->getPhone()) . '</p>
                </div>
                
                <div style="background: #e3f2fd; padding: 15px; margin: 30px 0; border-radius: 5px; border-left: 4px solid #2196f3;">
                    <p style="margin: 0; color: #1565c0;">
                        <strong>📦 What\'s Next?</strong><br>
                        We will send you a shipping confirmation email with tracking details once your order is shipped.
                    </p>
                </div>
                
                <p style="margin-top: 30px;">If you have any questions about your order, please contact our support team.</p>
                
                <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <p style="color: #666; margin: 5px 0;">Thank you for choosing HappyPouch!</p>
                </div>
            </div>
            
            <div style="background: #f8f8f8; padding: 20px; text-align: center; color: #666; border-top: 1px solid #ddd;">
                <p style="margin: 0; font-size: 14px;">© ' . date('Y') . ' HappyPouch. All rights reserved.</p>
                <p style="margin: 10px 0 0 0; font-size: 12px;">
                    <a href="mailto:support@happypouch.com" style="color: #f4a261; text-decoration: none;">support@happypouch.com</a>
                </p>
            </div>
        </div>
        ';
    }
}

class PaymentProcessor {
    private $conn;
    private $emailService;
    private $orderRepository;
    private $cartRepository;
    
    public function __construct($conn, EmailService $emailService) {
        $this->conn = $conn;
        $this->emailService = $emailService;
        $this->orderRepository = new OrderRepository($conn);
        $this->cartRepository = new CartRepository($conn);
    }
    
    public function processRazorpayPayment(array $paymentData, array $checkoutData, int $userId): OrderResult {
        try {
            $this->validatePaymentData($paymentData);
            
            $cartItems = $this->cartRepository->getCartItems($userId);
            if (empty($cartItems)) {
                throw new CartEmptyException("Cart is empty");
            }
            
            $this->conn->begin_transaction();
            
            $order = $this->createOrder($userId, $paymentData, $checkoutData, $cartItems);
            $this->cartRepository->clearCart($userId);
            
            $this->conn->commit();
            
            $this->emailService->sendOrderConfirmation($order);
            
            return new OrderResult(true, $order->getOrderNumber(), "Payment successful! Your order has been placed.");
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Payment processing failed: " . $e->getMessage());
            throw new PaymentProcessingException($e->getMessage(), 0, $e);
        }
    }
    
    private function validatePaymentData(array $data): void {
        if (empty($data['razorpay_payment_id']) || empty($data['order_number'])) {
            throw new InvalidPaymentDataException("Invalid payment data");
        }
    }
    
    private function createOrder(int $userId, array $paymentData, array $checkoutData, array $cartItems): Order {
        $order = new Order();
        $order->setUserId($userId);
        $order->setOrderNumber($paymentData['order_number']);
        $order->setPaymentMethod('razorpay');
        $order->setPaymentStatus('completed');
        $order->setOrderStatus('processing');
        $order->setCustomerDetails($checkoutData);
        $order->setPaymentDetails($paymentData);
        $order->calculateTotals($cartItems);
        
        $orderId = $this->orderRepository->save($order);
        $order->setId($orderId);
        
        foreach ($cartItems as $item) {
            $orderItem = new OrderItem();
            $orderItem->setOrderId($orderId);
            $orderItem->setProductId($item['product_id']);
            $orderItem->setQuantity($item['quantity']);
            $orderItem->setPrice($item['price']);
            $orderItem->calculateSubtotal();
            
            $this->orderRepository->saveOrderItem($orderItem);
            $this->updateProductStock($item['product_id'], $item['quantity']);
        }
        
        $order->setItems($cartItems);
        return $order;
    }
    
    private function updateProductStock(int $productId, int $quantity): void {
        $productRepo = new ProductRepository($this->conn);
        $productRepo->decreaseStock($productId, $quantity);
    }
}

// ==================== CONTROLLER LOGIC ====================
if (!User::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: checkout.php");
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    
    if (!isset($_SESSION['checkout_data'])) {
        throw new Exception("Session expired. Please checkout again.");
    }
    
    $checkoutData = $_SESSION['checkout_data'];
    $paymentData = [
        'razorpay_payment_id' => $_POST['razorpay_payment_id'] ?? '',
        'razorpay_order_id' => $_POST['razorpay_order_id'] ?? '',
        'razorpay_signature' => $_POST['razorpay_signature'] ?? '',
        'order_number' => $_POST['order_number'] ?? ''
    ];
    
    // Email Configuration
    $emailConfig = [
        'host' => 'smtp.gmail.com',
        'username' => 'aparnaprasad363@gmail.com',
        'password' => 'wbnh wldc yeqo sqzi',
        'port' => 587,
        'from_email' => 'aparnaprasad363@gmail.com',
        'from_name' => 'HappyPouch'
    ];
    
    // Process Payment using OOP
    $emailService = new EmailService($emailConfig);
    $processor = new PaymentProcessor($conn, $emailService);
    $result = $processor->processRazorpayPayment($paymentData, $checkoutData, $userId);
    
    // Clear session and set success
    unset($_SESSION['checkout_data']);
    $_SESSION['success'] = $result->getMessage();
    $_SESSION['order_number'] = $result->getOrderNumber();
    
    header("Location: order_success.php?order=" . urlencode($result->getOrderNumber()));
    exit();
    
} catch (CartEmptyException $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: cart.php");
    exit();
} catch (InvalidPaymentDataException $e) {
    $_SESSION['error'] = "Payment verification failed: " . $e->getMessage();
    header("Location: checkout.php");
    exit();
} catch (PaymentProcessingException $e) {
    $_SESSION['error'] = "Order processing failed. Please contact support with Payment ID: " . ($_POST['razorpay_payment_id'] ?? 'N/A');
    header("Location: checkout.php");
    exit();
} catch (Exception $e) {
    error_log("Unexpected error: " . $e->getMessage());
    $_SESSION['error'] = "An unexpected error occurred. Please try again.";
    header("Location: checkout.php");
    exit();
}
?>