<?php
/**
 * Test script for ID image viewing functionality
 * This script helps test the new file-based ID image system
 */

require_once '../includes/config.php';

echo "<h1>ID Image System Test</h1>";

// Test 1: Check uploads directory
echo "<h2>1. Uploads Directory Check</h2>";
$upload_dir = __DIR__ . '/../uploads/ids/';
if (is_dir($upload_dir)) {
    echo "✅ Uploads directory exists: $upload_dir<br>";
    if (is_writable($upload_dir)) {
        echo "✅ Uploads directory is writable<br>";
    } else {
        echo "❌ Uploads directory is not writable<br>";
    }
    
    // List existing files
    $files = glob($upload_dir . '*');
    echo "📁 Found " . count($files) . " files in uploads/ids/:<br>";
    foreach ($files as $file) {
        echo "   - " . basename($file) . " (" . number_format(filesize($file)/1024, 2) . " KB)<br>";
    }
} else {
    echo "❌ Uploads directory does not exist<br>";
}

// Test 2: Check database for ID images
echo "<h2>2. Database Check</h2>";
$stmt = $conn->prepare("SELECT id, id_image FROM bookings WHERE id_image IS NOT NULL AND id_image != '' LIMIT 10");
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "📊 Found " . count($bookings) . " bookings with ID images:<br>";
foreach ($bookings as $booking) {
    $booking_id = $booking['id'];
    $id_image = $booking['id_image'];
    
    if (strpos($id_image, 'data:') === 0) {
        echo "   - Booking #$booking_id: BASE64 (legacy format)<br>";
    } else {
        echo "   - Booking #$booking_id: FILE - $id_image<br>";
        
        // Check if file exists
        $filepath = $upload_dir . $id_image;
        if (file_exists($filepath)) {
            echo "     ✅ File exists<br>";
        } else {
            echo "     ❌ File missing<br>";
        }
    }
}

// Test 3: Test secure viewer
echo "<h2>3. Secure Viewer Test</h2>";
foreach ($bookings as $booking) {
    $booking_id = $booking['id'];
    echo "<p>";
    echo "<strong>Booking #$booking_id:</strong><br>";
    echo "<img src='../view-id.php?booking_id=$booking_id' style='max-width: 200px; border: 1px solid #ccc;' onerror=\"this.style.border='2px solid red'; this.alt='ERROR: Image failed to load';\">";
    echo "</p>";
}

echo "<h2>4. Security Check</h2>";
echo "Testing .htaccess protection:<br>";
$htaccess_file = $upload_dir . '.htaccess';
if (file_exists($htaccess_file)) {
    echo "✅ .htaccess file exists<br>";
} else {
    echo "❌ .htaccess file missing<br>";
}

echo "<h2>5. Migration Status</h2>";
$stmt_base64 = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE id_image IS NOT NULL AND id_image != '' AND id_image LIKE 'data:%'");
$stmt_base64->execute();
$base64_count = $stmt_base64->get_result()->fetch_assoc()['count'];

$stmt_files = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE id_image IS NOT NULL AND id_image != '' AND id_image NOT LIKE 'data:%'");
$stmt_files->execute();
$files_count = $stmt_files->get_result()->fetch_assoc()['count'];

echo "📈 Migration status:<br>";
echo "   - Base64 images (legacy): $base64_count<br>";
echo "   - File-based images (new): $files_count<br>";

if ($base64_count > 0) {
    echo "<br>⚠️  You have $base64_count base64 images that need migration.<br>";
    echo "Run: <code>php api/migrate_base64_to_files.php</code> to migrate them.<br>";
} else {
    echo "<br>✅ All images are using the new file-based format!<br>";
}

echo "<hr>";
echo "<p><em>Test completed. If you see broken images above, check the error messages and server logs.</em></p>";
?>
