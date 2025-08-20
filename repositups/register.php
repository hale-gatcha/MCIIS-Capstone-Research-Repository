<?php
require_once 'config.php';

$errors = [];
$fieldErrors = []; // Track field-specific errors
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentID = isset($_POST['studentID']) && $_POST['studentID'] !== '' ? $_POST['studentID'] : null;
    $firstName = trim($_POST['firstName']);
    $middleName = isset($_POST['middleName']) && $_POST['middleName'] !== '' ? trim($_POST['middleName']) : null;
    $lastName = trim($_POST['lastName']);
    $contactNumber = isset($_POST['contactNumber']) && $_POST['contactNumber'] !== '' ? trim($_POST['contactNumber']) : null;
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $confirmPassword = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';

    $passwordValid = !(
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[\W_]/', $password)
    );

    if (!in_array($role, ['Student', 'Faculty'])) {
        $errors[] = "Role must be Student or Faculty.";
        $fieldErrors['role'] = "Role must be Student or Faculty.";
    }

    if (!$passwordValid) {
        $errors[] = "Password must be at least 8 characters and include uppercase, lowercase, number, and special character.";
        $fieldErrors['password'] = "Password must be at least 8 characters and include uppercase, lowercase, number, and special character.";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
        $fieldErrors['confirmPassword'] = "Passwords do not match.";
    }

    if (!preg_match('/@usep\.edu\.ph$/i', $email)) {
        $errors[] = "Email must be a valid USeP email address.";
        $fieldErrors['email'] = "Email must be a valid USeP email address.";
    }

    if ($role === 'Student' && $studentID !== null) {
        if (!preg_match('/^\d{4}-\d{5}$/', $studentID)) {
            $errors[] = "Student ID must be in the format YYYY-XXXXX (e.g., 2023-00800).";
            $fieldErrors['studentID'] = "Student ID must be in the format YYYY-XXXXX (e.g., 2023-00800).";
        }
    }

    if ($contactNumber !== null && $contactNumber !== '') {
        if (!preg_match('/^\d{11}$/', $contactNumber)) {
            $errors[] = "Contact number must be exactly 11 digits.";
            $fieldErrors['contactNumber'] = "Contact number must be exactly 11 digits.";
        }
    }
    
    $stmt = $conn->prepare("SELECT userID FROM User WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "Email already exists.";
        $fieldErrors['email'] = "Email already exists.";
    }
    $stmt->close();

    if ($role === 'Student') {
        if (!$studentID) {
            $errors[] = "Student ID is required for students.";
            $fieldErrors['studentID'] = "Student ID is required for students.";
        } else {
            $stmt = $conn->prepare("SELECT userID FROM User WHERE studentID = ?");
            $stmt->bind_param("s", $studentID);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "Student ID already exists.";
                $fieldErrors['studentID'] = "Student ID already exists.";
            }
            $stmt->close();
        }
    } elseif ($role === 'Faculty') {
        $studentID = null;
        $stmt = $conn->prepare("SELECT facultyID FROM Faculty WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $errors[] = "Faculty email not found in Faculty table.";
            $fieldErrors['email'] = "Faculty email not found in Faculty table.";
        }
        $stmt->close();
    }

    if (count($errors) === 0) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("CALL sp_AddUser(?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $studentID, $firstName, $middleName, $lastName, $contactNumber, $email, $role, $hashedPassword);
        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = "Error: " . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create an Account</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="form-section">
        <div class="logo-branding">
            <img src="images/octopus-logo.png" alt="Octopus Logo">
            <span>Repositups</span>
        </div>
        <h2>Create an Account</h2>

        <?php if ($success): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showSuccessModal();
        });
    </script>
