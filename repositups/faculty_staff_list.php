<?php
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Check for delete success/error messages
$deleteError = isset($_SESSION['delete_error']) ? $_SESSION['delete_error'] : '';
unset($_SESSION['delete_error']);

// SQL with concatenated full name
$sql = "SELECT 
    facultyID,
    CONCAT(firstName, ' ', middleName, ' ', lastName) AS fullName, 
    position, 
    designation, 
    email, 
    ORCID, 
    contactNumber, 
    educationalAttainment, 
    fieldOfSpecialization, 
    researchInterest 
FROM faculty";

if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql .= " WHERE 
        CONCAT(firstName, ' ', middleName, ' ', lastName) LIKE '%$search%' OR
        firstName LIKE '%$search%' OR
        middleName LIKE '%$search%' OR
        
        lastName LIKE '%$search%'";
}

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Faculty and Staff List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Add new navbar styles */
        .navbar-brand {
            font-weight: bold;
            letter-spacing: 1px;
            font-size: clamp(1.1rem, 2vw, 2rem);
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .home-btn {
            font-size: clamp(1.5rem, 2.5vw, 2.5rem) !important;
            padding: clamp(0.8rem, 1.5vw, 1.5rem) !important;
            transition: background-color 0.3s ease, color 0.3s ease;
            background: transparent;
            color: inherit;
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1100;
            padding-left: 16px !important;
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
            padding: 20px;
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            max-width: 900px;
            margin: 0 auto;
        }

        .faculty-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .faculty-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(34, 0, 68, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s ease;
            display: flex;
            justify-content: space-between;
            border-left: 4px solid #220044;
        }
        
        .faculty-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(34, 0, 68, 0.2);
            border-left-color: #FF6600;
        }
        
        .faculty-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #220044;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .faculty-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .detail-item {
            margin-bottom: 8px;
        }
        
        .detail-label {
            font-weight: 500;
            color: #FF6600;
            display: inline-block;
            margin-right: 5px;
        }
        
        .detail-value {
            color: #220044;
            display: flex;
            align-items: center;
            word-break: break-word;
        }
        
        .detail-value i {
            margin-right: 12px;
            color: #220044;
            min-width: 16px;
            font-size: 0.95rem;
        }
        
        .buttons {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 10px;
            margin-left: 20px;
        }
        
        .btn {
            border: none;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-block;
            text-align: center;
            transition: background-color 0.2s;
        }
        
        .btn.edit,
        .search-btn,
        .btn[style*="background-color: rgb(35, 1, 68)"],
        .btn-primary {
            background-color: #220044 !important;
            border-color: #220044 !important;
            color: #fff !important;
            transition: transform 0.2s ease !important;
        }
        
        .btn.edit:hover,
        .btn.edit:focus,
        .btn.edit:active,
        .search-btn:hover,
        .search-btn:focus,
        .search-btn:active,
        .btn[style*="background-color: rgb(35, 1, 68)"]:hover,
        .btn[style*="background-color: rgb(35, 1, 68)"]:focus,
        .btn[style*="background-color: rgb(35, 1, 68)"]:active,
        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active {
            background-color: #220044 !important;
            border-color: #220044 !important;
            color: #fff !important;
            transform: translateY(-2px);
        }
        
        .btn.edit:active,
        .search-btn:active,
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn.delete {
            background-color: #FF6600;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .add-btn-container {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .add-btn {
            background-color: #FF6600;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .add-btn:hover {
            background-color: #FF884D;
            opacity: 1;
        }
        
        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
            text-align: center;
            font-weight: 500;
        }
        
        .error {
            background-color: rgba(255, 102, 0, 0.1);
            color: #FF6600;
            border: 1px solid rgba(255, 102, 0, 0.2);
        }
        
        .empty-message {
            text-align: center;
            padding: 30px;
            font-size: 1.1rem;
            color: #FF6600;
        }

        .search-container {
            margin-bottom: 25px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .search-input {
            padding: 8px 15px;
            width: 300px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: #FF6600;
            box-shadow: 0 0 0 2px rgba(255, 102, 0, 0.1);
        }

        .search-btn {
            background-color: #220044;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .search-btn:hover {
            background-color: #1a0033;
        }

        h2 {
            color: #220044;
            margin-bottom: 30px;
            font-weight: 600;
            text-align: center;
        }
        
        /* Media queries for responsive design */
        @media (max-width: 768px) {
            .container-fluid.flex-grow-1 {
                padding: 15px;
            }
            
            .faculty-card {
                flex-direction: column;
                padding: 15px;
            }
            
            .faculty-name {
                font-size: 1.1rem;
                margin-bottom: 12px;
            }
            
            .faculty-details {
                display: flex;
                flex-direction: column;
            }
            
            .detail-item {
                margin-bottom: 12px;
                padding-bottom: 8px;
                border-bottom: 1px dashed rgba(34, 0, 68, 0.1);
            }
            
            .detail-item:last-child {
                border-bottom: none;
            }
            
            .detail-label {
                font-size: 0.85rem;
                font-weight: 600;
                display: block;
                margin-bottom: 4px;
            }
            
            .detail-value {
                font-size: 0.9rem;
                display: flex;
                align-items: center;
                word-break: break-word;
            }
            
            .detail-value i {
                margin-right: 15px;
                min-width: 18px;
            }
            
            .buttons {
                flex-direction: row;
                margin-left: 0;
                margin-top: 15px;
                justify-content: space-between;
                width: 100%;
            }
            
            .btn {
                flex: 1;
                padding: 10px;
            }
            
            .search-container {
                flex-direction: column;
                width: 100%;
            }
            
            .search-input {
                width: 100%;
                padding: 12px 15px;
            }
            
            form[method="GET"] {
                width: 100%;
                flex-direction: column;
            }
            
            .search-btn {
                width: 100%;
                margin-top: 8px;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Add Navigation Bar -->
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(35, 1, 68);">
            <div class="container-fluid position-relative justify-content-center">
                <!-- Home button -->
                <a href="welcome.php" class="btn btn-outline-light me-2 show-hamburger border-0 home-btn">
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
        <h2>Faculty and Staff List</h2>

        <?php
        // Display delete error if any
        if (!empty($deleteError)) {
            echo '<div class="message error">' . htmlspecialchars($deleteError) . '</div>';
        }
        ?>

        <div class="add-btn-container">
            <a href="add.php" class="btn add-btn">Add Faculty</a>
        </div>

        <?php
        // Display success message if set
        if (isset($_SESSION['message'])) {
            echo '<div class="alert alert-success text-center" style="margin: 20px auto; max-width: 500px;">' . htmlspecialchars($_SESSION['message']) . '</div>';
            unset($_SESSION['message']); // Clear the message after displaying
        }
        ?>

        <div class="search-container">
            <form method="GET" style="display: flex; gap: 10px;">
                <input type="text" name="search" placeholder="Search faculty..." class="search-input" 
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit" class="search-btn">Search</button>
                <?php if(isset($_GET['search'])): ?>
                    <a href="faculty_staff_list.php" class="search-btn" style="text-decoration: none;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php
        if ($result && $result->num_rows > 0) {
            echo '<ul class="faculty-list">';
            
            while ($row = $result->fetch_assoc()) {
                echo '<li class="faculty-card">';
                echo '<div class="faculty-info">';
                echo '<div class="faculty-name">' . htmlspecialchars($row['fullName']) . '</div>';
                echo '<div class="faculty-details">';
                
                echo '<div class="detail-item">';
                echo '<span class="detail-label">Position:</span>';
                echo '<span class="detail-value"><i class="fas fa-user-tie"></i> ' . htmlspecialchars($row['position']) . '</span>';
                echo '</div>';
                
                echo '<div class="detail-item">';
                echo '<span class="detail-label">Designation:</span>';
                echo '<span class="detail-value"><i class="fas fa-id-badge"></i> ' . htmlspecialchars($row['designation']) . '</span>';
                echo '</div>';
                
                echo '<div class="detail-item">';
                echo '<span class="detail-label">Email:</span>';
                echo '<span class="detail-value"><i class="fas fa-envelope"></i> ' . htmlspecialchars($row['email']) . '</span>';
                echo '</div>';
                
                echo '<div class="detail-item">';
                echo '<span class="detail-label">ORCID:</span>';
                echo '<span class="detail-value"><i class="fas fa-id-card"></i> ' . htmlspecialchars($row['ORCID']) . '</span>';
                echo '</div>';
                
                echo '<div class="detail-item">';
                echo '<span class="detail-label">Contact Number:</span>';
                echo '<span class="detail-value"><i class="fas fa-phone"></i> ' . htmlspecialchars($row['contactNumber']) . '</span>';
                echo '</div>';
                
                echo '<div class="detail-item">';
                echo '<span class="detail-label">Educational Attainment:</span>';
                echo '<span class="detail-value"><i class="fas fa-graduation-cap"></i> ' . htmlspecialchars($row['educationalAttainment']) . '</span>';
                echo '</div>';
                
                echo '<div class="detail-item">';
                echo '<span class="detail-label">Field of Specialization:</span>';
                echo '<span class="detail-value"><i class="fas fa-book"></i> ' . htmlspecialchars($row['fieldOfSpecialization']) . '</span>';
                echo '</div>';
                
                echo '<div class="detail-item">';
                echo '<span class="detail-label">Research Interest:</span>';
                echo '<span class="detail-value"><i class="fas fa-microscope"></i> ' . htmlspecialchars($row['researchInterest']) . '</span>';
                echo '</div>';
                
                echo '</div>'; // end faculty-details
                echo '</div>'; // end faculty-info
                
                echo '<div class="buttons">';
                echo '<a href="edit.php?id=' . urlencode($row['facultyID']) . '" class="btn edit">Edit</a>';
                echo '<a href="delete.php?id=' . urlencode($row['facultyID']) . '" class="btn delete" onclick="return confirm(\'Are you sure you want to delete this faculty?\');">Delete</a>';
                echo '</div>';
                
                echo '</li>';
            }
            
            echo '</ul>';
        } else {
            echo '<div class="empty-message">No faculty found.</div>';
        }
        ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>