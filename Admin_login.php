<?php
session_start();

// Check if already logged in as admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
    exit();
}

$error = "";

// Admin credentials (you can change these)
define('ADMIN_EMAIL', 'admin@gmail.com');
define('ADMIN_PASSWORD', 'Admin@123'); // Change this to a secure password

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($email === ADMIN_EMAIL && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_login_time'] = time();
        header("Location: admin.php");
        exit();
    } else {
        $error = "Invalid admin credentials!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | HappyPouch</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    * {margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
    body {background:linear-gradient(135deg, #2c3e50 0%, #34495e 100%); display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px;}
    .container {background:#fff; padding:40px; border-radius:20px; box-shadow:0 10px 40px rgba(0,0,0,0.3); max-width:400px; width:100%;}
    h2 {text-align:center; color:#2c3e50; margin-bottom:10px; font-size:2rem;}
    .subtitle {text-align:center; color:#7f8c8d; margin-bottom:30px; font-size:0.9rem;}
    .admin-badge {background:#e74c3c; color:#fff; padding:5px 15px; border-radius:20px; display:inline-block; font-size:0.8rem; font-weight:600; margin-bottom:20px;}
    .form-group {margin-bottom:20px; position:relative;}
    label {display:block; margin-bottom:8px; color:#333; font-weight:500;}
    input {width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:10px; font-size:1rem; transition:0.3s;}
    input:focus {outline:none; border-color:#e74c3c;}
    .btn {width:100%; padding:12px; background:#e74c3c; color:#fff; border:none; border-radius:10px; font-size:1rem; font-weight:600; cursor:pointer; transition:0.3s; margin-top:10px;}
    .btn:hover {background:#c0392b; transform:translateY(-2px);}
    .error {background:#ffebee; color:#c62828; padding:10px; border-radius:8px; margin-bottom:20px; text-align:center;}
    .back-home {text-align:center; margin-top:15px;}
    .back-home a {color:#7f8c8d; text-decoration:none;}
    .back-home a:hover {text-decoration:underline; color:#2c3e50;}
    .info-box {background:#ecf0f1; padding:15px; border-radius:8px; margin-top:20px; font-size:0.85rem; color:#555;}
    .info-box strong {color:#2c3e50;}
  </style>
</head>
<body>

  <div class="container">
    <div style="text-align:center;">
      <span class="admin-badge">🔐 ADMIN ACCESS</span>
    </div>
    <h2>Admin Login</h2>
    <p class="subtitle">HappyPouch Dashboard</p>
    
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
      <div class="form-group">
        <label>Admin Email</label>
        <input type="email" name="email" placeholder="Enter admin email" required>
      </div>
      
      <div class="form-group">
        <label>Admin Password</label>
        <input type="password" name="password" placeholder="Enter admin password" required>
      </div>
      
      <button type="submit" class="btn">Login as Admin</button>
    </form>
    
    <div class="back-home">
      <a href="index.php">← Back to Store</a>
    </div>

    
  </div>

</body>
</html>