<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any previous form data when loading the page initially (not on form submit)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    unset($_SESSION['form_data']);
}

// Check if user is Administrator (deny access)
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            line-height: 1.6;
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            flex: 1 0 auto;
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(34, 0, 68, 0.1);
            overflow: hidden;
        }

        .header {
            
            padding: 20px;
            text-align: center;
            color: white;
            font-weight: 600;
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            color:#220044;
        }

        .header p {
            opacity: 0.9;
            font-size: 1rem;
            margin-bottom: 0;
            color: #220044;
        }

        .form-container {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 1rem;
        }

        label::after {
            content: ' *';
            color: #FF0000;
        }

        input[type="text"], textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        input[type="text"]:focus, textarea:focus {
            outline: none;
            border-color: #FF6600;
            box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        .submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #FF6600;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            margin: 20px auto 0;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.3s ease;
            width: fit-content;
            min-width: 120px;
        }

        .submit-btn:hover {
            background-color: #FF884D;
            color: white;
            transform: none;
            box-shadow: none;
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .char-count {
            text-align: right;
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }

        .input-group {
            position: relative;
        }

        .validation-message {
            font-size: 0.875rem;
            margin-top: 5px;
            display: none;
        }

        .validation-message.error {
            color: #dc3545;
            display: block;
        }

        .validation-message.success {
            color: #28a745;
            display: block;
        }

        .form-field {
            position: relative;
        }

        .form-field.valid input,
        .form-field.valid textarea {
            border-color: #FF6600;
            box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.1);
        }

        .form-field.invalid input,
        .form-field.invalid textarea {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .validation-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2rem;
            display: none;
        }

        .validation-icon.valid {
            color: #FF6600;
            display: block;
        }

        .validation-icon.invalid {
            color: #dc3545;
            display: block;
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .strength-meter {
            height: 4px;
            background-color: #e1e5e9;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: width 0.3s ease, background-color 0.3s ease;
            width: 0%;
        }

        .strength-weak { background-color: #dc3545; }
        .strength-fair { background-color: #ffc107; }
        .strength-good { background-color: #28a745; }

        .requirements-list {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #6c757d;
            display: none;
        }

        .requirements-list.show {
            display: block;
        }

        .requirement {
            font-size: 0.85rem;
            margin-bottom: 5px;
            color: #6c757d;
            display: flex;
            align-items: center;
        }

        .requirement.met {
            color: #FF6600;
        }

        .requirement::before {
            content: "✗";
            margin-right: 8px;
            font-weight: bold;
        }

        .requirement.met::before {
            content: "✓";
        }

        .form-progress {
            height: 6px;
            background-color: #e1e5e9;
            border-radius: 3px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #220044;
            width: 0%;
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .container {
                margin: 15px;
            }
            
            .form-container {
                padding: 15px;
            }
            
            .header {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }

            .submit-btn {
                font-size: 0.9rem;
                padding: 7px 12px;
                width: 100%;
            }
        }

        /* Navigation bar styles */
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
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(35, 1, 68);">
            <div class="container-fluid position-relative justify-content-center">
                <!-- Home button -->
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

    <div class="container">
        <div class="header">
            <h1>Contact Us</h1>
            <p>We'd love to hear from you. Send us a message!</p>
        </div>
        
        <div class="form-container">
            <?php
            // Database configuration for repositups database
            require_once 'config.php'; // Use the shared database connection
            
            $message = "";
            $messageType = "";
            
            // Create contact table if it doesn't exist
            try {
                $createTableSQL = "CREATE TABLE IF NOT EXISTS contact (
                    contactID INT AUTO_INCREMENT PRIMARY KEY,
                    userID INT,
                    subject VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    submittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (userID) REFERENCES User(userID) ON DELETE SET NULL
                )";
                $pdo->exec($createTableSQL);
            } catch(PDOException $e) {
                error_log("Error creating contact table: " . $e->getMessage());
                // Continue execution even if table creation fails
            }
            
            // Check if form is submitted
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $subject = trim($_POST['subject']);
                $userMessage = trim($_POST['message']);
                
                // Store form data in session temporarily in case of error
                $_SESSION['form_data'] = [
                    'subject' => $subject,
                    'message' => $userMessage
                ];
                
                // For now using userID = 1, update this with actual session user ID
                // Example: $userID = $_SESSION['user_id']; 
                $userID = 1;
                
                // Server-Side PHP Validation
                if (empty($subject) || empty($userMessage)) {
                    $message = "Please fill in all fields.";
                    $messageType = "error";
                } elseif (strlen($subject) > 255) {
                    $message = "Subject must be less than 255 characters.";
                    $messageType = "error";
                } elseif (strlen($userMessage) > 1000) {
                    $message = "Message must be less than 1000 characters.";
                    $messageType = "error";
                } else {
                    try {
                        // Use the PDO connection from config.php
                        
                        // Insert data into contact table
                        $sql = "INSERT INTO contact (userID, subject, message) VALUES (:userID, :subject, :message)";
                        $stmt = $pdo->prepare($sql);
                        
                        // Bind parameters and execute
                        $result = $stmt->execute([
                            ':userID' => $userID,
                            ':subject' => $subject,
                            ':message' => $userMessage
                        ]);
                        
                        if ($result) {
                            $message = "Thank you! Your message has been sent successfully.";
                            $messageType = "success";
                            
                            // Clear form data after successful submission
                            unset($_SESSION['form_data']);
                            
                            // Clear localStorage draft
                            echo "<script>localStorage.removeItem('contactDraft');</script>";
                        } else {
                            $message = "Error: Failed to save your message. Please try again.";
                            $messageType = "error";
                        }
                        
                    } catch(PDOException $e) {
                        $message = "Database Error: " . $e->getMessage();
                        $messageType = "error";
                        
                        // Log the error for debugging (in production, log to file instead)
                        error_log("Contact form error: " . $e->getMessage());
                    }
                }
            }
            ?>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="contactForm" novalidate>
                <div class="form-progress">
                    <div class="progress-fill" id="formProgress"></div>
                </div>
                
                <div class="form-group">
                    <div class="form-field" id="subjectField">
                        <label for="subject">Subject</label>
                        <div class="input-group">
                            <input type="text" 
                                   id="subject" 
                                   name="subject" 
                                   minlength="5"
                                   maxlength="255"
                                   value="<?php echo isset($_SESSION['form_data']['subject']) ? htmlspecialchars($_SESSION['form_data']['subject']) : ''; ?>"
                                   required>
                            <div class="validation-icon" id="subjectIcon"></div>
                        </div>
                        <div class="char-count" id="subject-count">0/255 characters</div>
                        <div class="validation-message" id="subjectError"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-field" id="messageField">
                        <label for="message">Message</label>
                        <div class="input-group">
                            <textarea id="message" 
                                      name="message" 
                                      placeholder="Type your message here..."
                                      minlength="10"
                                      maxlength="1000"
                                      required><?php echo isset($_SESSION['form_data']['message']) ? htmlspecialchars($_SESSION['form_data']['message']) : ''; ?></textarea>
                            <div class="validation-icon" id="messageIcon"></div>
                        </div>
                        <div class="char-count" id="message-count">0/1000 characters</div>
                        <div class="validation-message" id="messageError"></div>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    <span id="submitText">Send Message</span>
                    <span id="submitLoader" style="display: none;">Sending...</span>
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-Side java script validation
        const validationConfig = {
            subject: {
                required: true,
                minLength: 5,
                maxLength: 255
            },
            message: {
                required: true,
                minLength: 10,
                maxLength: 1000
            }
        };

        function validateField(fieldName) {
            const input = document.getElementById(fieldName);
            const field = document.getElementById(fieldName + 'Field');
            const icon = document.getElementById(fieldName + 'Icon');
            const error = document.getElementById(fieldName + 'Error');
            
            const value = input.value.trim();
            let isValid = true;
            let errorMessage = '';

            // Reset previous state
            field.classList.remove('valid', 'invalid');
            icon.classList.remove('valid', 'invalid');
            error.classList.remove('error', 'success');

            // Validation checks
            if (value === '') {
                isValid = false;
                errorMessage = `${fieldName.charAt(0).toUpperCase() + fieldName.slice(1)} is required`;
            } else if (value.length < validationConfig[fieldName].minLength) {
                isValid = false;
                errorMessage = `${fieldName.charAt(0).toUpperCase() + fieldName.slice(1)} must be at least ${validationConfig[fieldName].minLength} characters`;
            } else if (value.length > validationConfig[fieldName].maxLength) {
                isValid = false;
                errorMessage = `${fieldName.charAt(0).toUpperCase() + fieldName.slice(1)} must be less than ${validationConfig[fieldName].maxLength} characters`;
            }

            // Apply validation state
            if (isValid && value.length > 0) {
                field.classList.add('valid');
                icon.classList.add('valid');
                icon.innerHTML = '✓';
                error.textContent = '';
            } else if (!isValid) {
                field.classList.add('invalid');
                icon.classList.add('invalid');
                icon.innerHTML = '✗';
                error.textContent = errorMessage;
                error.classList.add('error');
            } else {
                // Neutral state (empty but not invalid)
                icon.innerHTML = '';
                error.textContent = '';
            }

            return isValid;
        }

        function validateForm() {
            const subjectValid = validateField('subject');
            const messageValid = validateField('message');
            return subjectValid && messageValid;
        }

        function updateFormProgress() {
            const subjectInput = document.getElementById('subject');
            const messageInput = document.getElementById('message');
            const progressFill = document.getElementById('formProgress');
            
            let progress = 0;
            
            // Check subject completion
            if (subjectInput.value.trim().length > 0) {
                progress += 50;
            }
            
            // Check message completion
            if (messageInput.value.trim().length > 0) {
                progress += 50;
            }
            
            progressFill.style.width = progress + '%';
        }

        // Initialization and event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('contactForm');
            const subjectInput = document.getElementById('subject');
            const messageInput = document.getElementById('message');
            const submitBtn = document.getElementById('submitBtn');

            // Initialize character counters
            updateCharCount(subjectInput, document.getElementById('subject-count'), 255);
            updateCharCount(messageInput, document.getElementById('message-count'), 1000);
            
            // Real-time validation
            subjectInput.addEventListener('input', () => {
                updateCharCount(subjectInput, document.getElementById('subject-count'), 255);
                validateField('subject');
                updateFormProgress();
            });

            messageInput.addEventListener('input', () => {
                updateCharCount(messageInput, document.getElementById('message-count'), 1000);
                validateField('message');
                updateFormProgress();
            });

            // Form submission validation
            form.addEventListener('submit', function(e) {
                const isValid = validateForm();
                if (!isValid) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                showLoadingState();
            });

            // Clear form if there was a successful submission
            <?php if ($messageType === 'success'): ?>
            clearForm();
            <?php endif; ?>
        });

        function showLoadingState() {
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const submitLoader = document.getElementById('submitLoader');
            
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitText.style.display = 'none';
            submitLoader.style.display = 'inline';
        }

        function updateCharCount(inputElement, countElement, maxLength) {
            const currentLength = inputElement.value.length;
            countElement.textContent = `${currentLength}/${maxLength} characters`;
            
            if (currentLength > maxLength * 0.9) {
                countElement.style.color = '#dc3545';
            } else if (currentLength > maxLength * 0.7) {
                countElement.style.color = '#ffc107';
            } else {
                countElement.style.color = '#666';
            }
        }

        // Additional utility functions for enhanced UX
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Auto-save draft functionality (optional)
        function saveDraft() {
            const subjectValue = document.getElementById('subject').value;
            const messageValue = document.getElementById('message').value;
            
            if (subjectValue || messageValue) {
                localStorage.setItem('contactDraft', JSON.stringify({
                    subject: subjectValue,
                    message: messageValue,
                    timestamp: Date.now()
                }));
            }
        }

        function loadDraft() {
            const draft = localStorage.getItem('contactDraft');
            if (draft) {
                const draftData = JSON.parse(draft);
                const timeElapsed = Date.now() - draftData.timestamp;
                
                // Load draft if it's less than 1 hour old
                if (timeElapsed < 3600000) {
                    document.getElementById('subject').value = draftData.subject;
                    document.getElementById('message').value = draftData.message;
                    
                    // Update counters and validation
                    updateCharCount(document.getElementById('subject'), document.getElementById('subject-count'), 255);
                    updateCharCount(document.getElementById('message'), document.getElementById('message-count'), 1000);
                    validateField('subject');
                    validateField('message');
                    updateFormProgress();
                }
            }
        }

        // Load draft on page load
        document.addEventListener('DOMContentLoaded', loadDraft);

        // Save draft periodically
        setInterval(saveDraft, 10000); // Save every 10 seconds

        function clearForm() {
            document.getElementById('subject').value = '';
            document.getElementById('message').value = '';
            localStorage.removeItem('contactDraft');
            updateFormProgress();
            validateField('subject');
            validateField('message');
        }
    </script>
</body>
</html>