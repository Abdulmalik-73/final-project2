<?php
/**
 * Security Test Page
 * Shows your current session information
 */

session_start();
require_once 'includes/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Test - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">🔒 Security Test Page</h4>
                    </div>
                    <div class="card-body">
                        <h5>Session Information</h5>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="alert alert-success">
                            <h6>✅ You are logged in</h6>
                        </div>
                        
                        <table class="table table-bordered">
                            <tr>
                                <th>User ID</th>
                                <td><?php echo $_SESSION['user_id']; ?></td>
                            </tr>
                            <tr>
                                <th>User Name</th>
                                <td><?php echo $_SESSION['user_name'] ?? 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>User Email</th>
                                <td><?php echo $_SESSION['user_email'] ?? 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>User Role</th>
                                <td><?php echo $_SESSION['user_role'] ?? 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Session ID</th>
                                <td><code><?php echo session_id(); ?></code></td>
                            </tr>
                            <tr>
                                <th>User Agent Hash</th>
                                <td><code><?php echo $_SESSION['user_agent'] ?? 'Not set'; ?></code></td>
                            </tr>
                            <tr>
                                <th>Current User Agent Hash</th>
                                <td><code><?php echo md5($_SERVER['HTTP_USER_AGENT'] ?? ''); ?></code></td>
                            </tr>
                            <tr>
                                <th>Match?</th>
                                <td>
                                    <?php 
                                    $match = isset($_SESSION['user_agent']) && $_SESSION['user_agent'] === md5($_SERVER['HTTP_USER_AGENT'] ?? '');
                                    echo $match ? '<span class="badge bg-success">✓ Yes</span>' : '<span class="badge bg-danger">✗ No</span>';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Login Time</th>
                                <td><?php echo isset($_SESSION['login_time']) ? date('Y-m-d H:i:s', $_SESSION['login_time']) : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Last Activity</th>
                                <td><?php echo isset($_SESSION['last_activity']) ? date('Y-m-d H:i:s', $_SESSION['last_activity']) : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Inactive Time</th>
                                <td>
                                    <?php 
                                    if (isset($_SESSION['last_activity'])) {
                                        $inactive = time() - $_SESSION['last_activity'];
                                        $hours = floor($inactive / 3600);
                                        $minutes = floor(($inactive % 3600) / 60);
                                        $seconds = $inactive % 60;
                                        echo "{$hours}h {$minutes}m {$seconds}s";
                                        
                                        if ($inactive > 86400) {
                                            echo ' <span class="badge bg-danger">Expired!</span>';
                                        } else {
                                            echo ' <span class="badge bg-success">Active</span>';
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Session Age</th>
                                <td>
                                    <?php 
                                    if (isset($_SESSION['login_time'])) {
                                        $age = time() - $_SESSION['login_time'];
                                        $days = floor($age / 86400);
                                        $hours = floor(($age % 86400) / 3600);
                                        $minutes = floor(($age % 3600) / 60);
                                        echo "{$days}d {$hours}h {$minutes}m";
                                        
                                        if ($age > 604800) {
                                            echo ' <span class="badge bg-danger">Expired!</span>';
                                        } else {
                                            echo ' <span class="badge bg-success">Valid</span>';
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>IP Address</th>
                                <td><?php echo $_SESSION['ip_address'] ?? 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Current IP</th>
                                <td><?php echo $_SERVER['REMOTE_ADDR'] ?? 'N/A'; ?></td>
                            </tr>
                        </table>
                        
                        <div class="alert alert-info">
                            <h6>🔍 Security Checks:</h6>
                            <ul class="mb-0">
                                <li>User Agent Match: <?php echo $match ? '✅ Pass' : '❌ Fail (Session Hijacking!)'; ?></li>
                                <li>Inactivity: <?php echo (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) <= 86400) ? '✅ Pass' : '❌ Fail (Expired!)'; ?></li>
                                <li>Session Age: <?php echo (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) <= 604800) ? '✅ Pass' : '❌ Fail (Too old!)'; ?></li>
                            </ul>
                        </div>
                        
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <h6>⚠️ You are not logged in</h6>
                            <p class="mb-0">Please <a href="login.php">login</a> to see session information.</p>
                        </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <h5>Browser Information</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>User Agent</th>
                                <td><small><?php echo $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'; ?></small></td>
                            </tr>
                            <tr>
                                <th>IP Address</th>
                                <td><?php echo $_SERVER['REMOTE_ADDR'] ?? 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Request URI</th>
                                <td><?php echo $_SERVER['REQUEST_URI'] ?? 'N/A'; ?></td>
                            </tr>
                        </table>
                        
                        <hr>
                        
                        <h5>Test Instructions</h5>
                        <div class="alert alert-secondary">
                            <h6>Test 1: Same Browser (Should Work)</h6>
                            <ol>
                                <li>Copy this URL: <code><?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/my-bookings.php'; ?></code></li>
                                <li>Open new tab in <strong>same browser</strong></li>
                                <li>Paste URL</li>
                                <li><strong>Expected:</strong> You see your bookings ✅</li>
                            </ol>
                            
                            <h6>Test 2: Different Browser (Should Block)</h6>
                            <ol>
                                <li>Copy this URL: <code><?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/my-bookings.php'; ?></code></li>
                                <li>Open <strong>different browser</strong> (Firefox, Edge, or Incognito)</li>
                                <li>Paste URL</li>
                                <li><strong>Expected:</strong> Redirected to login ✅</li>
                            </ol>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="index.php" class="btn btn-primary">Home</a>
                            <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="my-bookings.php" class="btn btn-success">My Bookings</a>
                            <a href="logout.php" class="btn btn-danger">Logout</a>
                            <?php else: ?>
                            <a href="login.php" class="btn btn-success">Login</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
