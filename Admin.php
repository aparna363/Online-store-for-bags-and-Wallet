<?php
require_once("db.php");
require_once("Product.php");

$product = new Product();

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
    
    move_uploaded_file($_FILES['image']['tmp_name'], $target);

    $product->addProduct($title, $price, $image, $quantity, $category);
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
    h1 {text-align:center; color:#f4a261; margin-bottom:20px;}
    .form-container {background:#fff; padding:20px; border-radius:10px; max-width:600px; margin:0 auto 40px; box-shadow:0 4px 20px rgba(244,162,97,0.2);}
    form input, form select, form button {width:100%; padding:10px; margin:10px 0; border:1px solid #ccc; border-radius:5px;}
    button {background:#f4a261; color:white; font-weight:600; border:none; cursor:pointer;}
    button:hover {background:#e07b39;}
    table {width:100%; border-collapse:collapse; margin-top:30px;}
    th, td {border:1px solid #ddd; padding:10px; text-align:center;}
    th {background:#f4a261; color:#fff;}
    tr:nth-child(even){background:#fdf1e7;}
    img {width:80px; height:80px; object-fit:cover; border-radius:8px;}
    a.delete {color:red; text-decoration:none; font-weight:600;}
    a.delete:hover {text-decoration:underline;}
    .no-products {text-align:center; padding:40px; color:#999; font-style:italic;}
  </style>
</head>
<body>

  <h1>👜 HappyPouch Admin Dashboard</h1>

  <div class="form-container">
    <h3>Add New Product</h3>
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
      <th>Action</th>
    </tr>
    <?php foreach ($allProducts as $item): ?>
    <tr>
      <td><?= $item['id'] ?></td>
      <td><img src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>"></td>
      <td><?= htmlspecialchars($item['title']) ?></td>
      <td><?= htmlspecialchars($item['price']) ?></td>
      <td><?= htmlspecialchars($item['quantity']) ?></td>
      <td><?= htmlspecialchars($item['category'] ?? 'Bag') ?></td>
      <td><a class="delete" href="?delete=<?= $item['id'] ?>" onclick="return confirm('Delete this product?')">Delete</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

</body>
</html>