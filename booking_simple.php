<?php
/**
 * Simplified booking.php without RoomLockManager to isolate the 500 error
 */

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'includes/config.php';
    require_once 'includes/functions.php';
    // Skip RoomLockManager for now
    
    // Add cache-busting headers to prevent browser caching
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    $selected_room_id = isset($_GET['room']) ? (int)$_GET['room'] : null;
    $selected_room = null;
    
    if ($selected_room_id) {
        $selected_room = get_room_by_id($selected_room_id);
    }
    
    $error = '';
    $success = '';
    
    // Get all rooms for the form
    $rooms = get_all_rooms();
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
} catch (Error $e) {
    die("Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Now - Harar Ras Hotel (Simple)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3><i class="fas fa-calendar-check"></i> <?php echo __('booking.title'); ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if ($selected_room): ?>
                            <div class="alert alert-info">
                                <strong>Selected Room:</strong> <?php echo htmlspecialchars($selected_room['name']); ?>
                                <br><strong>Price:</strong> ETB <?php echo number_format($selected_room['price'], 2); ?>/night
                                <br><strong>Capacity:</strong> <?php echo $selected_room['capacity']; ?> customers
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!is_logged_in()): ?>
                            <div class="alert alert-warning">
                                <h5><i class="fas fa-exclamation-triangle"></i> Login Required</h5>
                                <p>You need to login to make a booking.</p>
                                <a href="login.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : ''; ?>" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Login Now
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="booking.php">
                                <div class="mb-3">
                                    <label class="form-label">Select Room *</label>
                                    <select name="room_id" class="form-select" required>
                                        <option value="">Choose a room...</option>
                                        <?php foreach ($rooms as $room): ?>
                                            <option value="<?php echo $room['id']; ?>" 
                                                    <?php echo ($selected_room_id == $room['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($room['name']); ?> - ETB <?php echo number_format($room['price'], 2); ?>/night
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Check-in Date *</label>
                                            <input type="date" name="check_in" class="form-control" required 
                                                   min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Check-out Date *</label>
                                            <input type="date" name="check_out" class="form-control" required 
                                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Number of Customers *</label>
                                    <select name="customers" class="form-select" required>
                                        <option value="">Select...</option>
                                        <option value="1">1 Customer</option>
                                        <option value="2">2 Customers</option>
                                        <option value="3">3 Customers</option>
                                        <option value="4">4 Customers</option>
                                    </select>
                                </div>
                                
                                <div class="alert alert-info">
                                    <p><strong>Note:</strong> This is a simplified booking form for testing. 
                                    ID upload and full validation will be added back once the basic form works.</p>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check"></i> Test Booking Submission
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Home
                            </a>
                            <a href="booking.php<?php echo $selected_room_id ? '?room=' . $selected_room_id : ''; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-sync"></i> Try Full Booking Page
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>