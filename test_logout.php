<?php
// Simple logout test
session_start();

echo "<h2>Logout Test</h2>";
echo "<p>Current session status:</p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo '<p><a href="logout.php" class="btn btn-danger">Test Logout</a></p>';
echo '<p><a href="login.php">Go to Login</a></p>';
?>