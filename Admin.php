<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: Admin_login.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin_login.php");
    exit();
}

require_once("db.php");
require_once("Product.php");

$product = new Product();

// Get product for editing
$editProduct = null;
if (isset($_GET['edit'])) {
    $editProduct = $product->getProductById($_GET['edit']);
}

// Handle Add Product
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_product'])) {
    $title = $_POST['title'];
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $category = $_POST['category'];

    // Handle file upload
    $image = $_FILES['image']['name'];
    $target = "uploads/" . basename($image);
    
    // Create uploads directory if it doesn't exist
    if (!file_exists('uploads')) {
        mkdir('uploads', 0777, true);
    }
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
        $product->addProduct($title, $price, $image, $quantity, $category);
    } else {
        $_SESSION['error_message'] = "Failed to upload image.";
    }
}

// Handle Update Product
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_product'])) {
    $id = $_POST['product_id'];
    $title = $_POST['title'];
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $category = $_POST['category'];
    
    $newImage = null;
    
    // Check if new image was uploaded
    if (!empty($_FILES['image']['name'])) {
        $newImage = $_FILES['image']['name'];
        $target = "uploads/" . basename($newImage);
        
        if (!file_exists('uploads')) {
            mkdir('uploads', 0777, true);
        }
        
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
    }
    
    $product->updateProduct($id, $title, $price, $quantity, $category, $newImage);
}

// Handle Delete Product
if (isset($_GET['delete'])) {
    $product->deleteProduct($_GET['delete']);
}

// Get all products with error handling
$allProducts = $product->getAllProducts();

