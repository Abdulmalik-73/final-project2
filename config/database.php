<?php
/**
 * Database Configuration and Connection
 * Professional mysqli connection with error handling
 * NOTE: Uses graceful error handling — never calls die() so Apache stays alive
 */

// Prevent direct access
if (!defined('DB_HOST')) {
    // Return silently — config.php will handle this
    return;
}

// Initialize connection variable
$conn = null;

try {
    $host     = DB_HOST;
    $port     = defined('DB_PORT') ? (int)DB_PORT : 3306;
    $username = DB_USER;
    $password = DB_PASS;
    $database = DB_NAME;

    // Suppress connection warnings — we handle errors ourselves
    mysqli_report(MYSQLI_REPORT_OFF);

    $conn = new mysqli($host, $username, $password, $database, $port);

    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        $conn = null;
    } else {
        // Set charset to UTF-8
        $conn->set_charset("utf8mb4");

        // Set Ethiopia timezone (UTC+3)
        $conn->query("SET time_zone = '+03:00'");
    }

} catch (Exception $e) {
    error_log("Database connection exception: " . $e->getMessage());
    $conn = null;
}
