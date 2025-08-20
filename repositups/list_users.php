<?php
// Start session
session_start();

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

// Database connection
require_once 'config.php';

// Check if delete action was performed
if(isset($_GET['delete']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    header("Location: delete_user.php?id=$id");
    exit();
}

// HTML header
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
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
        }
        
        .navbar-brand {
            font-weight: bold;
            letter-spacing: 1px;
            font-size: clamp(1.1rem, 2vw, 2rem);
            ont-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .container-fluid.flex-grow-1 {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        h1 {
            color: #220044;
            font-weight: 600;
            margin-bottom: 30px;
            font-size: clamp(1.5rem, 2.5vw, 3rem);
        }

        /* Existing table styles */
        .user-list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 4px 8px rgba(34, 0, 68, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        .user-list th, .user-list td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .user-list th {
            background-color: #220044;
            color: white;
            font-weight: 500;
        }

        .user-list tr:hover {
            background-color: rgba(255, 102, 0, 0.05);
        }

        .action-buttons {
            white-space: nowrap;
        }

        .action-buttons a {
            display: inline-block;
            margin-right: 5px;
            padding: 8px 15px;
            text-decoration: none;
            color: white;
            border-radius: 4px;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .edit-btn {
            background-color: #220044;
        }

        .edit-btn:hover {
            background-color: #1a0033;
        }

        .delete-btn {
            background-color: #FF6600;
        }

        .delete-btn:hover {
            background-color: #FF884D;
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
            width: fit-content;  /* Makes button only as wide as its content */
        }

        .add-btn:hover {
            background-color: #FF884D;
            color: white;
            text-decoration: none;
        }

        .no-results {
            color: #FF6600;
            font-style: italic;
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(34, 0, 68, 0.1);
            margin-top: 20px;
        }

        /* Back button styles */
        .back-btn {
            font-size: clamp(1rem, 1.5vw, 1.5rem);
            padding: clamp(0.5rem, 1vw, 1rem);
            transition: background-color 0.3s ease;
        }

        .back-btn i {
            display: block;
            line-height: 1;
        }

        /* Home button styles */
        .home-btn {
            font-size: clamp(1.5rem, 2.5vw, 2.5rem);
            padding: clamp(0.8rem, 1.5vw, 1.5rem);
            transition: background-color 0.3s ease, color 0.3s ease;
            background: transparent;
            color: inherit; /* Use inherited color */
        }

        .home-btn i {
            display: block;
            line-height: 1;
            color: white; /* Default icon color */
            transition: color 0.3s ease;
        }

        .home-btn:hover {
            background-color: transparent !important;
        }

        .home-btn:hover i {
            color: #FF6600 !important; /* Icon turns orange on hover */
        }

        /* LOGO styles */
        .navbar-logo {
            height: clamp(32px, 6vw, 80px);
            width: auto;
        }

        .header-content {
            text-align: center;
            margin-bottom: 30px;
        }

        .header-content h1 {
            margin-bottom: 15px;
        }

        .add-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #FF6600;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            margin: 0 auto;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.3s ease;
            width: fit-content;
        }

        @media (max-width: 768px) {
            .user-list, .user-list thead, .user-list tbody, .user-list tr, .user-list th, .user-list td {
                display: block;
                width: 100%;
            }
            .user-list {
                border: none;
                box-shadow: none;
            }
            .user-list thead {
                display: none;
            }
            .user-list tr {
                margin-bottom: 32px; /* More space between cards */
                background: white;
                border-radius: 16px; /* More rounded */
                box-shadow: 0 4px 16px rgba(34, 0, 68, 0.10); /* Stronger shadow */
                padding: 16px 0;
                border: 2px solid #f0e6ff; /* Subtle border */
                transition: box-shadow 0.2s;
            }
            .user-list tr:last-child {
                margin-bottom: 0;
            }
            .user-list td {
                border: none;
                position: relative;
                padding-left: 50%;
                min-height: 40px;
                text-align: left;
                box-sizing: border-box;
            }
            .user-list td:before {
                position: absolute;
                left: 16px;
                top: 12px;
                width: 45%;
                white-space: nowrap;
                font-weight: 600;
                color: #220044;
                content: attr(data-label);
            }
            .user-list tr,
            .user-list td,
            .user-list td:before {
                font-size: 0.7rem;
            }
            .user-list td {
                padding: 8px 8px 8px 48%;
                min-height: 32px;
            }
            .user-list td:before {
                font-size: 0.8rem;
            }
            .action-buttons a {
                font-size: 0.7rem;
                padding: 7px 12px;
            }
            h1 {
                font-size: 1rem;
            }
            .add-btn {
                font-size: 0.7rem;
                padding: 7px 12px;
            }
            .action-buttons {
                padding-left: 0;
                margin-top: 10px;
            }
            .header-content h1 {
                font-size: 1.5rem;
                margin-bottom: 10px;
            }
            
            .add-btn {
                font-size: 0.9rem;
                padding: 7px 12px;
            }
        }
    </style>
</head>
<body>';

// Header Navigation Bar
echo '<header>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(35, 1, 68);">
        <div class="container-fluid position-relative justify-content-center">
            <!-- Home button (top left) -->
            <a href="welcome.php" class="btn btn-outline-light me-2 show-hamburger border-0 home-btn" style="position:absolute;left:0;top:50%;transform:translateY(-50%);z-index:1100;padding-left:16px;background:transparent;">
                <i class="fas fa-home"></i>
            </a>
            <!-- Centered brand -->
            <a class="navbar-brand mx-auto d-flex align-items-center justify-content-center" href="#">
                <img src="images/octopus-logo.png" alt="Logo" class="navbar-logo me-2">
                <span>Repositups</span>
            </a>
        </div>
    </nav>
</header>';

// Main content container
echo '<div class="container-fluid flex-grow-1">';
echo '<div class="header-content">';
echo '<h1>User Management</h1>';
echo '<a href="add_user.php" class="add-btn">Add User</a>';

// Add success message here
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success mt-3" role="alert">' . $_SESSION['message'] . '</div>';
    unset($_SESSION['message']);
}

echo '</div>';

// SQL query to get all users
$sql = "SELECT userID, studentID, firstName, middleName, lastName, contactNumber, email, role, createdTimestamp FROM user";
$result = $conn->query($sql);

// Check if query was successful
if ($result === false) {
    die("Error executing query: " . $conn->error);
}

// Check if there are results
if ($result->num_rows > 0) {
    echo '<table class="user-list">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Contact Number</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';
    
    // Output data of each row
    while($row = $result->fetch_assoc()) {
        // Concatenate name parts
        $fullName = $row["firstName"];
        if (!empty($row["middleName"])) {
            $fullName .= " " . $row["middleName"];
        }
        $fullName .= " " . $row["lastName"];
        
        echo '<tr>
                <td data-label="User ID">' . $row["userID"] . '</td>
                <td data-label="Student ID">' . $row["studentID"] . '</td>
                <td data-label="Name">' . $fullName . '</td>
                <td data-label="Contact Number">' . $row["contactNumber"] . '</td>
                <td data-label="Email">' . $row["email"] . '</td>
                <td data-label="Role">' . $row["role"] . '</td>
                <td data-label="Created">' . $row["createdTimestamp"] . '</td>
                <td data-label="Actions" class="action-buttons">
                    <a href="edit_user.php?id=' . $row["userID"] . '" class="edit-btn">Edit</a>
                    <a href="delete_user.php?id=' . $row["userID"] . '" class="delete-btn" onclick="return confirm(\'Are you sure you want to delete this user?\');">Delete</a>
                </td>
              </tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p class="no-results">No users found.</p>';
}

echo '</div>'; // Close container-fluid

// Add Bootstrap and other necessary scripts
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>';

// Close connection
// $conn->close(); // Removed as config.php handles the connection closing

echo '</body>
</html>';