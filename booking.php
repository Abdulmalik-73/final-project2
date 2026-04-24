<?php 
// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Add cache-busting headers to prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$selected_room_id = isset($_GET['room']) ? (int)$_GET['room'] : (isset($_GET['room_id']) ? (int)$_GET['room_id'] : null);
$selected_room = null;

if ($selected_room_id) {
    $selected_room = get_room_by_id($selected_room_id);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!is_logged_in()) {
        $_SESSION['booking_data'] = $_POST;
        header('Location: login.php');
        exit();
    }
    
    $room_id = (int)$_POST['room_id'];
    $check_in = sanitize_input($_POST['check_in']);
    $check_out = sanitize_input($_POST['check_out']);
    $customers = (int)$_POST['customers'];
    $id_token = trim($_POST['id_image_path'] ?? ''); // token (32-char hex) from temp_id_uploads

    // Validate ID upload
    if (empty($id_token)) {
        $error = 'Please upload your ID before confirming booking.';
    } else {
        // Resolve: token → filename, or accept legacy base64/filepath directly
        $is_token    = preg_match('/^[a-f0-9]{32}$/', $id_token);
        $is_base64   = (strpos($id_token, 'data:image/') === 0);
        $is_filepath = preg_match('/^uploads\/ids\/id_\d+_\d+_[a-zA-Z0-9._]+\.(jpg|jpeg|png)$/i', $id_token);

        if (!$is_token && !$is_base64 && !$is_filepath) {
            $error = 'Invalid ID image. Please re-upload your ID.';
        } else {
            $id_image = $id_token; // default for base64/filepath
            if ($is_token) {
                $uid_tmp  = (int)$_SESSION['user_id'];
                $tok_stmt = $conn->prepare("SELECT image_data FROM temp_id_uploads WHERE token = ? AND user_id = ?");
                if ($tok_stmt) {
                    $tok_stmt->bind_param("si", $id_token, $uid_tmp);
                    $tok_stmt->execute();
                    $tok_row = $tok_stmt->get_result()->fetch_assoc();
                    $tok_stmt->close();
                    if ($tok_row && !empty($tok_row['image_data'])) {
                        $id_image = $tok_row['image_data']; // actual filename
                    } else {
                        $error = 'ID upload session expired. Please re-upload your ID.';
                    }
                } else {
                    $error = 'Database error. Please try again.';
                }
            }

            if (!$error) {
                $room = get_room_by_id($room_id);
                
                if (!$room) {
                    $error = 'Invalid room selected';
                } else {
                    // Create booking with ID image
                    $nights = calculate_nights($check_in, $check_out);
                    $total_price = $room['price'] * $nights;
                    
                    $booking_data = [
                        'user_id' => $_SESSION['user_id'],
                        'room_id' => $room_id,
                        'check_in' => $check_in,
                        'check_out' => $check_out,
                        'customers' => $customers,
                        'total_price' => $total_price,
                        'special_requests' => '',
                    ];
                    
                    $result = create_booking($booking_data);
                    
                    if ($result['success']) {
                        // Save ID image to booking
                        if (!empty($id_image)) {
                            $id_upd = $conn->prepare("UPDATE bookings SET id_image = ? WHERE id = ?");
                            if ($id_upd) {
                                $id_upd->bind_param("si", $id_image, $result['booking_id']);
                                $id_upd->execute();
                                $id_upd->close();
                            }
                            
                            // Clean up temporary upload if it was a token
                            if ($is_token) {
                                $cleanup_stmt = $conn->prepare("DELETE FROM temp_id_uploads WHERE token = ? AND user_id = ?");
                                if ($cleanup_stmt) {
                                    $cleanup_stmt->bind_param("si", $id_token, $_SESSION['user_id']);
                                    $cleanup_stmt->execute();
                                    $cleanup_stmt->close();
                                }
                            }
                        }
                        
                        // Generate payment reference and set deadline
                        $payment_ref = 'HRH-' . str_pad($result['booking_id'], 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5($result['booking_id'] . time()), 0, 6));
                        $deadline = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                        
                        // Update booking with payment verification fields
                        $update_query = "UPDATE bookings SET 
                                        payment_reference = ?, 
                                        payment_deadline = ?, 
                                        verification_status = 'pending_payment' 
                                        WHERE id = ?";
                        
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bind_param("ssi", $payment_ref, $deadline, $result['booking_id']);
                        $update_stmt->execute();
                        
                        // Store booking reference in session for payment
                        $_SESSION['pending_booking'] = $result['booking_reference'];
                        $_SESSION['current_booking_id'] = $result['booking_id'];
                        
                        // Redirect to payment upload page
                        header('Location: payment-upload.php?booking=' . $result['booking_id']);
                        exit();
                    } else {
                        $error = 'Booking failed. Please try again. Error: ' . $result['message'];
                    }
                }
            }
        }
    }
}

