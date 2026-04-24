<?php
/**
 * Cleanup old temporary ID uploads (older than 1 hour)
 * This can be run as a cron job or called periodically
 */

require_once __DIR__ . '/../includes/config.php';

try {
    // Delete temporary uploads older than 1 hour
    $cleanup_stmt = $conn->prepare("DELETE FROM temp_id_uploads WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    
    if ($cleanup_stmt && $cleanup_stmt->execute()) {
        $deleted_count = $cleanup_stmt->affected_rows;
        error_log("Cleaned up $deleted_count old temporary ID uploads");
        
        if (php_sapi_name() === 'cli') {
            echo "✅ Cleaned up $deleted_count old temporary ID uploads\n";
        } else {
            echo json_encode(['success' => true, 'deleted' => $deleted_count]);
        }
    } else {
        throw new Exception('Failed to cleanup temporary uploads: ' . $conn->error);
    }
    
} catch (Exception $e) {
    error_log("Cleanup failed: " . $e->getMessage());
    
    if (php_sapi_name() === 'cli') {
        echo "❌ Cleanup failed: " . $e->getMessage() . "\n";
    } else {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>