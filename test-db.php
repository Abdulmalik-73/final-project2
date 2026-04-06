<?php
/**
 * Database Connection Test
 */

// Test direct connection
$host = 'localhost';
$port = 3307;
$user = 'root';
$pass = '';
$db = 'harar_ras_hotel';

echo "<h2>Database Connection Test</h2>";

// Test 1: Check if MySQL extension is loaded
echo "<h3>1. PHP MySQL Extension</h3>";
if (extension_loaded('mysqli')) {
    echo "✅ MySQLi extension is loaded<br>";
} else {
    echo "❌ MySQLi extension is NOT loaded<br>";
    die("Please enable mysqli extension in php.ini");
}

// Test 2: Try to connect
echo "<h3>2. Connection Test</h3>";
echo "Attempting to connect to:<br>";
echo "Host: $host<br>";
echo "Port: $port<br>";
echo "User: $user<br>";
echo "Database: $db<br><br>";

$conn = @new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    echo "❌ Connection failed: " . $conn->connect_error . "<br>";
    echo "<br><strong>Common Solutions:</strong><br>";
    echo "1. Make sure XAMPP/MySQL is running<br>";
    echo "2. Check if port 3307 is correct (default is 3306)<br>";
    echo "3. Verify database 'harar_ras_hotel' exists<br>";
    echo "4. Check username and password<br>";
} else {
    echo "✅ Connected successfully!<br>";
    echo "MySQL version: " . $conn->server_info . "<br>";
    
    // Test 3: Check if database exists
    echo "<h3>3. Database Check</h3>";
    $result = $conn->query("SHOW DATABASES LIKE 'harar_ras_hotel'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Database 'harar_ras_hotel' exists<br>";
        
        // Test 4: Check tables
        echo "<h3>4. Tables Check</h3>";
        $tables = $conn->query("SHOW TABLES");
        if ($tables && $tables->num_rows > 0) {
            echo "✅ Found " . $tables->num_rows . " tables<br>";
            echo "<ul>";
            while ($row = $tables->fetch_array()) {
                echo "<li>" . $row[0] . "</li>";
            }
            echo "</ul>";
        } else {
            echo "⚠️ No tables found. You may need to import setup.sql<br>";
        }
    } else {
        echo "❌ Database 'harar_ras_hotel' does NOT exist<br>";
        echo "<br><strong>Solution:</strong> Create the database first:<br>";
        echo "<code>CREATE DATABASE harar_ras_hotel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code><br>";
    }
    
    $conn->close();
}

echo "<br><hr>";
echo "<a href='index.php'>Back to Home</a> | ";
echo "<a href='test-chapa.php'>Test Chapa</a>";
?>
