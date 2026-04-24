<?php
/**
 * Migration script: Convert base64 ID images to files
 * This script should be run once to migrate existing base64 data to file storage
 * Run: php api/migrate_base64_to_files.php
 */

require_once '../includes/config.php';

echo "Starting migration of base64 ID images to files...\n";

// Create uploads/ids directory if it doesn't exist
$upload_dir = __DIR__ . '/../uploads/ids/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        die("Failed to create upload directory: $upload_dir\n");
    }
    echo "Created upload directory: $upload_dir\n";
}

// Get all bookings with base64 ID images
$stmt = $conn->prepare("SELECT id, id_image FROM bookings WHERE id_image IS NOT NULL AND id_image != '' AND id_image LIKE 'data:%'");
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total = count($bookings);
echo "Found $total bookings with base64 ID images\n";

$migrated = 0;
$failed = 0;

foreach ($bookings as $booking) {
    $booking_id = $booking['id'];
    $base64_data = $booking['id_image'];
    
    echo "Processing booking ID: $booking_id\n";
    
    // Parse base64 data
    if (preg_match('/^data:(image\/(jpeg|png|jpg));base64,(.+)$/s', $base64_data, $matches)) {
        $mime = $matches[1];
        $extension = ($matches[2] === 'jpg' || $matches[2] === 'jpeg') ? 'jpg' : 'png';
        $image_data = base64_decode($matches[3], true);
        
        if ($image_data === false) {
            echo "  - Failed to decode base64 for booking $booking_id\n";
            $failed++;
            continue;
        }
        
        // Generate unique filename
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        $filename = "id_{$booking_id}_{$timestamp}_{$random}.{$extension}";
        $filepath = $upload_dir . $filename;
        
        // Save image to file
        if (file_put_contents($filepath, $image_data) !== false) {
            // Update database with filename
            $update_stmt = $conn->prepare("UPDATE bookings SET id_image = ? WHERE id = ?");
            $update_stmt->bind_param("si", $filename, $booking_id);
            
            if ($update_stmt->execute()) {
                echo "  + Migrated successfully: $filename\n";
                $migrated++;
            } else {
                echo "  - Failed to update database for booking $booking_id: " . $update_stmt->error . "\n";
                // Clean up file if database update failed
                @unlink($filepath);
                $failed++;
            }
            $update_stmt->close();
        } else {
            echo "  - Failed to save file for booking $booking_id\n";
            $failed++;
        }
    } else {
        echo "  - Invalid base64 format for booking $booking_id\n";
        $failed++;
    }
}

echo "\nMigration completed:\n";
echo "- Total processed: $total\n";
echo "- Successfully migrated: $migrated\n";
echo "- Failed: $failed\n";

if ($failed > 0) {
    echo "\n⚠️  Some migrations failed. Check the logs above for details.\n";
} else {
    echo "\n✅ All base64 images have been successfully migrated to files!\n";
}

echo "\nNote: You can now safely delete the base64 data from the database if needed.\n";
?>
