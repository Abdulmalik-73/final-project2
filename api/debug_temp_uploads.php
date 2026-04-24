<?php
/**
 * Debug script to check temp_id_uploads table and data
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

try {
    $debug_info = [];
    
    // Check if user is logged in
    $debug_info['session_user_id'] = $_SESSION['user_id'] ?? 'not set';
    $debug_info['session_status'] = session_status();
    $debug_info['session_id'] = session_id();
    
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'temp_id_uploads'");
    $debug_info['table_exists'] = $table_check->num_rows > 0;
    
    if ($debug_info['table_exists']) {
        // Get table structure
        $structure = $conn->query("DESCRIBE temp_id_uploads");
        $debug_info['table_structure'] = [];
        while ($row = $structure->fetch_assoc()) {
            $debug_info['table_structure'][] = $row;
        }
        
        // Count total records
        $count_result = $conn->query("SELECT COUNT(*) as count FROM temp_id_uploads");
        $debug_info['total_records'] = $count_result->fetch_assoc()['count'];
        
        // Get user's records if logged in
        if (isset($_SESSION['user_id'])) {
            $user_id = (int)$_SESSION['user_id'];
            $user_stmt = $conn->prepare("SELECT token, created_at, LENGTH(image_data) as image_size FROM temp_id_uploads WHERE user_id = ?");
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $debug_info['user_records'] = [];
            while ($row = $user_result->fetch_assoc()) {
                $debug_info['user_records'][] = $row;
            }
        }
    } else {
        // Try to create the table
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS temp_id_uploads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(32) NOT NULL UNIQUE,
                image_data MEDIUMTEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_token (token),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if ($conn->query($create_table_sql)) {
            $debug_info['table_created'] = true;
        } else {
            $debug_info['table_creation_error'] = $conn->error;
        }
    }
    
    echo json_encode($debug_info, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>