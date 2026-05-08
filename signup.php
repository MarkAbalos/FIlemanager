<?php
session_start();
include 'db.php';

// Check if user limit is reached (max 3 users)
$userCount = $conn->query("SELECT COUNT(*) as total FROM user")->fetch_assoc()['total'];
$maxUsers = 3;
$isLimitReached = $userCount >= $maxUsers;

if (isset($_POST['signup'])) {
    // Check user limit before processing signup
    if ($isLimitReached) {
        $error = "Registration is currently closed. Maximum user limit reached (3/3).";
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $confirmPassword = trim($_POST['confirm_password']);

        if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
            $error = "All fields are required!";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match!";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long!";
        } else {
            $check = $conn->prepare("SELECT * FROM user WHERE Email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = "Email already exists!";
            } else {
                $hashedPassword = hashPassword($password);
                $stmt = $conn->prepare("INSERT INTO user (Username, Email, Password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $email, $hashedPassword);

                if ($stmt->execute()) {
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    $_SESSION['user_id'] = $conn->insert_id;
                    header("Location: home_page.php");
                    exit();
                } else {
                    $error = "Error creating account. Please try again.";
                }
                $stmt->close();
            }
            $check->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - CIT File Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .signup-container {
            background: white;
            padding: 3rem;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 500px;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-container img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1rem;
            border: 4px solid #667eea;
        }

        h2 {
            text-align: center;
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            text-align: center;
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 2rem;
        }

        .user-limit-badge {
            text-align: center;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .user-limit-badge.available {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .user-limit-badge.full {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            color: #333;
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 2.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px white inset;
            -webkit-text-fill-color: #333;
            transition: background-color 5000s ease-in-out 0s;
        }

        .password-toggle-btn {
            position: absolute;
            right: 50px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0.3rem;
        }

        .password-toggle-btn:hover {
            color: #667eea;
        }

        .password-strength {
            font-size: 0.85rem;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        .password-strength.weak { color: #dc3545; }
        .password-strength.medium { color: #ffc107; }
        .password-strength.strong { color: #28a745; }

        button[type="submit"] {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
        }

        button[type="submit"]:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        button[type="submit"]:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #666;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .signup-container {
                padding: 2rem 1.5rem;
            }

            h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
<div class="signup-container">
    <div class="logo-container">
        <img src="design/logo.jpg" alt="CIT Logo" onerror="this.style.display='none'">
        <h2>Create Account</h2>
        <p class="subtitle">Join CIT File Management System</p>
    </div>

    <!-- User Limit Badge -->
    <div class="user-limit-badge <?php echo $isLimitReached ? 'full' : 'available'; ?>">
        <i class="fas <?php echo $isLimitReached ? 'fa-times-circle' : 'fa-check-circle'; ?>"></i>
        <span>Users: <?php echo $userCount; ?>/<?php echo $maxUsers; ?> 
            <?php echo $isLimitReached ? '(Registration Closed)' : '(Slots Available)'; ?>
        </span>
    </div>

    <?php if ($isLimitReached && !isset($error)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Registration is currently unavailable. Maximum user limit has been reached.</span>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="" onsubmit="return validateForm()" autocomplete="off">
        <div class="form-group">
            <label for="username">Full Name</label>
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" id="username" name="username" placeholder="Enter your full name" 
                       <?php echo $isLimitReached ? 'disabled' : 'required'; ?> autocomplete="off">
            </div>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" name="email" placeholder="Enter your email" 
                       <?php echo $isLimitReached ? 'disabled' : 'required'; ?> autocomplete="off">
            </div>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" id="password" name="password" placeholder="Create a password (min 6 characters)" 
                       <?php echo $isLimitReached ? 'disabled' : 'required'; ?> autocomplete="new-password" 
                       onkeyup="checkPasswordStrength()">
                <button type="button" class="password-toggle-btn" onclick="togglePassword('password', 'toggleIcon1')" 
                        <?php echo $isLimitReached ? 'disabled' : ''; ?>>
                    <i class="fas fa-eye" id="toggleIcon1"></i>
                </button>
            </div>
            <div id="password-strength" class="password-strength"></div>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" 
                       <?php echo $isLimitReached ? 'disabled' : 'required'; ?> autocomplete="new-password">
                <button type="button" class="password-toggle-btn" onclick="togglePassword('confirm_password', 'toggleIcon2')" 
                        <?php echo $isLimitReached ? 'disabled' : ''; ?>>
                    <i class="fas fa-eye" id="toggleIcon2"></i>
                </button>
            </div>
        </div>

        <button type="submit" name="signup" <?php echo $isLimitReached ? 'disabled' : ''; ?>>
            <i class="fas fa-user-plus"></i> 
            <?php echo $isLimitReached ? 'Registration Closed' : 'Create Account'; ?>
        </button>
    </form>

    <p class="login-link">
        Already have an account? <a href="login.php">Log in here</a>
    </p>
</div>

<script>
window.addEventListener('load', function() {
    document.getElementById('username').value = '';
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    document.getElementById('confirm_password').value = '';
});

function togglePassword(fieldId, iconId) {
    const passwordField = document.getElementById(fieldId);
    const toggleIcon = document.getElementById(iconId);
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordField.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

function checkPasswordStrength() {
    const password = document.getElementById('password').value;
    const strengthDiv = document.getElementById('password-strength');
    
    if (password.length === 0) {
        strengthDiv.textContent = '';
        return;
    }
    
    if (password.length < 6) {
        strengthDiv.textContent = 'Weak password - needs at least 6 characters';
        strengthDiv.className = 'password-strength weak';
    } else if (password.length < 10) {
        strengthDiv.textContent = 'Medium password strength';
        strengthDiv.className = 'password-strength medium';
    } else {
        strengthDiv.textContent = 'Strong password';
        strengthDiv.className = 'password-strength strong';
    }
}

function validateForm() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        alert('Passwords do not match!');
        return false;
    }
    
    if (password.length < 6) {
        alert('Password must be at least 6 characters long!');
        return false;
    }
    
    return true;
}
</script>
</body>
</html>