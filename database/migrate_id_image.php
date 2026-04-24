<?php
/**
 * Migration: Add id_image column to bookings and food_orders tables
 * Run once to enable ID upload verification feature.
 */

require_once __DIR__ . '/../includes/config.php';

echo "Starting migration: id_image columns...\n\n";

$steps = [
    "Add id_image to bookings table" =>
        "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS id_image MEDIUMTEXT DEFAULT NULL COMMENT 'Base64 encoded customer ID image'",

    "Add id_image to food_orders table" =>
        "ALTER TABLE food_orders ADD COLUMN IF NOT EXISTS id_image MEDIUMTEXT DEFAULT NULL COMMENT 'Base64 encoded customer ID image'",

    "Add index on bookings.id_image" =>
        "ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_id_image (id_image(50))",
];

foreach ($steps as $label => $sql) {
    if ($conn->query(trim($sql))) {
        echo "✓ $label\n";
    } else {
        echo "✗ $label: " . $conn->error . "\n";
    }
}

// Create uploads/ids directory if it doesn't exist
$ids_dir = __DIR__ . '/../uploads/ids';
if (!is_dir($ids_dir)) {
    if (mkdir($ids_dir, 0755, true)) {
        echo "✓ Created uploads/ids/ directory\n";
    } else {
        echo "✗ Failed to create uploads/ids/ directory\n";
    }
} else {
    echo "✓ uploads/ids/ directory already exists\n";
}

echo "\n✅ Migration complete!\n";
$conn->close();
