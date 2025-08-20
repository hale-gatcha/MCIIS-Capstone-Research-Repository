<?php
session_start();
require_once 'config.php';

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
$userID = "";
$studentID = "";
$firstName = "";
$middleName = "";
$lastName = "";
$contactNumber = "";
$email = "";
$role = "";
$errorMessage = "";
$successMessage = "";

// Check if ID is provided
if (!isset($_GET['id'])) {
    header("Location: list_users.php");
    exit();
}

$userID = $_GET['id'];

// Fetch user data
$sql = "SELECT * FROM user WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    header("Location: list_users.php");
    exit();
}

$userData = $result->fetch_assoc();
// Add this line for debugging
error_log("Current role: " . $userData['role']);

$studentID = $userData['studentID'];
$firstName = $userData['firstName'];
$middleName = $userData['middleName'];
$lastName = $userData['lastName'];
$contactNumber = $userData['contactNumber'];
$email = $userData['email'];
$role = $userData['role'];

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $role = isset($_POST['role']) ? $_POST['role'] : 'Student';
    $firstName = $_POST['firstName'];
    $middleName = $_POST['middleName'];
    $lastName = $_POST['lastName'];
    $contactNumber = $_POST['contactNumber'];
    $email = $_POST['email'];
    
    // Handle studentID based on role
    if (strtolower($role) === 'student') {
        $studentID = trim($_POST['studentID']);
        if (empty($studentID)) {
            $errorMessage = "Student ID is required for students";
        }
    } else {
        // For non-student roles, keep the existing studentID if any
        $studentID = $userData['studentID'];
    }
    
    // Validate data
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $errorMessage = "First name, last name, and email are required";
    } else if (strtolower($role) === 'student' && empty($studentID)) {
        $errorMessage = "Student ID is required for students";
    } else {
        // Check if any data has changed
        $hasChanges = false;
        
        if ($firstName !== $userData['firstName'] ||
            $middleName !== $userData['middleName'] ||
            $lastName !== $userData['lastName'] ||
            $contactNumber !== $userData['contactNumber'] ||
            $email !== $userData['email'] ||
            $role !== $userData['role'] ||
            (strtolower($role) === 'student' && $studentID !== $userData['studentID'])) {
            $hasChanges = true;
        }
        
        if (!$hasChanges) {
            // No changes made, redirect to list_users.php
            header("Location: list_users.php");
            exit();
        }

        // Update user data (only if changes were made)
        $updateSql = "UPDATE User SET 
                      firstName = ?, 
                      middleName = ?, 
                      lastName = ?, 
                      contactNumber = ?, 
                      email = ?, 
                      role = ?";
        
        // Only update studentID if role is student
        if (strtolower($role) === 'student') {
            $updateSql .= ", studentID = ?";
        }
        
        $updateSql .= " WHERE userID = ?";
        
        $updateStmt = $conn->prepare($updateSql);
        
        if (strtolower($role) === 'student') {
            $updateStmt->bind_param("sssssssi", 
                $firstName, 
                $middleName, 
                $lastName, 
                $contactNumber, 
                $email,
                $role,
                $studentID,
                $userID        // Note: userID is an integer, so we use 'i'
            );
        } else {
            $updateStmt->bind_param("ssssssi", 
                $firstName, 
                $middleName, 
                $lastName, 
                $contactNumber, 
                $email,
                $role,
                $userID        // Note: userID is an integer, so we use 'i'
            );
        }
        
        if ($updateStmt->execute()) {
            // Log the user update in userfacultyauditlog
            $logSql = "INSERT INTO userfacultyauditlog (modifiedBy, targetUserID, actionType) VALUES (?, ?, 'update user')";
            $logStmt = $conn->prepare($logSql);
            $logStmt->bind_param("ii", $_SESSION['user_id'], $userID);
            $logStmt->execute();
            $logStmt->close();
            
            // Set success message in session
            $_SESSION['message'] = "User updated successfully";
            
            // Redirect to list_users.php after successful update
            header("Location: list_users.php");
            exit();
        } else {
            $errorMessage = "Error updating user: " . $conn->error;
        }
        
        $updateStmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            line-height: 1.6;
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1 {
            color: #220044;
            font-weight: 600;
            margin-bottom: 30px;
            text-align: center;
            font-size: clamp(1.5rem, 2.5vw, 2.5rem);
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
            min-width: 120px;
            text-align: center;
        }

        .btn-primary {
            background-color: #220044;
        }

        .btn-primary:hover {
            background-color: #150029 !important;
        }

        .btn-primary:active {
            background-color: #0d001a !important; /* Even darker shade for click state */
        }

        .btn-secondary {
            background-color: #FF6600;
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
            margin: 20px;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(34, 0, 68, 0.1);
            border-left: 4px solid #220044;
        }

        .navbar {
            padding: 0.5rem 1rem;
        }

        .navbar-brand {
            font-weight: bold;
            letter-spacing: 1px;
            font-size: clamp(1.1rem, 2vw, 2rem);
            margin: 0;
            padding: 0;
        }

        .home-btn {
            background: transparent !important;
            border: none !important;
            padding: 0.5rem;
            margin: 0;
            line-height: 1;
        }

        .home-btn i {
            font-size: clamp(1.5rem, 2.5vw, 2.5rem);
            color: white;
            transition: color 0.3s ease;
        }

        .home-btn:hover i {
            color: #FF6600;
        }

        .navbar-logo {
            height: clamp(32px, 6vw, 80px);
            width: auto;
            margin-right: 0.5rem;
        }

        .container-fluid {
            display: flex;
            align-items: center;
            padding: 0 1rem;
        }

        .add-btn {
            display: inline-flex;
            align-items: center;
            background-color: #FF6600;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.3s ease;
            width: fit-content;
        }

        .add-btn:hover {
            background-color: #FF884D;
            color: white;
            text-decoration: none;
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-dark" style="background-color: rgb(35, 1, 68);">
            <div class="container-fluid">
                <!-- Home button -->
                <a href="welcome.php" class="btn home-btn">
                    <i class="fas fa-home"></i>
                </a>
                <!-- Logo and Brand -->
                <div class="d-flex align-items-center justify-content-center" style="flex-grow: 1;">
                    <a class="navbar-brand d-flex align-items-center" href="#">
                        <img src="images/octopus-logo.png" alt="Logo" class="navbar-logo me-2">
                        <span>Repositups</span>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="form-container">
        <h1>Edit User</h1>

        <div style="display: flex; justify-content: center;">
            <a href="list_users.php" class="add-btn" style="margin-bottom: 20px;">
                Back to User List
            </a>
        </div>
    
        <?php if (!empty($errorMessage)): ?>
            <div class="error-message"><?php echo $errorMessage; ?></div>
        <?php endif; ?>
    
        <?php if (!empty($successMessage)): ?>
            <div class="success-message"><?php echo $successMessage; ?></div>
        <?php endif; ?>
    
        <form method="post">
            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role">
                    <?php
                    $roles = array(
                        'Student' => 'Student',
                        'Administrator' => 'Administrator', 
                        'Faculty' => 'Faculty',
                        'MCIIS Staff' => 'MCIIS Staff'
                    );
                    
                    foreach ($roles as $roleValue => $roleLabel) {
                        $selected = ($roleValue === $userData['role']) ? 'selected' : '';
                        echo "<option value=\"$roleValue\" $selected>$roleLabel</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group <?php echo (strtolower($userData['role']) !== 'student') ? 'hidden' : ''; ?>" id="studentIDGroup">
                <label for="studentID">Student ID:</label>
                <input type="text" id="studentID" name="studentID" value="<?php echo htmlspecialchars($studentID); ?>">
            </div>
        
            <div class="form-group">
                <label for="firstName">First Name:</label>
                <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName); ?>" required>
            </div>

            <div class="form-group">
                <label for="middleName">Middle Name:</label>
                <input type="text" id="middleName" name="middleName" value="<?php echo htmlspecialchars($middleName); ?>">
            </div>

            <div class="form-group">
                <label for="lastName">Last Name:</label>
                <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($lastName); ?>" required>
            </div>

            <div class="form-group">
                <label for="contactNumber">Contact Number:</label>
                <input type="text" id="contactNumber" name="contactNumber" value="<?php echo htmlspecialchars($contactNumber); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
        
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="list_users.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
function toggleStudentIDField() {
    const roleSelect = document.getElementById('role');
    const studentIDGroup = document.getElementById('studentIDGroup');
    const studentIDInput = document.getElementById('studentID');
    
    if (roleSelect.value.toLowerCase() === 'student') {
        studentIDGroup.classList.remove('hidden');
        studentIDInput.setAttribute('required', 'required');
    } else {
        studentIDGroup.classList.add('hidden');
        studentIDInput.removeAttribute('required');
        studentIDInput.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const currentRole = '<?php echo addslashes($userData['role']); ?>';
    
    if (roleSelect) {
        roleSelect.value = currentRole;
        toggleStudentIDField(); // Call this immediately to set initial state
    }
});

// Add form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const roleSelect = document.getElementById('role');
    const studentIDInput = document.getElementById('studentID');
    
    if (roleSelect.value === 'student' && !studentIDInput.value.trim()) {
        e.preventDefault();
        alert('Student ID is required for students');
    }
});

// Initial check when page loads
document.addEventListener('DOMContentLoaded', toggleStudentIDField);

// Add event listener for role changes
document.getElementById('role').addEventListener('change', toggleStudentIDField);

// Set initial role value and trigger change event
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const currentRole = '<?php echo $userData['role']; ?>';
    
    if (roleSelect) {
        roleSelect.value = currentRole;
        // Trigger change event to ensure all dependent functionality works
        roleSelect.dispatchEvent(new Event('change'));
    }
});
    </script>
</body>
</html>
<?php
// Close connection
$conn->close();
?>