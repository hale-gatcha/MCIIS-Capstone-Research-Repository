<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

// Check if user is Faculty, if not show access denied message
if ($_SESSION['user_role'] !== 'Faculty') {
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
            <p>Faculty only.</p>
            <a href="welcome.php" class="back-link">Return to Homepage</a>
        </div>
    </body>
    </html>';
    exit();
}

// Get the logged-in user's email
$userID = $_SESSION['user_id'];
$userEmail = '';
try {
    $stmt = $pdo->prepare("SELECT email FROM User WHERE userID = ?");
    $stmt->execute([$userID]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $userEmail = $row['email'];
    } else {
        throw new Exception("User not found.");
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Get the facultyID from Faculty table using email
$facultyID = '';
try {
    $stmt = $pdo->prepare("SELECT facultyID, firstName, lastName FROM Faculty WHERE email = ?");
    $stmt->execute([$userEmail]);
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($faculty) {
        $facultyID = $faculty['facultyID'];
        $facultyName = $faculty['firstName'] . ' ' . $faculty['lastName'];
    } else {
        die("Faculty record not found for this user.");
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Count advised research
$stmt = $pdo->prepare("SELECT COUNT(*) AS advised_count FROM Research WHERE researchAdviser = ?");
$stmt->execute([$facultyID]);
$advisedCount = $stmt->fetch(PDO::FETCH_ASSOC)['advised_count'];

// Count paneled research
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT researchID) AS paneled_count FROM Panel WHERE facultyID = ?");
$stmt->execute([$facultyID]);
$paneledCount = $stmt->fetch(PDO::FETCH_ASSOC)['paneled_count'];

// Get advised research details
$advisedResearch = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.researchID, r.researchTitle, r.publishedYear, r.program
        FROM Research r
        WHERE r.researchAdviser = ?
        ORDER BY r.publishedYear DESC, r.researchTitle
    ");
    $stmt->execute([$facultyID]);
    $advisedResearch = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}

// Get paneled research details
$paneledResearch = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.researchID, r.researchTitle, r.publishedYear, r.program
        FROM Research r
        JOIN Panel p ON r.researchID = p.researchID
        WHERE p.facultyID = ?
        GROUP BY r.researchID
        ORDER BY r.publishedYear DESC, r.researchTitle
    ");
    $stmt->execute([$facultyID]);
    $paneledResearch = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Research Stats</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html {
            font-size: clamp(14px, 2vw, 18px);
        }
        body {
            font-family: 'Inter', Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding-top: 0; /* Remove top padding */
            color: #222;
            min-height: 100vh;
            display: block;
        }
        .container {
            max-width: 900px;
            margin: 1rem auto; /* Reduce top margin */
            background: #fff;
            border-radius: 1.125em;
            box-shadow: 0 0.25em 1.5em rgba(34,0,68,0.10);
            padding: 2.5em 2vw;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .stats-title {
            text-align: center;
            margin-bottom: 2em;
            font-size: clamp(0.7rem, 3.5vw, 1.5rem);
            font-weight: 700;
            color: #FF6600;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6em;
        }
        .stats-title i {
            font-size: clamp(1.2em, 4vw, 2.2em);
            color: #2e003e;
            vertical-align: middle;
        }
        .stats-table {
            border-collapse: collapse;
            min-width: 320px;
            width: 100%;
            font-size: 1.08rem;
            background: #fafaff;
            border-radius: 0.75em;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
            margin-bottom: 2em;
        }
        .stats-table th, .stats-table td {
            border: 1.5px solid #e0e0e0;
            padding: 1em 1.25em;
            text-align: center;
            vertical-align: middle;
        }
        .stats-table th {
            background: #2e003e;
            color: #fff;
            font-size: 1.08em;
            font-weight: 600;
            text-align: center;
            vertical-align: middle;
        }
        .stats-table tr:nth-child(even) {
            background: #f3f3f9;
        }
        .stats-table tr:nth-child(odd) {
            background: #fafaff;
        }
        
        /* Research list styles */
        .research-section {
            width: 100%;
            margin-top: 1.5em;
        }
        .section-title {
            font-size: clamp(1rem, 3vw, 1.3rem);
            font-weight: 600;
            color: #2e003e;
            margin-bottom: 1em;
            display: flex;
            align-items: center;
            gap: 0.5em;
        }
        .section-title i {
            color: #FF6600;
        }
        .research-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .research-item {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 0.5em;
            margin-bottom: 0.8em;
            padding: 1em;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .research-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        .research-title {
            font-size: clamp(0.9rem, 2.5vw, 1.1rem);
            font-weight: 500;
            margin-bottom: 0.3em;
            color: #2e003e;
        }
        .research-meta {
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            color: #666;
            display: flex;
            flex-wrap: wrap;
            gap: 1em;
        }
        .research-meta span {
            display: flex;
            align-items: center;
            gap: 0.3em;
        }
        .research-meta i {
            color: #FF6600;
            font-size: 0.9em;
        }
        .empty-list {
            text-align: center;
            padding: 2em;
            color: #666;
            font-style: italic;
            background: #f8f9fa;
            border-radius: 0.5em;
        }
        
        /* Navigation styles */
        .navbar {
            background-color: rgb(35, 1, 68) !important;
            position: static; /* Change from fixed to static */
            margin-top: -70px;
        }

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
        body {
            padding-top: 70px;
            margin: 0;
        }
        
        /* Tabs styling */
        .nav-tabs {
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 1.5em;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        .nav-tabs .nav-item {
            margin-bottom: -2px;
        }
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            color: #666;
            font-weight: 500;
            padding: 0.8em 1.5em;
            transition: color 0.2s, border-color 0.2s;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }
        .nav-tabs .nav-link:hover {
            color: #2e003e;
            border-color: #FF6600;
        }
        .nav-tabs .nav-link.active {
            color: #2e003e;
            border-color: #FF6600;
            background: transparent;
        }
        .tab-content {
            width: 100%;
        }

        @media (max-width: 900px) {
            .container {
                max-width: 98vw;
                padding: 2em 2vw 2em 2vw;
            }
            .stats-title {
                font-size: clamp(1.1rem, 5vw, 1.5rem);
            }
            .stats-title i {
                font-size: clamp(1em, 6vw, 1.5em);
            }
            .stats-table {
                font-size: 1rem;
            }
            .research-item {
                padding: 0.8em;
            }
        }
        @media (max-width: 600px) {
            html {
                font-size: clamp(13px, 3vw, 16px);
            }
            .container {
                padding: 1.2em 0.5em 1.2em 0.5em; /* Reduced horizontal padding */
                max-width: 100%;
                margin: 1rem 0; /* Remove horizontal margin */
                border-radius: 0; /* Optional: remove border radius on mobile */
            }
            .stats-title {
                font-size: clamp(1rem, 6vw, 1.2rem);
            }
            .stats-title i {
                font-size: clamp(0.9em, 7vw, 1.1em);
            }
            .stats-table {
                min-width: 100%;
                width: 100%;
                font-size: 0.98rem;
                border-radius: 0; /* Remove border radius on mobile */
                margin-left: 0;
                margin-right: 0;
            }
            .stats-table th, .stats-table td {
                padding: 0.7em 0.4em;
            }
            .nav-tabs .nav-link {
                padding: 0.6em 1em;
                font-size: 0.9rem;
            }
            .research-title {
                font-size: 0.95rem;
            }
            .research-meta {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Updated navigation structure -->
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

    <!-- Main container -->
    <div class="container">
        <div class="stats-title">
            <i class="fas fa-chalkboard-teacher"></i>
            Research Statistics for <?= htmlspecialchars($facultyName) ?>
        </div>
        
        <!-- Summary table -->
        <table class="stats-table">
            <tr>
                <th>Type</th>
                <th>Count</th>
            </tr>
            <tr>
                <td>Advised Research</td>
                <td><?= $advisedCount ?></td>
            </tr>
            <tr>
                <td>Paneled Research</td>
                <td><?= $paneledCount ?></td>
            </tr>
        </table>
        
        <!-- Tabs for research lists -->
        <ul class="nav nav-tabs" id="researchTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="advised-tab" data-bs-toggle="tab" data-bs-target="#advised" type="button" role="tab" aria-controls="advised" aria-selected="true">
                    <i class="fas fa-user-edit me-1"></i> Advised Research
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="paneled-tab" data-bs-toggle="tab" data-bs-target="#paneled" type="button" role="tab" aria-controls="paneled" aria-selected="false">
                    <i class="fas fa-users me-1"></i> Paneled Research
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="researchTabsContent">
            <!-- Advised Research Tab -->
            <div class="tab-pane fade show active" id="advised" role="tabpanel" aria-labelledby="advised-tab">
                <div class="research-section">
                    <?php if (empty($advisedResearch)): ?>
                        <div class="empty-list">
                            <i class="fas fa-info-circle me-2"></i> No advised research found.
                        </div>
                    <?php else: ?>
                        <ul class="research-list">
                            <?php foreach ($advisedResearch as $research): ?>
                                <li class="research-item">
                                    <div class="research-title"><?= htmlspecialchars($research['researchTitle']) ?></div>
                                    <div class="research-meta">
                                        <?php if (!empty($research['program'])): ?>
                                            <span><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($research['program']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($research['publishedYear'])): ?>
                                            <span><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($research['publishedYear']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Paneled Research Tab -->
            <div class="tab-pane fade" id="paneled" role="tabpanel" aria-labelledby="paneled-tab">
                <div class="research-section">
                    <?php if (empty($paneledResearch)): ?>
                        <div class="empty-list">
                            <i class="fas fa-info-circle me-2"></i> No paneled research found.
                        </div>
                    <?php else: ?>
                        <ul class="research-list">
                            <?php foreach ($paneledResearch as $research): ?>
                                <li class="research-item">
                                    <div class="research-title"><?= htmlspecialchars($research['researchTitle']) ?></div>
                                    <div class="research-meta">
                                        <?php if (!empty($research['program'])): ?>
                                            <span><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($research['program']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($research['publishedYear'])): ?>
                                            <span><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($research['publishedYear']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>