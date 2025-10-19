<?php
session_start();
require_once("db.php");
require_once("Product.php");
require_once("User.php");

if (!User::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$currentUser = User::getCurrentUser();

// Get cart items including stock (using 'quantity' as the stock column)
$stmt = $conn->prepare("
    SELECT c.id as cart_id, c.quantity as cart_quantity, p.*, p.quantity as stock 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$total = 0;

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $total += $row['price'] * $row['cart_quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shopping Cart | HappyPouch</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
/* ======= Styles ======= */
* {margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
body {background:#fffaf5; color:#333;}

header {background:white; padding:20px 60px; box-shadow:0 2px 10px rgba(0,0,0,0.1); display:flex; justify-content:space-between; align-items:center;}
header h1 {color:#f4a261; cursor:pointer;}
nav a {text-decoration:none; color:#333; margin:0 15px; font-weight:500; transition:0.3s;}
nav a:hover {color:#f4a261;}

.container {max-width:1200px; margin:40px auto; padding:0 20px;}
.page-title {text-align:center; color:#f4a261; font-size:2.5rem; margin-bottom:40px;}

.cart-container {display:grid; grid-template-columns:2fr 1fr; gap:30px;}

.cart-items {background:white; border-radius:20px; padding:30px; box-shadow:0 5px 20px rgba(0,0,0,0.1);}
.cart-item {display:flex; gap:20px; padding:20px; border-bottom:1px solid #f0f0f0; align-items:center;}
.cart-item:last-child {border-bottom:none;}
.item-image {width:100px; height:100px; object-fit:cover; border-radius:10px;}
.item-details {flex:1;}
.item-title {font-size:1.2rem; font-weight:600; color:#333; margin-bottom:5px;}
.item-category {color:#f4a261; font-size:0.9rem; text-transform:uppercase;}
.item-price {color:#e07b39; font-size:1.3rem; font-weight:700; margin-top:10px;}
.item-stock {font-size:0.9rem; color:#999; margin-top:5px;}
.item-actions {display:flex; align-items:center; gap:15px;}
.quantity-control {display:flex; align-items:center; gap:10px; background:#f5f5f5; padding:5px 10px; border-radius:10px;}
.quantity-control button {background:#f4a261; color:white; border:none; width:30px; height:30px; border-radius:50%; cursor:pointer; font-size:1.2rem; display:flex; align-items:center; justify-content:center;}
.quantity-control button:hover {background:#e07b39;}
.quantity-control button:disabled {background:#ccc; cursor:not-allowed;}
.quantity-control input {width:50px; text-align:center; border:none; background:transparent; font-weight:600; font-size:1rem;}
.remove-btn {background:#f44336; color:white; border:none; padding:8px 20px; border-radius:20px; cursor:pointer; font-weight:600;}
.remove-btn:hover {background:#d32f2f;}

.order-summary {background:white; border-radius:20px; padding:30px; box-shadow:0 5px 20px rgba(0,0,0,0.1); height:fit-content; position:sticky; top:20px;}
.order-summary h3 {color:#f4a261; margin-bottom:20px; font-size:1.5rem;}
.summary-row {display:flex; justify-content:space-between; margin-bottom:15px; font-size:1.1rem;}
.summary-row.total {border-top:2px solid #f0f0f0; padding-top:15px; margin-top:15px; font-weight:700; font-size:1.3rem; color:#e07b39;}
.checkout-btn {width:100%; padding:15px; background:#f4a261; color:white; border:none; border-radius:10px; font-size:1.1rem; font-weight:600; cursor:pointer; margin-top:20px; transition:0.3s;}
.checkout-btn:hover {background:#e07b39; transform:scale(1.02);}

.empty-cart {text-align:center; padding:80px 20px; color:#999;}
.empty-cart h3 {font-size:2rem; margin-bottom:20px;}
.continue-shopping {background:#f4a261; color:white; padding:12px 30px; border:none; border-radius:25px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block;}
.continue-shopping:hover {background:#e07b39;}

@media (max-width: 768px) {
  .cart-container {grid-template-columns:1fr;}
  .cart-item {flex-direction:column; text-align:center;}
  header {padding:20px;}
}
</style>
</head>
<body>

<header>
  <h1 onclick="window.location.href='index.php'">HappyPouch</h1>
  <nav>
    <a href="index.php">Home</a>
    <a href="products.php">Products</a>
    <a href="cart.php">Cart</a>
    <span style="color:#f4a261; font-weight:600;"><?= htmlspecialchars($currentUser['name']) ?></span>
    <a href="logout.php">Logout</a>
  </nav>
</header>

<div class="container">
  <h1 class="page-title">🛒 Shopping Cart</h1>

  <?php if (empty($cart_items)): ?>
    <div class="empty-cart">
      <h3>Your cart is empty</h3>
      <p>Add some products to get started!</p>
      <br>
      <a href="products.php" class="continue-shopping">Continue Shopping</a>
    </div>
  <?php else: ?>
    <div class="cart-container">
      <div class="cart-items">
        <?php foreach ($cart_items as $item): 
          $stock = isset($item['stock']) ? $item['stock'] : 0;
          $cart_qty = $item['cart_quantity'];
        ?>
          <div class="cart-item" id="cart-item-<?= $item['cart_id'] ?>">
            <img src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="item-image">
            <div class="item-details">
              <div class="item-category"><?= htmlspecialchars($item['category']) ?></div>
              <div class="item-title"><?= htmlspecialchars($item['title']) ?></div>
              <div class="item-price">₹<?= number_format($item['price'], 2) ?></div>
              <div class="item-stock">Stock available: <?= $stock ?></div>
            </div>
            <div class="item-actions">
              <div class="quantity-control">
                <button onclick="updateQuantity(<?= $item['cart_id'] ?>, <?= $cart_qty - 1 ?>, <?= $stock ?>)" <?= $cart_qty <= 1 ? 'disabled' : '' ?>>-</button>
                <input type="number" id="qty-<?= $item['cart_id'] ?>" value="<?= $cart_qty ?>" min="1" max="<?= $stock ?>" readonly>
                <button onclick="updateQuantity(<?= $item['cart_id'] ?>, <?= $cart_qty + 1 ?>, <?= $stock ?>)" <?= $cart_qty >= $stock ? 'disabled' : '' ?>>+</button>
              </div>
              <button class="remove-btn" onclick="removeItem(<?= $item['cart_id'] ?>)">Remove</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="order-summary">
        <h3>Order Summary</h3>
        <div class="summary-row">
          <span>Subtotal:</span>
          <span id="subtotal">₹<?= number_format($total, 2) ?></span>
        </div>
        <div class="summary-row">
          <span>Shipping:</span>
          <span>Free</span>
        </div>
        <div class="summary-row">
          <span>Tax (18%):</span>
          <span id="tax">₹<?= number_format($total * 0.18, 2) ?></span>
        </div>
        <div class="summary-row total">
          <span>Total:</span>
          <span id="total">₹<?= number_format($total * 1.18, 2) ?></span>
        </div>
        <button class="checkout-btn" onclick="window.location.href='checkout.php'">Proceed to Checkout</button>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
function updateQuantity(cartId, newQuantity, maxStock) {
  if (newQuantity < 1) {
    alert('Quantity cannot be less than 1. Use Remove button to delete item.');
    return;
  }

  if (newQuantity > maxStock) {
    alert('Cannot exceed stock available (' + maxStock + ' units)');
    return;
  }

  fetch('cart_handler.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      action: 'update',
      cart_id: cartId,
      quantity: newQuantity
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      alert('Failed to update quantity: ' + (data.message || 'Unknown error'));
    }
  })
  .catch(err => {
    console.error('Error:', err);
    alert('An error occurred. Please try again.');
  });
}

function removeItem(cartId) {
  if (!confirm('Are you sure you want to remove this item from cart?')) {
    return;
  }

  fetch('cart_handler.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      action: 'remove',
      cart_id: cartId
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      alert('Failed to remove item: ' + (data.message || 'Unknown error'));
    }
  })
  .catch(err => {
    console.error('Error:', err);
    alert('An error occurred. Please try again.');
  });
}
</script>

</body>
</html>