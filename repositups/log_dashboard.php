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

// Get counts for each log table
$keywordSearchCount = $conn->query("SELECT COUNT(*) as count FROM keywordsearchlog")->fetch_assoc()['count'];
$researchAccessCount = $conn->query("SELECT COUNT(*) as count FROM researchaccesslog")->fetch_assoc()['count'];
$researchEntryCount = $conn->query("SELECT COUNT(*) as count FROM researchentrylog")->fetch_assoc()['count'];
$userFacultyAuditCount = $conn->query("SELECT COUNT(*) as count FROM userfacultyauditlog")->fetch_assoc()['count'];

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repository Logs Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header h1 {
            color: #220044;
            margin: 20px 0;
            text-align: center;
            font-size: clamp(1.5rem, 2.5vw, 3rem);
            font-weight: 600;
            margin-top: 5px;
            margin-bottom: 50px;
        }

        .navbar-brand {
            font-weight: bold;
            letter-spacing: 1px;
            font-size: clamp(1.1rem, 2vw, 2rem);
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

        .card-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .card {
            text-align: center;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(34, 0, 68, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: #220044;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(34, 0, 68, 0.2);
        }

        .card-header {
            padding: 15px;
            font-weight: bold;
            font-size: 18px;
            border-bottom: 1px solid #eee;
            color: white;
        }

        .keyword-header {
            background-color: #220044;
        }

        .research-access-header {
            background-color: #FF6600;
        }

        .research-entry-header {
            background-color: #220044;
        }

        .user-faculty-header {
            background-color: #FF6600;
        }

        .card-body {
            padding: 20px;
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .count {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
            color: #220044;
        }

        .card-footer {
            background-color: #f9f9f9;
            padding: 10px;
            text-align: center;
            font-size: 14px;
            color: #FF6600;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            color: #220044;
            font-size: 14px;
        }

        .card:hover .card-footer {
            background-color: #220044;
            color: white;
        }

        .card-footer {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(35, 1, 68);">
        <div class="container-fluid position-relative justify-content-center">
            <a href="welcome.php" class="btn btn-outline-light me-2 show-hamburger border-0 home-btn" style="position:absolute;left:0;top:50%;transform:translateY(-50%);z-index:1100;padding-left:16px;background:transparent;">
                <i class="fas fa-home"></i>
            </a>
            <a class="navbar-brand mx-auto d-flex align-items-center justify-content-center" href="#">
                <img src="images/octopus-logo.png" alt="Logo" class="navbar-logo me-2">
                <span>Repositups</span>
            </a>
        </div>
    </nav>

    <div class="container">
        <header>
            <h1>Repository Logs Dashboard</h1>
        </header>
        
        <div class="card-container">
            <a href="keyword_search_log_display.php" class="card">
                <div class="card-header keyword-header">Keyword Search Log</div>
                <div class="card-body">
                    <div class="count"><?php echo $keywordSearchCount; ?></div>
                    <p>Total keyword searches recorded</p>
                </div>
                <div class="card-footer">Click to view all records</div>
            </a>
            
            <a href="research_access_log_display.php" class="card">
                <div class="card-header research-access-header">Research Access Log</div>
                <div class="card-body">
                    <div class="count"><?php echo $researchAccessCount; ?></div>
                    <p>Total research accesses recorded</p>
                </div>
                <div class="card-footer">Click to view all records</div>
            </a>
            
            <a href="research_entry_log_display.php" class="card">
                <div class="card-header research-entry-header">Research Entry Log</div>
                <div class="card-body">
                    <div class="count"><?php echo $researchEntryCount; ?></div>
                    <p>Total research entries recorded</p>
                </div>
                <div class="card-footer">Click to view all records</div>
            </a>
            
            <a href="user_faculty_audit_log_display.php" class="card">
                <div class="card-header user-faculty-header">User Faculty Audit Log</div>
                <div class="card-body">
                    <div class="count"><?php echo $userFacultyAuditCount; ?></div>
                    <p>Total user faculty audits recorded</p>
                </div>
                <div class="card-footer">Click to view all records</div>
            </a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
