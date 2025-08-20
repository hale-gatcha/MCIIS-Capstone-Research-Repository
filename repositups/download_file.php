<?php
require_once 'config.php';

// Validate parameters
$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$preview = isset($_GET['preview']) ? true : false;

if (!$id || !in_array($type, ['manuscript', 'approval'])) {
    http_response_code(400);
    exit('Invalid request');
}

try {
    // Determine which field to fetch
    $field = $type === 'manuscript' ? 'researchManuscript' : 'researchApprovalSheet';
    
    // Prepare and execute query
    $sql = "SELECT $field as file_content, researchTitle, publishedYear 
            FROM research 
            WHERE researchID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row || empty($row['file_content'])) {
        http_response_code(404);
        exit('File not found');
    }

    // Generate filename
    $sanitizedTitle = preg_replace('/[^a-zA-Z0-9]/', '_', $row['researchTitle']);
    $filename = sprintf('%s_%s_%d.pdf', 
        $sanitizedTitle,
        $type,
        $row['publishedYear']
    );

    // Set appropriate headers
    header('Content-Type: application/pdf');
    if ($preview) {
        header('Content-Disposition: inline; filename="' . $filename . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    }
    header('Content-Length: ' . strlen($row['file_content']));
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    // Output file content
    echo $row['file_content'];
    exit;

} catch (Exception $e) {
    error_log("File access error: " . $e->getMessage());
    http_response_code(500);
    exit('Error retrieving file');
}
?>