<?php
// Start session
session_start();

// Handle password reset success display
$show_reset_success = false;
$reset_user_name = '';
if (isset($_GET['reset_success']) && isset($_SESSION['password_reset_success'])) {
    $show_reset_success = true;
    $reset_user_name = $_SESSION['reset_user_name'] ?? '';
    // Clear the session variables after displaying
    unset($_SESSION['password_reset_success']);
    unset($_SESSION['reset_user_name']);
}

// Handle AJAX email validation request
if (isset($_POST['check_email']) && isset($_POST['email'])) {
    require_once 'config.php';
    
    $email = trim($_POST['email']);
    $response = array('exists' => false);
    
    if (!empty($email) && str_ends_with($email, '@usep.edu.ph')) {
        try {
            $stmt = $pdo->prepare("SELECT userID FROM User WHERE email = :email");
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $response['exists'] = true;
            }
        } catch (PDOException $e) {
            // Don't reveal database errors to client
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// If user is already logged in, redirect to welcome page
if (isset($_SESSION["user_id"])) {
    header("Location: welcome.php");
    exit();
}

// Database connection parameters
require_once 'config.php';

// Initialize variables
$email = "";
$password = "";
$remember_me = false;
$errors = [];
$email_error = "";
$password_error = "";

// Check for cookies to pre-fill login form
if (isset($_COOKIE['remember_email'])) {
    $email = $_COOKIE['remember_email'];
    $remember_me = true;
}
if (isset($_COOKIE['remember_password'])) {
    $password = $_COOKIE['remember_password'];
    $remember_me = true;
}

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Forgot Password logic
    if (isset($_POST['forgot_password']) && isset($_POST['forgot_email'])) {
        $forgot_email = trim($_POST['forgot_email']);
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $forgot_email_error = $forgot_password_error = $forgot_confirm_error = '';
        $forgot_valid = true;
        
        // Validate email
        if (empty($forgot_email)) {
            $forgot_email_error = "Email is required.";
            $forgot_valid = false;
        } elseif (!filter_var($forgot_email, FILTER_VALIDATE_EMAIL)) {
            $forgot_email_error = "Invalid email format.";
            $forgot_valid = false;
        } elseif (!str_ends_with($forgot_email, '@usep.edu.ph')) {
            $forgot_email_error = "Only @usep.edu.ph email addresses are allowed.";
            $forgot_valid = false;
        }
        
        // Enhanced password validation
        if (empty($new_password)) {
            $forgot_password_error = "New password is required.";
            $forgot_valid = false;
        } else {
            // Check minimum length
            if (strlen($new_password) < 8) {
                $forgot_password_error = "Password must be at least 8 characters long.";
                $forgot_valid = false;
            }
            // Check for at least one letter
            elseif (!preg_match('/[A-Za-z]/', $new_password)) {
                $forgot_password_error = "Password must contain at least one letter.";
                $forgot_valid = false;
            }
            // Check for at least one number
            elseif (!preg_match('/[0-9]/', $new_password)) {
                $forgot_password_error = "Password must contain at least one number.";
                $forgot_valid = false;
            }
        }
        
        // Validate confirm password
        if (empty($confirm_password)) {
            $forgot_confirm_error = "Please confirm your new password.";
            $forgot_valid = false;
        } elseif ($new_password !== $confirm_password) {
            $forgot_confirm_error = "Passwords do not match.";
            $forgot_valid = false;
        }
        
        if ($forgot_valid) {
    try {
        // Start transaction for data consistency
        $pdo->beginTransaction();
        
        // First, get the current password hash to compare later
        $currentStmt = $pdo->prepare("SELECT userID, firstName, lastName, email, password FROM User WHERE email = :email");
        $currentStmt->bindParam(":email", $forgot_email);
        $currentStmt->execute();
        
        if ($currentStmt->rowCount() > 0) {
            // Email exists in database - proceed with password reset
            $user = $currentStmt->fetch(PDO::FETCH_ASSOC);
            $oldPasswordHash = $user['password'];
            
            // Hash the new password securely
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 12]);
            
            // Ensure the new hash is different from the old one
            if ($hashed_password === $oldPasswordHash) {
                // This should never happen, but just in case, rehash
                $hashed_password = password_hash($new_password . time(), PASSWORD_DEFAULT, ['cost' => 12]);
            }
            
            // Update the password in the database with explicit WHERE clause
            $updateStmt = $pdo->prepare("UPDATE User SET password = :password WHERE userID = :userID AND email = :email");
            $updateStmt->bindParam(":password", $hashed_password, PDO::PARAM_STR);
            $updateStmt->bindParam(":userID", $user['userID'], PDO::PARAM_INT);
            $updateStmt->bindParam(":email", $forgot_email, PDO::PARAM_STR);
            
            $updateResult = $updateStmt->execute();
            $rowsAffected = $updateStmt->rowCount();
            
            if ($updateResult && $rowsAffected > 0) {
                // Verify the update by retrieving the new password hash
                $verifyStmt = $pdo->prepare("SELECT password FROM User WHERE userID = :userID AND email = :email");
                $verifyStmt->bindParam(":userID", $user['userID'], PDO::PARAM_INT);
                $verifyStmt->bindParam(":email", $forgot_email, PDO::PARAM_STR);
                $verifyStmt->execute();
                $updatedUser = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                
                // Triple verification:
                // 1. New hash is different from old hash
                // 2. New password verifies against new hash
                // 3. Old password does NOT verify against new hash (if different)
                if ($updatedUser && 
                    $updatedUser['password'] !== $oldPasswordHash && 
                    password_verify($new_password, $updatedUser['password'])) {
                    
                    // Log the password change in UserFacultyAuditLog
                    $actionType = 'update user';
                    $logStmt = $pdo->prepare("INSERT INTO UserFacultyAuditLog (modifiedBy, targetUserID, actionType, timestamp) VALUES (:userID, :userID, :actionType, NOW())");
                    $logStmt->bindParam(":userID", $user['userID'], PDO::PARAM_INT);
                    $logStmt->bindParam(":actionType", $actionType, PDO::PARAM_STR);
                    $logStmt->execute();
                    
                    // Force a database commit
                    $pdo->commit();
                    
                    // Additional verification - query the database one more time
                    $finalVerifyStmt = $pdo->prepare("SELECT password FROM User WHERE userID = :userID");
                    $finalVerifyStmt->bindParam(":userID", $user['userID'], PDO::PARAM_INT);
                    $finalVerifyStmt->execute();
                    $finalUser = $finalVerifyStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($finalUser && password_verify($new_password, $finalUser['password'])) {
    // Set session flag for one-time success message
    $_SESSION['password_reset_success'] = true;
    $_SESSION['reset_user_name'] = $user['firstName'] . " " . $user['lastName'];
    
    // Clear form data on success
    $forgot_email = '';
    $new_password = '';
    $confirm_password = '';
    
    // Log success for debugging
    error_log("Password successfully reset and verified for user ID: " . $user['userID'] . " (" . $user['email'] . ")");
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?reset_success=1");
    exit();
} else {
    $pdo->rollback();
    $forgot_error = "Final verification failed. Password may not have been updated properly.";
    error_log("Final verification failed for user ID: " . $user['userID']);
}
                } else {
                    $pdo->rollback();
                    $forgot_error = "Password verification failed after update. The database may not have been updated.";
                    error_log("Password verification failed for user ID: " . $user['userID'] . ". Old hash: " . substr($oldPasswordHash, 0, 20) . "... New hash: " . substr($updatedUser['password'] ?? 'NULL', 0, 20) . "...");
                }
            } else {
                $pdo->rollback();
                $forgot_error = "Database update failed. Rows affected: " . $rowsAffected . ". Please try again.";
                error_log("Database update failed for user ID: " . $user['userID'] . ". Rows affected: " . $rowsAffected);
            }
        } else {
            // Email doesn't exist in database
            $forgot_email_error = "Email address not found in our records.";
        }
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        $forgot_error = "Database error occurred. Please try again later.";
        // Log the actual error for debugging
        error_log("Password reset error for email " . $forgot_email . ": " . $e->getMessage());
    }
}
    } else {
        // Get form data
        $email = trim($_POST["email"]);
        $password = $_POST["password"];
        $remember_me = isset($_POST['remember_me']);
        
        // Basic validation
        if (empty($email)) {
            $email_error = "Email is required";
            $errors[] = $email_error;
        }
        if (empty($password)) {
            $password_error = "Password is required";
            $errors[] = $password_error;
        }
        if (empty($errors)) {
            try {
                // Prepare SQL statement to check user credentials
                $stmt = $pdo->prepare("SELECT userID, firstName, lastName, role, password FROM User WHERE email = :email");
                $stmt->bindParam(":email", $email);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Use password_verify to compare the hashed password
                    if (password_verify($password, $user["password"])) {
                        // Password is correct, set session variables
                        $_SESSION["user_id"] = $user["userID"];
                        $_SESSION["user_name"] = $user["firstName"] . " " . $user["lastName"];
                        $_SESSION["user_role"] = $user["role"];

                        // Set cookies (expires in 1 hour)
                        setcookie("user_id", $user["userID"], time() + 3600, "/");
                        setcookie("user_name", $user["firstName"] . " " . $user["lastName"], time() + 3600, "/");
                        setcookie("user_role", $user["role"], time() + 3600, "/");

                        // Remember Me cookies (email and password)
                        if ($remember_me) {
                            setcookie("remember_email", $email, time() + (86400 * 30), "/"); // 30 days
                            setcookie("remember_password", $password, time() + (86400 * 30), "/"); // 30 days
                        } else {
                            setcookie("remember_email", "", time() - 3600, "/");
                            setcookie("remember_password", "", time() - 3600, "/");
                        }

                        // Redirect to welcome page after successful login
                        header("Location: welcome.php");
                        exit();
                    } else {
                        // Wrong password
                        $password_error = "Invalid email or password";
                    }
                } else {
                    // Email not found
                    $email_error = "Invalid email or password";
                }
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Research Repository - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
    <style>
        /* Unique styles for login.php only */
        .container {
            min-height: 750px;
            max-width: 1100px;
        }
        form {
            max-width: 400px;
        }
        .alert {
            padding: 12px 20px;
            font-size: 15px;
            gap: 10px;
        }
        .alert-error {
            border: 1px solid #ffb3b3;
        }
        .alert-icon {
            font-size: 20px;
        }
        p.register-link {
            margin-top: 25px;
            color: #ccc;
            font-size: 14px;
        }
        p.register-link a {
            color: #FF6600;
            text-decoration: none;
            font-weight: bold;
        }
        p.register-link a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .container {
                min-height: unset;
            }
        }
        .field-error-message {
            font-size: 13px;
            margin-top: 4px;
        }
        /* Add this for proper checkbox alignment */
        .remember-me-row {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .remember-me-row label {
            white-space: nowrap;
        }

        /* Fix for forgot password modal fields */
        #forgotPasswordModal form label,
        #forgotPasswordModal form input {
            display: block;
            width: 100%;
            margin-bottom: 8px;
        }
        #forgotPasswordModal form label {
            color: #220044 !important;
            font-weight: 500;
            display: block;
            margin-bottom: 5px;
        }
        #forgotPasswordModal form label[for="forgot_email"],
        #forgotPasswordModal form label[for="new_password"], 
        #forgotPasswordModal form label[for="confirm_password"] {
            color: #220044 !important;
        }
        #forgotPasswordModal form input[type="email"] {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #aaa;
            font-size: 15px;
            margin-bottom: 10px;
            box-sizing: border-box;
            width: 100%;
        }
        #forgotPasswordModal form button {
            margin-top: 10px;
            background: #FF6600;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 0;
            font-size: 16px;
            cursor: pointer;
        }
        #forgotPasswordModal .field-error-message {
            color: #d00;
            margin-bottom: 6px;
        }
        #forgotPasswordModal .password-requirements {
            font-size: 12px;
            color: #220044;
            margin-bottom: 10px;
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #220044;
        }
        .email-validation-error {
            color: #d00;
            font-size: 13px;
            margin-top: 4px;
            display: none;
        }
        .success-message {
            margin-top: 15px;
            padding: 20px;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 3px solid #28a745;
            border-radius: 10px;
            color: #155724;
            font-weight: bold;
            text-align: center;
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
            animation: successPulse 3s ease-in-out;
            font-size: 14px;
            line-height: 1.5;
        }
        @keyframes successPulse {
            0% { transform: scale(0.9); opacity: 0.7; }
            25% { transform: scale(1.05); opacity: 1; }
            50% { transform: scale(1); opacity: 1; }
            75% { transform: scale(1.02); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-section">
            <div class="logo-branding">
                <img src="images/octopus-logo.png" alt="Logo" />
                <span>Repositups</span>
            </div>

            <h2>Login</h2>

            <!-- Remove the global error alert, or keep for DB errors only -->
            <?php if (!empty($errors) && isset($errors[0]) && strpos($errors[0], 'Database error') !== false): ?>
                <div class="alert alert-error">
                    <span class="alert-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <div>
                        <ul style="margin:0; padding-left: 18px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <label for="email">Email:</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                    class="<?php echo !empty($email_error) ? 'input-error' : ''; ?>"
                />
                <?php if (!empty($email_error)): ?>
                    <div class="field-error-message"><?php echo htmlspecialchars($email_error); ?></div>
                <?php endif; ?>

                <label for="password">Password:</label>
                <div style="position:relative;">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        value="<?php echo htmlspecialchars($password); ?>"
                        required
                        class="<?php echo !empty($password_error) ? 'input-error' : ''; ?>"
                    />
                    <span class="toggle-password" onclick="togglePasswordVisibility('password', this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#222;">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <?php if (!empty($password_error)): ?>
                    <div class="field-error-message"><?php echo htmlspecialchars($password_error); ?></div>
                <?php endif; ?>

                <div class="remember-me-row">
                    <input type="checkbox" id="remember_me" name="remember_me" <?php if ($remember_me) echo 'checked'; ?>>
                    <label for="remember_me" style="margin-bottom:0;">Remember Me</label>
                </div>

                <button type="submit">Login</button>
            </form>

            <div style="margin-top: 10px;">
                <a href="#" onclick="document.getElementById('forgotPasswordModal').style.display='block'; return false;" style="font-size: 14px; color: #FF6600; text-decoration: underline;">Forgot Password?</a>
            </div>            <!-- Forgot Password Modal -->
            <div id="forgotPasswordModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:1000; align-items:center; justify-content:center;">
                <div style="background:#fff; padding:30px 25px; border-radius:8px; max-width:350px; width:90vw; margin:auto; position:relative; box-shadow:0 4px 24px rgba(0,0,0,0.18);">
                    <span onclick="document.getElementById('forgotPasswordModal').style.display='none';" style="position:absolute; top:10px; right:15px; cursor:pointer; font-size:26px; color:#FF6600; font-weight:bold;">&times;</span>
                    <h3 style="margin-top:0; text-align:center; color:#220044; font-size:22px; font-weight:bold;">Create New Password</h3>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" autocomplete="off" <?php echo isset($password_reset_success) ? 'style="display:none;"' : ''; ?>>
                        <input type="hidden" name="forgot_password" value="1" />
                        <label for="forgot_email">Enter your email address:</label>
                        <input type="email" id="forgot_email" name="forgot_email" required style="width:100%; margin-bottom:10px;" placeholder="example@usep.edu.ph" pattern=".*@usep\.edu\.ph$" title="Please enter a valid @usep.edu.ph email address" value="<?php echo isset($forgot_email) ? htmlspecialchars($forgot_email) : ''; ?>" />
                        <div class="email-validation-error" id="email-validation-error"></div>
                        <div style="font-size:12px; color:#220044; margin-bottom:10px;">Please use your @usep.edu.ph email address only.</div>
                        <?php if (isset($forgot_email_error)) { echo '<div class="field-error-message">' . htmlspecialchars($forgot_email_error) . '</div>'; } ?>
                        
                        <label for="new_password">New Password:</label>
                        <div style="position:relative; width:100%; margin-bottom:10px;">
                            <input type="password" id="new_password" name="new_password" required 
                                   style="width:100%; padding:8px; padding-right:40px; border-radius:6px; border:1px solid #aaa; font-size:15px; box-sizing:border-box;" 
                                   pattern="^(?=.*[A-Za-z])(?=.*\d).{8,}$" 
                                   title="Password must be at least 8 characters, contain at least one letter and one number" />
                            <span class="toggle-password" onclick="togglePasswordVisibility('new_password', this)" 
                                  style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#222;">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <?php if (isset($forgot_password_error)) { echo '<div class="field-error-message">' . htmlspecialchars($forgot_password_error) . '</div>'; } ?>

                        <label for="confirm_password">Confirm New Password:</label>
                        <div style="position:relative; width:100%; margin-bottom:10px;">
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                   style="width:100%; padding:8px; padding-right:40px; border-radius:6px; border:1px solid #aaa; font-size:15px; box-sizing:border-box;" />
                            <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password', this)" 
                                  style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#222;">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <?php if (isset($forgot_confirm_error)) { echo '<div class="field-error-message">' . htmlspecialchars($forgot_confirm_error) . '</div>'; } ?>
                        
                        <div class="password-requirements">
                            <strong>Password Requirements:</strong><br>
                            • At least 8 characters long<br>
                            • Contains at least one letter (A-Z or a-z)<br>
                            • Contains at least one number (0-9)
                        </div>
                        
                        <button type="submit" style="width:100%;">Reset Password</button>
                    </form>
                    
                    <?php if ($show_reset_success): ?>
    <div id="successMessage" class="success-message">
         <strong>Password Successfully Reset!</strong> 
        <div style="text-align:center; margin-top:15px;">
            <button onclick="closeSuccessMessage()" 
                    style="background:#28a745; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">
                Continue to Login
            </button>
        </div>
    </div>
<?php endif; ?>
                    <?php if (isset($forgot_error)) { echo '<div style="margin-top:10px; color:red;">' . htmlspecialchars($forgot_error) . '</div>'; } ?>
                </div>
            </div>

            <p class="register-link">
                Don't have an account?
                <a href="register.php">Register here</a>
            </p>
        </div>

        <div class="image-section">
            <img src="images/school.png" alt="Login Illustration" />
        </div>
    </div>
</body>
<script>
    function togglePasswordVisibility(inputId, iconSpan) {
        const input = document.getElementById(inputId);
        const icon = iconSpan.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Real-time email validation for forgot password
    document.addEventListener('DOMContentLoaded', function() {
        const forgotEmailInput = document.getElementById('forgot_email');
        const emailErrorDiv = document.getElementById('email-validation-error');
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        // Email validation
        if (forgotEmailInput && emailErrorDiv) {
            forgotEmailInput.addEventListener('blur', function() {
                const email = this.value.trim();
                
                // Clear previous error
                emailErrorDiv.style.display = 'none';
                emailErrorDiv.textContent = '';
                
                if (email && email.includes('@')) {
                    // Check domain first
                    if (!email.endsWith('@usep.edu.ph')) {
                        emailErrorDiv.textContent = 'Only @usep.edu.ph email addresses are allowed.';
                        emailErrorDiv.style.display = 'block';
                        return;
                    }
                    
                    // Check if email exists in database
                    fetch('<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'check_email=1&email=' + encodeURIComponent(email)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.exists) {
                            emailErrorDiv.textContent = 'Email not found in our records.';
                            emailErrorDiv.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error checking email:', error);
                    });
                }
            });
        }

        // Real-time password validation
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const isValid = password.length >= 8 && 
                               /[A-Za-z]/.test(password) && 
                               /[0-9]/.test(password);
                
                if (password.length > 0) {
                    if (isValid) {
                        this.style.borderColor = '#28a745';
                        this.style.backgroundColor = '#f8fff9';
                    } else {
                        this.style.borderColor = '#dc3545';
                        this.style.backgroundColor = '#fff8f8';
                    }
                } else {
                    this.style.borderColor = '#aaa';
                    this.style.backgroundColor = '#fff';
                }
            });
        }

        // Real-time confirm password validation
        if (confirmPasswordInput && newPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                const password = newPasswordInput.value;
                const confirmPassword = this.value;
                
                if (confirmPassword.length > 0) {
                    if (password === confirmPassword) {
                        this.style.borderColor = '#28a745';
                        this.style.backgroundColor = '#f8fff9';
                    } else {
                        this.style.borderColor = '#dc3545';
                        this.style.backgroundColor = '#fff8f8';
                    }
                } else {
                    this.style.borderColor = '#aaa';
                    this.style.backgroundColor = '#fff';
                }
            });
        }

        // Prevent form submission if validation fails
        const forgotPasswordForm = document.querySelector('#forgotPasswordModal form');
        if (forgotPasswordForm) {
            forgotPasswordForm.addEventListener('submit', function(e) {
                const emailInput = document.getElementById('forgot_email');
                const passwordInput = document.getElementById('new_password');
                const confirmInput = document.getElementById('confirm_password');
                const errorDiv = document.getElementById('email-validation-error');
                
                // Check email validation
                if (errorDiv && errorDiv.style.display === 'block') {
                    e.preventDefault();
                    alert('Please fix the email error before submitting.');
                    return;
                }
                
                // Check password requirements
                const password = passwordInput.value;
                if (password.length < 8 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long and contain at least one letter and one number.');
                    return;
                }
                
                // Check password match
                if (password !== confirmInput.value) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return;
                }
            });
        }
    });

function closeSuccessMessage() {
    document.getElementById('successMessage').style.display = 'none';
    document.getElementById('forgotPasswordModal').style.display = 'none';
    // Clear the URL parameter
    if (window.location.search.includes('reset_success=1')) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

// Auto-show modal if success message needs to be displayed
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($show_reset_success): ?>
        document.getElementById('forgotPasswordModal').style.display = 'block';
    <?php endif; ?>
});
</script>
</html>
