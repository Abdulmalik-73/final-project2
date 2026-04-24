<?php
// Debug logout functionality
session_start();

echo "<h2>Logout Debug Test</h2>";
echo "<p><strong>Current Session Data:</strong></p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . session_status() . "</p>";

if (isset($_SESSION['user_id'])) {
    echo "<p><strong>User ID:</strong> " . $_SESSION['user_id'] . "</p>";
    echo "<p><strong>User Role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "</p>";
    echo "<p><strong>User Name:</strong> " . ($_SESSION['user_name'] ?? 'Not set') . "</p>";
}

echo "<hr>";
echo "<p><a href='logout.php' class='btn btn-danger'>Test Logout</a></p>";
echo "<p><a href='dashboard/receptionist.php'>Back to Receptionist Dashboard</a></p>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";

// Test if logout.php exists
if (file_exists('logout.php')) {
    echo "<p><strong>✅ logout.php file exists</strong></p>";
} else {
    echo "<p><strong>❌ logout.php file NOT found</strong></p>";
}

// Test if we can read logout.php
if (is_readable('logout.php')) {
    echo "<p><strong>✅ logout.php is readable</strong></p>";
} else {
    echo "<p><strong>❌ logout.php is NOT readable</strong></p>";
}
?>