<?php
/**
 * Migration Script: Add Cancellation Columns to Bookings Table
 * Run this once to add missing cancellation-related columns
 */

require_once __DIR__ . '/../includes/config.php';

echo "Starting migration: Adding cancellation columns to bookings table...\n\n";

try {
    // Add cancelled_at column
    $sql1 = "ALTER TABLE bookings 
             ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL COMMENT 'When booking was cancelled'";
    if ($conn->query($sql1)) {
        echo "✓ Added cancelled_at column\n";
    } else {
        echo "✗ Error adding cancelled_at: " . $conn->error . "\n";
    }
    
    // Add cancelled_by column
    $sql2 = "ALTER TABLE bookings 
             ADD COLUMN IF NOT EXISTS cancelled_by INT NULL COMMENT 'User ID who cancelled the booking'";
    if ($conn->query($sql2)) {
        echo "✓ Added cancelled_by column\n";
    } else {
        echo "✗ Error adding cancelled_by: " . $conn->error . "\n";
    }
    
    // Add cancellation_reason column
    $sql3 = "ALTER TABLE bookings 
             ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL COMMENT 'Reason for cancellation'";
    if ($conn->query($sql3)) {
        echo "✓ Added cancellation_reason column\n";
    } else {
        echo "✗ Error adding cancellation_reason: " . $conn->error . "\n";
    }
    
    // Add refund_amount column
    $sql4 = "ALTER TABLE bookings 
             ADD COLUMN IF NOT EXISTS refund_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Calculated refund amount'";
    if ($conn->query($sql4)) {
        echo "✓ Added refund_amount column\n";
    } else {
        echo "✗ Error adding refund_amount: " . $conn->error . "\n";
    }
    
    // Add penalty_amount column
    $sql5 = "ALTER TABLE bookings 
             ADD COLUMN IF NOT EXISTS penalty_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Penalty charged'";
    if ($conn->query($sql5)) {
        echo "✓ Added penalty_amount column\n";
    } else {
        echo "✗ Error adding penalty_amount: " . $conn->error . "\n";
    }
    
    // Update status enum to include 'cancelled'
    $sql6 = "ALTER TABLE bookings 
             MODIFY COLUMN status ENUM('pending', 'confirmed', 'verified', 'checked_in', 'checked_out', 'cancelled', 'no_show') DEFAULT 'pending'";
    if ($conn->query($sql6)) {
        echo "✓ Updated status enum\n";
    } else {
        echo "✗ Error updating status enum: " . $conn->error . "\n";
    }
    
    // Update payment_status enum to include refund statuses
    $sql7 = "ALTER TABLE bookings 
             MODIFY COLUMN payment_status ENUM('pending', 'paid', 'refunded', 'partial_refund', 'refund_pending') DEFAULT 'pending'";
    if ($conn->query($sql7)) {
        echo "✓ Updated payment_status enum\n";
    } else {
        echo "✗ Error updating payment_status enum: " . $conn->error . "\n";
    }
    
    // Add index for cancelled_at
    $sql8 = "ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_cancelled_at (cancelled_at)";
    if ($conn->query($sql8)) {
        echo "✓ Added index for cancelled_at\n";
    } else {
        echo "✗ Error adding index: " . $conn->error . "\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
}

$conn->close();
?>