// Initialize as empty array if null
if ($allProducts === null) {
    $allProducts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | HappyPouch</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    * {margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
    body {background:#fffaf5; color:#333; padding:30px;}
    .header {display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 20px rgba(244,162,97,0.2);}
    .header h1 {color:#f4a261; margin:0;}
    .admin-info {display:flex; align-items:center; gap:15px;}
    .admin-badge {background:#e74c3c; color:#fff; padding:5px 15px; border-radius:20px; font-size:0.85rem; font-weight:600;}
    .logout-btn {background:#e74c3c; color:#fff; padding:8px 20px; border:none; border-radius:8px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block;}
    .logout-btn:hover {background:#c0392b;}
    
    .message {
      padding: 15px 20px;
      margin: 20px auto;
      max-width: 600px;
      border-radius: 8px;
      font-weight: 500;
      text-align: center;
      animation: slideIn 0.3s ease;
    }
    .error-message {
      background: #ffe6e6;
      color: #c0392b;
      border: 2px solid #e74c3c;
    }
    .success-message {
      background: #e8f8f5;
      color: #27ae60;
      border: 2px solid #2ecc71;
    }
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .form-container {background:#fff; padding:20px; border-radius:10px; max-width:600px; margin:0 auto 40px; box-shadow:0 4px 20px rgba(244,162,97,0.2);}
    .form-container h3 {color:#f4a261; margin-bottom:15px;}
    form input, form select, form button {width:100%; padding:10px; margin:10px 0; border:1px solid #ccc; border-radius:5px;}
    button {background:#f4a261; color:white; font-weight:600; border:none; cursor:pointer;}
    button:hover {background:#e07b39;}
    .cancel-btn {background:#95a5a6; margin-top:5px;}
    .cancel-btn:hover {background:#7f8c8d;}
    
    .current-image {margin:10px 0; text-align:center;}
    .current-image img {width:100px; height:100px; object-fit:cover; border-radius:8px; border:2px solid #f4a261;}
    .current-image p {font-size:0.85rem; color:#666; margin-top:5px;}
    
    table {width:100%; border-collapse:collapse; margin-top:30px; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 4px 20px rgba(244,162,97,0.2);}
    th, td {border:1px solid #ddd; padding:10px; text-align:center;}
    th {background:#f4a261; color:#fff;}
    tr:nth-child(even){background:#fdf1e7;}
    img {width:80px; height:80px; object-fit:cover; border-radius:8px;}
    
    .action-links {display:flex; gap:10px; justify-content:center;}
    a.edit {color:#3498db; text-decoration:none; font-weight:600;}
    a.edit:hover {text-decoration:underline;}
    a.delete {color:#e74c3c; text-decoration:none; font-weight:600;}
    a.delete:hover {text-decoration:underline;}
    
    .no-products {text-align:center; padding:40px; color:#999; font-style:italic;}
  </style>
</head>
<body>

  <div class="header">
    <h1>👜 HappyPouch Admin Dashboard</h1>
    <div class="admin-info">
      <span class="admin-badge">🔐 Admin</span>
      <span style="color:#666;"><?= htmlspecialchars($_SESSION['admin_email']) ?></span>
      <a href="?logout=1" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
    </div>
  </div>

  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="message error-message">
      ❌ <?= htmlspecialchars($_SESSION['error_message']) ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="message success-message">
      ✅ <?= htmlspecialchars($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>

  <div class="form-container">
    <?php if ($editProduct): ?>
      <h3>✏️ Edit Product</h3>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">
        <input type="text" name="title" placeholder="Product Title" value="<?= htmlspecialchars($editProduct['title']) ?>" required>
        <input type="number" name="price" placeholder="Price (₹)" step="0.01" value="<?= htmlspecialchars($editProduct['price']) ?>" required>
        <input type="number" name="quantity" placeholder="Quantity" value="<?= htmlspecialchars($editProduct['quantity']) ?>" required>
        <select name="category" required>
          <option value="">Select Category</option>
          <option value="Bag" <?= $editProduct['category'] === 'Bag' ? 'selected' : '' ?>>Bag</option>
          <option value="Wallet" <?= $editProduct['category'] === 'Wallet' ? 'selected' : '' ?>>Wallet</option>
        </select>
        
        <div class="current-image">
          <p>Current Image:</p>
          <img src="uploads/<?= htmlspecialchars($editProduct['image']) ?>" alt="Current">
          <p style="margin-top:10px;">Upload new image (optional):</p>
        </div>
        
        <input type="file" name="image" accept="image/*">
        <button type="submit" name="update_product">Update Product</button>
        <a href="Admin.php"><button type="button" class="cancel-btn">Cancel</button></a>
      </form>
    <?php else: ?>
      <h3>➕ Add New Product</h3>
      <form method="POST" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="Product Title (e.g. Leather Bag)" required>
        <input type="number" name="price" placeholder="Price (₹)" step="0.01" required>
        <input type="number" name="quantity" placeholder="Quantity" required>
        <select name="category" required>
          <option value="">Select Category</option>
          <option value="Bag">Bag</option>
          <option value="Wallet">Wallet</option>
        </select>
        <input type="file" name="image" accept="image/*" required>
        <button type="submit" name="add_product">Add Product</button>
      </form>
    <?php endif; ?>
  </div>

  <h3 style="text-align:center; color:#f4a261;">All Products</h3>
  
  <?php if (empty($allProducts)): ?>
    <div class="no-products">No products found. Add your first product above!</div>
  <?php else: ?>
  <table>
    <tr>
      <th>ID</th>
      <th>Image</th>
      <th>Title</th>
      <th>Price (₹)</th>
      <th>Quantity</th>
      <th>Category</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($allProducts as $item): ?>
    <tr>
      <td><?= $item['id'] ?></td>
      <td><img src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>"></td>
      <td><?= htmlspecialchars($item['title']) ?></td>
      <td><?= htmlspecialchars($item['price']) ?></td>
      <td><?= htmlspecialchars($item['quantity']) ?></td>
      <td><?= htmlspecialchars($item['category'] ?? 'Bag') ?></td>
      <td>
        <div class="action-links">
          <a class="edit" href="?edit=<?= $item['id'] ?>">Edit</a>
          <a class="delete" href="?delete=<?= $item['id'] ?>" onclick="return confirm('Delete this product?')">Delete</a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

</body>
</html>