<?php
session_start();
require_once("db.php");
require_once("User.php");

// ==================== EXCEPTION CLASSES ====================
class OrdersNotFoundException extends Exception {}

// ==================== MODEL CLASSES ====================
class OrderSummary {
    private $id;
    private $orderNumber;
    private $orderDate;
    private $totalAmount;
    private $paymentStatus;
    private $orderStatus;
    private $itemCount;
    
    public static function fromArray(array $data): self {
        $order = new self();
        $order->id = $data['id'];
        $order->orderNumber = $data['order_number'];
        $order->orderDate = $data['created_at'];
        $order->totalAmount = $data['total'];
        $order->paymentStatus = $data['payment_status'];
        $order->orderStatus = $data['order_status'];
        $order->itemCount = $data['item_count'] ?? 0;
        return $order;
    }
    
    public function getId(): int { return $this->id; }
    public function getOrderNumber(): string { return $this->orderNumber; }
    public function getOrderDate(): string { return $this->orderDate; }
    public function getTotalAmount(): float { return $this->totalAmount; }
    public function getPaymentStatus(): string { return $this->paymentStatus; }
    public function getOrderStatus(): string { return $this->orderStatus; }
    public function getItemCount(): int { return $this->itemCount; }
    
    public function getFormattedDate(): string {
        return date('M d, Y', strtotime($this->orderDate));
    }
    
    public function getFormattedTime(): string {
        return date('h:i A', strtotime($this->orderDate));
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
    
    public function getStatusIcon(): string {
        return match($this->orderStatus) {
            'processing' => 'fa-clock',
            'shipped' => 'fa-shipping-fast',
            'delivered' => 'fa-check-circle',
            'cancelled' => 'fa-times-circle',
            default => 'fa-shopping-bag'
        };
    }
}

// ==================== REPOSITORY CLASSES ====================
class ViewOrdersRepository {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getUserOrders(int $userId): array {
        $sql = "SELECT o.*, 
                       COUNT(oi.id) as item_count 
                FROM orders o 
                LEFT JOIN order_items oi ON o.id = oi.order_id 
                WHERE o.user_id = ? 
                GROUP BY o.id 
                ORDER BY o.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $orders = [];
        
        while ($row = $result->fetch_assoc()) {
            $orders[] = OrderSummary::fromArray($row);
        }
        
        return $orders;
    }
    
    public function getOrderStatistics(int $userId): array {
        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                    SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                    SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
                    SUM(total) as total_spent
                FROM orders 
                WHERE user_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}

// ==================== SERVICE CLASSES ====================
class ViewOrdersService {
    private $repository;
    
    public function __construct(ViewOrdersRepository $repository) {
        $this->repository = $repository;
    }
    
    public function getUserOrdersWithStats(int $userId): array {
        $orders = $this->repository->getUserOrders($userId);
        $stats = $this->repository->getOrderStatistics($userId);
        
        return [
            'orders' => $orders,
            'stats' => $stats
        ];
    }
}

// ==================== VIEW RENDERER ====================
class ViewOrdersView {
    private $orders;
    private $stats;
    private $userName;
    
    public function __construct(array $orders, array $stats, string $userName) {
        $this->orders = $orders;
        $this->stats = $stats;
        $this->userName = $userName;
    }
    
    public function render(): void {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Order History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #f4a261;
            --secondary-color: #e76f51;
            --success-color: #2a9d8f;
            --light-bg: #f8f9fa;
            --dark-text: #264653;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 70px;
        }
        
        /* Navbar Styles */
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white !important;
            font-size: 1.5rem;
        }
        
        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            margin: 0 0.5rem;
            transition: all 0.3s;
        }
        
        .navbar-nav .nav-link:hover {
            color: white !important;
            transform: translateY(-2px);
        }
        
        /* Page Header */
        .page-header {
            background: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .page-title {
            color: var(--dark-text);
            font-weight: 700;
            margin: 0;
        }
        
        /* Statistics Cards */
        .stats-container {
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-icon.primary {
            background: rgba(244, 162, 97, 0.2);
            color: var(--primary-color);
        }
        
        .stat-icon.success {
            background: rgba(42, 157, 143, 0.2);
            color: var(--success-color);
        }
        
        .stat-icon.info {
            background: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
        }
        
        
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-text);
            margin: 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }
        
