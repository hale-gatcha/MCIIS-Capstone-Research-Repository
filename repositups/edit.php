<?php
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is Administrator
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
$errors = [];
$facultyData = [];

// Check if ID is provided in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: faculty_staff_list.php");
    exit();
}

$facultyID = $_GET['id'];

// If form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and assign POST data
    $firstName = trim($_POST['firstName']);
    $middleName = trim($_POST['middleName']);
    $lastName = trim($_POST['lastName']);
    $position = trim($_POST['position']);
    $designation = trim($_POST['designation']);
    $email = trim($_POST['email']);
    $ORCID = trim($_POST['ORCID']);
    $contactNumber = trim($_POST['contactNumber']);
    $educationalAttainment = trim($_POST['educationalAttainment']);
    $fieldOfSpecialization = trim($_POST['fieldOfSpecialization']);
    $researchInterest = trim($_POST['researchInterest']);

    // Basic validation
    if (empty($firstName)) $errors[] = "First Name is required.";
    if (empty($lastName)) $errors[] = "Last Name is required.";
    if (empty($email)) $errors[] = "Email is required.";

    if (empty($errors)) {
        // Prepare and bind the update statement
        $stmt = $conn->prepare("UPDATE faculty SET 
            firstName = ?, 
            middleName = ?, 
            lastName = ?, 
            position = ?, 
            designation = ?, 
            email = ?, 
            ORCID = ?, 
            contactNumber = ?, 
            educationalAttainment = ?, 
            fieldOfSpecialization = ?, 
            researchInterest = ? 
            WHERE facultyID = ?");
        
        $stmt->bind_param("ssssssssssss", 
            $firstName, 
            $middleName, 
            $lastName, 
            $position, 
            $designation, 
            $email, 
            $ORCID, 
            $contactNumber, 
            $educationalAttainment, 
            $fieldOfSpecialization, 
            $researchInterest,
            $facultyID
        );

        if ($stmt->execute()) {
            $logSql = "INSERT INTO userfacultyauditlog (modifiedBy, targetFacultyID, actionType) VALUES (?, ?, 'update faculty')";
            $logStmt = $conn->prepare($logSql);
            $logStmt->bind_param("is", $_SESSION['user_id'], $facultyID);
            $logStmt->execute();
            $logStmt->close();
            
            $_SESSION['message'] = "Faculty updated successfully";
            header("Location: faculty_staff_list.php");
            exit(); // Make sure nothing else executes after redirect
        } else {
            $errors[] = "Error updating record: " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    // Fetch faculty data for the form
    $stmt = $conn->prepare("SELECT * FROM faculty WHERE facultyID = ?");
    $stmt->bind_param("s", $facultyID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "Faculty not found.";
        exit();
    }
    
    $facultyData = $result->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Edit Faculty</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f5f5;
        }

        .navbar-brand {
            font-weight: bold;
            letter-spacing: 1px;
            font-size: clamp(1.1rem, 2vw, 2rem);
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .home-btn {
            font-size: clamp(1.5rem, 2.5vw, 2.5rem);
            padding: clamp(0.5rem, 1vw, 1rem);
            transition: background-color 0.3s ease, color 0.3s ease;
            background: transparent !important;
            margin-right: auto;
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

        .navbar-logo {
            height: clamp(32px, 6vw, 80px);
            width: auto;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            background-color: #FF6600;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .back-button:hover {
            background-color: #FF884D;
            color: white;
            text-decoration: none;
        }

        .content-wrapper {
            max-width: 600px;
            margin: 40px auto;
            padding: 0 15px;
        }

        h2 {
            color: #220044;
            font-weight: 600;
            margin-bottom: 30px;
            font-size: clamp(1.5rem, 2.5vw, 3rem);
            margin-top: -20px;
            text-align: center;
        }

        form {
            border: 1px solid #eee;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(34, 0, 68, 0.1);
            border-left: 4px solid #220044;
        }

        label {
            display: block;
            margin-top: 15px;
            color: #220044;
            font-weight: 500;
        }

        input[type=text], input[type=email] {
            width: 100%;
            padding: 8px 12px;
            margin-top: 5px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Inter', sans-serif;
        }

        input[type=text]:focus, input[type=email]:focus {
            outline: none;
            border-color: #FF6600;
            box-shadow: 0 0 0 2px rgba(255, 102, 0, 0.1);
        }

        .btn {
            background-color: #FF6600;
            color: white;
            border: none;
            padding: 10px 20px;
            margin-top: 20px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: #FF884D;
        }

        .error {
            color: #FF6600;
            margin-top: 15px;
            padding: 10px;
            background-color: rgba(255, 102, 0, 0.1);
            border-radius: 4px;
        }

        .success {
            color: #28a745;
            margin-top: 15px;
            padding: 10px;
            background-color: rgba(40, 167, 69, 0.1);
            border-radius: 4px;
        }
    </style>
</head>
<body>

<header>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(35, 1, 68);">
        <div class="container-fluid">
            <a href="welcome.php" class="navbar-brand home-btn" style="margin-right: auto;">
                <i class="fas fa-home"></i>
            </a>
            <a class="navbar-brand mx-auto d-flex align-items-center justify-content-center">
                <img src="images/octopus-logo.png" alt="Logo" class="navbar-logo me-2">
                <span>Repositups</span>
            </a>
            <div style="margin-left: auto; width: 40px;"></div>
        </div>
    </nav>
</header>

<div class="content-wrapper">
    <h2 style="text-align:center;">Edit Faculty</h2>
    <div style="display: flex; justify-content: center; margin-bottom: 24px;">
        <a href="faculty_staff_list.php" class="back-button">
            Back to Faculty List
        </a>
    </div>

    <?php
    if (!empty($errors)) {
        echo '<div class="error"><ul>';
        foreach ($errors as $err) {
            echo '<li>' . htmlspecialchars($err) . '</li>';
        }
        echo '</ul></div>';
    }
    ?>

    <form method="post" action="">
        <label>First Name: <input type="text" name="firstName" value="<?php echo htmlspecialchars($facultyData['firstName']); ?>" required></label>
        <label>Middle Name: <input type="text" name="middleName" value="<?php echo htmlspecialchars($facultyData['middleName']); ?>"></label>
        <label>Last Name: <input type="text" name="lastName" value="<?php echo htmlspecialchars($facultyData['lastName']); ?>" required></label>
        <label>Position: <input type="text" name="position" value="<?php echo htmlspecialchars($facultyData['position']); ?>"></label>
        <label>Designation: <input type="text" name="designation" value="<?php echo htmlspecialchars($facultyData['designation']); ?>"></label>
        <label>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($facultyData['email']); ?>" required></label>
        <label>ORCID: <input type="text" name="ORCID" value="<?php echo htmlspecialchars($facultyData['ORCID']); ?>"></label>
        <label>Contact Number: <input type="text" name="contactNumber" value="<?php echo htmlspecialchars($facultyData['contactNumber']); ?>"></label>
        <label>Educational Attainment: <input type="text" name="educationalAttainment" value="<?php echo htmlspecialchars($facultyData['educationalAttainment']); ?>"></label>
        <label>Field of Specialization: <input type="text" name="fieldOfSpecialization" value="<?php echo htmlspecialchars($facultyData['fieldOfSpecialization']); ?>"></label>
        <label>Research Interest: <input type="text" name="researchInterest" value="<?php echo htmlspecialchars($facultyData['researchInterest']); ?>"></label>
        <button type="submit" class="btn">Update Faculty</button>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