$rooms = get_all_rooms();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Now - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Top Guidance Banner for Non-Authenticated Users -->
    <?php if (!is_logged_in()): ?>
    <div class="alert alert-warning alert-dismissible fade show m-0 border-0 rounded-0" role="alert">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="alert-heading mb-2">
                        <i class="fas fa-exclamation-triangle"></i> Account Required to Book
                    </h5>
                    <p class="mb-0">
                        <strong>To proceed with booking, you must first create an account or sign in.</strong>
                        This ensures secure booking and allows you to manage your reservations.
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <a href="register.php?redirect=booking" class="btn btn-success btn-sm me-2">
                        <i class="fas fa-user-plus"></i> Create Account
                    </a>
                    <a href="login.php?redirect=booking" class="btn btn-primary btn-sm">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Clear Page Identifier for Logged-in Users -->
    <div class="alert alert-info border-info m-0 border-0 rounded-0">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="alert-heading mb-2">
                        <i class="fas fa-bed"></i> Room Booking
                    </h5>
                    <p class="mb-0">
                        <strong>This is the ROOM BOOKING page.</strong> Select your room and dates below.
                        <br><small>Looking to order food? <a href="food-booking.php" class="alert-link">Click here for Food Ordering</a></small>
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <i class="fas fa-bed fa-3x text-info"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <section class="py-5">
        <div class="container">
            <div class="row mb-4">
                <div class="col">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
            
            <?php if (!is_logged_in()): ?>
            <!-- Step-by-Step Guidance Section -->
            <div class="row justify-content-center mb-5">
                <div class="col-lg-10">
                    <div class="card border-danger shadow-lg">
                        <div class="card-header bg-danger text-white text-center">
                            <h3 class="mb-0">
                                <i class="fas fa-shield-alt"></i> Authentication Required
                            </h3>
                            <p class="mb-0 mt-2">Follow these simple steps to complete your booking</p>
                        </div>
                        <div class="card-body p-4">
                            <!-- Authentication Options -->
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="card bg-success text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-user-plus fa-3x mb-3"></i>
                                            <h5>New Customer?</h5>
                                            <p class="mb-3">Create a free account in just 2 minutes</p>
                                            <a href="register.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                               class="btn btn-light btn-lg">
                                                <i class="fas fa-user-plus"></i> Create Account Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card bg-primary text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-sign-in-alt fa-3x mb-3"></i>
                                            <h5>Existing Customer?</h5>
                                            <p class="mb-3">Sign in to your account to continue</p>
                                            <a href="login.php<?php echo $selected_room_id ? '?redirect=booking&room=' . $selected_room_id : '?redirect=booking'; ?>" 
                                               class="btn btn-light btn-lg">
                                                <i class="fas fa-sign-in-alt"></i> Sign In Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm <?php echo !is_logged_in() ? 'opacity-25' : ''; ?>">
                        <div class="card-header text-white" style="background: <?php echo !is_logged_in() ? '#6c757d' : 'linear-gradient(135deg, #1e88e5 0%, #1565c0 100%)'; ?>; padding: 1.5rem;">
                            <h3 class="mb-0 fw-bold" style="font-size: 1.75rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">
                                <i class="fas fa-calendar-check me-2"></i> Book Your Stay
                                <?php if (!is_logged_in()): ?>
                                <span class="badge bg-danger ms-2" style="font-size: 0.9rem;">
                                    <i class="fas fa-lock"></i> LOCKED - Authentication Required
                                </span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!is_logged_in()): ?>
                            <div class="alert alert-danger">
                                <h5 class="alert-heading">
                                    <i class="fas fa-lock"></i> Login Required
                                </h5>
                                <p class="mb-0">You must be logged in to make a booking. Please create an account or sign in above.</p>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" id="bookingForm" <?php echo !is_logged_in() ? 'style="pointer-events: none;"' : ''; ?>>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Select Room *</label>
                                    <select name="room_id" class="form-select form-select-lg" required id="roomSelect">
                                        <option value="">Choose your room...</option>
                                        <?php 
                                        $rooms_by_type = [];
                                        foreach ($rooms as $room) {
                                            $type = $room['room_type'] ?? 'Standard';
                                            if (!isset($rooms_by_type[$type])) {
                                                $rooms_by_type[$type] = [];
                                            }
                                            $rooms_by_type[$type][] = $room;
                                        }
                                        
                                        foreach ($rooms_by_type as $room_type_name => $rooms_in_type):
                                            $first_room = $rooms_in_type[0];
                                            $price_formatted = number_format($first_room['price'], 2);
                                        ?>
                                        <optgroup label="<?php echo htmlspecialchars($room_type_name); ?> - ETB <?php echo $price_formatted; ?>/night">
                                            <?php foreach ($rooms_in_type as $room): ?>
                                            <option value="<?php echo $room['id']; ?>" 
                                                    data-price="<?php echo $room['price']; ?>" 
                                                    data-capacity="<?php echo $room['capacity']; ?>"
                                                    <?php echo ($selected_room_id == $room['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($room['name']); ?> Number <?php echo $room['room_number']; ?> - ETB <?php echo number_format($room['price'], 2); ?>/night
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Check-in Date *</label>
                                        <input type="date" name="check_in" class="form-control form-control-lg" required 
                                               min="<?php echo date('Y-m-d'); ?>" id="checkInDate">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Check-out Date *</label>
                                        <input type="date" name="check_out" class="form-control form-control-lg" required 
                                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" id="checkOutDate">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold">Number of Customers</label>
                                    <div class="form-control form-control-lg bg-light" id="customersDisplay">
                                        Select a room to see capacity
                                    </div>
                                    <input type="hidden" name="customers" id="customersInput" value="">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> Number of customers is automatically set based on room capacity
                                    </small>
                                </div>

                                <!-- ID Upload Section -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-id-card"></i> Upload National ID / Passport / Driving License *
                                    </label>
                                    <div class="border rounded p-3 bg-light">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <button type="button" class="btn btn-outline-primary btn-sm me-2" id="uploadIdBtn">
                                                    <i class="fas fa-upload"></i> Upload ID
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="scanIdBtn">
                                                    <i class="fas fa-camera"></i> Scan ID (Use Camera)
                                                </button>
                                                <input type="file" id="idFileInput" accept="image/*" style="display: none;">
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <small class="text-muted">JPG, JPEG, PNG only • Max 2MB</small>
                                            </div>
                                        </div>
                                        
                                        <!-- Upload Progress -->
                                        <div id="idUploadProgress" class="mt-3 d-none">
                                            <div class="progress">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                     role="progressbar" style="width: 0%"></div>
                                            </div>
                                            <small class="text-muted">Uploading ID image...</small>
                                        </div>
                                        
                                        <!-- Upload Error -->
                                        <div id="idUploadError" class="alert alert-danger mt-3 d-none">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <span id="idErrorMessage">Upload failed. Please try again.</span>
                                        </div>
                                        
                                        <!-- Preview Area -->
                                        <div id="idPreviewArea" class="mt-3 d-none">
                                            <div class="alert alert-success">
                                                <div class="row align-items-center">
                                                    <div class="col-auto">
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    </div>
                                                    <div class="col">
                                                        <strong>ID uploaded successfully</strong><br>
                                                        <span id="idFileName">National ID.jpg (110.7 KB)</span>
                                                    </div>
                                                    <div class="col-auto">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" id="removeIdBtn">
                                                            <i class="fas fa-times"></i> Remove / Cancel Upload
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <img id="idPreviewImg" src="" alt="ID Preview" class="img-thumbnail" 
                                                         style="max-width: 200px; max-height: 150px; cursor: pointer;" 
                                                         data-bs-toggle="modal" data-bs-target="#idEnlargeModal">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Hidden field to carry path to PHP -->
                                    <input type="hidden" name="id_image_path" id="idImagePath" value="">
                                    <input type="hidden" id="currentUserId" value="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>">
                                </div>

                                <?php if (is_logged_in()): ?>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg" id="confirmBookingBtn" disabled>
                                        <i class="fas fa-lock"></i> Upload ID to Enable Booking
                                    </button>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-calculator"></i> Booking Summary</h5>
                        </div>
                        <div class="card-body">
                            <div id="bookingSummary">
                                <p class="text-muted text-center py-4">
                                    <i class="fas fa-info-circle"></i><br>
                                    Select room and dates to see pricing
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ID Enlarge Modal -->
    <div class="modal fade" id="idEnlargeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-id-card"></i> Uploaded ID Document
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="idEnlargeImg" src="" alt="ID Document" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Update booking summary and customer count when room changes
        function updateBookingSummary() {
            const roomSelect = document.getElementById('roomSelect');
            const checkIn = document.getElementById('checkInDate');
            const checkOut = document.getElementById('checkOutDate');
            const customersDisplay = document.getElementById('customersDisplay');
            const customersInput = document.getElementById('customersInput');
            const summaryDiv = document.getElementById('bookingSummary');
            
            if (roomSelect.value) {
                const selectedOption = roomSelect.options[roomSelect.selectedIndex];
                const price = parseFloat(selectedOption.dataset.price);
                const capacity = parseInt(selectedOption.dataset.capacity);
                
                // Auto-set customer count based on room capacity
                customersDisplay.textContent = `${capacity} Customer${capacity > 1 ? 's' : ''} (Room Capacity)`;
                customersInput.value = capacity;
                
                if (checkIn.value && checkOut.value) {
                    const checkInDate = new Date(checkIn.value);
                    const checkOutDate = new Date(checkOut.value);
                    const nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                    
                    if (nights > 0) {
                        const totalPrice = price * nights;
                        
                        summaryDiv.innerHTML = `
                            <div class="mb-2">
                                <strong>Room:</strong> ${selectedOption.text.split(' - ETB')[0]}
                            </div>
                            <div class="mb-2">
                                <strong>Dates:</strong> ${checkIn.value} to ${checkOut.value}
                            </div>
                            <div class="mb-2">
                                <strong>Nights:</strong> ${nights}
                            </div>
                            <div class="mb-2">
                                <strong>Customers:</strong> ${capacity}
                            </div>
                            <hr>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>ETB ${price.toFixed(2)} × ${nights} nights</span>
                                    <span>ETB ${totalPrice.toFixed(2)}</span>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Total:</strong>
                                <strong class="text-primary fs-4">ETB ${totalPrice.toFixed(2)}</strong>
                            </div>
                        `;
                    }
                }
            } else {
                customersDisplay.textContent = 'Select a room to see capacity';
                customersInput.value = '';
            }
        }
        
        // Add event listeners
        document.getElementById('roomSelect').addEventListener('change', updateBookingSummary);
        document.getElementById('checkInDate').addEventListener('change', updateBookingSummary);
        document.getElementById('checkOutDate').addEventListener('change', updateBookingSummary);
        
        // Update check-out minimum date when check-in changes
        document.getElementById('checkInDate').addEventListener('change', function() {
            const checkInDate = new Date(this.value);
            checkInDate.setDate(checkInDate.getDate() + 1);
            document.getElementById('checkOutDate').min = checkInDate.toISOString().split('T')[0];
        });

        // ── ID UPLOAD FUNCTIONALITY ────────────────────────────────────────────
        (function() {
            const uploadBtn   = document.getElementById('uploadIdBtn');
            const scanBtn     = document.getElementById('scanIdBtn');
            const fileInput   = document.getElementById('idFileInput');
            const progressDiv = document.getElementById('idUploadProgress');
            const errorDiv    = document.getElementById('idUploadError');
            const previewArea = document.getElementById('idPreviewArea');
            const previewImg  = document.getElementById('idPreviewImg');
            const enlargeImg  = document.getElementById('idEnlargeImg');
            const fileNameEl  = document.getElementById('idFileName');
            const pathInput   = document.getElementById('idImagePath');
            const confirmBtn  = document.getElementById('confirmBookingBtn');
            const removeBtn   = document.getElementById('removeIdBtn');
            const errorMsg    = document.getElementById('idErrorMessage');

            function showError(message) {
                errorMsg.textContent = message;
                errorDiv.classList.remove('d-none');
                setTimeout(() => errorDiv.classList.add('d-none'), 5000);
            }

            function setConfirmEnabled(enabled) {
                if (confirmBtn) {
                    confirmBtn.disabled = !enabled;
                    if (enabled) {
                        confirmBtn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm Booking';
                        confirmBtn.classList.remove('btn-secondary');
                        confirmBtn.classList.add('btn-primary');
                    } else {
                        confirmBtn.innerHTML = '<i class="fas fa-lock"></i> Upload ID to Enable Booking';
                        confirmBtn.classList.remove('btn-primary');
                        confirmBtn.classList.add('btn-secondary');
                    }
                }
            }

            function handleFile(file) {
                if (!file) return;

                // Validate file
                if (!file.type.match(/^image\/(jpeg|jpg|png)$/i)) {
                    showError('Please select a valid image file (JPG, JPEG, or PNG).');
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    showError('File size must be less than 2MB.');
                    return;
                }

                // Show progress
                progressDiv.classList.remove('d-none');
                errorDiv.classList.add('d-none');
                previewArea.classList.add('d-none');

                // Upload file
                const formData = new FormData();
                formData.append('id_image', file);
                formData.append('uid', document.getElementById('currentUserId')?.value || '0');

                fetch('api/upload_id.php', { method: 'POST', body: formData, credentials: 'include' })
                .then(r => r.json())
                .then(data => {
                    progressDiv.classList.add('d-none');
                    if (data.success) {
                        // Use server-returned preview for thumbnail (avoids re-reading large file)
                        previewImg.src = data.preview || '';
                        enlargeImg.src = data.preview || '';
                        fileNameEl.textContent = (data.file_name || 'ID') + ' (' + data.file_size + ')';
                        pathInput.value = data.file_path;  // 32-char token for temporary storage
                        previewArea.classList.remove('d-none');
                        setConfirmEnabled(true);
                    } else {
                        showError(data.error || 'ID upload failed. Please try again.');
                        setConfirmEnabled(false);
                    }
                })
                .catch(() => {
                    progressDiv.classList.add('d-none');
                    showError('ID upload failed. Please try again.');
                    setConfirmEnabled(false);
                });
            }

            // Camera capture function using getUserMedia API
            function startCameraCapture() {
                // Check if browser supports camera access
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    showError('Camera access is not supported in this browser. Please use the Upload button instead.');
                    return;
                }

                // Create camera modal
                const cameraModal = document.createElement('div');
                cameraModal.className = 'modal fade';
                cameraModal.innerHTML = `
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-camera"></i> Scan ID Document
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <video id="cameraVideo" width="100%" height="300" autoplay style="border: 2px solid #ddd; border-radius: 8px;"></video>
                                <canvas id="cameraCanvas" style="display: none;"></canvas>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-primary btn-lg" id="captureBtn">
                                        <i class="fas fa-camera"></i> Capture Photo
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-lg ms-2" data-bs-dismiss="modal">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(cameraModal);
                const modal = new bootstrap.Modal(cameraModal);
                
                const video = cameraModal.querySelector('#cameraVideo');
                const canvas = cameraModal.querySelector('#cameraCanvas');
                const captureBtn = cameraModal.querySelector('#captureBtn');
                let stream = null;

                // Start camera
                navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'environment', // Use back camera if available
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    } 
                })
                .then(function(mediaStream) {
                    stream = mediaStream;
                    video.srcObject = stream;
                    modal.show();
                })
                .catch(function(err) {
                    console.error('Camera access error:', err);
                    showError('Unable to access camera. Please check permissions and try again, or use the Upload button instead.');
                    document.body.removeChild(cameraModal);
                });

                // Capture photo
                captureBtn.addEventListener('click', function() {
                    const context = canvas.getContext('2d');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    context.drawImage(video, 0, 0);
                    
                    // Convert to blob
                    canvas.toBlob(function(blob) {
                        if (blob) {
                            // Create a file from the blob
                            const file = new File([blob], 'scanned_id.jpg', { type: 'image/jpeg' });
                            handleFile(file);
                        }
                        modal.hide();
                    }, 'image/jpeg', 0.8);
                });

                // Clean up when modal is closed
                cameraModal.addEventListener('hidden.bs.modal', function() {
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                    }
                    document.body.removeChild(cameraModal);
                });
            }

            // ── File input (Upload button) ────────────────────────────────────────
            uploadBtn.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) handleFile(this.files[0]);
            });

            // ── Camera capture (Scan button) ──────────────────────────────────────
            scanBtn.addEventListener('click', startCameraCapture);

            // ── Remove button ─────────────────────────────────────────────────────
            removeBtn.addEventListener('click', function() {
                fileInput.value = '';
                pathInput.value = '';
                previewArea.classList.add('d-none');
                previewImg.src = '';
                enlargeImg.src = '';
                setConfirmEnabled(false);

                // Call delete API if there was an uploaded file
                const token = pathInput.value;
                if (token) {
                    fetch('api/delete_id_image.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ token: token }),
                        credentials: 'include'
                    }).catch(() => {}); // Ignore errors for cleanup
                }
            });

            // ── Form submission validation ────────────────────────────────────────
            document.getElementById('bookingForm').addEventListener('submit', function(e) {
                const idPath = pathInput.value;
                if (!idPath) {
                    e.preventDefault();
                    showError('Please upload your ID before confirming booking.');
                    return false;
                }
            });

            // ── Drag & Drop Support ───────────────────────────────────────────────
            const dropZone = document.querySelector('.border.rounded.p-3.bg-light');
            
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('border-primary', 'bg-primary-subtle');
            });
            
            dropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('border-primary', 'bg-primary-subtle');
            });
            
            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('border-primary', 'bg-primary-subtle');
                const file = e.dataTransfer.files[0];
                if (file) handleFile(file);
            });
        })();
    </script>
</body>
</html>