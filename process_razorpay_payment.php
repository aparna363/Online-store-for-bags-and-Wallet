<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 temporarily for debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_errors.log');

// Start output buffering to prevent any accidental output
ob_start();

session_start();

// Check if required files exist before including
$required_files = ['db.php', 'User.php'];
foreach ($required_files as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        error_log("CRITICAL: Required file missing: " . $file);
        die("System configuration error. Please contact support.");
    }
}

require_once(__DIR__ . "/db.php");
require_once(__DIR__ . "/User.php");

// ==================== HELPER FUNCTION ====================
/**
 * Safe redirect function with multiple fallback methods
 */
function safeRedirect($page, $params = []) {
    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Build URL with parameters
    $url = $page;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    error_log("Attempting redirect to: " . $url);
    
    // Method 1: PHP Header Redirect
    if (!headers_sent()) {
        header("Location: " . $url, true, 302);
        exit();
    }
    
    // Method 2: JavaScript Redirect (if headers already sent)
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '">
        <title>Redirecting...</title>
    </head>
    <body>
        <script>window.location.href = "' . htmlspecialchars($url) . '";</script>
        <p>Redirecting... <a href="' . htmlspecialchars($url) . '">Click here if not redirected automatically</a></p>
    </body>
    </html>';
    exit();
}

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
    
    // Setters
    public function setId(int $id): void { $this->id = $id; }
    public function setUserId(int $userId): void { $this->userId = $userId; }
    public function setOrderNumber(string $orderNumber): void { $this->orderNumber = $orderNumber; }
    public function setPaymentMethod(string $method): void { $this->paymentMethod = $method; }
    public function setPaymentStatus(string $status): void { $this->paymentStatus = $status; }
    public function setOrderStatus(string $status): void { $this->orderStatus = $status; }
    public function setItems(array $items): void { $this->items = $items; }
    
    public function setCustomerDetails(array $data): void {
        $this->fullName = $data['full_name'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->phone = $data['phone'] ?? '';
        $this->addressLine1 = $data['address_line1'] ?? '';
        $this->addressLine2 = $data['address_line2'] ?? '';
        $this->city = $data['city'] ?? '';
        $this->state = $data['state'] ?? '';
        $this->pincode = $data['pincode'] ?? '';
        $this->country = $data['country'] ?? '';
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
            if (!$stmt) {
                throw new DatabaseException("Failed to prepare statement: " . $this->conn->error);
            }
            
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
            if (!$stmt) {
                throw new DatabaseException("Failed to prepare statement: " . $this->conn->error);
            }
            
            $stmt->bind_param(
                "issssssssssssssddd",
                $data['user_id'], $data['order_number'], $data['payment_method'],
                $data['payment_status'], $data['order_status'], $data['full_name'],
                $data['email'], $data['phone'], $data['address_line1'], $data['address_line2'],
                $data['city'], $data['state'], $data['pincode'], $data['country'],
                $data['order_notes'], $data['subtotal'], $data['tax'], $data['total']
            );
            
            error_log("Order {$data['order_number']} - Razorpay Payment ID: {$data['razorpay_payment_id']} (not saved - column missing)");
        }
        
        if (!$stmt->execute()) {
            throw new DatabaseException("Failed to save order: " . $stmt->error);
        }
        
        $insertId = $this->conn->insert_id;
        error_log("Order saved successfully with ID: " . $insertId);
        
        return $insertId;
    }
    
    public function saveOrderItem(OrderItem $item): bool {
        $data = $item->toArray();
        $sql = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException("Failed to prepare order item statement: " . $this->conn->error);
        }
        
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
        if (!$stmt) {
            throw new DatabaseException("Failed to prepare cart query: " . $this->conn->error);
        }
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        error_log("Retrieved " . count($items) . " cart items for user " . $userId);
        
        return $items;
    }
    
    public function clearCart(int $userId): bool {
        $sql = "DELETE FROM cart WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException("Failed to prepare clear cart statement: " . $this->conn->error);
        }
        
        $stmt->bind_param("i", $userId);
        
        if (!$stmt->execute()) {
            throw new DatabaseException("Failed to clear cart: " . $stmt->error);
        }
        
        error_log("Cart cleared for user " . $userId);
        
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
        if (!$stmt) {
            throw new DatabaseException("Failed to prepare stock update: " . $this->conn->error);
        }
        
        $stmt->bind_param("iii", $quantity, $productId, $quantity);
        
        return $stmt->execute();
    }
    
    private function hasStockColumn(): bool {
        $result = $this->conn->query("SHOW COLUMNS FROM products LIKE 'stock'");
        return ($result && $result->num_rows > 0);
    }
}

