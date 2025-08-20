<?php
session_start();
require_once 'config.php';

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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Feedback List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
            width: 100%;
        }
        
        h2 {
            text-align: center;
            font-weight: 600;
            margin-bottom: 30px;
            color: #220044;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(34, 0, 68, 0.1);
        }
        
        th, td {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #220044;
            color: white;
            font-weight: 500;
        }
        
        tr:hover {
            background-color: #f8f8f8;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .empty-message {
            text-align: center;
            padding: 30px;
            font-size: 1.1rem;
            color: #FF6600;
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

        .navbar {
            width: 100%;
            padding: 0.5rem 1rem;
        }

        .container-fluid {
            max-width: 100%;
            padding: 0 1rem;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(35, 1, 68);">
            <div class="container-fluid position-relative justify-content-center">
                <a href="welcome.php" class="btn btn-outline-light me-2 show-hamburger border-0 home-btn" style="position:absolute;left:-10px;top:50%;transform:translateY(-50%);z-index:1100;padding:8px;background:transparent;">
                    <i class="fas fa-home"></i>
                </a>
                <a class="navbar-brand mx-auto d-flex align-items-center justify-content-center" href="#">
                    <img src="images/octopus-logo.png" alt="Logo" class="navbar-logo me-2">
                    <span>Repositups</span>
                </a>
            </div>
        </nav>
    </header>

    <div class="content-container">
        <h2>Feedback List</h2>
        <?php
        // Include the database configuration file
        require_once 'config.php';

        // Query to select all records from contact table with user information
        $sql = "SELECT c.contactID, c.userID, c.subject, c.message, c.created_at 
                FROM contact c
                JOIN User u ON c.userID = u.userID
                ORDER BY c.created_at DESC";

        $result = $conn->query($sql);

        // Check if there are any records
        if ($result->num_rows > 0) {
            echo "<table>";
            echo "<tr>";
            echo "<th>Contact ID</th>";
            echo "<th>User ID</th>";  // Changed from Username to User ID
            echo "<th>Subject</th>";
            echo "<th>Message</th>";
            echo "<th>Created At</th>";
            echo "</tr>";

            // Output data of each row
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row["contactID"] . "</td>";
                echo "<td>" . $row["userID"] . "</td>";  // Changed from fullName to userID
                echo "<td>" . htmlspecialchars($row["subject"]) . "</td>";
                echo "<td>" . htmlspecialchars($row["message"]) . "</td>";
                echo "<td>" . $row["created_at"] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='empty-message'>No feedback records found</div>";
        }

        // Close the database connection
        $conn->close();
        ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>