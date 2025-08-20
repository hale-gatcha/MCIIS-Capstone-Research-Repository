<?php
require_once 'session_check.php';
require_once 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$error = "";
$success = "";
$researchID = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['uploadComplete'])) {
        $error = "";
        $success = "";
        $pdo->beginTransaction();
        
        try {
            // Create directories if they don't exist
            $uploadBaseDir = "uploads";
            $userDir = $uploadBaseDir . "/" . $_SESSION["user_id"];
            if (!file_exists($uploadBaseDir)) mkdir($uploadBaseDir, 0777, true);
            if (!file_exists($userDir)) mkdir($userDir, 0777, true);

            // Validate file uploads
            if (!isset($_FILES["approvalSheet"], $_FILES["manuscript"]) ||
                $_FILES["approvalSheet"]["error"] !== 0 || 
                $_FILES["manuscript"]["error"] !== 0) {
                throw new Exception("Please upload both the approval sheet and manuscript.");
            }
            
            // Add PDF validation
            $allowedType = 'application/pdf';
            if ($_FILES["approvalSheet"]["type"] !== $allowedType || 
                $_FILES["manuscript"]["type"] !== $allowedType) {
                throw new Exception("Only PDF files are allowed for uploads.");
            }

            // Optional: Add file size validation (e.g., max 10MB)
            $maxSize = 10 * 1024 * 1024; // 10MB in bytes
            if ($_FILES["approvalSheet"]["size"] > $maxSize || 
                $_FILES["manuscript"]["size"] > $maxSize) {
                throw new Exception("File size should not exceed 10MB.");
            }
            
            // Read files
            $approvalSheet = file_get_contents($_FILES["approvalSheet"]["tmp_name"]);
            $manuscript = file_get_contents($_FILES["manuscript"]["tmp_name"]);
            
            // Gather research form data
            $title = $_POST["title"];
            $adviserID = $_POST["adviserID"];
            $program = $_POST["program"];
            $month = $_POST["month"];
            $year = $_POST["year"];
            $abstract = $_POST["abstract"];

            // Sanitize the abstract text
            $abstract = strip_tags($abstract); // Remove HTML tags
            $abstract = preg_replace('/[^\p{L}\p{N}\s\p{P}]/u', '', $abstract); // Remove emojis and special characters
            $abstract = trim($abstract); // Remove extra whitespace

            // Add length validation after sanitization
            if (strlen($abstract) < 255) {
                throw new Exception("Abstract must be at least 255 characters after removing special characters.");
            }

            // Insert research entry
            $stmt = $pdo->prepare("CALL AddResearchEntry(?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt->execute([
                $_SESSION["user_id"],
                $title,
                $adviserID,
                $program,
                $month,
                $year,
                $abstract,
                $approvalSheet,
                $manuscript
            ])) {
                throw new Exception("Error adding the research entry.");
            }

            // Get research ID
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $researchID = $result['researchID'];
            $stmt->closeCursor();

            // Process keywords
            if (isset($_POST['keywords']) && !empty($_POST['keywords'])) {
                $keywords = array_map('trim', explode(',', $_POST['keywords']));
                
                foreach ($keywords as $keyword) {
                    if (!empty($keyword)) {
                        // First check if keyword exists
                        $stmtGetKeyword = $pdo->prepare("SELECT keywordID FROM Keyword WHERE keywordName = ?");
                        $stmtGetKeyword->execute([$keyword]);
                        $keywordID = $stmtGetKeyword->fetchColumn();
                        $stmtGetKeyword->closeCursor();
                        
                        if (!$keywordID) {
                            // Add new keyword
                            $stmtKeyword = $pdo->prepare("CALL AddKeyword(?)");
                            $stmtKeyword->execute([$keyword]);
                            $stmtKeyword->closeCursor();
                            
                            // Get new keyword ID
                            $stmtGetKeyword->execute([$keyword]);
                            $keywordID = $stmtGetKeyword->fetchColumn();
                            $stmtGetKeyword->closeCursor();
                        }
                        
                        // Link keyword to research
                        if ($keywordID) {
                            $stmtLink = $pdo->prepare("CALL AddKeywordToResearch(?, ?)");
                            $stmtLink->execute([$researchID, $keywordID]);
                            $stmtLink->closeCursor();
                        }
                    }
                }
            }

            // Process researchers
            if (!isset($_POST['researchers']) || !is_array($_POST['researchers'])) {
                throw new Exception("No researcher data submitted.");
            }

            foreach ($_POST['researchers'] as $researcher) {
                if (!isset($researcher['firstName'], $researcher['lastName'])) {
                    throw new Exception("Missing required researcher fields");
                }

                $email = (!empty($researcher['email'])) ? $researcher['email'] : null;
                $middleName = (!empty($researcher['middleName'])) ? $researcher['middleName'] : null;

                $stmtResearcher = $pdo->prepare("CALL AddResearcher(?, ?, ?, ?, ?)");
                if (!$stmtResearcher->execute([
                    $researchID,
                    $researcher['firstName'],
                    $middleName,
                    $researcher['lastName'],
                    $email
                ])) {
                    throw new Exception("Error adding researcher. Please check for duplicate emails.");
                }
                $stmtResearcher->closeCursor();
            }

            // Process panelists
            if (!isset($_POST['panelists']) || !is_array($_POST['panelists'])) {
                throw new Exception("No panelists selected.");
            }

            foreach ($_POST['panelists'] as $facultyID) {
                if (!empty($facultyID)) {
                    $stmtPanelist = $pdo->prepare("CALL AssignPanelist(?, ?)");
                    if (!$stmtPanelist->execute([$researchID, $facultyID])) {
                        throw new Exception("Error assigning panelist.");
                    }
                    $stmtPanelist->closeCursor();
                }
            }

            $pdo->commit();
            $success = "Research entry completed successfully!";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
            error_log($e->getMessage());
        }
    }
}

