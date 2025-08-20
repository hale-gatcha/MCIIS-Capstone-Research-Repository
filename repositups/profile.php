<?php
session_start();
require_once 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];

// Fetch user details
try {
    $stmt = $pdo->prepare("SELECT firstName, middleName, lastName, email, role FROM User WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo "User not found.";
        exit();
    }
    // Concatenate full name
    $fullName = trim($user['firstName'] . ' ' . $user['middleName'] . ' ' . $user['lastName']);
} catch (PDOException $e) {
    echo "Database error: " . htmlspecialchars($e->getMessage());
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            line-height: 1.6;
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-size: clamp(14px, 1vw + 12px, 16px); /* Base font size */
        }

        .container-fluid.flex-grow-1 {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(1rem, 5vw, 3rem);
        }

        .profile-container {
            width: 90%;
            max-width: 600px;
            margin: 1rem auto;
            padding: 1.5rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(34, 0, 68, 0.08);
        }

        .profile-container h2 {
            color: #220044;
            font-weight: 600;
            margin-bottom: clamp(1.5rem, 4vw, 2rem);
            text-align: center;
            font-size: clamp(1.5rem, 2vw + 1rem, 2rem); /* Adjusted heading size */
        }

        .profile-row {
            margin-bottom: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #eee;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            background: #fafafa;
            border-radius: 8px;
        }

        .profile-label {
            font-weight: 500;
            color: #220044;
            min-width: 120px;
            flex: 0 0 auto;
            font-size: clamp(0.875rem, 1vw + 0.5rem, 1rem); /* Label size */
        }

        .profile-value {
            color: #333;
            flex: 1;
            word-break: break-word;
            font-size: clamp(0.875rem, 1vw + 0.5rem, 1rem); /* Value size */
        }

        .contact-button-container {
            margin-top: clamp(1.5rem, 4vw, 2.5rem);
            display: flex;
            justify-content: center;
            padding: 0 1rem;
        }

        .contact-btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s ease;
            font-family: 'Inter', sans-serif;
            background-color: #FF6600;
            width: 100%;
            max-width: 200px;
            text-align: center;
            font-size: clamp(0.875rem, 1vw + 0.5rem, 1rem); /* Button text size */
        }

        /* Navbar adjustments */
        .navbar {
            padding: 1.5rem;
        }

        .navbar-brand {
            font-size: clamp(2rem, 5vw, 2.5rem);
            font-weight: 700;
        }

        .navbar-brand span {
            letter-spacing: 0.5px;
        }

        .navbar-logo {
            height: clamp(50px, 8vw, 60px);
            width: auto;
            margin-right: 1rem;
        }

        .home-btn {
            font-size: clamp(2rem, 5vw, 2.5rem);
            padding: 1rem !important;
        }

        .home-btn i {
            font-size: inherit;
            color: white;
            transition: color 0.3s ease;
        }

        .home-btn:hover {
            background-color: transparent !important;
            border-color: transparent !important;
        }

        .home-btn:hover i {
            color: #FF6600 !important;
        }

        @media (max-width: 768px) {
            body {
                font-size: 16px; /* Increased base font size */
            }

            .navbar {
                padding: 1.25rem;
            }

            .navbar-brand {
                font-size: 2.25rem;
            }

            .navbar-logo {
                height: 50px;
            }

            .home-btn {
                font-size: 2.25rem;
                padding: 1rem !important;
            }

            .home-btn i {
                font-size: 2.25rem;
            }

            .profile-container h2 {
                font-size: 1.75rem;
                margin-bottom: 1.5rem;
            }

            .profile-row {
                padding: 1rem;
                margin-bottom: 1rem;
                background: #fff;
                border: 1px solid #eee;
            }

            .profile-label {
                font-size: 1rem;
                color: #666;
                margin-bottom: 0.5rem;
                display: block;
                width: 100%;
            }

            .profile-value {
                font-size: 1.125rem;
                font-weight: 500;
                color: #220044;
                display: block;
                width: 100%;
            }

            .contact-btn {
                font-size: 1.125rem;
                padding: 1rem 1.5rem;
            }
        }

        /* Rest of your existing styles... */
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(35, 1, 68);">
            <div class="container-fluid position-relative justify-content-center">
                <!-- Home button -->
                <a href="welcome.php" class="btn btn-outline-light me-2 show-hamburger border-0 home-btn" style="position:absolute;left:-15px;top:50%;transform:translateY(-50%);z-index:1100;padding-left:0;margin-left:0;background:transparent;">
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
        <div class="profile-container">
            <h2>Your Profile</h2>
            <div class="profile-row">
                <span class="profile-label">Full Name:</span>
                <span class="profile-value"><?= htmlspecialchars($fullName) ?></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Email:</span>
                <span class="profile-value"><?= htmlspecialchars($user['email']) ?></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Role:</span>
                <span class="profile-value"><?= htmlspecialchars($user['role']) ?></span>
            </div>
        </div>
        
        <?php if (isset($user['role']) && in_array($user['role'], ['MCIIS Staff', 'Faculty', 'Student'])): ?>
            <div class="contact-button-container">
                <a href="contact_form.php" class="contact-btn">Contact Us</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>