<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is MCIIS Staff
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'MCIIS Staff') {
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

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');

// Database connection
require_once 'config.php';

// Add a constant for upload directory
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB in bytes
define('MIN_ABSTRACT_LENGTH', 255);

// Create the uploads directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
    chmod(UPLOAD_DIR, 0777);
}

// Verify upload directory is writable
if (!is_writable(UPLOAD_DIR)) {
    error_log("Upload directory is not writable: " . UPLOAD_DIR);
    die("Upload directory is not writable. Please check permissions.");
}

// Initialize variables
$researchID = isset($_GET['id']) ? intval($_GET['id']) : 0;
$errorMsg = '';
$successMsg = '';
$research = null;
$allFaculty = [];
$allUsers = [];
$allKeywords = [];
$researchKeywords = [];
$researchers = [];
$panelists = [];
$allPrograms = [];

// Fetch unique programs from research table
$programQuery = "SELECT DISTINCT program FROM research WHERE program IS NOT NULL ORDER BY program";
$programResult = $conn->query($programQuery);
if ($programResult) {
    while ($row = $programResult->fetch_assoc()) {
        if(!empty($row['program'])) {
            $allPrograms[] = $row['program'];
        }
    }
}

// Fetch existing research details
try {
    $stmt = $conn->prepare("SELECT * FROM research WHERE researchID = ?");
    $stmt->bind_param("i", $researchID);
    $stmt->execute();
    $result = $stmt->get_result();
    $research = $result->fetch_assoc();
    
    if (!$research) {
        die("Research not found.");
    }
    
    $stmt->close();
} catch (Exception $e) {
    die("Error fetching research: " . $e->getMessage());
}

