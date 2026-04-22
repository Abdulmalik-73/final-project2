<?php
require_once 'includes/config.php';

// Test staff login credentials
$email = 'receptionist@hararras.com';
$password = '@Ab7340di';

echo "<h2>Testing Staff Login Credentials</h2>";

// Query database for user
$query = "SELECT * FROM users WHERE email = ? AND status = 'active'";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        echo "<h3>User Found:</h3>";
        echo "ID: " . $user['id'] . "<br>";
        echo "Name: " . $user['first_name'] . " " . $user['last_name'] . "<br>";
        echo "Email: " . $user['email'] . "<br>";
        echo "Username: " . $user['username'] . "<br>";
        echo "Role: " . $user['role'] . "<br>";
        echo "Status: " . $user['status'] . "<br>";
        echo "Stored Password Hash: " . $user['password'] . "<br><br>";
        
        // Test password verification
        echo "<h3>Password Verification Tests:</h3>";
        
        // Test bcrypt
        $bcrypt_result = password_verify($password, $user['password']);
        echo "BCrypt verification: " . ($bcrypt_result ? "SUCCESS" : "FAILED") . "<br>";
        
        // Test MD5
        $md5_result = (md5($password) === $user['password']);
        echo "MD5 verification: " . ($md5_result ? "SUCCESS" : "FAILED") . "<br>";
        
        // Test plain text
        $plain_result = ($password === $user['password']);
        echo "Plain text verification: " . ($plain_result ? "SUCCESS" : "FAILED") . "<br>";
        
        // Test our custom function
        require_once 'includes/auth.php';
        $custom_result = verify_user_password($password, $user['password']);
        echo "Custom function verification: " . ($custom_result ? "SUCCESS" : "FAILED") . "<br>";
        
        if ($bcrypt_result || $md5_result || $plain_result || $custom_result) {
            echo "<br><strong style='color: green;'>✓ Password verification should work!</strong>";
        } else {
            echo "<br><strong style='color: red;'>✗ Password verification failed!</strong>";
            
            // Let's create a new hash
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            echo "<br><br>New BCrypt hash for password '$password': $new_hash";
            
            // Update the user with new hash
            $update_query = "UPDATE users SET password = ? WHERE email = ?";
            $update_stmt = $conn->prepare($update_query);
            if ($update_stmt) {
                $update_stmt->bind_param("ss", $new_hash, $email);
                if ($update_stmt->execute()) {
                    echo "<br><strong style='color: blue;'>✓ Password updated with new BCrypt hash!</strong>";
                } else {
                    echo "<br><strong style='color: red;'>✗ Failed to update password!</strong>";
                }
            }
        }
        
    } else {
        echo "<strong style='color: red;'>User not found or inactive!</strong>";
        
        // Let's check if user exists but is inactive
        $check_query = "SELECT * FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        if ($check_stmt) {
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $inactive_user = $check_result->fetch_assoc();
                echo "<br>User exists but status is: " . $inactive_user['status'];
            } else {
                echo "<br>User does not exist in database at all!";
            }
        }
    }
    $stmt->close();
} else {
    echo "Database error: " . $conn->error;
}
?>