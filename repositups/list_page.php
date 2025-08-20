<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is staff
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'MCIIS Staff') {  // Changed from userType to user_role
    // User is not staff - show access denied message and exit
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
            <p>MCIIS Staff only.</p>
            <a href="welcome.php" class="back-link">Return to Homepage</a>
        </div>
    </body>
    </html>';
    exit();
}

// Add this after your database connection code
function getDocumentPath($filepath) {
    if (empty($filepath)) return '';
    
    // Remove any leading slashes or backslashes
    $filepath = ltrim($filepath, '/\\');
    
    // Check if the file exists in different possible locations
    $possibilities = [
        __DIR__ . '/' . $filepath,
        __DIR__ . '/uploads/' . basename($filepath),
        'uploads/' . basename($filepath)
    ];
    
    foreach ($possibilities as $path) {
        if (file_exists($path)) {
            return $filepath;
        }
    }
    
    return '';
}

// Add this function to your list_page.php
function debugFilePath($filepath) {
    $fullPath = __DIR__ . '/' . $filepath;
    error_log("Checking file: " . $fullPath);
    error_log("File exists: " . (file_exists($fullPath) ? "Yes" : "No"));
    error_log("File permissions: " . substr(sprintf('%o', fileperms($fullPath)), -4));
    return file_exists($fullPath);
}

// Add this before your existing SQL query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = '';
if (!empty($search)) {
    $searchTerm = '%' . $conn->real_escape_string($search) . '%';
    $searchCondition = "WHERE 
        r.researchTitle LIKE '$searchTerm' OR
        r.researchAbstract LIKE '$searchTerm' OR
        f.firstName LIKE '$searchTerm' OR
        f.lastName LIKE '$searchTerm' OR
        EXISTS (
            SELECT 1 FROM researcher rs 
            WHERE rs.researchID = r.researchID AND 
            (rs.firstName LIKE '$searchTerm' OR rs.lastName LIKE '$searchTerm')
        ) OR
        EXISTS (
            SELECT 1 FROM researchkeyword rk 
            JOIN keyword k ON rk.keywordID = k.keywordID 
            WHERE rk.researchID = r.researchID AND k.keywordName LIKE '$searchTerm'
        )";
}

// Modify your existing SQL query to include the search condition
$sql = "SELECT 
            r.researchID,
            r.researchTitle, 
            r.program,
            r.publishedMonth,
            r.publishedYear,
            r.researchAbstract,
            r.researchApprovalSheet,
            r.researchManuscript,
            f.firstName AS adviserFirstName,
            f.middleName AS adviserMiddleName,
            f.lastName AS adviserLastName,
            u.firstName AS uploaderFirstName,
            u.middleName AS uploaderMiddleName,
            u.lastName AS uploaderLastName
        FROM research r
        LEFT JOIN faculty f ON r.researchAdviser = f.facultyID
        LEFT JOIN user u ON r.uploadedBy = u.userID
        $searchCondition
        ORDER BY r.publishedYear DESC, r.publishedMonth DESC";

$result = $conn->query($sql);

// Debug: Print query and result info
echo "<!-- Query executed: " . htmlspecialchars($sql) . " -->";
echo "<!-- Number of rows: " . ($result ? $result->num_rows : "Query failed") . " -->";

