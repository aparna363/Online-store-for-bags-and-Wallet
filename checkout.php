<?php
session_start();
require_once("db.php");
require_once("User.php");

if (!User::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$currentUser = User::getCurrentUser();

// Get cart items with cart_quantity (what customer wants to buy)
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
$subtotal = 0;

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    // Use cart_quantity (customer's order quantity) for price calculation
    $subtotal += $row['price'] * $row['cart_quantity'];
}

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

$tax = $subtotal * 0.18;
$total = $subtotal + $tax;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout | HappyPouch</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    * {margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
    body {background:#fffaf5; color:#333;}
    
    header {background:white; padding:20px 60px; box-shadow:0 2px 10px rgba(0,0,0,0.1);}
    header h1 {color:#f4a261; text-align:center;}
    
    .container {max-width:1200px; margin:40px auto; padding:0 20px;}
    .checkout-container {display:grid; grid-template-columns:1.5fr 1fr; gap:30px;}
    
    /* Checkout Form */
    .checkout-form {background:white; border-radius:20px; padding:40px; box-shadow:0 5px 20px rgba(0,0,0,0.1);}
    .checkout-form h2 {color:#f4a261; margin-bottom:30px; font-size:1.8rem;}
    .form-section {margin-bottom:30px;}
    .form-section h3 {color:#333; margin-bottom:20px; font-size:1.3rem;}
    .form-group {margin-bottom:20px;}
    .form-group label {display:block; margin-bottom:8px; font-weight:600; color:#555;}
    .form-group input, .form-group select, .form-group textarea {width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:10px; font-size:1rem; transition:0.3s; font-family:'Poppins',sans-serif;}
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {outline:none; border-color:#f4a261;}
    .form-row {display:grid; grid-template-columns:1fr 1fr; gap:15px;}
    
    /* Order Summary */
    .order-summary-checkout {background:white; border-radius:20px; padding:30px; box-shadow:0 5px 20px rgba(0,0,0,0.1); height:fit-content; position:sticky; top:20px;}
    .order-summary-checkout h3 {color:#f4a261; margin-bottom:20px; font-size:1.5rem;}
    .order-item {display:flex; justify-content:space-between; margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid #f0f0f0;}
    .order-item:last-child {border-bottom:none;}
    .item-info {flex:1;}
    .item-name {font-weight:600; color:#333; margin-bottom:5px;}
    .item-qty {color:#666; font-size:0.9rem;}
    .item-total {font-weight:600; color:#e07b39;}
    .summary-totals {margin-top:20px; padding-top:20px; border-top:2px solid #f0f0f0;}
    .summary-row {display:flex; justify-content:space-between; margin-bottom:12px; font-size:1.1rem;}
    .summary-row.final-total {font-weight:700; font-size:1.5rem; color:#e07b39; margin-top:15px; padding-top:15px; border-top:2px solid #f0f0f0;}
    .place-order-btn {width:100%; padding:15px; background:#4caf50; color:white; border:none; border-radius:10px; font-size:1.1rem; font-weight:600; cursor:pointer; margin-top:20px; transition:0.3s;}
    .place-order-btn:hover {background:#45a049; transform:scale(1.02);}
    
    @media (max-width: 768px) {
      .checkout-container {grid-template-columns:1fr;}
      .form-row {grid-template-columns:1fr;}
      header {padding:20px;}
    }
  </style>
</head>
<body>
  <header>
    <h1>Checkout</h1>
  </header>
  <div class="container">
    <div class="checkout-container">
      <div class="checkout-form">
        <h2>Billing & Shipping Details</h2>
        <form id="checkoutForm" method="POST" action="payment.php">
          <div class="form-section">
            <h3>Contact Information</h3>
            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="full_name" value="<?= htmlspecialchars($currentUser['name']) ?>" required>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
              </div>
              <div class="form-group">
                <label>Phone *</label>
                <input type="tel" name="phone" placeholder="+91 1234567890" required>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Shipping Address</h3>
            <div class="form-group">
              <label>Address Line 1 *</label>
              <input type="text" name="address_line1" placeholder="House No., Street Name" required>
            </div>
            <div class="form-group">
              <label>Address Line 2</label>
              <input type="text" name="address_line2" placeholder="Apartment, Suite, etc. (Optional)">
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>City *</label>
                <input type="text" name="city" placeholder="City" required>
              </div>
              <div class="form-group">
                <label>State *</label>
                <input type="text" name="state" placeholder="State" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>PIN Code *</label>
                <input type="text" name="pincode" placeholder="123456" required>
              </div>
              <div class="form-group">
                <label>Country *</label>
                <select name="country" required>
                  <option value="India">India</option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Order Notes (Optional)</h3>
            <div class="form-group">
              <textarea name="order_notes" rows="4" placeholder="Any special instructions for your order?"></textarea>
            </div>
          </div>

          <input type="hidden" name="subtotal" value="<?= $subtotal ?>">
          <input type="hidden" name="tax" value="<?= $tax ?>">
          <input type="hidden" name="total" value="<?= $total ?>">
        </form>
      </div>

      <div class="order-summary-checkout">
        <h3>Order Summary</h3>
        
        <div style="max-height:300px; overflow-y:auto; margin-bottom:20px;">
          <?php foreach ($cart_items as $item): ?>
            <div class="order-item">
              <div class="item-info">
                <div class="item-name"><?= htmlspecialchars($item['title']) ?></div>
                <div class="item-qty">Qty: <?= $item['cart_quantity'] ?> × ₹<?= number_format($item['price'], 2) ?></div>
              </div>
              <div class="item-total">₹<?= number_format($item['price'] * $item['cart_quantity'], 2) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="summary-totals">
          <div class="summary-row">
            <span>Subtotal:</span>
            <span>₹<?= number_format($subtotal, 2) ?></span>
          </div>
          <div class="summary-row">
            <span>Shipping:</span>
            <span style="color:#4caf50;">Free</span>
          </div>
          <div class="summary-row">
            <span>Tax (18%):</span>
            <span>₹<?= number_format($tax, 2) ?></span>
          </div>
          <div class="summary-row final-total">
            <span>Total:</span>
            <span>₹<?= number_format($total, 2) ?></span>
          </div>
        </div>

        <button type="submit" form="checkoutForm" class="place-order-btn">Proceed to Payment</button>
        <a href="cart.php" style="display:block; text-align:center; margin-top:15px; color:#f4a261; text-decoration:none;">← Back to Cart</a>
      </div>
    </div>
  </div>
</body>
</html>