// ==================== SERVICE CLASSES ====================
class PaymentProcessor {
    private $conn;
    private $orderRepository;
    private $cartRepository;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->orderRepository = new OrderRepository($conn);
        $this->cartRepository = new CartRepository($conn);
    }
    
    public function processRazorpayPayment(array $paymentData, array $checkoutData, int $userId): OrderResult {
        try {
            error_log("=== STARTING PAYMENT PROCESSING ===");
            error_log("User ID: " . $userId);
            
            $this->validatePaymentData($paymentData);
            
            $cartItems = $this->cartRepository->getCartItems($userId);
            if (empty($cartItems)) {
                throw new CartEmptyException("Cart is empty");
            }
            
            error_log("Cart items count: " . count($cartItems));
            
            $this->conn->begin_transaction();
            error_log("Transaction started");
            
            $order = $this->createOrder($userId, $paymentData, $checkoutData, $cartItems);
            error_log("Order created with ID: " . $order->getId());
            
            $this->cartRepository->clearCart($userId);
            error_log("Cart cleared");
            
            $this->conn->commit();
            error_log("Transaction committed");
            
            error_log("=== PAYMENT PROCESSING SUCCESSFUL ===");
            
            return new OrderResult(true, $order->getOrderNumber(), "Payment successful! Your order has been placed.");
            
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollback();
                error_log("Transaction rolled back");
            }
            error_log("Payment processing failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw new PaymentProcessingException($e->getMessage(), 0, $e);
        }
    }
    
    private function validatePaymentData(array $data): void {
        if (empty($data['razorpay_payment_id'])) {
            throw new InvalidPaymentDataException("Missing Razorpay payment ID");
        }
        if (empty($data['order_number'])) {
            throw new InvalidPaymentDataException("Missing order number");
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
error_log("=== PROCESS PAYMENT SCRIPT STARTED ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST Data: " . print_r($_POST, true));

// Check database connection
if (!isset($conn) || !$conn) {
    error_log("CRITICAL: Database connection not established");
    die("Database connection error. Please contact support.");
}

// Verify user is logged in
if (!User::isLoggedIn()) {
    error_log("User not logged in, redirecting to login");
    $_SESSION['error'] = "Please login to continue";
    safeRedirect('login.php');
}

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    $_SESSION['error'] = "Invalid request method";
    safeRedirect('checkout.php');
}

// Main processing block
try {
    $userId = $_SESSION['user_id'];
    error_log("Processing payment for user ID: " . $userId);
    
    // Check if checkout data exists in session
    if (!isset($_SESSION['checkout_data'])) {
        error_log("Checkout data missing from session");
        throw new Exception("Session expired. Please checkout again.");
    }
    
    $checkoutData = $_SESSION['checkout_data'];
    error_log("Checkout data retrieved from session");
    
    // Collect payment data from POST
    $paymentData = [
        'razorpay_payment_id' => $_POST['razorpay_payment_id'] ?? '',
        'razorpay_order_id' => $_POST['razorpay_order_id'] ?? '',
        'razorpay_signature' => $_POST['razorpay_signature'] ?? '',
        'order_number' => $_POST['order_number'] ?? ''
    ];
    
    error_log("Payment Data collected: razorpay_payment_id=" . $paymentData['razorpay_payment_id']);
    error_log("Order Number: " . $paymentData['order_number']);
    
    // Process Payment
    error_log("Creating PaymentProcessor instance");
    $processor = new PaymentProcessor($conn);
    
    error_log("Calling processRazorpayPayment method");
    $result = $processor->processRazorpayPayment($paymentData, $checkoutData, $userId);
    
    error_log("Payment processed successfully!");
    error_log("Order Number: " . $result->getOrderNumber());
    
    // Clear session data and set success
    unset($_SESSION['checkout_data']);
    $_SESSION['success'] = $result->getMessage();
    $_SESSION['order_number'] = $result->getOrderNumber();
    
    error_log("Session updated with success message");
    error_log("Attempting redirect to order_success.php");
    
    // Redirect to success page
    safeRedirect('order_success.php', [
        'order' => $result->getOrderNumber()
    ]);
    
} catch (CartEmptyException $e) {
    error_log("CartEmptyException: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    safeRedirect('cart.php');
    
} catch (InvalidPaymentDataException $e) {
    error_log("InvalidPaymentDataException: " . $e->getMessage());
    $_SESSION['error'] = "Payment verification failed: " . $e->getMessage();
    safeRedirect('checkout.php');
    
} catch (PaymentProcessingException $e) {
    error_log("PaymentProcessingException: " . $e->getMessage());
    $paymentId = $_POST['razorpay_payment_id'] ?? 'N/A';
    $_SESSION['error'] = "Order processing failed. Please contact support with Payment ID: " . $paymentId;
    safeRedirect('checkout.php');
    
} catch (DatabaseException $e) {
    error_log("DatabaseException: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['error'] = "Database error occurred. Please contact support.";
    safeRedirect('checkout.php');
    
} catch (Exception $e) {
    error_log("Unexpected Exception: " . $e->getMessage());
    error_log("Exception type: " . get_class($e));
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("POST data: " . print_r($_POST, true));
    error_log("Session data: " . print_r($_SESSION, true));
    $_SESSION['error'] = "An unexpected error occurred. Please try again or contact support.";
    safeRedirect('checkout.php');
}

error_log("=== PROCESS PAYMENT SCRIPT ENDED (This should not appear if redirect worked) ===");
?>