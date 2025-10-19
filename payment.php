<?php
session_start();
require_once("db.php");
require_once("User.php");

if (!User::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: checkout.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$currentUser = User::getCurrentUser();

// Get form data
$full_name = $_POST['full_name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$address_line1 = $_POST['address_line1'] ?? '';
$address_line2 = $_POST['address_line2'] ?? '';
$city = $_POST['city'] ?? '';
$state = $_POST['state'] ?? '';
$pincode = $_POST['pincode'] ?? '';
$country = $_POST['country'] ?? '';
$order_notes = $_POST['order_notes'] ?? '';

$subtotal = floatval($_POST['subtotal'] ?? 0);
$tax = floatval($_POST['tax'] ?? 0);
$total = floatval($_POST['total'] ?? 0);

// Store checkout data in session
$_SESSION['checkout_data'] = [
    'full_name' => $full_name,
    'email' => $email,
    'phone' => $phone,
    'address_line1' => $address_line1,
    'address_line2' => $address_line2,
    'city' => $city,
    'state' => $state,
    'pincode' => $pincode,
    'country' => $country,
    'order_notes' => $order_notes,
    'subtotal' => $subtotal,
    'tax' => $tax,
    'total' => $total
];

// Get cart items with cart_quantity (customer's order quantity)
$stmt = $conn->prepare("
    SELECT c.id as cart_id, c.quantity as cart_quantity, p.*, p.quantity as stock_quantity
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

// ==========================================
// RAZORPAY TEST MODE API KEYS
// ==========================================
$razorpay_key_id = "rzp_test_pM7XeD3uvgF2Or";
$razorpay_key_secret = "pjPyycAbpchrCl4tgwUqc7V6";

// Generate order number
$order_number = 'HP' . date('Ymd') . strtoupper(substr(uniqid(), -6));

// Convert to paise (Razorpay accepts amount in smallest currency unit)
$amount_in_paise = $total * 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment | HappyPouch</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <style>
    * {margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
    body {background:#fffaf5; color:#333;}
    
    header {background:white; padding:20px 60px; box-shadow:0 2px 10px rgba(0,0,0,0.1);}
    header h1 {color:#f4a261; text-align:center;}
    
    .container {max-width:800px; margin:40px auto; padding:0 20px;}
    
    .payment-card {background:white; border-radius:20px; padding:40px; box-shadow:0 5px 20px rgba(0,0,0,0.1); text-align:center;}
    .payment-card h2 {color:#f4a261; margin-bottom:20px; font-size:1.8rem;}
    .payment-card p {color:#666; margin-bottom:30px; line-height:1.6;}
    
    .order-summary-box {background:#f8f8f8; padding:25px; border-radius:15px; margin:30px 0; text-align:left;}
    .order-summary-box h3 {color:#333; margin-bottom:20px; font-size:1.3rem;}
    
    .summary-item {display:flex; justify-content:space-between; margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid #e0e0e0;}
    .summary-item:last-child {border-bottom:none; margin-bottom:0; padding-bottom:0;}
    .item-name {font-weight:600; color:#333;}
    .item-qty {color:#666; font-size:0.9rem; margin-top:5px;}
    
    .totals-section {margin-top:20px; padding-top:20px; border-top:2px solid #e0e0e0;}
    .total-row {display:flex; justify-content:space-between; margin-bottom:10px; font-size:1.05rem;}
    .total-row.final {font-weight:700; font-size:1.5rem; color:#e07b39; margin-top:15px; padding-top:15px; border-top:2px solid #e0e0e0;}
    
    .razorpay-btn {background:#4caf50; color:white; border:none; padding:18px 50px; border-radius:12px; font-size:1.2rem; font-weight:600; cursor:pointer; transition:0.3s; margin-top:20px;}
    .razorpay-btn:hover {background:#45a049; transform:translateY(-2px); box-shadow:0 8px 20px rgba(76,175,80,0.3);}
    .razorpay-btn:disabled {background:#ccc; cursor:not-allowed;}
    
    .payment-icons {display:flex; justify-content:center; gap:20px; margin:30px 0; flex-wrap:wrap;}
    .payment-icon {font-size:2.5rem;}
    
    .secure-badge {display:inline-flex; align-items:center; gap:8px; background:#e8f5e9; color:#2e7d32; padding:10px 20px; border-radius:20px; font-weight:600; margin-top:20px;}
    
    .back-link {display:block; margin-top:20px; color:#f4a261; text-decoration:none; font-weight:600;}
    .back-link:hover {text-decoration:underline;}
    
    .loading {display:none; margin-top:20px;}
    .loading.active {display:block;}
    .spinner {border:4px solid #f3f3f3; border-top:4px solid #f4a261; border-radius:50%; width:40px; height:40px; animation:spin 1s linear infinite; margin:0 auto;}
    
    .error-message {background:#ffebee; color:#c62828; padding:15px; border-radius:10px; margin-bottom:20px; display:none;}
    .error-message.active {display:block;}
    
    @keyframes spin {
      0% {transform:rotate(0deg);}
      100% {transform:rotate(360deg);}
    }
    
    @media (max-width: 768px) {
      header {padding:20px;}
      .payment-card {padding:25px;}
      .payment-icons {gap:15px;}
    }
  </style>
</head>
<body>
  <header>
    <h1>Secure Payment</h1>
  </header>
  
  <div class="container">
    <div class="payment-card">
      <div class="error-message" id="errorMessage"></div>
      
      <h2>Complete Your Payment</h2>
      <p>You're just one step away from completing your order. Click the button below to proceed with secure payment through Razorpay.</p>
      
      <div class="payment-icons">
        <span class="payment-icon" title="Cards">💳</span>
        <span class="payment-icon" title="UPI">📱</span>
        <span class="payment-icon" title="Net Banking">🏦</span>
        <span class="payment-icon" title="Wallets">💰</span>
      </div>
      
      <div class="order-summary-box">
        <h3>Order Summary</h3>
        
        <?php foreach ($cart_items as $item): ?>
          <div class="summary-item">
            <div>
              <div class="item-name"><?= htmlspecialchars($item['title']) ?></div>
              <div class="item-qty">Qty: <?= $item['cart_quantity'] ?> × ₹<?= number_format($item['price'], 2) ?></div>
            </div>
            <div style="font-weight:600; color:#e07b39;">₹<?= number_format($item['price'] * $item['cart_quantity'], 2) ?></div>
          </div>
        <?php endforeach; ?>
        
        <div class="totals-section">
          <div class="total-row">
            <span>Subtotal:</span>
            <span>₹<?= number_format($subtotal, 2) ?></span>
          </div>
          <div class="total-row">
            <span>Shipping:</span>
            <span style="color:#4caf50;">Free</span>
          </div>
          <div class="total-row">
            <span>Tax (18%):</span>
            <span>₹<?= number_format($tax, 2) ?></span>
          </div>
          <div class="total-row final">
            <span>Total Amount:</span>
            <span>₹<?= number_format($total, 2) ?></span>
          </div>
        </div>
      </div>
      
      <button id="rzp-button" class="razorpay-btn">
        Pay ₹<?= number_format($total, 2) ?> with Razorpay
      </button>
      
      <div class="loading" id="loading">
        <div class="spinner"></div>
        <p style="margin-top:10px; color:#666;">Processing payment...</p>
      </div>
      
      <div class="secure-badge">
        <span>🔒</span>
        <span>Secure Payment | SSL Encrypted</span>
      </div>
      
      <a href="checkout.php" class="back-link">← Back to Checkout</a>
    </div>
  </div>
  
  <script>
    // Debug: Log all configuration
    const razorpayKey = "<?= $razorpay_key_id ?>";
    const amount = "<?= $amount_in_paise ?>";
    const orderNum = "<?= $order_number ?>";
    
    console.log("=== Razorpay Debug Info ===");
    console.log("Key ID:", razorpayKey);
    console.log("Amount (paise):", amount);
    console.log("Order Number:", orderNum);
    console.log("Total (INR):", <?= $total ?>);
    console.log("=========================");
    
    // Check if key is configured
    if (razorpayKey === "rzp_test_PASTE_YOUR_KEY_ID_HERE" || razorpayKey === "YOUR_RAZORPAY_KEY_ID" || razorpayKey === "") {
      document.getElementById('errorMessage').textContent = "⚠️ Razorpay API Key not configured! Please add your Razorpay keys in payment.php (line 74-75)";
      document.getElementById('errorMessage').classList.add('active');
      document.getElementById('rzp-button').disabled = true;
      document.getElementById('rzp-button').textContent = "Configure Razorpay Keys First";
    }
    
    // Validate key format
    if (razorpayKey && !razorpayKey.startsWith('rzp_test_') && !razorpayKey.startsWith('rzp_live_')) {
      document.getElementById('errorMessage').textContent = "⚠️ Invalid Razorpay Key format! Key should start with 'rzp_test_' or 'rzp_live_'";
      document.getElementById('errorMessage').classList.add('active');
      document.getElementById('rzp-button').disabled = true;
    }
    
    const options = {
      "key": razorpayKey,
      "amount": amount,
      "currency": "INR",
      "name": "HappyPouch",
      "description": "Order #" + orderNum,
      "image": "https://via.placeholder.com/150x150.png?text=HP",
      "handler": function (response) {
        // Payment successful
        console.log("✓ Payment Success!", response);
        console.log("Payment ID:", response.razorpay_payment_id);
        
        document.getElementById('loading').classList.add('active');
        document.getElementById('rzp-button').style.display = 'none';
        
        // Send payment details to server
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_razorpay_payment.php';
        
        const fields = {
          'razorpay_payment_id': response.razorpay_payment_id,
          'razorpay_order_id': response.razorpay_order_id || '',
          'razorpay_signature': response.razorpay_signature || '',
          'order_number': orderNum
        };
        
        for (const key in fields) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = key;
          input.value = fields[key];
          form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
      },
      "prefill": {
        "name": "<?= htmlspecialchars($full_name) ?>",
        "email": "<?= htmlspecialchars($email) ?>",
        "contact": "<?= htmlspecialchars($phone) ?>"
      },
      "notes": {
        "order_number": orderNum,
        "address": "<?= htmlspecialchars($address_line1) ?>"
      },
      "theme": {
        "color": "#f4a261"
      },
      "modal": {
        "ondismiss": function() {
          console.log('User closed payment popup');
        }
      }
    };
    
    console.log("Razorpay Options:", options);
    
    let rzp;
    try {
      rzp = new Razorpay(options);
      console.log("✓ Razorpay initialized successfully");
    } catch (error) {
      console.error("✗ Failed to initialize Razorpay:", error);
      document.getElementById('errorMessage').textContent = "❌ Failed to initialize Razorpay: " + error.message;
      document.getElementById('errorMessage').classList.add('active');
      document.getElementById('rzp-button').disabled = true;
    }
    
    rzp.on('payment.failed', function (response) {
      console.error('✗ Payment Failed Event:', response);
      console.error('Error Code:', response.error.code);
      console.error('Error Description:', response.error.description);
      console.error('Error Source:', response.error.source);
      console.error('Error Step:', response.error.step);
      console.error('Error Reason:', response.error.reason);
      
      let errorMsg = response.error.description || 'Payment failed';
      if (response.error.reason) {
        errorMsg += ' (Reason: ' + response.error.reason + ')';
      }
      
      alert('❌ Payment Failed\n\n' + errorMsg + '\n\nPlease try again or use a different payment method.');
      
      document.getElementById('errorMessage').innerHTML = 
        "❌ <strong>Payment Failed:</strong> " + errorMsg + 
        "<br><small>Error Code: " + response.error.code + "</small>";
      document.getElementById('errorMessage').classList.add('active');
    });
    
    document.getElementById('rzp-button').addEventListener('click', function(e) {
      e.preventDefault();
      
      console.log("Payment button clicked");
      
      // Clear previous errors
      document.getElementById('errorMessage').classList.remove('active');
      
      // Check if key is valid
      if (!razorpayKey || razorpayKey.includes("PASTE_YOUR") || razorpayKey.includes("YOUR_RAZORPAY")) {
        alert("⚠️ Please configure Razorpay API keys first!\n\nEdit payment.php lines 74-75");
        document.getElementById('errorMessage').textContent = "⚠️ Razorpay keys not configured";
        document.getElementById('errorMessage').classList.add('active');
        return;
      }
      
      if (!razorpayKey.startsWith('rzp_')) {
        alert("⚠️ Invalid Razorpay Key!\n\nKey should start with 'rzp_test_' or 'rzp_live_'");
        return;
      }
      
      try {
        console.log("Opening Razorpay checkout...");
        rzp.open();
      } catch (error) {
        console.error("✗ Error opening Razorpay:", error);
        alert("❌ Error opening payment gateway\n\n" + error.message + "\n\nCheck browser console for details.");
        document.getElementById('errorMessage').textContent = "❌ Error: " + error.message;
        document.getElementById('errorMessage').classList.add('active');
      }
    });
    
    // Additional validation on page load
    window.addEventListener('load', function() {
      if (!window.Razorpay) {
        console.error("✗ Razorpay script not loaded!");
        document.getElementById('errorMessage').textContent = "❌ Razorpay script failed to load. Check your internet connection.";
        document.getElementById('errorMessage').classList.add('active');
        document.getElementById('rzp-button').disabled = true;
      } else {
        console.log("✓ Razorpay script loaded successfully");
      }
    });
  </script>
</body>
</html>