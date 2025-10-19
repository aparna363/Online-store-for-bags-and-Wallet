<?php
require_once("db.php");
require_once("User.php");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        $user = new User();
        $result = $user->register($name, $email, $password);
        
        if ($result === true) {
            $success = "Registration successful! Please login.";
        } else {
            $error = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up | HappyPouch</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    * {margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
    body {background:linear-gradient(135deg, #fffaf5 0%, #ffecd1 100%); display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px;}
    .container {background:#fff; padding:40px; border-radius:20px; box-shadow:0 10px 40px rgba(244,162,97,0.3); max-width:400px; width:100%;}
    h2 {text-align:center; color:#f4a261; margin-bottom:30px; font-size:2rem;}
    .form-group {margin-bottom:20px;}
    label {display:block; margin-bottom:8px; color:#333; font-weight:500;}
    input {width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:10px; font-size:1rem; transition:0.3s;}
    input:focus {outline:none; border-color:#f4a261;}
    .btn {width:100%; padding:12px; background:#f4a261; color:#fff; border:none; border-radius:10px; font-size:1rem; font-weight:600; cursor:pointer; transition:0.3s; margin-top:10px;}
    .btn:hover {background:#e07b39; transform:translateY(-2px);}
    .error {background:#ffebee; color:#c62828; padding:10px; border-radius:8px; margin-bottom:20px; text-align:center;}
    .success {background:#e8f5e9; color:#2e7d32; padding:10px; border-radius:8px; margin-bottom:20px; text-align:center;}
    .link {text-align:center; margin-top:20px; color:#555;}
    .link a {color:#f4a261; text-decoration:none; font-weight:600;}
    .link a:hover {text-decoration:underline;}
    .back-home {text-align:center; margin-top:15px;}
    .back-home a {color:#f4a261; text-decoration:none;}
    .back-home a:hover {text-decoration:underline;}
  </style>
</head>
<body>

  <div class="container">
    <h2>👜 Join HappyPouch</h2>
    
    <?php if ($error): ?><div class="error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div class="success"><?= $success ?></div>
    <?php endif; ?>
    
    <form method="POST">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" placeholder="Enter your name" required>
      </div>
      
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="Enter your email" required>
      </div>
      
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Create a password" required>
      </div>
      
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Confirm your password" required>
      </div>
      
      <button type="submit" class="btn">Sign Up</button>
    </form>
    
    <div class="link">
      Already have an account? <a href="login.php">Login</a>
    </div>
    
    <div class="back-home">
      <a href="index.php">← Back to Home</a>
    </div>
  </div>

</body>
</html>