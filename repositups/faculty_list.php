<?php
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] === 'Administrator') {
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
            <p>This page is only accessible to Faculty, MCIIS Staff, and Students.</p>
            <a href="welcome.php" class="back-link">Return to Homepage</a>
        </div>
    </body>
    </html>';
    exit();
}

// SQL with concatenated full name (firstName, middleName, lastName)
$sql = "SELECT 
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

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>List of Faculty and Staff</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        }

        .main-content {
            padding: 15px;
        }

        .navbar {
            width: 100%;
            padding: 1.0rem 1rem; /* Increased from 0.5rem to 1.5rem */
        }

        .container-fluid {
            max-width: 100%;
            padding: 0 1rem;
        }

        h2 {
            text-align: center;
            margin-bottom: 15px; /* Changed from margin-bottom: 25px */
            color: #220044;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .faculty-list {
            width: 100%;
            margin: 0 auto;
            list-style-type: none;
            padding: 0;
        }
        
        .faculty-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(34, 0, 68, 0.15);
            padding: 16px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
            border-left: 4px solid #220044;
        }
        
        .faculty-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(34, 0, 68, 0.2);
            border-left-color: #FF6600;
        }
        
        .faculty-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 12px;
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
            margin-bottom: 12px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #FF6600;
            display: block;
            margin-bottom: 4px;
            font-size: 0.9rem;
        }
        
        .detail-value {
            color: #220044;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            word-break: break-word;
        }
        
        .detail-value i {
            margin-right: 8px;
            color: #220044;
            min-width: 16px;
        }
        
        .empty-message {
            text-align: center;
            padding: 25px;
            font-size: 1rem;
            color: #FF6600;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(34, 0, 68, 0.15);
            border-left: 4px solid #220044;
        }

        .navbar-brand {
            font-weight: bold;
            font-size: 20px;
            letter-spacing: 1px;
            font-size: clamp(1.1rem, 2vw, 1.8rem);
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .home-btn {
            font-size: clamp(1.3rem, 2.5vw, 2rem);
            padding: clamp(0.6rem, 1.5vw, 1.2rem);
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
            height: clamp(40px, 8vw, 70px); /* Increased from 32px to 40px and 60px to 70px */
            width: auto;
        }

        .search-container {
            width: 100%;
            margin: 0 auto 25px; /* Changed from margin: 15px auto 25px */
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 14px 20px;
            padding-left: 45px;
            border: 2px solid #220044;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
            -webkit-appearance: none;
        }

        .search-input:focus {
            outline: none;
            border-color: #FF6600;
            box-shadow: 0 0 0 2px rgba(255, 102, 0, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #220044;
        }
        
        /* Media queries for responsive design */
        @media (max-width: 768px) {
            .main-content {
                padding: 12px;
            }
            
            .faculty-list {
                width: 100%;
            }
            
            .search-container {
                width: 100%;
            }
            
            .faculty-card {
                padding: 15px;
            }
            
            .faculty-name {
                font-size: 1.05rem;
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
                color: #FF6600;
            }
            
            .detail-value {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

<header>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(35, 1, 68);">
        <div class="container-fluid position-relative justify-content-center">
            <a href="welcome.php" class="btn btn-outline-light me-2 show-hamburger border-0 home-btn" style="position:absolute;left:5px;top:50%;transform:translateY(-50%);z-index:1100;padding-left:0;margin-left:0;background:transparent;">
                <i class="fas fa-home"></i>
            </a>
            <a class="navbar-brand mx-auto d-flex align-items-center justify-content-center" href="#">
                <img src="images/octopus-logo.png" alt="Logo" class="navbar-logo me-2">
                <span>Repositups</span>
            </a>
        </div>
    </nav>
</header>

<div class="main-content">
    <h2>List of Faculty and Staff</h2>

    <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input type="text" class="search-input" id="facultySearch" placeholder="Search faculty..." onkeyup="searchFaculty()">
    </div>

    <?php
    if ($result->num_rows > 0) {
        echo "<ul class='faculty-list'>";

        while ($row = $result->fetch_assoc()) {
            echo "<li class='faculty-card'>
                    <div class='faculty-name'>" . htmlspecialchars($row['fullName']) . "</div>
                    <div class='faculty-details'>
                        <div class='detail-item'>
                            <span class='detail-label'>Position:</span>
                            <span class='detail-value'>" . htmlspecialchars($row['position']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Designation:</span>
                            <span class='detail-value'>" . htmlspecialchars($row['designation']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Email:</span>
                            <span class='detail-value'><i class='fas fa-envelope'></i>" . htmlspecialchars($row['email']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>ORCID:</span>
                            <span class='detail-value'><i class='fas fa-id-card'></i>" . htmlspecialchars($row['ORCID']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Contact Number:</span>
                            <span class='detail-value'><i class='fas fa-phone'></i>" . htmlspecialchars($row['contactNumber']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Educational Attainment:</span>
                            <span class='detail-value'><i class='fas fa-graduation-cap'></i>" . htmlspecialchars($row['educationalAttainment']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Field of Specialization:</span>
                            <span class='detail-value'><i class='fas fa-book'></i>" . htmlspecialchars($row['fieldOfSpecialization']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Research Interest:</span>
                            <span class='detail-value'><i class='fas fa-microscope'></i>" . htmlspecialchars($row['researchInterest']) . "</span>
                        </div>
                    </div>
                  </li>";
        }

        echo "</ul>";
    } else {
        echo "<div class='empty-message'>No faculty found.</div>";
    }
    ?>
</div>

<script>
function searchFaculty() {
    let input = document.getElementById('facultySearch');
    let filter = input.value.toLowerCase();
    let facultyList = document.getElementsByClassName('faculty-card');
    let noResults = true;

    for (let i = 0; i < facultyList.length; i++) {
        let card = facultyList[i];
        let text = card.textContent || card.innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            card.style.display = "";
            noResults = false;
        } else {
            card.style.display = "none";
        }
    }
    
    // Show a message if no results found
    let existingMessage = document.getElementById('no-results-message');
    if (filter && noResults) {
        if (!existingMessage) {
            let message = document.createElement('div');
            message.id = 'no-results-message';
            message.className = 'empty-message';
            message.textContent = 'No matching faculty found.';
            document.querySelector('.faculty-list').after(message);
        }
    } else if (existingMessage) {
        existingMessage.remove();
    }
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>