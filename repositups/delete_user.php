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

// Check if ID is provided
if (!isset($_GET['id'])) {
    header("Location: list_users.php");
    exit();
}

$userID = $_GET['id'];

// Fetch user data to confirm it exists
$sql = "SELECT userID FROM user WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    header("Location: list_users.php");
    exit();
}

// Check if confirmation is provided
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Delete the user
    $deleteSql = "DELETE FROM user WHERE userID = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param("i", $userID);
    
    if ($deleteStmt->execute()) {
        header("Location: list_users.php?deleted=success");
    } else {
        $errorMessage = "Error deleting user: " . $conn->error;
    }
    
    $deleteStmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete User</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 20px;
            line-height: 1.6;
            background-color: #f5f5f5;
        }

        h1 {
            color: #220044;
            font-weight: 600;
            margin-bottom: 30px;
        }

        .delete-confirmation {
            background-color: white;
            border: 1px solid #ddd;
            color: #220044;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(34, 0, 68, 0.1);
        }

        .delete-confirmation p {
            margin-bottom: 15px;
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

        .btn-danger {
            background-color: #FF6600;
        }

        .btn-danger:hover {
            background-color: #FF884D;
        }

        .btn-secondary {
            background-color: #220044;
        }

        .btn-secondary:hover {
            background-color: #1a0033;
        }

        .error-message {
            color: #FF6600;
            margin-bottom: 15px;
            padding: 10px;
            background-color: rgba(255, 102, 0, 0.1);
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>Delete User</h1>
    
    <?php if (isset($errorMessage)): ?>
        <div class="error-message"><?php echo $errorMessage; ?></div>
    <?php endif; ?>
    
    <div class="delete-confirmation">
        <p>Are you sure you want to delete this user? This action cannot be undone.</p>
        
        <form method="post">
            <input type="hidden" name="confirm" value="yes">
            <button type="submit" class="btn btn-danger">Yes, Delete</button>
            <a href="list_users.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>

<?php
// Close connection
$conn->close();
?>