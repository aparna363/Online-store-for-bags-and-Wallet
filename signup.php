<?php
require_once("db.php");
require_once("User.php");

class SignupController {
    private $user;
    private $error;
    private $success;
    
    public function __construct() {
        $this->user = new User();
        $this->error = "";
        $this->success = "";
    }
    
    public function handleRequest() {
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->processSignup();
        }
        $this->renderView();
    }
    
    private function processSignup() {
        $name = $this->sanitizeInput($_POST['name'] ?? '');
        $email = $this->sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!$this->validatePasswords($password, $confirmPassword)) {
            return;
        }
        
        $result = $this->user->register($name, $email, $password);
        
        if ($result === true) {
            $this->success = "Registration successful! Please login.";
        } else {
            $this->error = $result;
        }
    }
    
    private function validatePasswords($password, $confirmPassword) {
        if ($password !== $confirmPassword) {
            $this->error = "Passwords do not match!";
            return false;
        }
        return true;
    }
    
    private function sanitizeInput($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
    
    public function getError() {
        return $this->error;
    }
    
    public function getSuccess() {
        return $this->success;
    }
    
    private function renderView() {
        $view = new SignupView($this->error, $this->success);
        $view->render();
    }
}

class SignupView {
    private $error;
    private $success;
    
    public function __construct($error = "", $success = "") {
        $this->error = $error;
        $this->success = $success;
    }
    
    public function render() {
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
    body {background-image:linear-gradient(135deg, #fffaf5 0%, #ffecd1 100%); display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px;}
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
    <h2> Join HappyPouch</h2>
    
    <?php if ($this->error): ?>
      <div class="error"><?= htmlspecialchars($this->error) ?></div>
    <?php endif; ?>
    
    <?php if ($this->success): ?>
      <div class="success"><?= htmlspecialchars($this->success) ?></div>
    <?php endif; ?>
    
    <form method="POST" id="signupForm" novalidate>
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" id="name" placeholder="Enter your name" required>
        <div class="validation-message" id="nameError"></div>
      </div>
      
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" id="email" placeholder="Enter your email" required>
        <div class="validation-message" id="emailError"></div>
      </div>
      
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" id="password" placeholder="Create a password" required>
        <div class="validation-message" id="passwordError"></div>
      </div>
      
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm your password" required>
        <div class="validation-message" id="confirmError"></div>
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

  <script>
    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');

    // Name validation
    nameInput.addEventListener('blur', function() {
      validateName();
    });

    nameInput.addEventListener('input', function() {
      if (this.classList.contains('invalid')) {
        validateName();
      }
    });

    function validateName() {
      const name = nameInput.value.trim();
      const nameError = document.getElementById('nameError');
      
      if (name === '') {
        showError(nameInput, nameError, 'Name is required');
      } else if (name.length < 2) {
        showError(nameInput, nameError, 'Name must be at least 2 characters');
      } else if (name.length > 50) {
        showError(nameInput, nameError, 'Name must be less than 50 characters');
      } else if (!/^[a-zA-Z\s'-]+$/.test(name)) {
        showError(nameInput, nameError, 'Name can only contain letters, spaces, hyphens, and apostrophes');
      } else if (/\s{2,}/.test(name)) {
        showError(nameInput, nameError, 'Name cannot contain multiple consecutive spaces');
      } else {
        clearError(nameInput, nameError);
      }
    }

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
      // Revalidate confirm password if it has value
      if (confirmPasswordInput.value && confirmPasswordInput.classList.contains('invalid')) {
        validateConfirmPassword();
      }
    });

    function validatePassword() {
      const password = passwordInput.value;
      const passwordError = document.getElementById('passwordError');
      
      if (password === '') {
        showError(passwordInput, passwordError, 'Password is required');
      } else if (password.length < 8) {
        showError(passwordInput, passwordError, 'Password must be at least 8 characters');
      } else if (!/[A-Z]/.test(password)) {
        showError(passwordInput, passwordError, 'Password must contain at least one uppercase letter');
      } else if (!/[a-z]/.test(password)) {
        showError(passwordInput, passwordError, 'Password must contain at least one lowercase letter');
      } else if (!/[0-9]/.test(password)) {
        showError(passwordInput, passwordError, 'Password must contain at least one number');
      } else if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
        showError(passwordInput, passwordError, 'Password must contain at least one special character (!@#$%^&*)');
      } else if (password.length > 128) {
        showError(passwordInput, passwordError, 'Password must be less than 128 characters');
      } else {
        clearError(passwordInput, passwordError);
      }
    }

    // Confirm password validation
    confirmPasswordInput.addEventListener('blur', function() {
      validateConfirmPassword();
    });

    confirmPasswordInput.addEventListener('input', function() {
      if (this.classList.contains('invalid')) {
        validateConfirmPassword();
      }
    });

    function validateConfirmPassword() {
      const confirmPassword = confirmPasswordInput.value;
      const password = passwordInput.value;
      const confirmError = document.getElementById('confirmError');

      if (confirmPassword === '') {
        showError(confirmPasswordInput, confirmError, 'Please confirm your password');
      } else if (confirmPassword !== password) {
        showError(confirmPasswordInput, confirmError, 'Passwords do not match');
      } else {
        clearError(confirmPasswordInput, confirmError);
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
    document.getElementById('signupForm').addEventListener('submit', function(e) {
      validateName();
      validateEmail();
      validatePassword();
      validateConfirmPassword();

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
$signupController = new SignupController();
$signupController->handleRequest();
?>