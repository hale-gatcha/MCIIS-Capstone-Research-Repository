<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    // Not logged in, redirect to login page
    header("Location: login.php");
    exit();
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection parameters
require_once 'config.php';

// --- Handle AJAX research access logging ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['researchID']) && !isset($_POST['search'])) {
    $researchID = intval($_POST['researchID']);
    $userID = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

    if ($researchID && $userID) {
        $stmt = $pdo->prepare("INSERT INTO ResearchAccessLog (researchID, userID) VALUES (?, ?)");
        $stmt->execute([$researchID, $userID]);
        echo "OK";
    } else {
        http_response_code(400);
        echo "Invalid data";
    }
    exit; // Prevent further output for AJAX requests
}

// Handle form submission and filtering
$results = [];
$error = '';
$filterMode = false; // Track if filter is used

// Fetch filter options (advisers, programs, years)
$advisers = [];
$programs = [];
$years = [];
try {
    // Advisers
    $stmt = $pdo->query("SELECT facultyID, CONCAT(firstName, ' ', lastName) AS adviserName FROM Faculty ORDER BY adviserName");
    $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Programs
    $stmt = $pdo->query("SELECT DISTINCT program FROM Research WHERE program IS NOT NULL ORDER BY program");
    $programs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Years
    $stmt = $pdo->query("SELECT DISTINCT publishedYear FROM Research WHERE publishedYear IS NOT NULL ORDER BY publishedYear DESC");
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Ignore filter options error for now
}

