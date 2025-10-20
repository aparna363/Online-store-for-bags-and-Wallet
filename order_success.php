<?php
session_start();
require_once("db.php");
require_once("User.php");

// ==================== EXCEPTION CLASSES ====================
class OrderNotFoundException extends Exception {}
class UnauthorizedAccessException extends Exception {}

// ==================== MODEL CLASSES ====================
class OrderDetails {
    private $id;
    private $orderNumber;
    private $userId;
    private $fullName;
    private $email;
    private $phone;
    private $addressLine1;
    private $addressLine2;
    private $city;
    private $state;
    private $pincode;
    private $country;
    private $paymentMethod;
    private $paymentStatus;
    private $orderStatus;
    private $subtotal;
    private $tax;
    private $total;
    private $razorpayPaymentId;
    private $razorpayOrderId;
    private $orderNotes;
    private $createdAt;
    private $items = [];
    
    public static function fromArray(array $data): self {
        $order = new self();
        $order->id = $data['id'];
        $order->orderNumber = $data['order_number'];
        $order->userId = $data['user_id'];
        $order->fullName = $data['full_name'];
        $order->email = $data['email'];
        $order->phone = $data['phone'];
        $order->addressLine1 = $data['address_line1'];
        $order->addressLine2 = $data['address_line2'] ?? '';
        $order->city = $data['city'];
        $order->state = $data['state'];
        $order->pincode = $data['pincode'];
        $order->country = $data['country'];
        $order->paymentMethod = $data['payment_method'];
        $order->paymentStatus = $data['payment_status'];
        $order->orderStatus = $data['order_status'];
        $order->subtotal = $data['subtotal'];
        $order->tax = $data['tax'];
        $order->total = $data['total'];
        $order->razorpayPaymentId = $data['razorpay_payment_id'] ?? null;
        $order->razorpayOrderId = $data['razorpay_order_id'] ?? null;
        $order->orderNotes = $data['order_notes'] ?? '';
        $order->createdAt = $data['created_at'];
        return $order;
    }
    
    // Getters
    public function getId(): int { return $this->id; }
    public function getOrderNumber(): string { return $this->orderNumber; }
    public function getUserId(): int { return $this->userId; }
    public function getFullName(): string { return $this->fullName; }
    public function getEmail(): string { return $this->email; }
    public function getPhone(): string { return $this->phone; }
    public function getAddressLine1(): string { return $this->addressLine1; }
    public function getAddressLine2(): string { return $this->addressLine2; }
    public function getCity(): string { return $this->city; }
    public function getState(): string { return $this->state; }
    public function getPincode(): string { return $this->pincode; }
    public function getCountry(): string { return $this->country; }
    public function getPaymentMethod(): string { return $this->paymentMethod; }
    public function getPaymentStatus(): string { return $this->paymentStatus; }
    public function getOrderStatus(): string { return $this->orderStatus; }
    public function getSubtotal(): float { return $this->subtotal; }
    public function getTax(): float { return $this->tax; }
    public function getTotal(): float { return $this->total; }
    public function getRazorpayPaymentId(): ?string { return $this->razorpayPaymentId; }
    public function getRazorpayOrderId(): ?string { return $this->razorpayOrderId; }
    public function getOrderNotes(): string { return $this->orderNotes; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getItems(): array { return $this->items; }
    
    public function setItems(array $items): void { $this->items = $items; }
    
    public function getFormattedDate(): string {
        return date('F d, Y', strtotime($this->createdAt));
    }
    
    public function getFormattedTime(): string {
        return date('h:i A', strtotime($this->createdAt));
    }
    
    public function getFullAddress(): string {
        $address = $this->addressLine1;
        if ($this->addressLine2) {
            $address .= ', ' . $this->addressLine2;
        }
        $address .= ', ' . $this->city . ', ' . $this->state . ' - ' . $this->pincode;
        $address .= ', ' . $this->country;
        return $address;
    }
    
    public function getStatusBadgeClass(): string {
        return match($this->orderStatus) {
            'processing' => 'badge-warning',
            'shipped' => 'badge-info',
            'delivered' => 'badge-success',
            'cancelled' => 'badge-danger',
            default => 'badge-secondary'
        };
    }
    
    public function getPaymentStatusBadgeClass(): string {
        return match($this->paymentStatus) {
            'completed' => 'badge-success',
            'pending' => 'badge-warning',
            'failed' => 'badge-danger',
            default => 'badge-secondary'
        };
    }
}

class OrderItemDetails {
    private $id;
    private $productId;
    private $productTitle;
    private $productImage;
    private $quantity;
    private $price;
    private $subtotal;
    
    public static function fromArray(array $data): self {
        $item = new self();
        $item->id = $data['id'];
        $item->productId = $data['product_id'];
        $item->productTitle = $data['title'] ?? $data['product_title'] ?? 'Unknown Product';
        $item->productImage = $data['image'] ?? $data['product_image'] ?? 'default.jpg';
        $item->quantity = $data['quantity'];
        $item->price = $data['price'];
        $item->subtotal = $data['subtotal'];
        return $item;
    }
    
