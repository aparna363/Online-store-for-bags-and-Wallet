<?php
require_once("db.php");
require_once("User.php");

class LoginController {
    private $user;
    private $error;
    
    // Admin credentials
    private $adminEmail = 'admin@gmail.com';
    private $adminPassword = 'Admin@123';
    
    public function __construct() {
        $this->user = new User();
        $this->error = "";
    }
    
    public function handleRequest() {
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->processLogin();
        }
        $this->renderView();
    }
    
    private function processLogin() {
        $email = $this->sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Check if it's admin login
        if ($email === $this->adminEmail && $password === $this->adminPassword) {
            session_start();
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_email'] = $email;
            $_SESSION['admin_login_time'] = time();
            $this->redirect("admin.php");
        }
        
        // Regular user login
        $result = $this->user->login($email, $password);
        
        if ($result === true) {
            $this->redirect("index.php");
        } else {
            $this->error = $result;
        }
    }
    
    private function sanitizeInput($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
    
    private function redirect($location) {
        header("Location: $location");
        exit();
    }
    
    public function getError() {
        return $this->error;
    }
    
    private function renderView() {
        $view = new LoginView($this->error);
        $view->render();
    }
}

class LoginView {
    private $error;
    
    public function __construct($error = "") {
        $this->error = $error;
    }
    
    public function render() {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | HappyPouch</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    * {margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
    body {background:linear-gradient(135deg, #fffaf5 0%, #ffecd1 100%); display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px;}
    .container {background:#fff; padding:40px; border-radius:20px; box-shadow:0 10px 40px rgba(244,162,97,0.3); max-width:400px; width:100%;}
    h2 {text-align:center; color:#f4a261; margin-bottom:30px; font-size:2rem;}
    .form-group {margin-bottom:20px; position:relative;}
    label {display:block; margin-bottom:8px; color:#333; font-weight:500;}
    input {width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:10px; font-size:1rem; transition:0.3s;}
    input:focus {outline:none; border-color:#f4a261;}
    input.valid {border-color:#4caf50;}
    input.invalid {border-color:#f44336;}
    .validation-message {font-size:0.85rem; margin-top:5px; min-height:20px;}
    .validation-message.error {color:#f44336;}
    .btn {width:100%; padding:12px; background:#f4a261; color:#fff; border:none; border-radius:10px; font-size:1rem; font-weight:600; cursor:pointer; transition:0.3s; margin-top:10px;}
    .btn:hover {background:#e07b39; transform:translateY(-2px);}
    .error {background:#ffebee; color:#c62828; padding:10px; border-radius:8px; margin-bottom:20px; text-align:center;}
    .link {text-align:center; margin-top:20px; color:#555;}
    .link a {color:#f4a261; text-decoration:none; font-weight:600;}
    .link a:hover {text-decoration:underline;}
    .back-home {text-align:center; margin-top:15px;}
    .back-home a {color:#f4a261; text-decoration:none;}
    .back-home a:hover {text-decoration:underline;}
    .admin-note {background:#fff3cd; border-left:4px solid #f4a261; padding:10px; margin-bottom:20px; font-size:0.85rem; color:#856404;}
  </style>
</head>
<body>

  <div class="container">
    <h2> Login to HappyPouch</h2>
    
   
    
    <?php if ($this->error): ?>
      <div class="error"><?= htmlspecialchars($this->error) ?></div>
    <?php endif; ?>
    
    <form method="POST" id="loginForm" novalidate>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" id="email" placeholder="Enter your email" required>
        <div class="validation-message" id="emailError"></div>
      </div>
      
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" id="password" placeholder="Enter your password" required>
        <div class="validation-message" id="passwordError"></div>
      </div>
      
      <button type="submit" class="btn">Login</button>
    </form>
    
    <div class="link">
      Don't have an account? <a href="signup.php">Sign Up</a>
    </div>
    
    <div class="back-home">
      <a href="index.php">← Back to Home</a>
    </div>
  </div>

  <script>
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    // Email validation
    emailInput.addEventListener('blur', function() {
      validateEmail();
    });

    emailInput.addEventListener('input', function() {
      if (this.classList.contains('invalid')) {
        validateEmail();
      }
    });

    function validateEmail() {
      const email = emailInput.value.trim();
      const emailError = document.getElementById('emailError');
      
      if (email === '') {
        showError(emailInput, emailError, 'Email is required');
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showError(emailInput, emailError, 'Please enter a valid email address');
      } else if (email.length > 100) {
        showError(emailInput, emailError, 'Email must be less than 100 characters');
      } else {
        clearError(emailInput, emailError);
      }
    }

    // Password validation
    passwordInput.addEventListener('blur', function() {
      validatePassword();
    });

    passwordInput.addEventListener('input', function() {
      if (this.classList.contains('invalid')) {
        validatePassword();
      }
    });

    function validatePassword() {
      const password = passwordInput.value;
      const passwordError = document.getElementById('passwordError');
      
      if (password === '') {
        showError(passwordInput, passwordError, 'Password is required');
      } else if (password.length < 8) {
        showError(passwordInput, passwordError, 'Password must be at least 8 characters');
      } else if (password.length > 128) {
        showError(passwordInput, passwordError, 'Password must be less than 128 characters');
      } else {
        clearError(passwordInput, passwordError);
      }
    }

    // Helper functions
    function showError(input, errorElement, message) {
      input.classList.add('invalid');
      input.classList.remove('valid');
      errorElement.textContent = message;
      errorElement.className = 'validation-message error';
    }

    function clearError(input, errorElement) {
      input.classList.remove('invalid');
      input.classList.add('valid');
      errorElement.textContent = '';
      errorElement.className = 'validation-message';
    }

    // Form submission validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      validateEmail();
      validatePassword();

      // Check if there are any invalid fields
      if (document.querySelectorAll('.invalid').length > 0) {
        e.preventDefault();
      }
    });
  </script>

</body>
</html>
        <?php
    }
}

// Initialize and run the application
$loginController = new LoginController();
$loginController->handleRequest();
?>