// Fetch advisers
$advisers = [];
$stmt = $pdo->query("SELECT facultyID, CONCAT(firstName, ' ', lastName) AS fullName FROM Faculty");
if ($stmt) {
    $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Research - Research Repository</title>
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

        .researcher h3 {
            color: #220044;
            font-size: clamp(1rem, 2vw, 1.2rem);
            margin-bottom: 1rem;
        }

        /* Researcher input styling */
        .researcher-inputs {
            display: grid;
            gap: clamp(0.5rem, 1.5vw, 1rem);
            grid-template-columns: repeat(auto-fit, minmax(min(250px, 100%), 1fr));
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
            -webkit-tap-highlight-color: transparent; /* Prevents tap color on mobile */
        }

        .btn-add:hover,
        .btn-add:focus,
        .btn-add:active,
        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active,
        .btn[style*="background-color: rgb(35, 1, 68)"]:hover,
        .btn[style*="background-color: rgb(35, 1, 68)"]:focus,
        .btn[style*="background-color: rgb(35, 1, 68)"]:active {
            background-color: #220044 !important;
            border-color: #220044 !important;
            color: #fff !important;
            opacity: 0.95;
            transform: translateY(-1px);
        }

        /* Reset hover effect */
        .btn-add:not(:hover):not(:active) {
            transform: none;
        }

        .btn-add i {
            font-size: 1rem;
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

        /* Remove Buttons */
        .btn-remove {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: none;
            border: none;
            color: #dc3545;
            padding: 0.25rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-remove:hover {
            color: #c82333;
            transform: scale(1.1);
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

        .researcher-inputs,
        .keyword-input-group,
        .panelist-input-group {
            display: flex;
            gap: 10px;
            align-items: end;
            margin-bottom: 10px;
        }

        .researcher-inputs input,
        .keyword-input-group input,
        .panelist-input-group select {
            flex: 1;
        }

        .add-section {
            margin-top: 15px;
            padding: 15px;
            background-color: #fff;
            border-radius: 4px;
            border: 1px solid #220044;
        }

        .add-section h4 {
            margin-top: 0;
            color: #220044;
        }

        /* Home button adjustment */
        .home-btn {
            font-size: 2rem !important; /* Increase from default size */
            padding: 8px 12px !important; /* Add more padding around the icon */
        }

        @media (max-width: 768px) {
            .home-btn {
                font-size: 1rem !important; /* Slightly smaller on mobile but still larger than original */
            }
        }

        /* Mobile-specific adjustments */
        @media (max-width: 768px) {
            .navbar-logo {
                height: clamp(24px, 8vw, 32px);
            }

            .navbar-brand span {
                font-size: clamp(1rem, 4vw, 1.1rem);
            }

            .researcher,
            .panelist {
                padding: 0.75rem;
            }

            .researcher-inputs {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .researcher-inputs input {
                width: 100%;
                margin: 0;
            }
            
            .researcher {
                padding: 15px;
            }

            .btn-remove {
                top: 0.25rem;
                right: 0.25rem;
            }

            .header-actions {
                padding: clamp(0.5rem, 2vw, 1rem);
            }
        }

        /* Tablet and larger screens */
        @media (min-width: 769px) {
            .researcher-inputs {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .container {
                padding: clamp(15px, 4vw, 30px);
            }
        }

        /* Large screens */
        @media (min-width: 1200px) {
            .container {
                padding: 30px;
            }

            .researcher-inputs {
                grid-template-columns: repeat(4, 1fr);
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
                <h1>Upload Research Paper</h1>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="section">
                    <h2 class="section-title">Research Details</h2>
                    
                    <div class="form-group">
                        <label for="title">Research Title:</label>
                        <input type="text" id="title" name="title" required>
                    </div>

                    <div class="form-group">
                        <label for="adviserID">Adviser:</label>
                        <select id="adviserID" name="adviserID" required>
                            <option value="">Select an Adviser</option>
                            <?php foreach ($advisers as $adviser): ?>
                                <option value="<?php echo htmlspecialchars($adviser['facultyID']); ?>">
                                    <?php echo htmlspecialchars($adviser['fullName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="program">Program:</label>
                        <select id="program" name="program" required>
                            <option value="">Select Program</option>
                            <option value="Bachelor of Science in Information Technology">Bachelor of Science in Information Technology</option>
                            <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                            <option value="Bachelor of Library and Information Science">Bachelor of Library and Information Science</option>
                            <option value="Master of Library and Information Science">Master of Library and Information Science</option>
                            <option value="Master in Information Technology">Master in Information Technology</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="month">Month:</label>
                        <select id="month" name="month" required>
                            <option value="">Select Month</option>
                            <?php 
                            for ($i = 1; $i <= 12; $i++): 
                                $monthName = date('F', mktime(0, 0, 0, $i, 1));
                            ?>
                                <option value="<?php echo $i; ?>"><?php echo $monthName; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="year">Year:</label>
                        <select id="year" name="year" required>
                            <option value="">Select Year</option>
                            <?php 
                            $currentYear = date('Y');
                            for($year = $currentYear; $year >= 1990; $year--):
                            ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="abstract">Abstract (minimum 255 characters):</label>
                        <textarea id="abstract" name="abstract" required minlength="255" rows="6"></textarea>
                        <small class="form-text text-muted">Please enter at least 255 characters</small>
                        <div id="charCount" class="form-text"></div>
                    </div>

                    <div class="form-group">
                        <label for="approvalSheet">Select Approval Sheet (PDF only, max 10MB):</label>
                        <input type="file" 
                               id="approvalSheet" 
                               name="approvalSheet" 
                               accept="application/pdf,.pdf" 
                               required>
                        <small class="form-text text-muted">Only PDF files are accepted</small>
                    </div>

                    <div class="form-group">
                        <label for="manuscript">Select Manuscript (PDF only, max 10MB):</label>
                        <input type="file" 
                               id="manuscript" 
                               name="manuscript" 
                               accept="application/pdf,.pdf" 
                               required>
                        <small class="form-text text-muted">Only PDF files are accepted</small>
                    </div>
                </div>

                <!-- Researchers Section -->
                <div class="section">
                    <h2 class="section-title">Researcher Details</h2>
                    <div id="researchers">
                        <div class="researcher">
                            <h3>Researcher 1</h3>
                            <div class="researcher-inputs">
                                <input type="text" name="researchers[1][firstName]" placeholder="First Name" required>
                                <input type="text" name="researchers[1][middleName]" placeholder="Middle Name">
                                <input type="text" name="researchers[1][lastName]" placeholder="Last Name" required>
                                <input type="email" name="researchers[1][email]" placeholder="Email">
                            </div>
                        </div>
                    </div>
                    <div class="btn-add-container">
                        <button type="button" class="btn-add" onclick="addResearcher()">
                            <i class="fas fa-plus"></i> Add Another Researcher
                        </button>
                    </div>
                </div>

                <!-- Keywords Section -->
                <div class="section">
                    <h2 class="section-title">Keywords</h2>
                    <div class="form-group">
                        <label for="keywords">Keywords (comma-separated):</label>
                        <input type="text" id="keywords" name="keywords" required placeholder="e.g. PHP, Database, Web Development">
                    </div>
                </div>

                <!-- Panelists Section -->
                <div class="section">
                    <h2 class="section-title">Assign Panelists</h2>
                    <div id="panelists">
                        <div class="panelist">
                            <label for="panelist">Select Panelist:</label>
                            <select name="panelists[]" required>
                                <option value="">Select a Panelist</option>
                                <?php foreach ($advisers as $adviser): ?>
                                    <option value="<?php echo htmlspecialchars($adviser['facultyID']); ?>">
                                        <?php echo htmlspecialchars($adviser['fullName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                        <button type="button" class="btn-add" onclick="assignPanelist()">
                            <i class="fas fa-plus"></i> Add Another Panelist
                        </button>
                    </div>

                <div style="margin-top: 20px; text-align: center;">
                    <button type="submit" name="uploadComplete" class="btn-back" style="border: none; cursor: pointer;">Submit Research Entry</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let researcherCount = 1;

        function addResearcher() {
            researcherCount++;
            const container = document.createElement('div');
            container.className = 'researcher';
            container.innerHTML = `
                <h3>Researcher ${researcherCount}</h3>
                <div class="researcher-inputs">
                    <input type="text" name="researchers[${researcherCount}][firstName]" placeholder="First Name" required>
                    <input type="text" name="researchers[${researcherCount}][middleName]" placeholder="Middle Name">
                    <input type="text" name="researchers[${researcherCount}][lastName]" placeholder="Last Name" required>
                    <input type="email" name="researchers[${researcherCount}][email]" placeholder="Email">
                </div>
                <div style="margin-top: 10px;">
                    <button type="button" class="btn-back" onclick="this.parentElement.parentElement.remove()" style="background-color: grey;">Remove Researcher</button>
                </div>
            `;
            document.getElementById('researchers').appendChild(container);
        }

        function assignPanelist() {
            const container = document.createElement('div');
            container.className = 'panelist';
            container.innerHTML = `
                <div style="margin-bottom: 10px;">
                    <select name="panelists[]" required>
                        <option value="">Select a Panelist</option>
                        <?php foreach ($advisers as $adviser): ?>
                            <option value="<?php echo htmlspecialchars($adviser['facultyID']); ?>">
                                <?php echo htmlspecialchars($adviser['fullName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn-back" onclick="this.parentElement.remove()" style="background-color: grey;">Remove Panelist</button>
            `;
            document.getElementById('panelists').appendChild(container);
        }

        function validateFileInput(input) {
            const file = input.files[0];
            if (file) {
                if (!file.type.match('application/pdf')) {
                    alert('Only PDF files are allowed!');
                    input.value = '';
                    return false;
                }
                if (file.size > 10 * 1024 * 1024) {
                    alert('File size should not exceed 10MB!');
                    input.value = '';
                    return false;
                }
            }
            return true;
        }

        document.getElementById('approvalSheet').addEventListener('change', function() {
            validateFileInput(this);
        });

        document.getElementById('manuscript').addEventListener('change', function() {
            validateFileInput(this);
        });

        document.getElementById('abstract').addEventListener('input', function() {
            const minLength = 255;
            const current = this.value.length;
            const remaining = minLength - current;
            const charCount = document.getElementById('charCount');
            
            if (remaining > 0) {
                charCount.textContent = `${remaining} more characters needed`;
                charCount.style.color = '#dc3545';
            } else {
                charCount.textContent = `Character minimum reached`;
                charCount.style.color = '#28a745';
            }
        });
    </script>
</body>
</html>