<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;

try {
    $rooms = get_all_rooms($limit);
    
    echo json_encode([
        'success' => true,
        'data' => $rooms
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching rooms: ' . $e->getMessage()
    ]);
}
?>
