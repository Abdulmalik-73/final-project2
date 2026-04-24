<?php
/**
 * Create temp_id_uploads table for temporary ID storage during booking process
 */

require_once __DIR__ . '/../includes/config.php';

try {
    // Create temp_id_uploads table
    $create_table = "
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
    
    if ($conn->query($create_table)) {
        echo "✅ temp_id_uploads table created successfully\n";
    } else {
        throw new Exception("Failed to create temp_id_uploads table: " . $conn->error);
    }
    
    // Add cleanup for old entries (older than 1 hour)
    $cleanup = "DELETE FROM temp_id_uploads WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    if ($conn->query($cleanup)) {
        echo "✅ Cleaned up old temporary uploads\n";
    }
    
    echo "✅ Migration completed successfully\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>