// Load all research by default on initial page load (GET request)
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    try {
        $sql = "SELECT DISTINCT r.researchID, r.researchTitle, r.researchAbstract, r.program, 
                r.publishedMonth, r.publishedYear, r.researchManuscript, r.researchApprovalSheet,
                r.researchAdviser,
                CONCAT(f.firstName, ' ', f.lastName) AS adviserName,
                CONCAT(u.firstName, ' ', u.lastName) AS uploadedBy,
                GROUP_CONCAT(DISTINCT CONCAT(res.firstName, ' ', res.lastName) SEPARATOR ', ') AS researchers,
                GROUP_CONCAT(DISTINCT CONCAT(pf.firstName, ' ', pf.lastName) SEPARATOR ', ') AS panelists,
                GROUP_CONCAT(DISTINCT k.keywordName SEPARATOR ', ') AS keywords
                FROM Research r
                LEFT JOIN Faculty f ON r.researchAdviser = f.facultyID
                LEFT JOIN User u ON r.uploadedBy = u.userID
                LEFT JOIN Researcher res ON r.researchID = res.researchID
                LEFT JOIN Panel p ON r.researchID = p.researchID
                LEFT JOIN Faculty pf ON p.facultyID = pf.facultyID
                LEFT JOIN ResearchKeyword rk ON r.researchID = rk.researchID
                LEFT JOIN Keyword k ON rk.keywordID = k.keywordID
                GROUP BY r.researchID ORDER BY r.researchTitle";
        
        $result = $conn->query($sql);
        $results = $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle combined search and filter
$search = !empty($_POST['search']) ? trim($_POST['search']) : null;
$adviserID = !empty($_POST['adviserID']) ? $_POST['adviserID'] : null;
$program = !empty($_POST['program']) ? $_POST['program'] : null;
$year = !empty($_POST['year']) ? $_POST['year'] : null;

// Check if any search or filter criteria is provided
$hasSearchCriteria = $search || $adviserID || $program || $year;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Build dynamic query based on provided criteria
        $sql = "SELECT DISTINCT r.researchID, r.researchTitle, r.researchAbstract, r.program, 
                r.publishedMonth, r.publishedYear, r.researchManuscript, r.researchApprovalSheet,
                r.researchAdviser,
                CONCAT(f.firstName, ' ', f.lastName) AS adviserName,
                CONCAT(u.firstName, ' ', u.lastName) AS uploadedBy,
                GROUP_CONCAT(DISTINCT CONCAT(res.firstName, ' ', res.lastName) SEPARATOR ', ') AS researchers,
                GROUP_CONCAT(DISTINCT CONCAT(pf.firstName, ' ', pf.lastName) SEPARATOR ', ') AS panelists,
                GROUP_CONCAT(DISTINCT k.keywordName SEPARATOR ', ') AS keywords
                FROM Research r
                LEFT JOIN Faculty f ON r.researchAdviser = f.facultyID
                LEFT JOIN User u ON r.uploadedBy = u.userID
                LEFT JOIN Researcher res ON r.researchID = res.researchID
                LEFT JOIN Panel p ON r.researchID = p.researchID
                LEFT JOIN Faculty pf ON p.facultyID = pf.facultyID
                LEFT JOIN ResearchKeyword rk ON r.researchID = rk.researchID
                LEFT JOIN Keyword k ON rk.keywordID = k.keywordID
                WHERE 1=1";
        
        $params = [];
        $types = "";
        
        // Add search criteria
        if ($search) {
            $sql .= " AND (r.researchTitle LIKE ? OR k.keywordName LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "ss";
        }
        
        // Add filter criteria
        if ($adviserID) {
            $sql .= " AND r.researchAdviser = ?";
            $params[] = $adviserID;
            $types .= "s";
        }
        
        if ($program) {
            $sql .= " AND r.program = ?";
            $params[] = $program;
            $types .= "s";
        }
        
        if ($year) {
            $sql .= " AND r.publishedYear = ?";
            $params[] = $year;
            $types .= "i";
        }
        
        $sql .= " GROUP BY r.researchID ORDER BY r.researchTitle";
        
        // Use mysqli for dynamic query
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $results = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Log keyword search if it exists in the Keyword table
        if ($search) {
            $keywordStmt = $pdo->prepare("SELECT keywordID FROM Keyword WHERE LOWER(keywordName) = LOWER(?)");
            $keywordStmt->execute([$search]);
            $keywordRow = $keywordStmt->fetch(PDO::FETCH_ASSOC);

            if ($keywordRow && isset($_SESSION['user_id'])) {
                $userID = intval($_SESSION['user_id']);
                $keywordID = intval($keywordRow['keywordID']);
                $logStmt = $pdo->prepare("INSERT INTO KeywordSearchLog (keywordID, userID) VALUES (?, ?)");
                $logStmt->execute([$keywordID, $userID]);
            }
        }

    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Repository</title>
    <!-- Add Inter font from Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Inter', sans-serif; /* Use Inter font */
        }
        .navbar-brand {
            font-weight: bold;
            letter-spacing: 1px;
            font-size: clamp(1.1rem, 2vw, 2rem);
            font-family: 'Inter', sans-serif;
        }
        .research-item {
            margin-bottom: 0;
        }
        
        .card {
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: box-shadow 0.2s, transform 0.2s;
            font-size: clamp(0.95rem, 1.5vw, 1.1rem);
            font-family: 'Inter', sans-serif;
        }
        .card-title {
            font-size: clamp(1.1rem, 2vw, 1.5rem);
            font-family: 'Inter', sans-serif;
        }
        .card-text,
        .card-body {
            font-size: clamp(0.95rem, 1.5vw, 1.1rem);
            font-family: 'Inter', sans-serif;
        }
        .btn, .form-control, .form-label, .alert {
            font-size: clamp(0.9rem, 1.2vw, 1.05rem);
            font-family: 'Inter', sans-serif;
        }

        .search-btn {
            background-color: #FF6600;
            border-color: #FF6600;
            color: #fff;
            transition: background 0.15s, border-color 0.15s;
        }
        .search-btn:hover, .search-btn:focus {
            background-color: #FF884D;
            border-color: #FF884D;
            color: #fff;
        }
        .filter-btn {
            background-color: #FF6600;
            border-color: #FF6600;
            color: #fff;
            transition: background 0.15s, border-color 0.15s;
        }
        .filter-btn:hover, .filter-btn:focus {
            background-color: #FF884D;
            border-color: #FF884D;
            color: #fff;
        }
        
        .clear-filter-btn {
            background-color: #220044;
            border-color: #220044;
            color: #fff;
            transition: none;
            font-size: 0.95rem;
            padding: 0.25rem 0.5rem;
        }
        .clear-filter-btn:hover,
        .clear-filter-btn:focus,
        .clear-filter-btn:active,
        .clear-filter-btn.active {
            background-color: #220044 !important;
            border-color: #220044 !important;
            color: #fff !important;
        }

        /* Responsive adjustments for filter buttons */
        @media (min-width: 768px) {
            .filter-form-wrapper .d-grid.gap-2 {
                display: flex !important;
                gap: 0.5rem;
            }
            .filter-form-wrapper .d-grid.gap-2 .btn {
                flex: 1;
            }
        }

        @media (max-width: 767.98px) {
            .filter-form-wrapper .clear-filter-btn {
                font-size: 0.8rem;
                padding: 0.15rem 0.3rem;
            }
            .filter-form-wrapper .d-grid.gap-2 {
                display: grid !important;
                grid-template-columns: 1fr 1fr;
                gap: 0.25rem;
            }
        }
        
        .filter-section {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            padding: clamp(8px, 2vw, 16px) clamp(8px, 2vw, 16px) clamp(2px, 0.5vw, 4px) clamp(8px, 2vw, 16px); /* reduced padding */
            margin-bottom: clamp(12px, 2vw, 24px);
        }
        .filter-form-wrapper .form-label,
        .filter-form-wrapper .form-select,
        .filter-form-wrapper .btn {
            font-size: 0.95rem; /* smaller font */
            padding: 0.25rem 0.5rem; /* smaller padding */
        }
        .filter-form-wrapper .form-select {
            min-height: 32px;
        }
        @media (max-width: 767.98px) {
            .filter-form-wrapper {
                max-width: 350px;
                margin: 0 auto;
                padding: 12px 8px 8px 8px; /* smaller padding */
            }
            .filter-section {
                padding: 8px 4px 4px 4px; /* even smaller on mobile */
            }
            .filter-form-wrapper .form-label,
            .filter-form-wrapper .form-select,
            .filter-form-wrapper .btn {
                font-size: 0.8rem; /* even smaller font */
                padding: 0.15rem 0.3rem; /* even smaller padding */
            }
            .filter-form-wrapper .form-select {
                min-height: 28px; /* reduce min-height */
            }
        }
        /* --- Sticky footer styles --- */
        html, body {
            height: 100%;
        }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container-fluid.flex-grow-1 {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
        }
        /* Responsive filter toggle */
        @media (max-width: 767.98px) {
            .filter-toggle-btn {
                display: block !important;
                margin-bottom: 12px;
            }
            .filter-form-wrapper {
                display: none;
                position: static;
                width: 100%;
                background: #fff;
                padding: 8px 4px 4px 4px;
                box-shadow: none;
                border-radius: 8px;
                margin: 0;
                max-width: 100%;
            }
            .filter-form-wrapper.active {
                display: block;
                position: absolute; /* changed from fixed to absolute */
                top: 100%;          /* position just below the toggle button */
                left: 0;
                right: 0;
                z-index: 1060;
                max-width: 100vw;
                margin: 0;
            }
            .filter-backdrop {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.2);
                z-index: 1050;
            }
            .filter-backdrop.active {
                display: block;
            }
        }
        @media (min-width: 768px) {
            .filter-toggle-btn {
                display: none !important;
            }
            .filter-form-wrapper {
                display: block !important;
                position: static;
                box-shadow: none;
                border-radius: 8px;
                padding-bottom: 0;
            }
            .filter-backdrop {
                display: none !important;
            }
        }
        @media (max-width: 767.98px) {
            .card-body > .d-none.d-md-block {
                display: none !important;
            }
            .card-title .d-block.d-md-none {
                display: block !important;
            }
            .card-title .d-none.d-md-block {
                display: none !important;
            }
        }
        
        .show-hamburger:hover {
            color: #FF884D !important; /* Optional: lighter orange on hover */
        }

        /* Custom styling for the View Abstract button */
        .btn-outline-primary {
            color: #fff;
            border-color: #220044; /* DeepPink color */
            background-color: #220044;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover, 
        .btn-outline-primary:focus, 
        .btn-outline-primary:active,
        .btn-outline-primary.active,
        .btn-outline-primary[aria-expanded="true"] {
            color: #fff;
            background-color: #220044 !important; 
            border-color:  #220044!important;
        }
    </style>
    <script>
    function logAccess(researchID) {
        fetch('welcome.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'researchID=' + encodeURIComponent(researchID)
        });
    }
    // Toggle filter form on mobile
    document.addEventListener('DOMContentLoaded', function() {
        var toggleBtn = document.getElementById('filterToggleBtn');
        var filterWrapper = document.getElementById('filterFormWrapper');
        var backdrop = document.getElementById('filterBackdrop');
        if (toggleBtn && filterWrapper && backdrop) {
            toggleBtn.addEventListener('click', function() {
                var isActive = filterWrapper.classList.toggle('active');
                backdrop.classList.toggle('active', isActive);
                toggleBtn.innerHTML = isActive
                    ? '<i class="fas fa-filter"></i> Hide Filters'
                    : '<i class="fas fa-filter"></i> Show Filters';
            });
            backdrop.addEventListener('click', function() {
                filterWrapper.classList.remove('active');
                backdrop.classList.remove('active');
                toggleBtn.innerHTML = '<i class="fas fa-filter"></i> Show Filters';
            });
        }
    });
    </script>