// Function to get researchers for a research
function getResearchers($conn, $researchID) {
    try {
        $sql = "SELECT firstName, middleName, lastName
                FROM researcher
                WHERE researchID = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return "Error preparing statement: " . $conn->error;
        }
        
        $stmt->bind_param("i", $researchID);
        $stmt->execute();
        $researcherResult = $stmt->get_result();
        
        $researchers = [];
        while($row = $researcherResult->fetch_assoc()) {
            $fullName = $row['firstName'];
            if (!empty($row['middleName'])) {
                $fullName .= ' ' . $row['middleName'];
            }
            $fullName .= ' ' . $row['lastName'];
            $researchers[] = $fullName;
        }
        $stmt->close();
        
        return empty($researchers) ? "No researchers assigned" : implode(", ", $researchers);
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Function to get panelists for a research
function getPanelists($conn, $researchID) {
    try {
        $sql = "SELECT f.firstName, f.middleName, f.lastName
                FROM panel p
                JOIN faculty f ON p.facultyID = f.facultyID
                WHERE p.researchID = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return "Error preparing statement: " . $conn->error;
        }
        
        $stmt->bind_param("i", $researchID);
        $stmt->execute();
        $panelistResult = $stmt->get_result();
        
        $panelists = [];
        while($row = $panelistResult->fetch_assoc()) {
            $fullName = $row['firstName'];
            if (!empty($row['middleName'])) {
                $fullName .= ' ' . $row['middleName'];
            }
            $fullName .= ' ' . $row['lastName'];
            $panelists[] = $fullName;
        }
        $stmt->close();
        
        return empty($panelists) ? "No panelists assigned" : implode(", ", $panelists);
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Function to get keywords for a research
function getKeywords($conn, $researchID) {
    try {
        $sql = "SELECT k.keywordName
                FROM researchkeyword rk
                JOIN keyword k ON rk.keywordID = k.keywordID
                WHERE rk.researchID = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return "Error preparing statement: " . $conn->error;
        }
        
        $stmt->bind_param("i", $researchID);
        $stmt->execute();
        $keywordResult = $stmt->get_result();
        
        $keywords = [];
        while($row = $keywordResult->fetch_assoc()) {
            $keywords[] = $row['keywordName'];
        }
        $stmt->close();
        
        return empty($keywords) ? "No keywords" : implode(", ", $keywords);
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Repository</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            width: 100%;
            padding: 0.5rem 0;
            background-color: rgb(35, 1, 68) !important;
        }

        .container-fluid {
            padding: 0 1rem;
        }

        .main-content {
            padding: 20px;
            flex: 1;
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

        .title-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .title-section h1 {
            color: #220044;
            margin-bottom: 1rem;
        }

        .upload-button {
            display: inline-block;
            background-color: #FF6600;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .upload-button:hover {
            background-color: #FF884D;
            color: white;
            text-decoration: none;
        }

        .research-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            padding: 20px;
            list-style-type: none;
            margin: 0;
        }

        .research-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #fff;
            height: 100%;
            display: flex;
            flex-direction: column;
            border-left: 4px solid #220044;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .research-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .edit-container {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: right;
        }

        .edit-button {
            background-color: #FF6600;
            border: none;
            color: white;
            padding: 8px 15px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin: 4px 2px;
            cursor: pointer;
            border-radius: 4px;
        }

        .edit-button:hover {
            background-color: #e65c00;
        }

        .research-content {
            flex: 1;
            padding: 15px;
            display: flex;
            flex-direction: column;
        }

        .research-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #220044;
            line-height: 1.4;
        }

        .research-details {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 8px;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .research-label {
            font-weight: bold;
            color: #220044;
        }

        .research-value {
            color: #333;
        }

        .abstract-container {
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .abstract-toggle {
            background-color: #FF6600;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
            color: white;
        }

        .abstract-toggle:hover {
            background-color: #e65c00;
        }

        .abstract-content {
            display: none;
            padding: 10px;
            border: 1px solid #ddd;
            margin-top: 5px;
            border-radius: 3px;
            background-color: #fff;
            white-space: pre-line;
        }

        .abstract-content.show {
            display: block;
        }

        .file-link {
            color: #220044;
            text-decoration: none;
            margin-right: 10px;
        }

        .file-link:hover {
            color: #FF6600;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination a {
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid #220044;
            margin: 0 4px;
            color: #220044;
        }

        .pagination a.active {
            background-color: #220044;
            color: white;
            border: 1px solid #220044;
        }

        .pagination a:hover:not(.active) {
            background-color: #FF6600;
            color: white;
            border-color: #FF6600;
        }

        .search-section {
            margin: 20px auto;
            max-width: 600px;
        }

        .search-container {
            display: flex;
            gap: 10px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #FF6600;
            box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.1);
        }

        .search-button {
            background-color: #FF6600;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .search-button:hover {
            background-color: #e65c00;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid position-relative justify-content-center">
                <!-- Home button (top left) -->
                <a href="welcome.php" class="btn btn-outline-light me-2 show-hamburger border-0 home-btn" 
                   style="position:absolute;left:0;top:50%;transform:translateY(-50%);z-index:1100;padding-left:16px;background:transparent;">
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

    <div class="main-content">
        <div class="title-section">
            <h1>Research List</h1>
            <a href="upload_research.php" class="upload-button">
                <i class="fas fa-upload"></i> Upload Research
            </a>
        </div>

        <div class="search-section">
            <form action="" method="GET" class="search-container">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search by title, researcher, adviser, or keywords..."
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit" class="search-button">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <ul class="research-list">
                <?php 
                while($row = $result->fetch_assoc()): 
                    echo "<!-- Processing row: " . htmlspecialchars($row['researchID']) . " -->";
                    
                    // Format adviser name
                    $adviserName = isset($row['adviserFirstName']) ? $row['adviserFirstName'] : 'N/A';
                    if (!empty($row['adviserMiddleName'])) {
                        $adviserName .= ' ' . $row['adviserMiddleName'];
                    }
                    if (isset($row['adviserLastName'])) {
                        $adviserName .= ' ' . $row['adviserLastName'];
                    }
                    
                    // Format uploader name
                    $uploaderName = isset($row['uploaderFirstName']) ? $row['uploaderFirstName'] : 'N/A';
                    if (!empty($row['uploaderMiddleName'])) {
                        $uploaderName .= ' ' . $row['uploaderMiddleName'];
                    }
                    if (isset($row['uploaderLastName'])) {
                        $uploaderName .= ' ' . $row['uploaderLastName'];
                    }
                    
                    // Format published date with full month name
                    $publishedDate = '';
                    $monthNames = [
                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                    ];

                    $month = intval($row['publishedMonth']);
                    $monthName = $monthNames[$month] ?? $row['publishedMonth'];

                    if (!empty($monthName)) {
                        $publishedDate .= $monthName . ' ';
                    }
                    if (!empty($row['publishedYear'])) {
                        $publishedDate .= $row['publishedYear'];
                    }
                    if (empty(trim($publishedDate))) {
                        $publishedDate = 'N/A';
                    }
                    
                    try {
                        $researchers = getResearchers($conn, $row['researchID']);
                        $panelists = getPanelists($conn, $row['researchID']);
                        $keywords = getKeywords($conn, $row['researchID']);
                    } catch (Exception $e) {
                        echo "<!-- Error: " . htmlspecialchars($e->getMessage()) . " -->";
                        $researchers = "Error retrieving researchers";
                        $panelists = "Error retrieving panelists";
                        $keywords = "Error retrieving keywords";
                    }
                ?>
                <li class="research-item">
                    <div class="edit-container">
                        <a href="edit_research.php?id=<?php echo $row['researchID']; ?>" class="edit-button">Edit</a>
                    </div>
                    
                    <div class="research-content">
                        <div class="research-title">
                            <?php echo htmlspecialchars($row['researchTitle'] ?? 'Untitled Research'); ?>
                        </div>
                        
                        <div class="research-details">
                            <div class="research-label">Researchers:</div>
                            <div class="research-value"><?php echo htmlspecialchars($researchers); ?></div>
                            
                            <div class="research-label">Research Adviser:</div>
                            <div class="research-value"><?php echo htmlspecialchars($adviserName); ?></div>
                            
                            <div class="research-label">Program:</div>
                            <div class="research-value"><?php echo htmlspecialchars($row['program'] ?? 'N/A'); ?></div>
                            
                            <div class="research-label">Published Date:</div>
                            <div class="research-value"><?php echo htmlspecialchars($publishedDate); ?></div>
                            
                            <div class="research-label">Panelists:</div>
                            <div class="research-value"><?php echo htmlspecialchars($panelists); ?></div>
                            
                            <div class="research-label">Keywords:</div>
                            <div class="research-value"><?php echo htmlspecialchars($keywords); ?></div>
                            
                            <div class="research-label">Uploaded By:</div>
                            <div class="research-value"><?php echo htmlspecialchars($uploaderName); ?></div>
                            
                            <div class="research-label">Research Approval Sheet:</div>
                            <div class="research-value">
                                <?php if (!empty($row['researchApprovalSheet'])): ?>
                                    <a href="download_file.php?type=approval&id=<?php echo $row['researchID']; ?>&preview=true" 
                                       class="file-link" 
                                       target="_blank">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="download_file.php?type=approval&id=<?php echo $row['researchID']; ?>" 
                                       class="file-link">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </div>

                            <div class="research-label">Research Manuscript:</div>
                            <div class="research-value">
                                <?php if (!empty($row['researchManuscript'])): ?>
                                    <a href="download_file.php?type=manuscript&id=<?php echo $row['researchID']; ?>&preview=true" 
                                       class="file-link" 
                                       target="_blank">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="download_file.php?type=manuscript&id=<?php echo $row['researchID']; ?>" 
                                       class="file-link">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="abstract-container">
                            <button class="abstract-toggle" onclick="toggleAbstract(this)">Show Abstract</button>
                            <div class="abstract-content">
                                <?php echo htmlspecialchars($row['researchAbstract'] ?? 'No abstract available'); ?>
                            </div>
                        </div>
                    </div>
                </li>
                <?php endwhile; ?>
            </ul>
            
            <div class="pagination">
                <a href="#">&laquo;</a>
                <a href="#" class="active">1</a>
                <a href="#">2</a>
                <a href="#">3</a>
                <a href="#">&raquo;</a>
            </div>
            
            <script>
                function toggleAbstract(button) {
                    const abstractContent = button.nextElementSibling;
                    abstractContent.classList.toggle('show');
                    button.textContent = abstractContent.classList.contains('show') ? 'Hide Abstract' : 'Show Abstract';
                }
            </script>
        <?php else: ?>
            <p>No research entries found. <?php echo $conn->error ? "Database error: " . htmlspecialchars($conn->error) : ""; ?></p>
        <?php endif; ?>
        
        <?php
        // Remove manual DB connection close, as config.php handles it
        echo "<!-- Script completed execution -->";
        ?>
    </div>
</body>
</html>