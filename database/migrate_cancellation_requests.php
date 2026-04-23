<?php
/**
 * Migration: Create cancellation_requests table and update bookings status enum
 * Run once to set up the manager-approval cancellation flow.
 */

require_once __DIR__ . '/../includes/config.php';

echo "Starting migration: cancellation_requests table...\n\n";

$steps = [

    "Create cancellation_requests table" => "
        CREATE TABLE IF NOT EXISTS `cancellation_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `booking_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `refund_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `refund_percentage` INT NOT NULL DEFAULT 0,
            `processing_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `final_refund` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `days_before_checkin` INT NOT NULL DEFAULT 0,
            `status` ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
            `manager_notes` TEXT DEFAULT NULL,
            `processed_by` INT DEFAULT NULL,
            `processed_at` DATETIME DEFAULT NULL,
            `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_booking_id` (`booking_id`),
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    "Update bookings.status enum (add Pending Cancellation)" => "
        ALTER TABLE bookings MODIFY COLUMN status ENUM(
            'pending','confirmed','verified','checked_in','checked_out',
            'cancelled','Cancelled','Pending Cancellation','no_show'
        ) DEFAULT 'pending'
    ",

    "Update bookings.payment_status enum (add refund statuses)" => "
        ALTER TABLE bookings MODIFY COLUMN payment_status ENUM(
            'pending','paid','refunded','partial_refund','refund_pending'
        ) DEFAULT 'pending'
    ",

    "Add cancelled_at column to bookings" => "
        ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL
    ",

    "Add cancelled_by column to bookings" => "
        ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancelled_by INT NULL
    ",

    "Add cancellation_reason column to bookings" => "
        ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL
    ",

    "Add refund_amount column to bookings" => "
        ALTER TABLE bookings ADD COLUMN IF NOT EXISTS refund_amount DECIMAL(10,2) DEFAULT 0.00
    ",

    "Add penalty_amount column to bookings" => "
        ALTER TABLE bookings ADD COLUMN IF NOT EXISTS penalty_amount DECIMAL(10,2) DEFAULT 0.00
    ",
];

foreach ($steps as $label => $sql) {
    if ($conn->query(trim($sql))) {
        echo "✓ $label\n";
    } else {
        echo "✗ $label: " . $conn->error . "\n";
    }
}

echo "\n✅ Migration complete!\n";
$conn->close();