    public function getId(): int { return $this->id; }
    public function getProductId(): int { return $this->productId; }
    public function getProductTitle(): string { return $this->productTitle; }
    public function getProductImage(): string { return $this->productImage; }
    public function getQuantity(): int { return $this->quantity; }
    public function getPrice(): float { return $this->price; }
    public function getSubtotal(): float { return $this->subtotal; }
}

// ==================== REPOSITORY CLASSES ====================
class OrderSuccessRepository {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function findOrderByNumber(string $orderNumber): ?OrderDetails {
        $sql = "SELECT * FROM orders WHERE order_number = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $orderNumber);
        $stmt->execute();
        
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return OrderDetails::fromArray($row);
        }
        
        return null;
    }
    
    public function getOrderItems(int $orderId): array {
        $sql = "SELECT oi.*, p.title, p.image 
                FROM order_items oi 
                LEFT JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = OrderItemDetails::fromArray($row);
        }
        
        return $items;
    }
}

// ==================== SERVICE CLASSES ====================
class OrderSuccessService {
    private $repository;
    
    public function __construct(OrderSuccessRepository $repository) {
        $this->repository = $repository;
    }
    
    public function getOrderWithItems(string $orderNumber, int $userId): OrderDetails {
        $order = $this->repository->findOrderByNumber($orderNumber);
        
        if (!$order) {
            throw new OrderNotFoundException("Order not found");
        }
        
        if ($order->getUserId() !== $userId) {
            throw new UnauthorizedAccessException("Unauthorized access to order");
        }
        
        $items = $this->repository->getOrderItems($order->getId());
        $order->setItems($items);
        
        return $order;
    }
}

// ==================== VIEW RENDERER ====================
class OrderSuccessView {
    private $order;
    
    public function __construct(OrderDetails $order) {
        $this->order = $order;
    }
    
