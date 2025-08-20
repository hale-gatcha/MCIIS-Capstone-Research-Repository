<?php
require_once 'config.php';
require_once 'session_check.php';

// Check if user is logged in and is an Administrator
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Administrator') {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: "Inter", sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .error-container {
                max-width: 500px;
                margin: 40px auto;
                text-align: center;
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                border-top: 4px solid #220044;
            }
            .error-icon {
                font-size: 48px;
                color: #220044;
                margin-bottom: 20px;
            }
            h1 {
                color: #220044;
                margin: 0 0 15px 0;
                font-size: 24px;
            }
            p {
                color: #666;
                margin: 0 0 20px 0;
                font-size: 16px;
            }
            .back-link {
                display: inline-block;
                padding: 10px 20px;
                background-color: #FF6600;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                transition: background-color 0.3s;
            }
            .back-link:hover {
                background-color: #e65c00;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1>Access Denied</h1>
            <p>Administrator only.</p>
            <a href="welcome.php" class="back-link">Return to Homepage</a>
        </div>
    </body>
    </html>';
    exit();
}

// Initialize variables
$studentID = "";
$facultyID = "";
$staffID = "";
$firstName = "";
$middleName = "";
$lastName = "";
$contactNumber = "";
$email = "";
$role = "student"; // Default role
$errorMessage = "";
$successMessage = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $studentID = $_POST['studentID'];
    $facultyID = $_POST['facultyID'] ?? "";
    $staffID = $_POST['staffID'] ?? "";
    $firstName = $_POST['firstName'];
    $middleName = $_POST['middleName'];
    $lastName = $_POST['lastName'];
    $contactNumber = $_POST['contactNumber'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = $_POST['password']; // You should hash this password
    
    // Validate data
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $errorMessage = "First name, last name, email, and password are required";
    } else {
        // Password validation
        $passwordErrors = [];
        
        if (strlen($password) < 8) {
            $passwordErrors[] = "Password must be at least 8 characters";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $passwordErrors[] = "Password must contain at least one uppercase letter";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $passwordErrors[] = "Password must contain at least one lowercase letter";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $passwordErrors[] = "Password must contain at least one number";
        }
        if (!preg_match('/[\W_]/', $password)) {
            $passwordErrors[] = "Password must contain at least one special character";
        }

        if (!empty($passwordErrors)) {
            $errorMessage = "Password requirements not met:<br>" . implode("<br>", $passwordErrors);
        } else {
            // Contact number validation
            if (!empty($contactNumber)) {
                if (!preg_match('/^\d{11}$/', $contactNumber)) {
                    $errorMessage = "Contact number must be exactly 11 digits";
                }
            }

            // Email validation for USeP domain
            if (!preg_match('/@usep\.edu\.ph$/i', $email)) {
                $errorMessage = "Please use a valid USeP email address (@usep.edu.ph)";
            } else {
                // Faculty email validation
                if ($role === 'Faculty') {
                    // Check if email exists in Faculty table
                    $checkFacultySQL = "SELECT email FROM faculty WHERE email = ?";
                    $stmt = $conn->prepare($checkFacultySQL);
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        $errorMessage = "Faculty email not found in Faculty table";
                    }
                    $stmt->close();
                }

                if (empty($errorMessage)) {
                    // Hash the password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Prepare base SQL without ID fields
                    $sql = "INSERT INTO user (firstName, middleName, lastName, contactNumber, email, role, password) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    
                    // For Student only, update SQL to include student ID field
                    if ($role === 'Student') {
                        $sql = "INSERT INTO user (studentID, firstName, middleName, lastName, contactNumber, email, role, password) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    }

                    $stmt = $conn->prepare($sql);
                    
                    // Bind parameters based on role
                    if ($role === 'Student') {
                        $stmt->bind_param("ssssssss", 
                            $studentID,
                            $firstName, 
                            $middleName, 
                            $lastName, 
                            $contactNumber, 
                            $email, 
                            $role,
                            $hashedPassword
                        );
                    } else {
                        // For Faculty, Staff and Administrator
                        $stmt->bind_param("sssssss", 
                            $firstName, 
                            $middleName, 
                            $lastName, 
                            $contactNumber, 
                            $email, 
                            $role,
                            $hashedPassword
                        );
                    }
                    
                    if ($stmt->execute()) {
                        $successMessage = "User added successfully";
                        // Clear form fields after successful submission
                        $studentID = "";
                        $facultyID = "";
                        $firstName = "";
                        $middleName = "";
                        $lastName = "";
                        $contactNumber = "";
                        $email = "";
                        $role = "Student";
                        
                        // Redirect to list_users.php after successful user addition
                        header("Location: list_users.php");
                        exit();
                    } else {
                        $errorMessage = "Error adding user: " . $conn->error;
                    }
                    
                    $stmt->close();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Navigation and Header Styles */
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            line-height: 1.6;
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            width: 100%;
            padding: 0.5rem 0;
            background-color: rgb(35, 1, 68);
            margin: 0;
        }

        .navbar-brand {
            font-weight: bold;
            letter-spacing: 1px;
            font-size: clamp(1.1rem, 2vw, 2rem);
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .navbar-logo {
            height: clamp(32px, 6vw, 80px);
            width: auto;
        }

        .home-btn {
            font-size: clamp(1.5rem, 2.5vw, 2.5rem);
            padding: clamp(0.8rem, 1.5vw, 1.5rem);
            transition: background-color 0.3s ease, color 0.3s ease;
            background: transparent;
            color: inherit;
        }

        .home-btn i {
            display: block;
            line-height: 1;
            color: white;
            transition: color 0.3s ease;
        }

        .home-btn:hover {
            background-color: transparent !important;
        }

        .home-btn:hover i {
            color: #FF6600 !important;
        }

        .container-fluid {
            padding: 0 1rem;
        }

        /* Main content wrapper */
        .container-fluid.flex-grow-1 {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        .header-content {
            text-align: center;
            margin-bottom: 30px;
        }

        h1 {
            color: #220044;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
            font-size: clamp(1.5rem, 2.5vw, 3rem);
        }

        .back-button-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(34, 0, 68, 0.1);
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #220044;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #FF6600;
            box-shadow: 0 0 0 2px rgba(255, 102, 0, 0.1);
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            text-decoration: none;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s ease;
            font-family: 'Inter', sans-serif;
            min-width: 120px;  /* Fixed width for both buttons */
            text-align: center;
        }

        .btn-primary {
            background-color: #220044;
            color: white;
        }

        .btn-primary:hover,
        .btn-primary:active {
            background-color: #150029 !important; /* Darker shade of #220044 */
        }

        .btn-secondary {
            background-color: #FF6600;
        }

        .btn-secondary:hover {
            background-color: #FF884D;
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: left;  /* Center the buttons */
            align-items: center;
            margin-top: 20px;
        }

        .button-group .btn {
            flex: 0 0 120px;  /* Fixed width for buttons */
            padding: 10px 20px;
            font-weight: 500;
            text-align: center;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .btn-primary {
            background-color: #220044;
            color: white;
        }

        .btn-primary:hover {
            background-color: #150029; /* Darker shade of #220044 */
        }

        .btn-secondary {
            background-color: #FF6600;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #FF884D;
        }

        .error-message {
            color: #FF6600;
            margin-bottom: 15px;
            padding: 10px;
            background-color: rgba(255, 102, 0, 0.1);
            border-radius: 4px;
        }

        .success-message {
            color: #28a745;
            margin-bottom: 15px;
            padding: 10px;
            background-color: rgba(40, 167, 69, 0.1);
            border-radius: 4px;
        }

        form {
            max-width: 600px;
            margin: 0 auto;
        }

        .form-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(34, 0, 68, 0.1);
            border-left: 4px solid #220044;
        }

        .password-requirements-list {
            list-style: none;
            padding-left: 0;
            margin-top: 10px;
            font-size: 0.9em;
        }

        .password-requirements-list li {
            margin-bottom: 5px;
            padding-left: 20px;
            position: relative;
        }

        .password-requirements-list li:before {
            content: '×';
            position: absolute;
            left: 0;
            color: #FF6600;
        }

        .password-requirements-list li.met:before {
            content: '✓';
            color: #28a745;
        }

        .note {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-toggle {
            color: #666;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #220044;
        }

        .password-container {
            position: relative;
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            z-index: 10;
            padding: 5px;
        }

        .password-toggle:hover {
            color: #220044;
        }

        input[type="password"] {
            padding-right: 35px !important;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(35, 1, 68);">
            <div class="container-fluid position-relative justify-content-center">
                <!-- Home button (top left) -->
                <a href="welcome.php" class="btn btn-outline-light me-2 show-hamburger border-0 home-btn" style="position:absolute;left:0;top:50%;transform:translateY(-50%);z-index:1100;padding-left:1px;background:transparent;">
                    <i class="fas fa-home"></i>
                </a>
                <!-- Centered brand -->
                <a class="navbar-brand mx-auto d-flex align-items-center justify-content-center" href="#">
                    <img src="images/octopus-logo.png" alt="Logo" class="navbar-logo me-2">
                    <span>Repositups</span>
                </a>
            </div>
        </nav>
    </header>

    <div class="container-fluid flex-grow-1">
        <div class="form-container">
            <div class="header-content">
                <h1>Add New User</h1>
                <div class="back-button-container">
                    <a href="list_users.php" class="btn btn-secondary">Back to User List</a>
                </div>
            </div>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="error-message"><?php echo $errorMessage; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($successMessage)): ?>
                <div class="success-message"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            
            <form method="post" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="role"><i class="fas fa-user-tag"></i> Role:</label>
                    <select id="role" name="role" onchange="toggleIDFields()" required>
                        <option value="Student" <?php if($role == 'Student') echo 'selected'; ?>>Student</option>
                        <option value="Faculty" <?php if($role == 'Faculty') echo 'selected'; ?>>Faculty</option>
                        <option value="MCIIS Staff" <?php if($role == 'MCIIS Staff') echo 'selected'; ?>>MCIIS Staff</option>
                        <option value="Administrator" <?php if($role == 'Administrator') echo 'selected'; ?>>Administrator</option>
                    </select>
                </div>

                <div class="form-group" id="studentIDField" style="display: none;">
                    <label for="studentID"><i class="fas fa-id-card"></i> Student ID:</label>
                    <input type="text" id="studentID" name="studentID" value="<?php echo htmlspecialchars($studentID); ?>" 
                           placeholder="Format: YYYY-XXXXX (e.g., 2023-00800)">
                </div>
                
                <div class="form-group">
                    <label for="firstName"><i class="fas fa-user"></i> First Name:</label>
                    <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="middleName"><i class="fas fa-user"></i> Middle Name: <span class="note">(optional)</span></label>
                    <input type="text" id="middleName" name="middleName" value="<?php echo htmlspecialchars($middleName); ?>">
                </div>
                
                <div class="form-group">
                    <label for="lastName"><i class="fas fa-user"></i> Last Name:</label>
                    <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($lastName); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="contactNumber"><i class="fas fa-phone"></i> Contact Number: <span class="note">(optional)</span></label>
                    <input type="text" id="contactNumber" name="contactNumber" 
                           value="<?php echo htmlspecialchars($contactNumber); ?>" 
                           placeholder="11 digits" 
                           pattern="\d{11}"
                           title="Contact number must be exactly 11 digits">
                    <div id="contactNumberError" class="error-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> USeP Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password:</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required>
                        <span class="password-toggle" style="cursor: pointer;">
                            <i class="fas fa-eye" id="togglePassword"></i>
                        </span>
                    </div>
                    <div id="password-requirements" class="note"></div>
                </div>
                
                <div class="form-group button-group">
                    <button type="submit" class="btn btn-primary">Add User</button>
                    <button type="button" onclick="window.location.href='list_users.php'" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleIDFields() {
            // Get all ID field elements
            const studentIDField = document.getElementById('studentIDField');
            const role = document.getElementById('role').value;

            // Hide student ID field first
            studentIDField.style.display = 'none';
            document.getElementById('studentID').required = false;

            // Show student ID field only if role is Student
            if (role === 'Student') {
                studentIDField.style.display = 'block';
                document.getElementById('studentID').required = true;
            }
        }

        function validatePassword(password) {
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[\W_]/.test(password)
            };

            return requirements;
        }

        function updatePasswordFeedback() {
            const password = document.getElementById('password').value;
            const requirements = validatePassword(password);
            const feedbackDiv = document.getElementById('password-requirements');
            
            let html = '<ul class="password-requirements-list">';
            html += `<li class="${requirements.length ? 'met' : 'unmet'}">At least 8 characters</li>`;
            html += `<li class="${requirements.uppercase ? 'met' : 'unmet'}">One uppercase letter</li>`;
            html += `<li class="${requirements.lowercase ? 'met' : 'unmet'}">One lowercase letter</li>`;
            html += `<li class="${requirements.number ? 'met' : 'unmet'}">One number</li>`;
            html += `<li class="${requirements.special ? 'met' : 'unmet'}">One special character</li>`;
            html += '</ul>';
            
            feedbackDiv.innerHTML = html;
        }

        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePassword');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        function validateContactNumber() {
            const contactNumber = document.getElementById('contactNumber').value;
            if (contactNumber && !/^\d{11}$/.test(contactNumber)) {
                document.getElementById('contactNumberError').textContent = 'Contact number must be exactly 11 digits';
                return false;
            }
            document.getElementById('contactNumberError').textContent = '';
            return true;
        }

        function validateForm() {
            const contactNumberValid = validateContactNumber();
            if (!contactNumberValid) {
                return false;
            }
            return true;
        }

        // Add this to your existing DOMContentLoaded event listener
        document.addEventListener('DOMContentLoaded', function() {
            toggleIDFields();
            
            const passwordInput = document.getElementById('password');
            passwordInput.addEventListener('input', updatePasswordFeedback);

            // Add password toggle click handler
            const togglePassword = document.getElementById('togglePassword');
            if (togglePassword) {
                togglePassword.addEventListener('click', function() {
                    const passwordInput = document.getElementById('password');
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        this.classList.remove('fa-eye');
                        this.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        this.classList.remove('fa-eye-slash');
                        this.classList.add('fa-eye');
                    }
                });
            }

            // Add contact number validation
            const contactNumberInput = document.getElementById('contactNumber');
            if (contactNumberInput) {
                contactNumberInput.addEventListener('input', validateContactNumber);
            }
        });
    </script>
</body>
</html>

<?php
// Close connection
$conn->close();
?>