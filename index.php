<?php
session_start();
require_once("db.php");
require_once("Product.php");
require_once("User.php");

$product = new Product();
$bags = $product->getProductsByCategory('Bag');
$wallets = $product->getProductsByCategory('Wallet');

// Check if user is logged in
$isLoggedIn = User::isLoggedIn();
$currentUser = User::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HappyPouch | Style That Smiles Back</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    * {margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
    body {background:#fffaf5;color:#333;overflow-x:hidden;}
    header {position:fixed;top:0;width:100%;background:rgba(255,255,255,0.95);backdrop-filter:blur(5px);display:flex;justify-content:space-between;align-items:center;padding:20px 60px;z-index:100;box-shadow:0 2px 10px rgba(0,0,0,0.05);}
    header h1 {font-weight:700;color:#f4a261;font-size:1.8rem;letter-spacing:1px;}
    nav {display:flex;align-items:center;gap:20px;}
    nav a {text-decoration:none;color:#333;margin:0 15px;font-weight:500;transition:0.3s;}
    nav a:hover {color:#f4a261;}
    .user-menu {display:flex;align-items:center;gap:15px;}
    .user-name {color:#f4a261;font-weight:600;}
    .auth-buttons {display:flex;gap:10px;}
    .auth-buttons a {padding:8px 20px;border-radius:20px;text-decoration:none;font-weight:600;transition:0.3s;}
    .login-btn {color:#f4a261;border:2px solid #f4a261;}
    .login-btn:hover {background:#f4a261;color:#fff;}
    .signup-btn {background:#f4a261;color:#fff;}
    .signup-btn:hover {background:#e07b39;}
    .hero {height:90vh;background:url('https://www.avendus.com/crypted_storage_img/avendus-eye-article-chirag-shah-banner-img-685d549f86ef0-.png') center/cover no-repeat;display:flex;align-items:center;justify-content:center;text-align:center;position:relative;}
    .hero-content {position:relative;z-index:1;max-width:700px;}
    .hero-content h2 {font-size:3rem;margin-bottom:20px;color:#f4a261;}
    .hero-content p {font-size:1.2rem;margin-bottom:30px;color:white;}
    .btn {background:#f4a261;color:#fff;padding:12px 35px;font-weight:600;border:none;border-radius:25px;cursor:pointer;transition:0.3s;text-decoration:none;display:inline-block;}
    .btn:hover {background:#e07b39;transform:translateY(-2px);box-shadow:0 5px 15px rgba(244,162,97,0.3);}
    .section {padding:100px 50px;text-align:center;}
    .section h3 {font-size:2rem;color:#f4a261;margin-bottom:50px;}
    .products {display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:30px;}
    .product {background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 8px 20px rgba(244,162,97,0.2);transform:translateY(20px);opacity:0;transition:0.5s;position:relative;}
    .product img {width:100%;height:300px;object-fit:cover;cursor:pointer;}
    .product h4 {margin:15px 0 5px;font-weight:600;color:#f4a261;}
    .product p {color:#555;margin-bottom:15px;}
    .product:hover {transform:translateY(-10px);box-shadow:0 15px 25px rgba(244,162,97,0.3);}
    .product-actions {padding:0 15px 20px;}
    .out-of-stock {color:#e74c3c;font-weight:600;padding:10px;font-size:0.9rem;}
    footer {background:#fffaf5;color:#555;text-align:center;padding:30px;font-size:0.9rem;border-top:1px solid #f4a261;}
    .empty-section {padding:60px 20px;text-align:center;color:#999;font-style:italic;}
    
    @media (max-width: 768px) {
      header {padding:15px 20px;flex-direction:column;gap:15px;}
      .hero-content h2 {font-size:2rem;}
      .section {padding:60px 20px;}
      nav {flex-wrap:wrap;justify-content:center;}
    }
    /* Dropdown Menu */
.dropdown {
  position: relative;
  display: inline-block;
}

.dropbtn {
  background: none;
  border: none;
  color: #f4a261;
  font-weight: 600;
  cursor: pointer;
  font-size: 1rem;
}

.dropdown-content {
  display: none;
  position: absolute;
  right: 0;
  background-color: white;
  min-width: 150px;
  box-shadow: 0 8px 16px rgba(0,0,0,0.1);
  border-radius: 10px;
  z-index: 1;
}

.dropdown-content a {
  color: #333;
  padding: 10px 15px;
  text-decoration: none;
  display: block;
  transition: 0.2s;
}

.dropdown-content a:hover {
  background-color: #f4a261;
  color: white;
  border-radius: 10px;
}

.dropdown:hover .dropdown-content {
  display: block;
}

  </style>
</head>
<body>

  <header>
    <h1>HappyPouch</h1>
    <nav>
      <a href="#bags">Bags</a>
      <a href="#wallets">Wallets</a>
      <a href="cart.php">Cart</a>
      
      <?php if ($isLoggedIn): ?>
        <div class="user-menu">
    <div class="dropdown">
      <button class="dropbtn">Hi, <?= htmlspecialchars($currentUser['name']) ?> ▼</button>
      <div class="dropdown-content">
        <a href="my_orders.php">My Orders</a>
        <a href="logout.php">Logout</a>
      </div>
    </div>
  </div>
      <?php else: ?>
        <div class="auth-buttons">
          <a href="login.php" class="login-btn">Login</a>
          <a href="signup.php" class="signup-btn">Sign Up</a>
        </div>
      <?php endif; ?>
    </nav>
  </header>

  <section class="hero">
    <div class="hero-content">
      <h2>Style That Smiles Back</h2>
      <p>Brighten your day with our exclusive collection of cheerful and stylish bags and wallets.</p>
      <button class="btn" onclick="window.location.href='Products.php'">Shop Now</button>
    </div>
  </section>

  <section class="section" id="bags">
    <h3>Our Bags</h3>
    <?php if (empty($bags)): ?>
      <div class="empty-section">No bags available at the moment. Check back soon!</div>
    <?php else: ?>
    <div class="products">
      <?php foreach ($bags as $bag): ?>
        <div class="product" onclick="window.location.href='Products.php?product=<?php echo $bag['id']; ?>'">
          <img src="uploads/<?php echo htmlspecialchars($bag['image']); ?>" alt="<?php echo htmlspecialchars($bag['title']); ?>">
          <h4><?php echo htmlspecialchars($bag['title']); ?></h4>
          <p>₹<?php echo number_format($bag['price'], 2); ?></p>
          <div class="product-actions" onclick="event.stopPropagation();">
            <?php if ($bag['quantity'] > 0): ?>
              <a href="Products.php?product=<?php echo $bag['id']; ?>" class="btn">View Details</a>
            <?php else: ?>
              <p class="out-of-stock">Out of Stock</p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section class="section" id="wallets">
    <h3>Our Wallets</h3>
    <?php if (empty($wallets)): ?>
      <div class="empty-section">No wallets available at the moment. Check back soon!</div>
    <?php else: ?>
    <div class="products">
      <?php foreach ($wallets as $wallet): ?>
        <div class="product" onclick="window.location.href='Products.php?product=<?php echo $wallet['id']; ?>'">
          <img src="uploads/<?php echo htmlspecialchars($wallet['image']); ?>" alt="<?php echo htmlspecialchars($wallet['title']); ?>">
          <h4><?php echo htmlspecialchars($wallet['title']); ?></h4>
          <p>₹<?php echo number_format($wallet['price'], 2); ?></p>
          <div class="product-actions" onclick="event.stopPropagation();">
            <?php if ($wallet['quantity'] > 0): ?>
              <a href="Products.php?product=<?php echo $wallet['id']; ?>" class="btn">View Details</a>
            <?php else: ?>
              <p class="out-of-stock">Out of Stock</p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <footer>
    © 2025 HappyPouch — Style That Smiles Back.
  </footer>

  <script>
    const products = document.querySelectorAll('.product');
    window.addEventListener('scroll', () => {
      const triggerBottom = window.innerHeight * 0.8;
      products.forEach(product => {
        const top = product.getBoundingClientRect().top;
        if(top < triggerBottom){
          product.style.opacity = 1;
          product.style.transform = 'translateY(0)';
        }
      });
    });
  </script>

</body>
</html>