<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$tab = $_GET['tab'] ?? 'password';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required';
    } else {
        // Fetch current password hash
        $query = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && !empty($user['password']) && password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_query = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param("si", $new_password_hash, $user_id);
                    
                    if ($stmt->execute()) {
                        $message = 'Password changed successfully!';
                    } else {
                        $error = 'Failed to update password';
                    }
                } else {
                    $error = 'New password must be at least 6 characters long';
                }
            } else {
                $error = 'New passwords do not match';
            }
        } else {
            $error = 'Current password is incorrect';
        }
    }
}

// Handle notification preferences
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_notifications'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $booking_reminders = isset($_POST['booking_reminders']) ? 1 : 0;
    
    $update_query = "UPDATE users SET email_notifications = ?, sms_notifications = ?, booking_reminders = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("iiii", $email_notifications, $sms_notifications, $booking_reminders, $user_id);
    
    if ($stmt->execute()) {
        $message = 'Notification preferences updated successfully!';
    } else {
        $error = 'Failed to update notification preferences';
    }
}

// Fetch user settings
$settings_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($settings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_settings = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <section class="py-5">
        <div class="container">
            <!-- Back Button -->
            <div class="row mb-3">
                <div class="col-12">
                    <button onclick="goBackToMain()" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back
                    </button>
                </div>
            </div>
            <h2 class="mb-4"><i class="fas fa-cog me-2"></i> Account Settings</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="list-group">
                        <a href="settings.php?tab=password" class="list-group-item list-group-item-action <?php echo $tab == 'password' ? 'active' : ''; ?>">
                            <i class="fas fa-key me-2"></i> Change Password
                        </a>
                        <a href="settings.php?tab=notifications" class="list-group-item list-group-item-action <?php echo $tab == 'notifications' ? 'active' : ''; ?>">
                            <i class="fas fa-bell me-2"></i> Notifications
                        </a>
                        <a href="settings.php?tab=privacy" class="list-group-item list-group-item-action <?php echo $tab == 'privacy' ? 'active' : ''; ?>">
                            <i class="fas fa-shield-alt me-2"></i> Privacy Settings
                        </a>
                        <a href="profile.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user me-2"></i> Back to Profile
                        </a>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <?php if ($tab == 'password'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-key me-2"></i> Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Current Password *</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" name="new_password" class="form-control" minlength="6" required>
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-gold">
                                    <i class="fas fa-save me-2"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($tab == 'notifications'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bell me-2"></i> Notification Preferences</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="email_notifications" id="emailNotif" <?php echo ($user_settings['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="emailNotif">
                                        <strong>Email Notifications</strong><br>
                                        <small class="text-muted">Receive booking confirmations and updates via email</small>
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="sms_notifications" id="smsNotif" <?php echo ($user_settings['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="smsNotif">
                                        <strong>SMS Notifications</strong><br>
                                        <small class="text-muted">Receive text messages for important updates</small>
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="booking_reminders" id="bookingReminders" <?php echo ($user_settings['booking_reminders'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="bookingReminders">
                                        <strong>Booking Reminders</strong><br>
                                        <small class="text-muted">Get reminders before your check-in date</small>
                                    </label>
                                </div>
                                <button type="submit" name="update_notifications" class="btn btn-gold">
                                    <i class="fas fa-save me-2"></i> Save Preferences
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($tab == 'privacy'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i> Privacy Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Your privacy is important to us. We protect your personal information according to our privacy policy.
                            </div>
                            <h6>Data Privacy</h6>
                            <p>Your personal information is stored securely and is only used for booking and communication purposes.</p>
                            
                            <h6 class="mt-4">Account Deletion</h6>
                            <p>If you wish to delete your account, please contact our support team.</p>
                            <a href="contact.php" class="btn btn-outline-danger">
                                <i class="fas fa-envelope me-2"></i> Contact Support
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function goBackToMain() {
            // Always redirect to the main page where user can access their profile dropdown
            <?php if (isset($_SESSION['user_role'])): ?>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    window.location.href = 'dashboard/admin.php';
                <?php elseif ($_SESSION['user_role'] === 'manager'): ?>
                    window.location.href = 'dashboard/manager.php';
                <?php elseif ($_SESSION['user_role'] === 'receptionist'): ?>
                    window.location.href = 'dashboard/receptionist.php';
                <?php else: ?>
                    window.location.href = 'index.php';
                <?php endif; ?>
            <?php else: ?>
                window.location.href = 'index.php';
            <?php endif; ?>
        }
    </script>
</body>
</html>
