<?php
session_start();
require_once("db.php");
require_once("Product.php");
require_once("User.php");

$product = new Product();

// Get filter parameters
$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get products based on filter
if ($category === 'all') {
    $products = $product->getAllProducts();
} else {
    $products = $product->getProductsByCategory($category);
}

// Filter by search if provided
if ($search) {
    $products = array_filter($products, function($item) use ($search) {
        return stripos($item['title'], $search) !== false;
    });
}

$isLoggedIn = User::isLoggedIn();
$currentUser = User::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Products | HappyPouch</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    * {margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
    body {background:#fffaf5; color:#333;}
    
    /* Header */
    header {position:sticky; top:0; width:100%; background:rgba(255,255,255,0.98); backdrop-filter:blur(10px); display:flex; justify-content:space-between; align-items:center; padding:20px 60px; z-index:100; box-shadow:0 2px 15px rgba(0,0,0,0.1);}
    header h1 {font-weight:700; color:#f4a261; font-size:1.8rem; letter-spacing:1px; cursor:pointer;}
    nav {display:flex; align-items:center; gap:30px;}
    nav a {text-decoration:none; color:#333; font-weight:500; transition:0.3s; position:relative;}
    nav a:hover {color:#f4a261;}
    nav a::after {content:''; position:absolute; bottom:-5px; left:0; width:0; height:2px; background:#f4a261; transition:0.3s;}
    nav a:hover::after {width:100%;}
    .user-info {display:flex; align-items:center; gap:15px;}
    .user-name {color:#f4a261; font-weight:600;}
    .cart-icon {position:relative; font-size:1.5rem; color:#f4a261; cursor:pointer;}
    .cart-count {position:absolute; top:-8px; right:-8px; background:#e07b39; color:white; border-radius:50%; width:20px; height:20px; display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:bold;}
    
    /* Search & Filter Section */
    .filter-section {background:white; padding:30px 60px; box-shadow:0 2px 10px rgba(0,0,0,0.05);}
    .filter-container {display:flex; justify-content:space-between; align-items:center; gap:20px; flex-wrap:wrap;}
    .search-box {flex:1; min-width:300px;}
    .search-box input {width:100%; padding:12px 20px; border:2px solid #e0e0e0; border-radius:25px; font-size:1rem; transition:0.3s;}
    .search-box input:focus {outline:none; border-color:#f4a261;}
    .filter-buttons {display:flex; gap:10px;}
    .filter-btn {padding:10px 25px; border:2px solid #f4a261; background:white; color:#f4a261; border-radius:20px; cursor:pointer; font-weight:600; transition:0.3s;}
    .filter-btn:hover, .filter-btn.active {background:#f4a261; color:white;}
    
    /* Products Grid */
    .products-container {padding:60px; max-width:1400px; margin:0 auto;}
    .products-header {text-align:center; margin-bottom:40px;}
    .products-header h2 {font-size:2.5rem; color:#f4a261; margin-bottom:10px;}
    .products-header p {color:#666; font-size:1.1rem;}
    .products-grid {display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:30px;}
    
    /* Product Card */
    .product-card {background:white; border-radius:20px; overflow:hidden; box-shadow:0 5px 20px rgba(0,0,0,0.1); transition:0.3s; cursor:pointer;}
    .product-card:hover {transform:translateY(-10px); box-shadow:0 15px 35px rgba(244,162,97,0.3);}
    .product-image {width:100%; height:280px; object-fit:cover; position:relative;}
    .product-badge {position:absolute; top:15px; right:15px; background:#e07b39; color:white; padding:5px 15px; border-radius:20px; font-size:0.85rem; font-weight:600;}
    .product-info {padding:20px;}
    .product-category {color:#f4a261; font-size:0.85rem; font-weight:600; text-transform:uppercase; letter-spacing:1px;}
    .product-title {font-size:1.2rem; font-weight:600; margin:10px 0; color:#333;}
    .product-price {font-size:1.5rem; color:#e07b39; font-weight:700; margin:10px 0;}
    .product-stock {font-size:0.9rem; color:#666; margin-bottom:15px;}
    .product-stock.in-stock {color:#4caf50;}
    .product-stock.out-stock {color:#f44336;}
    .add-to-cart-btn {width:100%; padding:12px; background:#f4a261; color:white; border:none; border-radius:10px; font-weight:600; cursor:pointer; transition:0.3s; font-size:1rem;}
    .add-to-cart-btn:hover {background:#e07b39; transform:scale(1.02);}
    .add-to-cart-btn:disabled {background:#ccc; cursor:not-allowed; transform:none;}
    .add-to-cart-btn.adding {background:#999; cursor:wait;}
    
    /* Toast Notification */
    .toast {position:fixed; top:20px; right:20px; background:#4caf50; color:white; padding:15px 25px; border-radius:10px; box-shadow:0 5px 20px rgba(0,0,0,0.3); z-index:1000; opacity:0; transform:translateX(400px); transition:all 0.3s ease;}
    .toast.show {opacity:1; transform:translateX(0);}
    .toast.error {background:#f44336;}
    
    /* Empty State */
    .empty-state {text-align:center; padding:80px 20px; color:#999;}
    .empty-state h3 {font-size:2rem; margin-bottom:10px;}
    
    /* Footer */
    footer {background:#333; color:white; text-align:center; padding:30px; margin-top:60px;}
    
    /* Responsive */
    @media (max-width: 768px) {
      header, .filter-section, .products-container {padding:20px;}
      .filter-container {flex-direction:column;}
      .search-box {width:100%;}
      .products-grid {grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:20px;}
    }
  </style>
</head>
<body>

  <header>
    <h1 onclick="window.location.href='index.php'">HappyPouch</h1>
    <nav>
      <a href="index.php">Home</a>
      
      <?php if ($isLoggedIn): ?>
        <a href="cart.php">
          <span class="cart-icon">🛒 <span class="cart-count" id="cartCount">0</span></span>
        </a>
        <div class="user-info">
          <span class="user-name"><?= htmlspecialchars($currentUser['name']) ?></span>
          <a href="logout.php">Logout</a>
        </div>
      <?php else: ?>
        <a href="login.php">Login</a>
        <a href="signup.php">Sign Up</a>
      <?php endif; ?>
    </nav>
  </header>

  <div class="filter-section">
    <div class="filter-container">
      <div class="search-box">
        <form method="GET" action="products.php">
          <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
        </form>
      </div>
      <div class="filter-buttons">
        <button class="filter-btn <?= $category === 'all' ? 'active' : '' ?>" onclick="window.location.href='products.php?category=all'">All</button>
        <button class="filter-btn <?= $category === 'Bag' ? 'active' : '' ?>" onclick="window.location.href='products.php?category=Bag'">Bags</button>
        <button class="filter-btn <?= $category === 'Wallet' ? 'active' : '' ?>" onclick="window.location.href='products.php?category=Wallet'">Wallets</button>
      </div>
    </div>
  </div>

  <div class="products-container">
    <div class="products-header">
      <h2>Our Products</h2>
      <p>Discover our exclusive collection of stylish bags and wallets</p>
    </div>

    <?php if (empty($products)): ?>
      <div class="empty-state">
        <h3>No products found</h3>
        <p>Try adjusting your search or filter to find what you're looking for.</p>
      </div>
    <?php else: ?>
      <div class="products-grid">
        <?php foreach ($products as $item): ?>
          <div class="product-card">
            <div style="position:relative;">
              <img src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="product-image">
              <?php if ($item['quantity'] < 5 && $item['quantity'] > 0): ?>
                <div class="product-badge">Only <?= $item['quantity'] ?> left!</div>
              <?php endif; ?>
            </div>
            <div class="product-info">
              <div class="product-category"><?= htmlspecialchars($item['category']) ?></div>
              <h3 class="product-title"><?= htmlspecialchars($item['title']) ?></h3>
              <div class="product-price">₹<?= number_format($item['price'], 2) ?></div>
              <div class="product-stock <?= $item['quantity'] > 0 ? 'in-stock' : 'out-stock' ?>">
                <?= $item['quantity'] > 0 ? "In Stock ({$item['quantity']} available)" : "Out of Stock" ?>
              </div>
              <?php if ($isLoggedIn): ?>
                <?php if ($item['quantity'] > 0): ?>
                  <button class="add-to-cart-btn" data-product-id="<?= $item['id'] ?>" onclick="addToCart(<?= $item['id'] ?>, this)">Add to Cart</button>
                <?php else: ?>
                  <button class="add-to-cart-btn" disabled>Out of Stock</button>
                <?php endif; ?>
              <?php else: ?>
                <button class="add-to-cart-btn" onclick="window.location.href='login.php'">Login to Purchase</button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <footer>
    <p>© 2025 HappyPouch — Style That Smiles Back</p>
  </footer>

  <!-- Toast Notification -->
  <div class="toast" id="toast"></div>

  <script>
    // Show toast notification
    function showToast(message, isError = false) {
      const toast = document.getElementById('toast');
      toast.textContent = message;
      toast.className = 'toast' + (isError ? ' error' : '');
      toast.classList.add('show');
      
      setTimeout(() => {
        toast.classList.remove('show');
      }, 3000);
    }

    // Add to cart function
    function addToCart(productId, buttonElement) {
      console.log('Adding product to cart:', productId);
      
      // Disable button and show loading state
      buttonElement.disabled = true;
      buttonElement.classList.add('adding');
      const originalText = buttonElement.textContent;
      buttonElement.textContent = 'Adding...';

      fetch('cart_handler.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'add',
          product_id: productId
        })
      })
      .then(response => {
        console.log('Response status:', response.status);
        return response.json();
      })
      .then(data => {
        console.log('Response data:', data);
        
        // Re-enable button
        buttonElement.disabled = false;
        buttonElement.classList.remove('adding');
        buttonElement.textContent = originalText;
        
        if (data.success) {
          showToast('✓ Product added to cart!');
          updateCartCount();
        } else {
          showToast('✗ ' + (data.message || 'Failed to add product'), true);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        
        // Re-enable button
        buttonElement.disabled = false;
        buttonElement.classList.remove('adding');
        buttonElement.textContent = originalText;
        
        showToast('✗ An error occurred. Please try again.', true);
      });
    }

    // Update cart count
    function updateCartCount() {
      fetch('cart_handler.php?action=count')
        .then(response => response.json())
        .then(data => {
          console.log('Cart count:', data.count);
          const cartCountElement = document.getElementById('cartCount');
          if (cartCountElement) {
            cartCountElement.textContent = data.count || 0;
          }
        })
        .catch(error => {
          console.error('Error updating cart count:', error);
        });
    }

    // Initial cart count update on page load
    <?php if ($isLoggedIn): ?>
      updateCartCount();
    <?php endif; ?>
  </script>

</body>
</html>