    public function render(): void {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success - <?php echo htmlspecialchars($this->order->getOrderNumber()); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #f4a261;
            --secondary-color: #e76f51;
            --success-color: #2a9d8f;
            --light-bg: #f8f9fa;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .success-header {
            background: linear-gradient(135deg, var(--success-color) 0%, #264653 100%);
            color: white;
            padding: 3rem 0;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: scaleIn 0.5s ease-out;
        }
        
        @keyframes scaleIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .order-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header-custom {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .card-body-custom {
            padding: 2rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .badge {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
        
        .badge-success {
            background-color: #28a745;
        }
        
        .badge-info {
            background-color: #17a2b8;
        }
        
        .badge-danger {
            background-color: #dc3545;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 1.5rem;
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .product-price {
            color: #666;
        }
        
        .total-section {
            background-color: var(--light-bg);
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
        }
        
        .total-final {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            border-top: 2px solid #ddd;
            padding-top: 1rem;
            margin-top: 0.5rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn-custom {
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary-custom {
            background: var(--primary-color);
            border: none;
            color: white;
        }
        
        .btn-primary-custom:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(244, 162, 97, 0.3);
        }
        
        .btn-outline-custom {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: white;
        }
        
        .btn-outline-custom:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--success-color);
            border: 3px solid white;
            box-shadow: 0 0 0 2px var(--success-color);
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -1.7rem;
            top: 12px;
            width: 2px;
            height: 100%;
            background: #ddd;
        }
        
        .timeline-item:last-child::after {
            display: none;
        }
    </style>
</head>
<body>
    <div class="success-header">
        <div class="container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="mb-3">Order Placed Successfully!</h1>
            <p class="lead mb-0">Thank you for your purchase, <?php echo htmlspecialchars($this->order->getFullName()); ?>!</p>
            <p class="mb-0">Order #<?php echo htmlspecialchars($this->order->getOrderNumber()); ?></p>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row">
            <!-- Order Details -->
            <div class="col-lg-8">
                <div class="order-card">
                    <div class="card-header-custom">
                        <i class="fas fa-receipt me-2"></i>Order Details
                    </div>
                    <div class="card-body-custom">
                        <div class="info-row">
                            <span class="info-label">Order Number:</span>
                            <span><strong><?php echo htmlspecialchars($this->order->getOrderNumber()); ?></strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Order Date:</span>
                            <span><?php echo $this->order->getFormattedDate(); ?> at <?php echo $this->order->getFormattedTime(); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Payment Method:</span>
                            <span class="text-capitalize"><?php echo htmlspecialchars($this->order->getPaymentMethod()); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Payment Status:</span>
                            <span>
                                <span class="badge <?php echo $this->order->getPaymentStatusBadgeClass(); ?>">
                                    <?php echo ucfirst($this->order->getPaymentStatus()); ?>
                                </span>
                            </span>
                        </div>
                        
                        <?php if ($this->order->getRazorpayPaymentId()): ?>
                        <div class="info-row">
                            <span class="info-label">Payment ID:</span>
                            <span><small class="text-muted"><?php echo htmlspecialchars($this->order->getRazorpayPaymentId()); ?></small></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="order-card">
                    <div class="card-header-custom">
                        <i class="fas fa-shopping-bag me-2"></i>Order Items
                    </div>
                    <div class="card-body-custom p-0">
                        <?php foreach ($this->order->getItems() as $item): ?>
                        <div class="product-item">
                            <img src="<?php echo htmlspecialchars($item->getProductImage()); ?>" 
                                 alt="<?php echo htmlspecialchars($item->getProductTitle()); ?>" 
                                 class="product-image"
                                 onerror="this.src='https://via.placeholder.com/80'">
                            <div class="product-details">
                                <div class="product-title"><?php echo htmlspecialchars($item->getProductTitle()); ?></div>
                                <div class="product-price">
                                    ₹<?php echo number_format($item->getPrice(), 2); ?> × <?php echo $item->getQuantity(); ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <strong>₹<?php echo number_format($item->getSubtotal(), 2); ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="total-section">
                            <div class="total-row">
                                <span>Subtotal:</span>
                                <span>₹<?php echo number_format($this->order->getSubtotal(), 2); ?></span>
                            </div>
                            <div class="total-row">
                                <span>Tax (18%):</span>
                                <span>₹<?php echo number_format($this->order->getTax(), 2); ?></span>
                            </div>
                            <div class="total-row">
                                <span>Shipping:</span>
                                <span class="text-success"><strong>Free</strong></span>
                            </div>
                            <div class="total-row total-final">
                                <span>Total Paid:</span>
                                <span>₹<?php echo number_format($this->order->getTotal(), 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shipping & Next Steps -->
            <div class="col-lg-4">
                <!-- Shipping Address -->
                <div class="order-card">
                    <div class="card-header-custom">
                        <i class="fas fa-map-marker-alt me-2"></i>Shipping Address
                    </div>
                    <div class="card-body-custom">
                        <p class="mb-2"><strong><?php echo htmlspecialchars($this->order->getFullName()); ?></strong></p>
                        <p class="mb-1"><?php echo htmlspecialchars($this->order->getAddressLine1()); ?></p>
                        <?php if ($this->order->getAddressLine2()): ?>
                        <p class="mb-1"><?php echo htmlspecialchars($this->order->getAddressLine2()); ?></p>
                        <?php endif; ?>
                        <p class="mb-1"><?php echo htmlspecialchars($this->order->getCity()); ?>, <?php echo htmlspecialchars($this->order->getState()); ?> - <?php echo htmlspecialchars($this->order->getPincode()); ?></p>
                        <p class="mb-2"><?php echo htmlspecialchars($this->order->getCountry()); ?></p>
                        <p class="mb-0"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($this->order->getPhone()); ?></p>
                        <p class="mb-0"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($this->order->getEmail()); ?></p>
                    </div>
                </div>

                <!-- What's Next -->
                <div class="order-card">
                    <div class="card-header-custom">
                        <i class="fas fa-clipboard-list me-2"></i>What's Next?
                    </div>
                    <div class="card-body-custom">
                        <div class="timeline">
                            <div class="timeline-item">
                                <strong>Order Confirmed</strong>
                                <p class="text-muted mb-0 small">We've received your order</p>
                            </div>
                            <div class="timeline-item">
                                <strong>Processing</strong>
                                <p class="text-muted mb-0 small">We're preparing your items</p>
                            </div>
                            <div class="timeline-item">
                                <strong>Shipped</strong>
                                <p class="text-muted mb-0 small">You'll receive tracking details via email</p>
                            </div>
                            <div class="timeline-item">
                                <strong>Delivered</strong>
                                <p class="text-muted mb-0 small">Enjoy your purchase!</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons flex-column">
                    <a href="index.php" class="btn btn-primary-custom btn-custom w-100">
                        <i class="fas fa-home me-2"></i>Continue Shopping
                    </a>
                    <a href="my_orders.php" class="btn btn-outline-custom btn-custom w-100">
                        <i class="fas fa-list me-2"></i>View All Orders
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-custom btn-custom w-100">
                        <i class="fas fa-print me-2"></i>Print Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
        <?php
    }
}

// ==================== CONTROLLER LOGIC ====================
try {
    // Check if user is logged in
    if (!User::isLoggedIn()) {
        header("Location: login.php");
        exit();
    }

    $userId = $_SESSION['user_id'];
    $orderNumber = $_GET['order'] ?? null;

    if (!$orderNumber) {
        $_SESSION['error'] = "Invalid order reference";
        header("Location: index.php");
        exit();
    }

    // Initialize service with dependency injection
    $repository = new OrderSuccessRepository($conn);
    $service = new OrderSuccessService($repository);

    // Get order with items
    $order = $service->getOrderWithItems($orderNumber, $userId);

    // Render view
    $view = new OrderSuccessView($order);
    $view->render();

} catch (OrderNotFoundException $e) {
    $_SESSION['error'] = "Order not found";
    header("Location: index.php");
    exit();
} catch (UnauthorizedAccessException $e) {
    $_SESSION['error'] = "You don't have permission to view this order";
    header("Location: index.php");
    exit();
} catch (Exception $e) {
    error_log("Order success page error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading the order";
    header("Location: index.php");
    exit();
}
?>