// Check if we have a valid research ID
if ($researchID <= 0) {
    $errorMsg = "Invalid research ID";
} else {
    // Get all faculty for the adviser dropdown and panelists
    $facultyQuery = "SELECT facultyID, firstName, middleName, lastName FROM faculty ORDER BY lastName, firstName";
    $facultyResult = $conn->query($facultyQuery);
    if ($facultyResult) {
        while ($row = $facultyResult->fetch_assoc()) {
            $fullName = $row['lastName'] . ', ' . $row['firstName'];
            if (!empty($row['middleName'])) {
                $fullName .= ' ' . $row['middleName'];
            }
            $allFaculty[$row['facultyID']] = $fullName;
        }
    }

    // Get all users for the uploader dropdown
    $userQuery = "SELECT userID, firstName, middleName, lastName FROM user ORDER BY lastName, firstName";
    $userResult = $conn->query($userQuery);
    if ($userResult) {
        while ($row = $userResult->fetch_assoc()) {
            $fullName = $row['lastName'] . ', ' . $row['firstName'];
            if (!empty($row['middleName'])) {
                $fullName .= ' ' . $row['middleName'];
            }
            $allUsers[$row['userID']] = $fullName;
        }
    }

    // Get all keywords for the keywords dropdown
    $keywordQuery = "SELECT keywordID, keywordName FROM keyword ORDER BY keywordName";
    $keywordResult = $conn->query($keywordQuery);
    if ($keywordResult) {
        while ($row = $keywordResult->fetch_assoc()) {
            $allKeywords[$row['keywordID']] = $row['keywordName'];
        }
    }

    // Query to get the research details
    $sql = "SELECT 
                r.researchID,
                r.researchTitle, 
                r.program,
                r.publishedMonth,
                r.publishedYear,
                r.researchAdviser,
                r.uploadedBy,
                r.researchApprovalSheet,
                r.researchManuscript,
                r.researchAbstract
            FROM research r
            WHERE r.researchID = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $researchID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $research = $result->fetch_assoc();
            
            // Get keywords
            $keywordsSql = "SELECT k.keywordID, k.keywordName
                           FROM researchkeyword rk
                           JOIN keyword k ON rk.keywordID = k.keywordID
                           WHERE rk.researchID = ?";
            
            $keywordsStmt = $conn->prepare($keywordsSql);
            if ($keywordsStmt) {
                $keywordsStmt->bind_param("i", $researchID);
                $keywordsStmt->execute();
                $keywordsResult = $keywordsStmt->get_result();
                
                while ($row = $keywordsResult->fetch_assoc()) {
                    $researchKeywords[] = $row;
                }
                $keywordsStmt->close();
            }
            
            // Get panelists
            $panelistsSql = "SELECT p.panelID, p.facultyID, f.firstName, f.middleName, f.lastName
                            FROM panel p
                            JOIN faculty f ON p.facultyID = f.facultyID
                            WHERE p.researchID = ?";
            
            $panelistsStmt = $conn->prepare($panelistsSql);
            if ($panelistsStmt) {
                $panelistsStmt->bind_param("i", $researchID);
                $panelistsStmt->execute();
                $panelistsResult = $panelistsStmt->get_result();
                
                while ($row = $panelistsResult->fetch_assoc()) {
                    $panelists[] = $row;
                }
                $panelistsStmt->close();
            }
            
            // Get researchers
            $checkTableSql = "SHOW TABLES LIKE 'researcher'";
            $tableResult = $conn->query($checkTableSql);
            $researcherTableExists = $tableResult && $tableResult->num_rows > 0;
            
            if ($researcherTableExists) {
                try {
                    $researchersSql = "SELECT researcherID, firstName, middleName, lastName 
                                      FROM researcher
                                      WHERE researchID = ?";
                    
                    $researchersStmt = $conn->prepare($researchersSql);
                    if ($researchersStmt) {
                        $researchersStmt->bind_param("i", $researchID);
                        $researchersStmt->execute();
                        $researchersResult = $researchersStmt->get_result();
                        
                        while ($row = $researchersResult->fetch_assoc()) {
                            $researchers[] = $row;
                        }
                        $researchersStmt->close();
                    }
                } catch (Exception $e) {
                    // Handle error silently
                }
            }
        } else {
            $errorMsg = "Research not found";
        }
        $stmt->close();
    } else {
        $errorMsg = "Database error: " . $conn->error;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Validate and sanitize input
    $title = trim($_POST['title']);
    $adviserID = isset($_POST['adviser']) ? intval($_POST['adviser']) : null;
    $program = trim($_POST['program']);
    $publishedMonth = isset($_POST['publishedMonth']) ? intval($_POST['publishedMonth']) : null;
    $publishedYear = isset($_POST['publishedYear']) ? intval($_POST['publishedYear']) : null;
    $uploaderID = isset($_POST['uploader']) ? intval($_POST['uploader']) : null;
    $abstract = $_POST['abstract'] ?? '';
    
    // Validation
    $errors = [];
    if (empty($title)) {
        $errors[] = "Research title is required";
    }
    if (empty($program)) {
        $errors[] = "Program is required";
    }
    if (strlen($abstract) < MIN_ABSTRACT_LENGTH) {
        $errors[] = "Abstract must be at least " . MIN_ABSTRACT_LENGTH . " characters long";
    }
    
    // Validate approval sheet file if uploaded
    if (!empty($_FILES['researchApprovalSheet']['name'])) {
        if ($_FILES['researchApprovalSheet']['size'] > MAX_FILE_SIZE) {
            $errors[] = "Approval sheet file size must not exceed 10MB";
        }
    }
    
    // Validate manuscript file if uploaded
    if (!empty($_FILES['researchManuscript']['name'])) {
        if ($_FILES['researchManuscript']['size'] > MAX_FILE_SIZE) {
            $errors[] = "Manuscript file size must not exceed 10MB";
        }
    }
    
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Initialize file paths with existing values
            $approvalSheetPath = $research['researchApprovalSheet'];
            $manuscriptPath = $research['researchManuscript'];

            // Initialize variables for file contents
            $approvalSheetContent = $research['researchApprovalSheet']; // Keep existing content by default
            $manuscriptContent = $research['researchManuscript']; // Keep existing content by default

            // Handle Approval Sheet upload
            if (!empty($_FILES['researchApprovalSheet']['name'])) {
                $approvalSheetTmpName = $_FILES['researchApprovalSheet']['tmp_name'];
                if (file_exists($approvalSheetTmpName)) {
                    $approvalSheetContent = file_get_contents($approvalSheetTmpName);
                    if ($approvalSheetContent === false) {
                        throw new Exception("Failed to read approval sheet file content");
                    }
                } else {
                    throw new Exception("Approval sheet temporary file not found");
                }
            }

            // Handle Manuscript upload
            if (!empty($_FILES['researchManuscript']['name'])) {
                $manuscriptTmpName = $_FILES['researchManuscript']['tmp_name'];
                if (file_exists($manuscriptTmpName)) {
                    $manuscriptContent = file_get_contents($manuscriptTmpName);
                    if ($manuscriptContent === false) {
                        throw new Exception("Failed to read manuscript file content");
                    }
                } else {
                    throw new Exception("Manuscript temporary file not found");
                }
            }

            // Update research with correct content
            $updateSql = "UPDATE research SET 
                          researchTitle = ?,
                          researchAdviser = ?,
                          program = ?,
                          publishedMonth = ?,
                          publishedYear = ?,
                          uploadedBy = ?,
                          researchApprovalSheet = ?,
                          researchManuscript = ?,
                          researchAbstract = ?
                          WHERE researchID = ?";

            $updateStmt = $conn->prepare($updateSql);
            if (!$updateStmt) {
                throw new Exception("Failed to prepare research update statement: " . $conn->error);
            }
            
            $updateStmt->bind_param("sssiiisssi", 
                $title,
                $adviserID,
                $program,
                $publishedMonth,
                $publishedYear,
                $uploaderID,
                $approvalSheetContent,
                $manuscriptContent,
                $abstract,
                $researchID
            );

            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update research: " . $updateStmt->error);
            }
            $updateStmt->close();

            // Handle researchers updates
            if (!empty($_POST['delete_researchers'])) {
                $deleteResearcherStmt = $conn->prepare("DELETE FROM researcher WHERE researcherID = ? AND researchID = ?");
                foreach ($_POST['delete_researchers'] as $researcherID) {
                    $deleteResearcherStmt->bind_param("ii", $researcherID, $researchID);
                    if (!$deleteResearcherStmt->execute()) {
                        throw new Exception("Failed to delete researcher: " . $deleteResearcherStmt->error);
                    }
                }
                $deleteResearcherStmt->close();
            }

            // Add new researchers
            if (!empty($_POST['new_researcher_first'])) {
                $insertResearcher = $conn->prepare("INSERT INTO researcher (researchID, firstName, middleName, lastName) VALUES (?, ?, ?, ?)");
                
                foreach ($_POST['new_researcher_first'] as $key => $firstName) {
                    $lastName = $_POST['new_researcher_last'][$key] ?? '';
                    $middleName = $_POST['new_researcher_middle'][$key] ?? '';
                    
                    if (!empty($firstName) && !empty($lastName)) {
                        $insertResearcher->bind_param("isss", $researchID, $firstName, $middleName, $lastName);
                        if (!$insertResearcher->execute()) {
                            throw new Exception("Failed to add researcher: " . $insertResearcher->error);
                        }
                    }
                }
                $insertResearcher->close();
            }

            // Handle panelists updates
            if (!empty($_POST['delete_panelists'])) {
                $deletePanelistStmt = $conn->prepare("DELETE FROM panel WHERE panelID = ? AND researchID = ?");
                foreach ($_POST['delete_panelists'] as $panelID) {
                    $deletePanelistStmt->bind_param("ii", $panelID, $researchID);
                    if (!$deletePanelistStmt->execute()) {
                        throw new Exception("Failed to delete panelist: " . $deletePanelistStmt->error);
                    }
                }
                $deletePanelistStmt->close();
            }

            // Add new panelists
            if (!empty($_POST['new_panelists'])) {
                $insertPanelist = $conn->prepare("INSERT INTO panel (researchID, facultyID) VALUES (?, ?)");
                
                foreach ($_POST['new_panelists'] as $facultyID) {
                    if (!empty($facultyID)) {
                        $insertPanelist->bind_param("ii", $researchID, $facultyID);
                        if (!$insertPanelist->execute()) {
                            throw new Exception("Failed to add panelist: " . $insertPanelist->error);
                        }
                    }
                }
                $insertPanelist->close();
            }

            // Handle keywords updates
            if (!empty($_POST['delete_keywords'])) {
                $deleteKeywordStmt = $conn->prepare("DELETE FROM researchkeyword WHERE researchID = ? AND keywordID = ?");
                foreach ($_POST['delete_keywords'] as $keywordID) {
                    $deleteKeywordStmt->bind_param("ii", $researchID, $keywordID);
                    if (!$deleteKeywordStmt->execute()) {
                        throw new Exception("Failed to delete keyword: " . $deleteKeywordStmt->error);
                    }
                }
                $deleteKeywordStmt->close();
            }

            // Add existing keywords
            if (!empty($_POST['new_keywords'])) {
                $insertResearchKeyword = $conn->prepare("INSERT INTO researchkeyword (researchID, keywordID) VALUES (?, ?)");
                
                foreach ($_POST['new_keywords'] as $keywordID) {
                    if (!empty($keywordID)) {
                        $insertResearchKeyword->bind_param("ii", $researchID, $keywordID);
                        if (!$insertResearchKeyword->execute()) {
                            throw new Exception("Failed to add keyword: " . $insertResearchKeyword->error);
                        }
                    }
                }
                $insertResearchKeyword->close();
            }

            // Add new keywords
            if (!empty($_POST['new_keyword_names'])) {
                $checkKeyword = $conn->prepare("SELECT keywordID FROM keyword WHERE LOWER(keywordName) = LOWER(?)");
                $insertKeyword = $conn->prepare("INSERT INTO keyword (keywordName) VALUES (?)");
                $insertResearchKeyword = $conn->prepare("INSERT INTO researchkeyword (researchID, keywordID) VALUES (?, ?)");
                
                foreach ($_POST['new_keyword_names'] as $keywordName) {
                    if (!empty($keywordName)) {
                        // First check if keyword already exists (case-insensitive)
                        $checkKeyword->bind_param("s", $keywordName);
                        if (!$checkKeyword->execute()) {
                            throw new Exception("Failed to check existing keyword: " . $checkKeyword->error);
                        }
                        
                        $result = $checkKeyword->get_result();
                        
                        if ($result->num_rows > 0) {
                            // Keyword exists, get its ID
                            $row = $result->fetch_assoc();
                            $keywordID = $row['keywordID'];
                        } else {
                            // Keyword doesn't exist, insert new one
                            $insertKeyword->bind_param("s", $keywordName);
                            if (!$insertKeyword->execute()) {
                                throw new Exception("Failed to add new keyword: " . $insertKeyword->error);
                            }
                            $keywordID = $conn->insert_id;
                        }
                        
                        // Link keyword to research (use IGNORE to prevent duplicate entries)
                        $insertResearchKeyword->bind_param("ii", $researchID, $keywordID);
                        if (!$insertResearchKeyword->execute()) {
                            throw new Exception("Failed to link keyword: " . $insertResearchKeyword->error);
                        }
                    }
                }
                $checkKeyword->close();
                $insertKeyword->close();
                $insertResearchKeyword->close();
            }

            // Log the research modification
            $logSql = "INSERT INTO ResearchEntryLog (performedBy, actionType, researchID, timestamp) 
                       VALUES (?, 'modify', ?, NOW())";
            $logStmt = $conn->prepare($logSql);
            if (!$logStmt) {
                throw new Exception("Failed to prepare log statement: " . $conn->error);
            }

            $logStmt->bind_param("ii", $uploaderID, $researchID);
            if (!$logStmt->execute()) {
                throw new Exception("Failed to log research modification: " . $logStmt->error);
            }
            $logStmt->close();

            $conn->commit();
            $successMsg = "Research updated successfully";
            header("Location: list_page.php");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = "Error updating research: " . $e->getMessage();
            error_log($errorMsg);
        }
    } else {
        $errorMsg = "Please correct the following errors: " . implode(", ", $errors);
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $successMsg = "Research updated successfully";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Research</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base styles */
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: rgb(35, 1, 68);
            overflow-x: hidden;
            width: 100%;
            font-size: clamp(14px, 1vw, 16px);
        }

        /* Navbar styles */
        .navbar {
            width: 100%;
            padding: 0.5rem 0;
            background-color: rgb(35, 1, 68) !important;
            margin-bottom: 0;
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

        /* Container adjustments */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: clamp(10px, 3vw, 20px);
            flex: 1;
            box-sizing: border-box;
            background-color: #f5f5f5;
        }

        /* Header actions */
        .header-actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            width: 100%;
            background-color: #f5f5f5;
        }

        h1 {
            margin: 0;
            color: #220044;
            width: 100%;
            text-align: center;
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            margin-bottom: clamp(1rem, 3vw, 2rem);
        }

        /* Section Cards */
        .section {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(34, 0, 68, 0.1);
            margin-bottom: clamp(1rem, 3vw, 2rem);
            padding: clamp(1rem, 3vw, 1.5rem);
            border: 1px solid #eee;
        }

        .section:hover {
            box-shadow: 0 4px 8px rgba(34, 0, 68, 0.15);
        }

        .section-title {
            color: #220044;
            font-size: clamp(1.1rem, 2.5vw, 1.25rem);
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #FF6600;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: clamp(1rem, 2vw, 1.5rem);
        }

        .form-group label {
            font-size: clamp(0.875rem, 1.5vw, 1rem);
            margin-bottom: clamp(0.3rem, 1vw, 0.5rem);
            display: block;
        }

        input[type="text"],
        input[type="email"],
        select,
        textarea {
            width: 100%;
            padding: clamp(0.5rem, 1.5vw, 0.75rem);
            font-size: clamp(0.875rem, 1.5vw, 1rem);
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        select:focus,
        textarea:focus {
            border-color: #FF6600;
            box-shadow: 0 0 0 2px rgba(255, 102, 0, 0.1);
            outline: none;
        }

        /* Researcher and Panelist Cards */
        .researcher,
        .panelist {
            background: #f8f9fa;
            border-radius: 6px;
            padding: clamp(0.75rem, 2vw, 1.25rem);
            margin-bottom: clamp(0.5rem, 1.5vw, 1rem);
            position: relative;
        }

        .btn-add {
            background: #220044;
            color: white;
            padding: clamp(0.5rem, 1.5vw, 0.75rem) clamp(1rem, 2vw, 1.5rem);
            border-radius: 25px;
            border: none;
            font-size: clamp(0.875rem, 1.5vw, 1rem);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s ease, transform 0.3s ease;
            cursor: pointer;
        }

        .btn-add:hover {
            background: #FF6600;
            transform: translateY(-2px);
        }

        /* Back Button */
        .btn-back {
            display: inline-block;
            background-color: #FF6600;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .btn-back:hover {
            background-color: #FF884D;
            color: white;
            text-decoration: none;
        }

        /* Error and success messages */
        .error-message,
        .success-message {
            padding: clamp(0.75rem, 2vw, 1rem);
            margin-bottom: clamp(1rem, 2vw, 1.5rem);
            font-size: clamp(0.875rem, 1.5vw, 1rem);
        }

        .error-message {
            color: #FF6600;
            background-color: #fee;
            border: 1px solid #fcc;
            border-radius: 4px;
        }

        .success-message {
            color: #28a745;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
        }

        /* Home button */
        .home-btn {
            font-size: 2rem !important;
            padding: 8px 12px !important;
        }

        /* Mobile-specific adjustments */
        @media (max-width: 768px) {
            .navbar-logo {
                height: clamp(24px, 8vw, 32px);
            }

            .navbar-brand span {
                font-size: clamp(1rem, 4vw, 1.1rem);
            }

            .home-btn {
                font-size: 1rem !important;
            }

            .researcher,
            .panelist {
                padding: 0.75rem;
            }
        }

        /* File input styling */
        input[type="file"] {
            font-size: clamp(0.875rem, 1.5vw, 1rem);
            padding: clamp(0.4rem, 1vw, 0.6rem);
        }

        /* Help text */
        .form-text {
            font-size: clamp(0.75rem, 1.2vw, 0.875rem);
        }

        .researcher-inputs {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
        }

        .researcher-group {
            display: grid;
            grid-gap: 10px;
        }

        .btn-add-container {
            text-align: center;
            margin-top: 10px;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .panelist-inputs,
        .keyword-inputs {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
        }

        .panelist-group,
        .keyword-group {
            display: grid;
            grid-gap: 10px;
        }

        .add-section h4 {
            margin-top: 20px;
            margin-bottom: 15px;
            color: #220044;
        }

        .keyword-section {
            margin-bottom: 20px;
        }

        .keyword-inputs {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .keyword-group {
            display: grid;
            grid-gap: 10px;
        }

        .btn-add-container {
            margin-top: 10px;
            text-align: center;
        }

        select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1em;
            padding-right: 2.5rem !important;
        }

        .select-wrapper {
            position: relative;
            width: 100%;
        }

        .btn-add,
        .btn[type="submit"],
        .btn-primary,
        button[style*="background-color: #220044"],
        button[style*="background-color: rgb(35, 1, 68)"] {
            background-color: #220044 !important;
            border-color: #220044 !important;
            color: #fff !important;
            transition: transform 0.2s ease !important;
        }

        .btn-add:hover,
        .btn-add:focus,
        .btn-add:active,
        .btn[type="submit"]:hover,
        .btn[type="submit"]:focus,
        .btn[type="submit"]:active,
        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active,
        button[style*="background-color: #220044"]:hover,
        button[style*="background-color: #220044"]:focus,
        button[style*="background-color: #220044"]:active,
        button[style*="background-color: rgb(35, 1, 68)"]:hover,
        button[style*="background-color: rgb(35, 1, 68)"]:focus,
        button[style*="background-color: rgb(35, 1, 68)"]:active {
            background-color: #220044 !important;
            border-color: #220044 !important;
            color: #fff !important;
            transform: translateY(-2px);
        }

        .btn-add:active,
        .btn[type="submit"]:active,
        .btn-primary:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid position-relative justify-content-center">
                <a href="welcome.php" class="btn btn-outline-light me-2 show-hamburger border-0 home-btn" style="position:absolute;left:0;top:50%;transform:translateY(-50%);z-index:1100;padding-left:8px;background:transparent;">
                    <i class="fas fa-home"></i>
                </a>
                <a class="navbar-brand mx-auto d-flex align-items-center justify-content-center" href="#">
                    <img src="images/octopus-logo.png" alt="Logo" class="navbar-logo me-2">
                    <span>Repositups</span>
                </a>
            </div>
        </nav>
    </header>

    <div style="background-color: #f5f5f5; flex: 1; width: 100%; padding-top: 20px;">
        <div class="container">
            <div class="header-actions">
                <h1>Edit Research</h1>
                <div style="display: flex; justify-content: center; width: 100%;">
                    <a href="list_page.php" class="btn-back">Back to List</a>
                </div>
            </div>

            <!-- Keep the rest of your existing PHP/HTML content but wrap sections in the new section divs -->
            <?php if (!empty($errorMsg)): ?>
                <div class="error-message"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>

            <?php if (!empty($successMsg)): ?>
                <div class="success-message"><?php echo htmlspecialchars($successMsg); ?></div>
            <?php endif; ?>

            <!-- Continue with your existing form but wrap each logical section in .section divs -->
            <!-- Example: -->
            <form method="post" enctype="multipart/form-data">
                <div class="section">
                    <h2 class="section-title">Basic Information</h2>
                    
                    <div class="form-group">
                        <label for="title">Research Title:</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($research['researchTitle'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="adviser">Research Adviser:</label>
                        <select id="adviser" name="adviser">
                            <option value="">Select Adviser</option>
                            <?php foreach($allFaculty as $facultyID => $facultyName): ?>
                                <option value="<?php echo $facultyID; ?>" <?php echo ($research['researchAdviser'] == $facultyID) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($facultyName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="program">Program:</label>
                        <select name="program" id="program" class="form-control" required>
                            <option value="">Select Program</option>
                            <?php foreach($allPrograms as $programName): ?>
                                <option value="<?php echo $programName; ?>" <?php echo ($research['program'] == $programName) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($programName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="publishedMonth">Published Month:</label>
                        <select name="publishedMonth" id="publishedMonth" class="form-control" required>
                            <option value="">Select Month</option>
                            <?php 
                            for ($i = 1; $i <= 12; $i++): 
                                $monthName = date('F', mktime(0, 0, 0, $i, 1));
                            ?>
                                <option value="<?php echo $i; ?>" <?php echo ($research['publishedMonth'] == $i) ? 'selected' : ''; ?>>
                                    <?php echo $monthName; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Published Date:</label>
                        <div class="date-inputs">
                            <select name="publishedYear">
                                <option value="">Select Year</option>
                                <?php $currentYear = date('Y'); ?>
                                <?php for($year = $currentYear; $year >= 1990; $year--): ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($research['publishedYear'] == $year) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="uploader">Uploaded By:</label>
                        <input type="text" 
                               value="<?php echo htmlspecialchars($allUsers[$research['uploadedBy']] ?? ''); ?>" 
                               class="form-control-plaintext" 
                               readonly 
                               style="background-color: #f8f9fa; padding: 8px; border: 1px solid #ddd; border-radius: 4px; color: #6c757d; cursor: not-allowed;">
                        <input type="hidden" name="uploader" value="<?php echo $research['uploadedBy']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="abstract">Research Abstract:</label>
                        <textarea id="abstract" 
                                  name="abstract" 
                                  rows="6" 
                                  class="form-control"
                                  minlength="255"
                                  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"
                                  required
                        ><?php echo htmlspecialchars($research['researchAbstract'] ?? ''); ?></textarea>
                        <small class="form-text text-muted">Minimum 255 characters required</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="researchApprovalSheet">Research Approval Sheet (PDF):</label>
                        <input type="file" 
                               id="researchApprovalSheet" 
                               name="researchApprovalSheet" 
                               accept=".pdf"
                               max="10485760">
                        <small class="form-text text-muted">Maximum file size: 10MB</small>
                        <?php if (!empty($research['researchApprovalSheet'])): ?>
                            <p class="current-file">Current file: <?php echo htmlspecialchars(basename($research['researchApprovalSheet'])); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="researchManuscript">Research Manuscript (PDF):</label>
                        <input type="file" 
                               id="researchManuscript" 
                               name="researchManuscript" 
                               accept=".pdf"
                               max="10485760">
                        <small class="form-text text-muted">Maximum file size: 10MB</small>
                        <?php if (!empty($research['researchManuscript'])): ?>
                            <p class="current-file">Current file: <?php echo htmlspecialchars(basename($research['researchManuscript'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Researchers Section -->
                <div class="section">
                    <h2 class="section-title">Researchers</h2>
                    <?php if (!empty($researchers)): ?>
                        <h3>Current Researchers</h3>
                        <ul class="item-list">
                            <?php foreach ($researchers as $researcher): ?>
                                <li class="item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="delete_researchers[]" value="<?php echo $researcher['researcherID']; ?>">
                                        <?php 
                                        $fullName = $researcher['firstName'];
                                        if (!empty($researcher['middleName'])) {
                                            $fullName .= ' ' . $researcher['middleName'];
                                        }
                                        $fullName .= ' ' . $researcher['lastName'];
                                        echo htmlspecialchars($fullName);
                                        ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p><small>Check the boxes next to researchers you want to delete.</small></p>
                    <?php else: ?>
                        <p>No researchers assigned to this research.</p>
                    <?php endif; ?>
                    
                    <div class="add-section">
                        <h4>Add New Researchers</h4>
                        <div id="researcher-container">
                            <!-- This will contain only the researcher input groups -->
                        </div>
                        <div class="btn-add-container" style="margin-top: 10px;">
                            <button type="button" class="btn btn-add" onclick="addResearcherInput()">
                                <i class="fas fa-plus"></i> Add Another Researcher
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Panelists Section -->
                <div class="section">
                    <h2 class="section-title">Panelists</h2>
                    <?php if (!empty($panelists)): ?>
                        <h3>Current Panelists</h3>
                        <ul class="item-list">
                            <?php foreach ($panelists as $panelist): ?>
                                <li class="item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="delete_panelists[]" value="<?php echo $panelist['panelID']; ?>">
                                        <?php 
                                        $fullName = $panelist['firstName'];
                                        if (!empty($panelist['middleName'])) {
                                            $fullName .= ' ' . $panelist['middleName'];
                                        }
                                        $fullName .= ' ' . $panelist['lastName'];
                                        echo htmlspecialchars($fullName); 
                                        ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p><small>Check the boxes next to panelists you want to delete.</small></p>
                    <?php else: ?>
                        <p>No panelists assigned to this research.</p>
                    <?php endif; ?>
                    
                    <div class="add-section">
                        <h4>Add New Panelists</h4>
                        <div id="panelist-container">
                            <!-- This will contain only the panelist input groups -->
                        </div>
                        <div class="btn-add-container" style="margin-top: 10px;">
                            <button type="button" class="btn btn-add" onclick="addPanelistInput()">
                                <i class="fas fa-plus"></i> Add Another Panelist
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Keywords Section -->
                <div class="section">
                    <h2 class="section-title">Keywords</h2>
                    <?php if (!empty($researchKeywords)): ?>
                        <h3>Current Keywords</h3>
                        <ul class="item-list">
                            <?php foreach ($researchKeywords as $keyword): ?>
                                <li class="item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="delete_keywords[]" value="<?php echo $keyword['keywordID']; ?>">
                                        <?php echo htmlspecialchars($keyword['keywordName']); ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p><small>Check the boxes next to keywords you want to delete.</small></p>
                    <?php else: ?>
                        <p>No keywords assigned to this research.</p>
                    <?php endif; ?>
                    
                    <div class="add-section">
                        <h4>Add Existing Keywords</h4>
                        <div class="keyword-section">
                            <div id="existing-keyword-container">
                                <!-- Existing keyword inputs will be added here -->
                            </div>
                            <div class="btn-add-container">
                                <button type="button" class="btn btn-add" onclick="addExistingKeywordInput()">
                                    <i class="fas fa-plus"></i> Add Another Keyword
                                </button>
                            </div>
                        </div>

                        <h4>Create New Keywords</h4>
                        <div class="keyword-section">
                            <div id="new-keyword-container">
                                <!-- New keyword inputs will be added here -->
                            </div>
                            <div class="btn-add-container">
                                <button type="button" class="btn btn-add" onclick="addNewKeywordInput()">
                                    <i class="fas fa-plus"></i> Add New Keyword
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <button type="submit" name="submit" class="btn-back" style="border: none; cursor: pointer;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addResearcherInput() {
            const container = document.getElementById('researcher-container');
            const newDiv = document.createElement('div');
            newDiv.className = 'researcher-inputs';
            newDiv.style.cssText = 'display: grid; grid-gap: 10px; margin-bottom: 15px;';
            newDiv.innerHTML = `
                <div class="researcher-group">
                    <input type="text" name="new_researcher_first[]" placeholder="First Name" class="form-control" required>
                    <input type="text" name="new_researcher_middle[]" placeholder="Middle Name (Optional)" class="form-control">
                    <input type="text" name="new_researcher_last[]" placeholder="Last Name" class="form-control" required>
                    <input type="email" name="new_researcher_email[]" placeholder="Email" class="form-control">
                    <button type="button" class="btn btn-secondary" onclick="removeResearcherInput(this)">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div
            `;
            container.appendChild(newDiv);
        }

        function removeResearcherInput(button) {
            button.parentElement.remove();
        }

        function addPanelistInput() {
            const container = document.getElementById('panelist-container');
            const newDiv = document.createElement('div');
            newDiv.className = 'panelist-inputs';
            newDiv.style.cssText = 'display: grid; grid-gap: 10px; margin-bottom: 15px;';
            newDiv.innerHTML = `
                <div class="panelist-group">
                    <div class="select-wrapper">
                        <select name="new_panelists[]" class="form-control" required>
                            <option value="">Select Panelist</option>
                            <?php foreach($allFaculty as $facultyID => $facultyName): ?>
                                <option value="<?php echo $facultyID; ?>">
                                    <?php echo htmlspecialchars($facultyName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="removePanelistInput(this)">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>
            `;
            container.appendChild(newDiv);
        }

        function removePanelistInput(button) {
            button.parentElement.remove();
        }

        function addExistingKeywordInput() {
            const container = document.getElementById('existing-keyword-container');
            const newDiv = document.createElement('div');
            newDiv.className = 'keyword-inputs';
            newDiv.style.cssText = 'display: grid; grid-gap: 10px; margin-bottom: 15px;';
            newDiv.innerHTML = `
                <div class="keyword-group">
                    <div class="select-wrapper">
                        <select name="new_keywords[]" class="form-control" required>
                            <option value="">Select Existing Keyword</option>
                            <?php foreach($allKeywords as $keywordID => $keywordName): ?>
                                <option value="<?php echo $keywordID; ?>">
                                    <?php echo htmlspecialchars($keywordName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="removeKeywordInput(this)">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>
            `;
            container.appendChild(newDiv);
        }
        
        function addNewKeywordInput() {
            const container = document.getElementById('new-keyword-container');
            const newDiv = document.createElement('div');
            newDiv.className = 'keyword-inputs';
            newDiv.style.cssText = 'display: grid; grid-gap: 10px; margin-bottom: 15px;';
            newDiv.innerHTML = `
                <div class="keyword-group">
                    <input type="text" name="new_keyword_names[]" placeholder="Enter new keyword" class="form-control" required>
                    <button type="button" class="btn btn-secondary" onclick="removeKeywordInput(this)">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>
            `;
            container.appendChild(newDiv);
        }

        function removeKeywordInput(button) {
            button.closest('.keyword-inputs').remove();
        }

        // Add form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const abstract = document.getElementById('abstract').value;
            const approvalSheet = document.getElementById('researchApprovalSheet').files[0];
            const manuscript = document.getElementById('researchManuscript').files[0];
            const maxSize = 10 * 1024 * 1024; // 10MB in bytes
            
            if (abstract.length < 255) {
                e.preventDefault();
                alert('Abstract must be at least 255 characters long');
                return;
            }
            
            if (approvalSheet && approvalSheet.size > maxSize) {
                e.preventDefault();
                alert('Approval sheet file size must not exceed 10MB');
                return;
            }
            
            if (manuscript && manuscript.size > maxSize) {
                e.preventDefault();
                alert('Manuscript file size must not exceed 10MB');
                return;
            }
        });

        // Add file input validation
        document.getElementById('researchApprovalSheet').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const maxSize = 10 * 1024 * 1024; // 10MB in bytes
            
            if (file && file.size > maxSize) {
                alert('Approval sheet file size must not exceed 10MB');
                this.value = ''; // Clear the file input
            }
        });

        document.getElementById('researchManuscript').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const maxSize = 10 * 1024 * 1024; // 10MB in bytes
            
            if (file && file.size > maxSize) {
                alert('Manuscript file size must not exceed 10MB');
                this.value = ''; // Clear the file input
            }
        });
    </script>
</body>
</html>