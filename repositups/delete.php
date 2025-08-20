<?php
require_once 'config.php';

// Check if ID is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $facultyID = $_GET['id'];
    
    // Prepare and execute the delete statement
    $stmt = $conn->prepare("DELETE FROM faculty WHERE facultyID = ?");
    $stmt->bind_param("s", $facultyID);
    
    if ($stmt->execute()) {
        // Success message will be displayed on redirect
    } else {
        // Store error in session to display after redirect
        session_start();
        $_SESSION['delete_error'] = "Error deleting record: " . $stmt->error;
    }
    
    $stmt->close();
}

// Redirect back to the faculty list
header("Location: faculty_staff_list.php");
exit();
?>

