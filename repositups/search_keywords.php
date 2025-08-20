<?php
require_once 'config.php';

// Ensure the request is AJAX
if(isset($_GET['query'])) {
    $query = $_GET['query'];
    
    // Prepare and execute search query
    $sql = "SELECT keywordID, keywordName FROM Keyword 
            WHERE keywordName LIKE ? 
            ORDER BY keywordName ASC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $searchTerm = "%" . $query . "%";
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $keywords = array();
        while ($row = $result->fetch_assoc()) {
            $keywords[] = array(
                'id' => $row['keywordID'],
                'name' => $row['keywordName']
            );
        }
        
        header('Content-Type: application/json');
        echo json_encode($keywords);
        
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare statement']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No search query provided']);
}

$conn->close();
?>
