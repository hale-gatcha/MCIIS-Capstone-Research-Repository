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

// SQL query to select all data from keywordsearchlog table
$sql = "SELECT searchLogID, keywordID, userID, searchTimeStamp FROM keywordsearchlog";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keyword Search Log</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0; /* Remove the margin */
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1 {
            color: #220044;
            margin-bottom: 20px;  /* Reduced from 30px */
            font-weight: 600;
            font-size: clamp(1.5rem, 2.5vw, 3rem);
            text-align: center;  /* Center the heading */
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 4px 8px rgba(34, 0, 68, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            border: 1px solid #eee;
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: #220044;
            color: white;
            font-weight: 500;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tr:hover {
            background-color: rgba(255, 102, 0, 0.05);
        }

        .no-records {
            color: #FF6600;
            margin-top: 20px;
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(34, 0, 68, 0.1);
        }

        /* Update back button styles */
        .back-button {
            display: block;  /* Changed from inline-flex */
            align-items: center;
            background-color: #FF6600;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            margin: 0 auto 20px;  /* Center the button horizontally */
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.3s ease;
            width: fit-content;
        }

        .back-button:hover {
            background-color: #FF884D;
            color: white;
            text-decoration: none;
        }

        /* Add min-width and padding for responsiveness */
        @media (max-width: 768px) {
            .back-button {
                font-size: 0.7rem;
                padding: 7px 12px;
            }
        }

        /* Add new navbar styles */
        .navbar-brand {
            font-weight: bold;
            letter-spacing: 1px;
            font-size: clamp(1.1rem, 2vw, 2rem);
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
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

        .navbar-logo {
            height: clamp(32px, 6vw, 80px);
            width: auto;
        }

        .container-fluid.flex-grow-1 {
            padding: 20px; /* Add padding to the container instead */
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
        }
    </style>
</head>
<body>
    <!-- Add Navigation Bar -->
    <header>
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
    </header>

    <div class="container-fluid flex-grow-1">
        <h1>Keyword Search Log</h1>
        <a href="log_dashboard.php" class="back-button">Back to Log Dashboard</a>
        
        <?php
        if ($result->num_rows > 0) {
            // Output data in table format
            echo "<table>";
            echo "<tr>
                    <th>Search Log ID</th>
                    <th>Keyword ID</th>
                    <th>User ID</th>
                    <th>Search Timestamp</th>
                  </tr>";
            
            // Output data of each row
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row["searchLogID"] . "</td>";
                echo "<td>" . $row["keywordID"] . "</td>";
                echo "<td>" . $row["userID"] . "</td>";
                echo "<td>" . $row["searchTimeStamp"] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='no-records'>No records found in the keyword search log.</p>";
        }
        
        // Close connection
        $conn->close();
        ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