<?php endif; ?>

        <form method="POST">
            <label for="role"><i class="fas fa-user-tag"></i> Role</label>
            <select name="role" id="role" onchange="toggleStudentID()" required class="<?= isset($fieldErrors['role']) ? 'input-error' : '' ?>">
                <option value="">Select Role</option>
                <option value="Student" <?= isset($_POST['role']) && $_POST['role']=='Student' ? 'selected' : '' ?>>Student</option>
                <option value="Faculty" <?= isset($_POST['role']) && $_POST['role']=='Faculty' ? 'selected' : '' ?>>Faculty</option>
            </select>
            <?php if (isset($fieldErrors['role'])): ?>
                <div class="field-error-message"><?= htmlspecialchars($fieldErrors['role']) ?></div>
            <?php endif; ?>

            <div id="studentIDField" style="display:<?= isset($_POST['role']) && $_POST['role']=='Student' ? 'block' : 'none' ?>">
                <label for="studentID"><i class="fas fa-id-card"></i> Student ID</label>
                <input type="text" name="studentID" id="studentID" value="<?= isset($_POST['studentID']) ? htmlspecialchars($_POST['studentID']) : '' ?>" class="<?= isset($fieldErrors['studentID']) ? 'input-error' : '' ?>">
                <?php if (isset($fieldErrors['studentID'])): ?>
                    <div class="field-error-message"><?= htmlspecialchars($fieldErrors['studentID']) ?></div>
                <?php endif; ?>
            </div>

            <label for="firstName"><i class="fas fa-user"></i> First Name</label>
            <input type="text" name="firstName" required value="<?= isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName']) : '' ?>" class="<?= isset($fieldErrors['firstName']) ? 'input-error' : '' ?>">
            <?php if (isset($fieldErrors['firstName'])): ?>
                <div class="field-error-message"><?= htmlspecialchars($fieldErrors['firstName']) ?></div>
            <?php endif; ?>

            <label for="middleName"><i class="fas fa-user"></i> Middle Name <span class="note">(optional)</span></label>
            <input type="text" name="middleName" value="<?= isset($_POST['middleName']) ? htmlspecialchars($_POST['middleName']) : '' ?>" class="<?= isset($fieldErrors['middleName']) ? 'input-error' : '' ?>">
            <?php if (isset($fieldErrors['middleName'])): ?>
                <div class="field-error-message"><?= htmlspecialchars($fieldErrors['middleName']) ?></div>
            <?php endif; ?>

            <label for="lastName"><i class="fas fa-user"></i> Last Name</label>
            <input type="text" name="lastName" required value="<?= isset($_POST['lastName']) ? htmlspecialchars($_POST['lastName']) : '' ?>" class="<?= isset($fieldErrors['lastName']) ? 'input-error' : '' ?>">
            <?php if (isset($fieldErrors['lastName'])): ?>
                <div class="field-error-message"><?= htmlspecialchars($fieldErrors['lastName']) ?></div>
            <?php endif; ?>

            <label for="contactNumber"><i class="fas fa-phone"></i> Contact Number <span class="note">(optional)</span></label>
            <input type="text" name="contactNumber" value="<?= isset($_POST['contactNumber']) ? htmlspecialchars($_POST['contactNumber']) : '' ?>" class="<?= isset($fieldErrors['contactNumber']) ? 'input-error' : '' ?>">
            <?php if (isset($fieldErrors['contactNumber'])): ?>
                <div class="field-error-message"><?= htmlspecialchars($fieldErrors['contactNumber']) ?></div>
            <?php endif; ?>

            <label for="email"><i class="fas fa-envelope"></i> USeP Email</label>
            <input type="email" name="email" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" class="<?= isset($fieldErrors['email']) ? 'input-error' : '' ?>">
            <?php if (isset($fieldErrors['email'])): ?>
                <div class="field-error-message"><?= htmlspecialchars($fieldErrors['email']) ?></div>
            <?php endif; ?>

            <label for="password"><i class="fas fa-lock"></i> Password</label>
            <div style="position:relative;">
                <input type="password" name="password" id="password" required class="<?= isset($fieldErrors['password']) ? 'input-error' : '' ?>">
                <span class="toggle-password" onclick="togglePasswordVisibility('password', this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#222;">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
            <?php if (isset($fieldErrors['password'])): ?>
                <div class="field-error-message"><?= htmlspecialchars($fieldErrors['password']) ?></div>
            <?php endif; ?>
            <?php if (!isset($fieldErrors['password'])): ?>
                <div class="note">At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character.</div>
            <?php endif; ?>

            <label for="confirmPassword"><i class="fas fa-lock"></i> Confirm Password</label>
            <div style="position:relative;">
                <input type="password" name="confirmPassword" id="confirmPassword" required class="<?= isset($fieldErrors['confirmPassword']) ? 'input-error' : '' ?>">
                <span class="toggle-password" onclick="togglePasswordVisibility('confirmPassword', this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#222;">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
            <?php if (isset($fieldErrors['confirmPassword'])): ?>
                <div class="field-error-message"><?= htmlspecialchars($fieldErrors['confirmPassword']) ?></div>
            <?php endif; ?>

            <button type="submit">Sign Up</button>
        </form>
        <!-- Add this below the form -->
        <div style="margin-top: 20px;">
            Already have an account? <a href="login.php" class="login-link">Log in here</a>
        </div>
    </div>  

    <div class="image-section">
        <img src="images/school.png" alt="Building Image">
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-check-circle" style="color: #28a745; font-size: 3em; margin-bottom: 15px;"></i>
            <h3>Registration Successful!</h3>
        </div>
        <div class="modal-body">
            <p>Your account has been created successfully!</p>
            <p><strong>Proceed to log in?</strong></p>
        </div>
        <div class="modal-footer">
            <button onclick="goToLogin()" class="btn-primary">Yes, Log In</button>
            <button onclick="closeModal()" class="btn-secondary">Stay Here</button>
        </div>
    </div>
</div>

<script>
    function toggleStudentID() {
        const role = document.getElementById('role').value;
        document.getElementById('studentIDField').style.display = role === 'Student' ? 'block' : 'none';
    }
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

function showSuccessModal() {
    document.getElementById('successModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('successModal').style.display = 'none';
}

function goToLogin() {
    window.location.href = 'login.php';
}
</script>
</body>
</html>
<style>
    .input-error {
        border: 2px solid #b30000 !important;
        background-color: #fff6f6;
    }
    .field-error-message {
        color: #b30000;
        font-size: 0.95em;
        margin-top: 3px;
        margin-bottom: 0;
        padding-left: 2px;
    }
    .login-link {
        color: #FF6600;
        text-decoration: none;
        transition: text-decoration 0.2s;
    }
    .login-link:hover {
        text-decoration: underline;
    }

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease-in-out;
}

.modal-content {
    background: white;
    border-radius: 15px;
    padding: 30px;
    text-align: center;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.3s ease-in-out;
}

.modal-header h3 {
    color: #2e003e;
    margin: 0;
    font-size: 1.5em;
}

.modal-body {
    margin: 20px 0;
    color: #333;
}

.modal-body p {
    margin: 10px 0;
    font-size: 1.1em;
}

.modal-footer {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 25px;
}

.btn-primary {
    background-color: #FF6600;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    font-size: 1em;
    transition: background-color 0.3s;
}

.btn-primary:hover {
    background-color: #e65c00;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    font-size: 1em;
    transition: background-color 0.3s;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { 
        opacity: 0;
        transform: translateY(-50px);
    }
    to { 
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