</head>
<body>
    <!-- Sidebar Offcanvas Menu -->
    <div class="offcanvas offcanvas-start custom-offcanvas" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title d-flex align-items-center" id="sidebarMenuLabel" style="gap: 8px;">
                <img src="images/octopus-logo.png" alt="Logo" class="sidebar-logo">
                <span class="sidebar-title">Repositups</span>
            </h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body px-2">
            <ul class="list-unstyled sidebar-list">
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Administrator'): ?>
                    <li><a href="profile.php" class="nav-link sidebar-link"><i class="fas fa-user-graduate me-2"></i> <span class="sidebar-link-text">My Profile</span></a></li>
                    <li><a href="list_users.php" class="nav-link sidebar-link"><i class="fas fa-users-cog me-2"></i> <span class="sidebar-link-text">Manage Users</span></a></li>
                    <li><a href="adminstats.php" class="nav-link sidebar-link"><i class="fas fa-chart-bar me-2"></i> <span class="sidebar-link-text">View Statistics</span></a></li>
                    <li><a href="log_dashboard.php" class="nav-link sidebar-link"><i class="fas fa-file-alt me-2"></i> <span class="sidebar-link-text">View Logs</span></a></li>
                    <li><a href="feedback.php" class="nav-link sidebar-link"><i class="fas fa-envelope me-2"></i> <span class="sidebar-link-text">View Feedback</span></a></li>
                    <li><a href="faculty_staff_list.php" class="nav-link sidebar-link"><i class="fas fa-user-tie me-2"></i> <span class="sidebar-link-text">View Faculty</span></a></li>
                    <li><a href="logout.php" class="nav-link sidebar-link"><i class="fas fa-sign-out-alt me-2"></i> <span class="sidebar-link-text">Logout</span></a></li>
                <?php elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'MCIIS Staff'): ?>
                    <li><a href="profile.php" class="nav-link sidebar-link"><i class="fas fa-user-graduate me-2"></i> <span class="sidebar-link-text">My Profile</span></a></li>
                    <li><a href="upload_research.php" class="nav-link sidebar-link"><i class="fas fa-upload me-2"></i> <span class="sidebar-link-text">Upload Research</span></a></li>
                    <li><a href="list_page.php" class="nav-link sidebar-link"><i class="fas fa-book-open me-2"></i> <span class="sidebar-link-text">View Research</span></a></li>
                    <li><a href="staffstats.php" class="nav-link sidebar-link"><i class="fas fa-chart-bar me-2"></i> <span class="sidebar-link-text">View Statistics</span></a></li>
                    <li><a href="faculty_list.php" class="nav-link sidebar-link"><i class="fas fa-user-tie me-2"></i> <span class="sidebar-link-text">View Faculty</span></a></li>
                    <li><a href="logout.php" class="nav-link sidebar-link"><i class="fas fa-sign-out-alt me-2"></i> <span class="sidebar-link-text">Logout</span></a></li>
                <?php elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Faculty'): ?>
                    <li><a href="profile.php" class="nav-link sidebar-link"><i class="fas fa-user-graduate me-2"></i> <span class="sidebar-link-text">My Profile</span></a></li>
                    <li><a href="upload_research.php" class="nav-link sidebar-link"><i class="fas fa-upload me-2"></i> <span class="sidebar-link-text">Upload Research</span></a></li>
                    <li><a href="facultystats.php" class="nav-link sidebar-link"><i class="fas fa-book-open me-2"></i> <span class="sidebar-link-text">View Statistics</span></a></li>
                    <li><a href="faculty_list.php" class="nav-link sidebar-link"><i class="fas fa-user-tie me-2"></i> <span class="sidebar-link-text">View Faculty</span></a></li>
                    <li><a href="logout.php" class="nav-link sidebar-link"><i class="fas fa-sign-out-alt me-2"></i> <span class="sidebar-link-text">Logout</span></a></li>
                <?php elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Student'): ?>
                    <li><a href="profile.php" class="nav-link sidebar-link"><i class="fas fa-user-graduate me-2"></i> <span class="sidebar-link-text">My Profile</span></a></li>
                    <li><a href="faculty_list.php" class="nav-link sidebar-link"><i class="fas fa-user-tie me-2"></i> <span class="sidebar-link-text">View Faculty</span></a></li>
                    <li><a href="logout.php" class="nav-link sidebar-link"><i class="fas fa-sign-out-alt me-2"></i> <span class="sidebar-link-text">Logout</span></a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Header Navigation Bar -->
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(35, 1, 68);">
            <div class="container-fluid position-relative justify-content-center">
                <!-- Hamburger button (top left) -->
                <button class="btn btn-outline-light me-2 show-hamburger border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" style="position:absolute;left:0;top:50%;transform:translateY(-50%);z-index:1100;padding-left:16px;background:transparent;">
                    <i class="fas fa-bars hamburger-icon"></i>
                </button>
                <!-- Centered brand -->
                <a class="navbar-brand mx-auto d-flex align-items-center justify-content-center" href="#">
                    <img src="images/octopus-logo.png" alt="Logo" class="navbar-logo me-2">
                    <span>Repositups</span>
                </a>
            </div>
        </nav>
    </header>

    <!-- Style -->
    <style>
        /* LOGO styles */
        .navbar-logo {
            height: clamp(32px, 6vw, 80px);
            width: auto;
        }

        .sidebar-logo {
            height: clamp(24px, 4vw, 36px);
            width: auto;
            vertical-align: middle;
            position: relative;
            z-index: 2001 !important; /* Increase z-index to be above offcanvas content */
            background: #fff; /* Optional: add background if needed to prevent see-through */
        }

        .sidebar-logo:hover {
            opacity: 1 !important;
            /* Ensure no filter or opacity is applied on hover */
            z-index: 2002 !important;
        }

        /* Offcanvas customizations */
        .custom-offcanvas.offcanvas-start {
            width: clamp(260px, 28vw, 300px);
            background: #fff;
            border-right: 1px solid #e0e0e0;
            z-index: 2000; /* Make sure offcanvas is above navbar */
        }

        .sidebar-title {
            font-size: clamp(1.1rem, 2vw, 1.4rem);
            font-weight: bold;
            letter-spacing: 1px;
            color: #230144;
        }

        .sidebar-list {
            padding-top: clamp(8px, 2vw, 16px);
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: clamp(6px, 1vw, 12px);
            font-size: clamp(0.95rem, 1.5vw, 1.1rem);
            padding: clamp(6px, 1vw, 12px) clamp(8px, 2vw, 16px);
            border-radius: 6px;
            transition: background 0.15s, color 0.15s;
        }

        .sidebar-link:hover, .sidebar-link:focus {
            background: #f2f0fa;
            color: #230144;
            text-decoration: none;
        }

        .sidebar-link i {
            font-size: clamp(1.1rem, 2vw, 1.3rem);
            min-width: 1.2em;
            text-align: center;
        }

        .sidebar-link-text {
            display: inline;
        }

        .hamburger-icon {
            font-size: clamp(1.1rem, 4vw, 2.2rem);
        }

        /* Responsive Tweaks for small devices */
        @media (max-width: 575.98px) {
            .custom-offcanvas.offcanvas-start {
                width: clamp(180px, 60vw, 240px);
            }

            .sidebar-title {
                font-size: clamp(1rem, 4vw, 1.2rem);
            }

            .sidebar-link {
                font-size: clamp(0.85rem, 2.5vw, 1rem);
                padding: clamp(4px, 1vw, 8px) clamp(6px, 2vw, 12px);
            }

            .sidebar-logo {
                height: clamp(18px, 6vw, 24px);
            }

            .sidebar-link i {
                font-size: clamp(1rem, 3vw, 1.1rem);
            }
        }

        @media (max-width: 400px) {
            .sidebar-title {
                font-size: 0.95rem;
            }

            .sidebar-link-text {
                font-size: 0.85rem;
            }
        }
    </style>

    <!-- Filter Backdrop for mobile -->
    <div class="filter-backdrop" id="filterBackdrop"></div>

    <!-- Add a flex-grow-1 wrapper for main content -->
    <div class="container-fluid flex-grow-1 d-flex flex-column">
        <!-- Error/Info Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Filter & Search Section -->
        <div class="filter-section">
            <!-- Search Form (Always Visible) -->
            <form method="POST" class="row g-2 mb-2">
                <div class="col-10 col-md-10">
                    <input type="text" name="search" placeholder="Search by Title or Keyword" class="form-control" value="<?php echo isset($_POST['search']) ? htmlspecialchars($_POST['search']) : ''; ?>">
                </div>
                <div class="col-2 col-md-2 d-grid">
                    <button type="submit" class="btn search-btn px-2 py-2" title="Search" style="min-width:40px;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <!-- Hidden inputs to preserve filter state -->
                <input type="hidden" name="adviserID" value="<?php echo isset($_POST['adviserID']) ? htmlspecialchars($_POST['adviserID']) : ''; ?>">
                <input type="hidden" name="program" value="<?php echo isset($_POST['program']) ? htmlspecialchars($_POST['program']) : ''; ?>">
                <input type="hidden" name="year" value="<?php echo isset($_POST['year']) ? htmlspecialchars($_POST['year']) : ''; ?>">
            </form>
            <div style="position: relative;">
                <button type="button" class="btn btn-outline-secondary w-100 mb-2 filter-toggle-btn" id="filterToggleBtn" style="display:none;">
                    <i class="fas fa-filter"></i> Show Filters
                </button>
                <div class="filter-form-wrapper" id="filterFormWrapper">
                    <form method="POST" class="row g-3 align-items-end mb-2">
                        <!-- Hidden input to preserve search term -->
                        <input type="hidden" name="search" value="<?php echo isset($_POST['search']) ? htmlspecialchars($_POST['search']) : ''; ?>">
                        <div class="col-md-4">
                            <label for="adviserID" class="form-label">Adviser</label>
                            <select name="adviserID" id="adviserID" class="form-select">
                                <option value="">All Advisers</option>
                                <?php foreach ($advisers as $adv): ?>
                                    <option value="<?php echo htmlspecialchars($adv['facultyID']); ?>"
                                        <?php if (isset($_POST['adviserID']) && $_POST['adviserID'] === $adv['facultyID']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($adv['adviserName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="program" class="form-label">Program</label>
                            <select name="program" id="program" class="form-select">
                                <option value="">All Programs</option>
                                <?php foreach ($programs as $prog): ?>
                                    <option value="<?php echo htmlspecialchars($prog); ?>"
                                        <?php if (isset($_POST['program']) && $_POST['program'] === $prog) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($prog); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="year" class="form-label">Year</label>
                            <select name="year" id="year" class="form-select">
                                <option value="">All Years</option>
                                <?php foreach ($years as $yr): ?>
                                    <option value="<?php echo htmlspecialchars($yr); ?>"
                                        <?php if (isset($_POST['year']) && $_POST['year'] == $yr) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($yr); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-grid gap-2">
                            <button type="submit" class="btn filter-btn">Filter</button>
                            <a href="welcome.php" class="btn clear-filter-btn">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Research Results -->
        <?php if (!empty($results)): ?>
            <div class="row g-4">
                <?php foreach ($results as $row): ?>
                    <div class="col-12 col-md-6 col-lg-4 research-item">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <!-- Research Title (clickable on mobile) -->
                                <h5 class="card-title">
                                    <span 
                                        class="d-block d-md-none text-primary" 
                                        style="cursor:pointer;" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#researchModal-<?php echo $row["researchID"]; ?>"
                                        onclick="logAccess(<?php echo $row['researchID']; ?>)"
                                    >
                                        <?php echo htmlspecialchars($row["researchTitle"]); ?>
                                    </span>
                                    <span class="d-none d-md-block">
                                        <?php echo htmlspecialchars($row["researchTitle"]); ?>
                                    </span>
                                </h5>
                                <!-- Hide details on mobile, show on md+ -->
                                <div class="d-none d-md-block">
                                    <p class="card-text mb-2">
                                        <strong>Adviser:</strong> <?php echo htmlspecialchars($row["adviserName"] ?? $row["researchAdviser"] ?? ''); ?><br>
                                        <strong>Program:</strong> <?php echo htmlspecialchars($row["program"]); ?><br>
                                        <strong>Published:</strong>
                                        <?php
                                            $monthNum = $row["publishedMonth"] ?? '';
                                            $year = $row["publishedYear"] ?? '';
                                            $monthName = '';
                                            if ($monthNum && $year) {
                                                $dateObj = DateTime::createFromFormat('!m', $monthNum);
                                                $monthName = $dateObj ? $dateObj->format('F') : $monthNum;
                                                echo htmlspecialchars($monthName . ' ' . $year);
                                            } else {
                                                echo htmlspecialchars($monthNum . ' ' . $year);
                                            }
                                        ?><br>
                                        <?php if (!empty($row["uploadedBy"])): ?>
                                            <strong>Uploaded By:</strong> <?php echo htmlspecialchars($row["uploadedBy"]); ?>
                                        <?php endif; ?>
                                    </p>
                                    <div class="mb-2">
                                        <button class="btn btn-sm btn-outline-primary"
                                                type="button"
                                                id="abstractBtn-<?php echo $row["researchID"]; ?>"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#abstract-<?php echo $row["researchID"]; ?>"
                                                aria-expanded="false"
                                                aria-controls="abstract-<?php echo $row["researchID"]; ?>">
                                            View Abstract
                                        </button>
                                        <div class="collapse mt-2" id="abstract-<?php echo $row["researchID"]; ?>">
                                            <div class="card card-body">
                                                <?php echo nl2br(htmlspecialchars($row["researchAbstract"])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Researchers:</strong> <?php echo htmlspecialchars($row["researchers"] ?? ''); ?><br>
                                        <strong>Panelists:</strong> <?php echo htmlspecialchars($row["panelists"] ?? ''); ?><br>
                                        <strong>Keywords:</strong> <?php echo htmlspecialchars($row["keywords"] ?? ''); ?>
                                    </div>
                                    <div class="row mt-auto">
                                        <div class="col">
                                            <strong>Manuscript:</strong>
                                            <?php if(!empty($row["researchManuscript"])): ?>
                                                <button class="btn btn-sm btn-info" type="button"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#manuscriptModal-<?php echo $row["researchID"]; ?>"
                                                        onclick="logAccess(<?php echo $row['researchID']; ?>)">
                                                    <i class="fas fa-file-pdf"></i> View Manuscript
                                                </button>
                                                <!-- Modal for Manuscript -->
                                                <div class="modal fade" id="manuscriptModal-<?php echo $row["researchID"]; ?>" tabindex="-1" aria-labelledby="manuscriptModalLabel-<?php echo $row["researchID"]; ?>" aria-hidden="true">
                                                    <div class="modal-dialog modal-xl">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="manuscriptModalLabel-<?php echo $row["researchID"]; ?>">Research Manuscript Preview</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body" style="height:80vh;">
                                                                <iframe src="download_file.php?type=manuscript&id=<?php echo $row["researchID"]; ?>&preview=1" width="100%" height="100%" style="border:none;"></iframe>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <a href="download_file.php?type=manuscript&id=<?php echo $row["researchID"]; ?>" class="btn btn-success" download>
                                                                    <i class="fas fa-download"></i> Download
                                                                </a>
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col">
                                            <strong>Approval Sheet:</strong>
                                            <?php if(!empty($row["researchApprovalSheet"])): ?>
                                                <button class="btn btn-sm btn-secondary" type="button"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#approvalModal-<?php echo $row["researchID"]; ?>"
                                                        onclick="logAccess(<?php echo $row['researchID']; ?>)">
                                                    <i class="fas fa-file-alt"></i> View Approval Sheet
                                                </button>
                                                <!-- Modal for Approval Sheet -->
                                                <div class="modal fade" id="approvalModal-<?php echo $row["researchID"]; ?>" tabindex="-1" aria-labelledby="approvalModalLabel-<?php echo $row["researchID"]; ?>" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="approvalModalLabel-<?php echo $row["researchID"]; ?>">Approval Sheet Preview</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body" style="height:70vh;">
                                                                <iframe src="download_file.php?type=approval&id=<?php echo $row["researchID"]; ?>&preview=1" width="100%" height="100%" style="border:none;"></iframe>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <a href="download_file.php?type=approval&id=<?php echo $row["researchID"]; ?>" class="btn btn-success" download>
                                                                    <i class="fas fa-download"></i> Download
                                                                </a>
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <!-- End details for desktop -->
                            </div>
                        </div>
                    </div>

                    <!-- Modal for mobile: Full research info -->
                    <div class="modal fade" id="researchModal-<?php echo $row["researchID"]; ?>" tabindex="-1" aria-labelledby="researchModalLabel-<?php echo $row["researchID"]; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="researchModalLabel-<?php echo $row["researchID"]; ?>">
                                        <?php echo htmlspecialchars($row["researchTitle"]); ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>
                                        <strong>Adviser:</strong> <?php echo htmlspecialchars($row["adviserName"] ?? $row["researchAdviser"] ?? ''); ?><br>
                                        <strong>Program:</strong> <?php echo htmlspecialchars($row["program"]); ?><br>
                                        <strong>Published:</strong>
                                        <?php
                                            $monthNum = $row["publishedMonth"] ?? '';
                                            $year = $row["publishedYear"] ?? '';
                                            $monthName = '';
                                            if ($monthNum && $year) {
                                                $dateObj = DateTime::createFromFormat('!m', $monthNum);
                                                $monthName = $dateObj ? $dateObj->format('F') : $monthNum;
                                                echo htmlspecialchars($monthName . ' ' . $year);
                                            } else {
                                                echo htmlspecialchars($monthNum . ' ' . $year);
                                            }
                                        ?><br>
                                        <?php if (!empty($row["uploadedBy"])): ?>
                                            <strong>Uploaded By:</strong> <?php echo htmlspecialchars($row["uploadedBy"]); ?><br>
                                        <?php endif; ?>
                                    </p>
                                    <div class="mb-2">
                                        <strong>Abstract:</strong>
                                        <div class="card card-body">
                                            <?php echo nl2br(htmlspecialchars($row["researchAbstract"])); ?>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Researchers:</strong> <?php echo htmlspecialchars($row["researchers"] ?? ''); ?><br>
                                        <strong>Panelists:</strong> <?php echo htmlspecialchars($row["panelists"] ?? ''); ?><br>
                                        <strong>Keywords:</strong> <?php echo htmlspecialchars($row["keywords"] ?? ''); ?>
                                    </div>
                                    <div class="row">
                                        <div class="col">
                                            <strong>Manuscript:</strong>
                                            <?php if(!empty($row["researchManuscript"])): ?>
                                                <a href="download_file.php?type=manuscript&id=<?php echo $row["researchID"]; ?>" class="btn btn-sm btn-info" target="_blank" onclick="logAccess(<?php echo $row['researchID']; ?>)">
                                                    <i class="fas fa-file-pdf"></i> View Manuscript
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col">
                                            <strong>Approval Sheet:</strong>
                                            <?php if(!empty($row["researchApprovalSheet"])): ?>
                                                <a href="download_file.php?type=approval&id=<?php echo $row["researchID"]; ?>" class="btn btn-sm btn-secondary" target="_blank" onclick="logAccess(<?php echo $row['researchID']; ?>)">
                                                    <i class="fas fa-file-alt"></i> View Approval Sheet
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info mt-4">
                No research papers found. Please try a different search or filter.
            </div>
        <?php endif; ?>
    </div>
    <footer>
    </footer>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // For each abstract button, handle toggle and logging
    <?php foreach ($results as $row): ?>
    (function() {
        var btn = document.getElementById('abstractBtn-<?php echo $row["researchID"]; ?>');
        var collapse = document.getElementById('abstract-<?php echo $row["researchID"]; ?>');
        if (btn && collapse) {
            collapse.addEventListener('show.bs.collapse', function () {
                btn.textContent = 'Hide Abstract';
                logAccess(<?php echo $row['researchID']; ?>);
            });
            collapse.addEventListener('hide.bs.collapse', function () {
                btn.textContent = 'View Abstract';
            });
        }
    })();
    <?php endforeach; ?>
});
</script>
</body>
</html>