        /* Orders Section */
        .orders-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-weight: 700;
            color: var(--dark-text);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Order Card */
        .order-card {
            background: white;
            border: 2px solid #eee;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }
        
        .order-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 20px rgba(244, 162, 97, 0.1);
            transform: translateY(-2px);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .order-number {
            font-weight: 700;
            color: var(--dark-text);
            font-size: 1.1rem;
        }
        
        .order-date {
            color: #666;
            font-size: 0.9rem;
        }
        
        .order-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .order-info {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--dark-text);
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 2px solid #f0f0f0;
        }
        
        .order-amount {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        /* Badges */
        .badge {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        
        
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        /* Buttons */
        .btn-custom {
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-primary-custom {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary-custom:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(244, 162, 97, 0.3);
            color: white;
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
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-icon {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 1.5rem;
        }
        
        .empty-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-text);
            margin-bottom: 1rem;
        }
        
        .empty-text {
            color: #666;
            margin-bottom: 2rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .order-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .stat-card {
                margin-bottom: 1rem;
            }
        }
        
        /* Print Styles */
        @media print {
            body {
                padding-top: 0;
            }
            
            .navbar,
            .btn-custom,
            .empty-state .btn-primary-custom {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart me-1"></i>Cart
                        </a>
                    </li>
                    
                </ul>
            </div>
        </div>
    </nav>

    

    

        <!-- Orders List -->
        <div class="orders-section">
            <h2 class="section-title">
                <i class="fas fa-list"></i>
                Order History
            </h2>

            <?php if (empty($this->orders)): ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-shopping-basket"></i>
                    </div>
                    <h3 class="empty-title">No Orders Yet</h3>
                    <p class="empty-text">You haven't placed any orders yet. Start shopping to see your orders here!</p>
                    <a href="index.php" class="btn btn-primary-custom btn-custom">
                        <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <!-- Orders List -->
                <?php foreach ($this->orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-number">
                                <i class="fas <?php echo $order->getStatusIcon(); ?> me-2"></i>
                                Order #<?php echo htmlspecialchars($order->getOrderNumber()); ?>
                            </div>
                            <div class="order-date">
                                <i class="far fa-calendar me-1"></i>
                                <?php echo $order->getFormattedDate(); ?> at <?php echo $order->getFormattedTime(); ?>
                            </div>
                        </div>
                        <div>
                            <span class="badge <?php echo $order->getStatusBadgeClass(); ?>">
                                <?php echo ucfirst($order->getOrderStatus()); ?>
                            </span>
                        </div>
                    </div>

                    <div class="order-body">
                        <div class="order-info">
                            <span class="info-label">Items</span>
                            <span class="info-value">
                                <?php echo $order->getItemCount(); ?> 
                                <?php echo $order->getItemCount() == 1 ? 'item' : 'items'; ?>
                            </span>
                        </div>
                        <div class="order-info">
                            <span class="info-label">Payment Status</span>
                            <span class="info-value">
                                <span class="badge <?php echo $order->getPaymentStatusBadgeClass(); ?>">
                                    <?php echo ucfirst($order->getPaymentStatus()); ?>
                                </span>
                            </span>
                        </div>
                        <div class="order-info">
                            <span class="info-label">Order Status</span>
                            <span class="info-value text-capitalize">
                                <?php echo ucfirst($order->getOrderStatus()); ?>
                            </span>
                        </div>
                    </div>

                    <div class="order-footer">
                        <div class="order-amount">
                            <i class="fas fa-rupee-sign me-1"></i><?php echo number_format($order->getTotalAmount(), 2); ?>
                        </div>
                        <div>
                           
                            <?php if ($order->getOrderStatus() === 'delivered'): ?>
                            <a href="invoice.php?order=<?php echo urlencode($order->getOrderNumber()); ?>" 
                               class="btn btn-outline-custom btn-custom ms-2" 
                               target="_blank">
                                <i class="fas fa-file-invoice me-2"></i>Invoice
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Total Spent Summary -->
                
            <?php endif; ?>
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
        $_SESSION['error'] = "Please login to view your orders";
        header("Location: login.php");
        exit();
    }

    $userId = User::getCurrentUserId();
    $userName = User::getCurrentUserFullName() ?? User::getCurrentUsername();

    // Initialize service with dependency injection
    $repository = new ViewOrdersRepository($conn);
    $service = new ViewOrdersService($repository);

    // Get user orders with statistics
    $data = $service->getUserOrdersWithStats($userId);

    // Render view
    $view = new ViewOrdersView($data['orders'], $data['stats'], $userName);
    $view->render();

} catch (Exception $e) {
    error_log("View orders page error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading your orders";
    header("Location: index.php");
    exit();
}
?>