<?php
/**
 * Database Configuration and Connection
 * Professional mysqli connection with error handling
 */

// Prevent direct access
if (!defined('DB_HOST')) {
    die('Configuration not loaded. Please include config.php first.');
}

// Initialize connection variable
$conn = null;

try {
    // Get database configuration
    $host = DB_HOST;
    $port = defined('DB_PORT') ? (int)DB_PORT : 3307;
    $username = DB_USER;
    $password = DB_PASS;
    $database = DB_NAME;
    
    // Create mysqli connection
    $conn = new mysqli($host, $username, $password, $database, $port);
    
    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Database connection error. Please check your database settings.");
    }
    
    // Set charset to UTF-8
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Error loading character set utf8mb4: " . $conn->error);
    }
    
    // Set timezone
    $conn->query("SET time_zone = '+03:00'"); // Ethiopia timezone
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection error. Please try again later.");
}

// Verify connection is working
if (!$conn || $conn->connect_error) {
    die("Database connection failed. Please check your configuration.");
}