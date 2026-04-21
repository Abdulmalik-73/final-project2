<?php
header('Content-Type: application/json');
require_once '../includes/config.php';

try {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 6;
    
    $query = "SELECT * FROM services WHERE status = 'active' ORDER BY category, name LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $services = [];
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $services
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching services: ' . $e->getMessage()
    ]);
}
?>
