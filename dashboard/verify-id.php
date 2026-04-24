<?php
/**
 * Verify ID — Receptionist Panel
 * Shows all bookings where customer uploaded an ID.
 * Receptionist verifies the person picking up the room key matches the ID.
 */
ob_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_auth_role('receptionist', '../login.php');

// Ensure temp_id_uploads table exists
$conn->query("CREATE TABLE IF NOT EXISTS `temp_id_uploads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `image_data` MEDIUMTEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token` (`token`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Search/filter
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all | with_id | without_id

// Build query — show all room bookings, highlight those with/without ID
$where = "b.booking_type = 'room' AND b.status NOT IN ('cancelled','checked_out')";
$params = [];
$types  = '';

if (!empty($search)) {
    $like    = '%' . $search . '%';
    $where  .= " AND (b.booking_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params  = [$like, $like, $like, $like];
    $types   = 'ssss';
}

if ($filter === 'with_id') {
    $where .= " AND b.id_image IS NOT NULL AND b.id_image != ''";
} elseif ($filter === 'without_id') {
    $where .= " AND (b.id_image IS NULL OR b.id_image = '')";
}

$sql = "SELECT b.id, b.booking_reference, b.check_in_date, b.check_out_date,
               b.total_price, b.status, b.payment_status, b.id_image,
               b.created_at,
               COALESCE(r.name,'N/A') AS room_name,
               COALESCE(r.room_number,'N/A') AS room_number,
               CONCAT(u.first_name,' ',u.last_name) AS guest_name,
               u.email, u.phone
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        JOIN users u ON b.user_id = u.id
        WHERE $where
        ORDER BY
            CASE WHEN b.id_image IS NOT NULL AND b.id_image != '' THEN 0 ELSE 1 END,
            b.created_at DESC
        LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$total_q   = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE booking_type='room' AND status NOT IN ('cancelled','checked_out')");
$with_id_q = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE booking_type='room' AND status NOT IN ('cancelled','checked_out') AND id_image IS NOT NULL AND id_image != ''");
$total_count   = $total_q   ? (int)$total_q->fetch_assoc()['c']   : 0;
$with_id_count = $with_id_q ? (int)$with_id_q->fetch_assoc()['c'] : 0;
$without_count = $total_count - $with_id_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify ID - Receptionist Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .navbar-receptionist { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important; }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        .sidebar .nav-link { color: rgba(255,255,255,.8); padding: .75rem 1rem; margin: .25rem 0; border-radius: .5rem; transition: all .3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.15); }
        .main-content { background: #f4f6f9; min-height: 100vh; }
        .card { border: none; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); }
        .id-thumb { width: 80px; height: 52px; object-fit: cover; border-radius: 5px; border: 2px solid #007bff; cursor: pointer; transition: transform .2s; }
        .id-thumb:hover { transform: scale(1.08); }
        .stat-card { border-left: 4px solid; border-radius: 8px; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-receptionist">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="../index.php">
                <i class="fas fa-hotel text-warning me-1"></i> Harar Ras Hotel
            </a>
            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="text-white small"><i class="fas fa-user-tie me-1"></i> Receptionist</span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <h5 class="text-white mb-3"><i class="fas fa-concierge-bell me-2"></i>Reception Panel</h5>
                    <nav class="nav flex-column">
                        <a href="receptionist.php"         class="nav-link"><i class="fas fa-tachometer-alt me-2"></i>Dashboard Overview</a>
                        <a href="verify-id.php"            class="nav-link active"><i class="fas fa-id-card me-2"></i>Verify ID</a>
                        <a href="receptionist-checkout.php" class="nav-link"><i class="fas fa-minus-circle me-2"></i>Process Check-out</a>
                        <a href="receptionist-rooms.php"   class="nav-link"><i class="fas fa-bed me-2"></i>Manage Rooms</a>
                        <a href="../generate_bill.php"     class="nav-link"><i class="fas fa-file-invoice-dollar me-2"></i>Generate Bill</a>
                    </nav>
                    <div class="mt-auto">
                        <a href="../logout.php" class="nav-link text-white"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="main-content p-4">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="receptionist.php" class="btn btn-outline-secondary btn-sm me-2">
                                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                            </a>
                            <h3 class="d-inline fw-bold">
                                <i class="fas fa-id-card text-primary me-2"></i>Verify ID
                            </h3>
                        </div>
                        <div class="text-muted small">
                            <i class="fas fa-shield-alt text-success me-1"></i>
                            Verify customer identity before issuing room key
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="row mb-4 g-3">
                        <div class="col-md-4">
                            <div class="card stat-card border-primary p-3">
                                <div class="text-muted small">Total Active Bookings</div>
                                <div class="fs-2 fw-bold text-primary"><?php echo $total_count; ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card border-success p-3">
                                <div class="text-muted small">ID Uploaded ✓</div>
                                <div class="fs-2 fw-bold text-success"><?php echo $with_id_count; ?></div>
                                <small class="text-muted">Ready to verify</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card border-warning p-3">
                                <div class="text-muted small">No ID Uploaded</div>
                                <div class="fs-2 fw-bold text-warning"><?php echo $without_count; ?></div>
                                <small class="text-muted">Ask customer to upload</small>
                            </div>
                        </div>
                    </div>

                    <!-- Search & Filter -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold">Search (Booking Ref / Name / Email)</label>
                                    <input type="text" name="search" class="form-control"
                                           value="<?php echo htmlspecialchars($search); ?>"
                                           placeholder="e.g. HRH20240101 or John Doe">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Filter</label>
                                    <select name="filter" class="form-select">
                                        <option value="all"        <?php echo $filter==='all'        ? 'selected':''; ?>>All Bookings</option>
                                        <option value="with_id"    <?php echo $filter==='with_id'    ? 'selected':''; ?>>ID Uploaded</option>
                                        <option value="without_id" <?php echo $filter==='without_id' ? 'selected':''; ?>>No ID Uploaded</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-1"></i>Search
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <a href="verify-id.php" class="btn btn-outline-secondary w-100">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Bookings Table -->
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Customer Bookings — ID Verification
                            </h5>
                            <span class="badge bg-light text-dark"><?php echo count($bookings); ?> record(s)</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($bookings)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-id-card fa-3x text-muted mb-3 d-block"></i>
                                <p class="text-muted">No bookings found.</p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID Document</th>
                                            <th>Customer</th>
                                            <th>Booking Ref</th>
                                            <th>Room</th>
                                            <th>Check-in</th>
                                            <th>Check-out</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($bookings as $b):
                                        $has_id = !empty($b['id_image']);
                                        $img_url = '../view-id.php?booking_id=' . (int)$b['id'];

                                        // Status badge
                                        $st = strtolower($b['status']);
                                        $st_class = 'secondary';
                                        $st_label = ucfirst(str_replace('_',' ',$b['status']));
                                        if ($st === 'confirmed')           { $st_class = 'success'; }
                                        if ($st === 'pending')             { $st_class = 'warning'; }
                                        if ($st === 'checked_in')          { $st_class = 'primary'; $st_label = 'Checked In'; }
                                        if ($st === 'pending_cancellation'){ $st_class = 'warning'; $st_label = 'Pending Cancellation'; }
                                        if ($st === 'verified')            { $st_class = 'info'; }

                                        // Payment badge
                                        $pay = strtolower($b['payment_status'] ?? 'pending');
                                        $pay_class = ($pay === 'paid') ? 'success' : 'secondary';
                                    ?>
                                    <tr class="<?php echo $has_id ? '' : 'table-warning'; ?>">
                                        <!-- ID Thumbnail -->
                                        <td style="width:100px;">
                                            <?php if ($has_id): ?>
                                            <div style="position:relative; display:inline-block;">
                                                <img src="<?php echo htmlspecialchars($img_url); ?>"
                                                     alt="ID of <?php echo htmlspecialchars($b['guest_name']); ?>"
                                                     class="id-thumb"
                                                     onclick="openIdModal('<?php echo htmlspecialchars($img_url); ?>','<?php echo htmlspecialchars($b['guest_name']); ?>','<?php echo htmlspecialchars($b['booking_reference']); ?>')"
                                                     title="Click to view full ID"
                                                     onerror="this.parentElement.innerHTML='<span class=\'badge bg-danger\'>Load error</span>';">
                                                <span style="position:absolute;bottom:2px;right:2px;background:rgba(0,86,179,.85);color:#fff;font-size:9px;padding:1px 4px;border-radius:3px;">
                                                    <i class="fas fa-search-plus"></i>
                                                </span>
                                            </div>
                                            <?php else: ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Not uploaded
                                            </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Customer -->
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($b['guest_name']); ?></div>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($b['email']); ?></small>
                                            <small class="text-muted"><?php echo htmlspecialchars($b['phone'] ?? '—'); ?></small>
                                        </td>

                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($b['booking_reference']); ?></span>
                                        </td>

                                        <td>
                                            <div><?php echo htmlspecialchars($b['room_name']); ?></div>
                                            <small class="text-muted">Room <?php echo htmlspecialchars($b['room_number']); ?></small>
                                        </td>

                                        <td><?php echo date('M j, Y', strtotime($b['check_in_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($b['check_out_date'])); ?></td>

                                        <td><strong>ETB <?php echo number_format($b['total_price'], 2); ?></strong></td>

                                        <td>
                                            <span class="badge bg-<?php echo $st_class; ?> d-block mb-1"><?php echo $st_label; ?></span>
                                            <span class="badge bg-<?php echo $pay_class; ?>"><?php echo ucfirst($pay); ?></span>
                                        </td>

                                        <!-- Action -->
                                        <td>
                                            <?php if ($has_id): ?>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <button class="btn btn-sm btn-primary"
                                                        onclick="openIdModal('<?php echo htmlspecialchars($img_url); ?>','<?php echo htmlspecialchars($b['guest_name']); ?>','<?php echo htmlspecialchars($b['booking_reference']); ?>',<?php echo (int)$b['id']; ?>)">
                                                    <i class="fas fa-id-card me-1"></i>View ID
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteId(<?php echo (int)$b['id']; ?>,'<?php echo htmlspecialchars($b['booking_reference']); ?>')"
                                                        title="Delete ID image">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted small">No ID on file</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- /main-content -->
            </div>
        </div>
    </div>

    <!-- Full-Screen ID Viewer Modal -->
    <div id="idViewModal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.92); z-index:9999;
                align-items:center; justify-content:center; flex-direction:column;">

        <!-- Header -->
        <div style="width:100%; max-width:860px; display:flex; justify-content:space-between;
                    align-items:center; padding:12px 16px; color:#fff;">
            <div style="font-size:1rem;">
                <i class="fas fa-id-card me-2" style="color:#f7931e;"></i>
                <span id="idViewGuestName" style="font-weight:600;"></span>
                <span id="idViewRef" style="color:#aaa; margin-left:10px; font-size:.9rem;"></span>
            </div>
            <button onclick="closeIdModal()"
                    style="background:rgba(255,255,255,.15); border:none; color:#fff;
                           width:36px; height:36px; border-radius:50%; font-size:18px;
                           cursor:pointer; display:flex; align-items:center; justify-content:center;">
                &times;
            </button>
        </div>

        <!-- Image -->
        <div style="flex:1; display:flex; align-items:center; justify-content:center;
                    padding:0 16px; max-width:860px; width:100%;">
            <div style="position:relative; background:#111; border-radius:10px; overflow:hidden;
                        box-shadow:0 8px 40px rgba(0,0,0,.7); max-width:100%; max-height:75vh;
                        display:flex; align-items:center; justify-content:center; min-width:300px; min-height:200px;">
                <img id="idViewImg" src="" alt="Customer ID"
                     style="max-width:100%; max-height:72vh; display:none; object-fit:contain; border-radius:10px;">
                <div id="idViewSpinner"
                     style="position:absolute; inset:0; display:flex; align-items:center;
                            justify-content:center; background:#111; border-radius:10px;">
                    <div style="text-align:center; color:#aaa;">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2 d-block"></i>
                        <small>Loading ID image...</small>
                    </div>
                </div>
                <div id="idViewError"
                     style="display:none; position:absolute; inset:0; align-items:center;
                            justify-content:center; background:#1a1a2e; border-radius:10px;
                            flex-direction:column; color:#fff; text-align:center; padding:30px;">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3 d-block" style="color:#f7931e;"></i>
                    <h5>Image Not Available</h5>
                    <p class="text-muted small mb-0">The ID image could not be loaded.<br>
                    The customer may need to re-upload their ID.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="padding:14px 16px; color:#aaa; font-size:.8rem; text-align:center;">
            <i class="fas fa-shield-alt me-1" style="color:#f7931e;"></i>
            Confidential — For identity verification only. Do not share or distribute.
            &nbsp;&nbsp;
            <button onclick="closeIdModal()" class="btn btn-outline-light btn-sm ms-3">
                <i class="fas fa-times me-1"></i> Close
            </button>
            <button id="modalDeleteBtn" onclick="deleteIdFromModal()" class="btn btn-outline-danger btn-sm ms-2">
                <i class="fas fa-trash me-1"></i> Delete ID
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let currentBookingId = 0;

    function openIdModal(src, guestName, bookingRef, bookingId) {
        const modal   = document.getElementById('idViewModal');
        const img     = document.getElementById('idViewImg');
        const spinner = document.getElementById('idViewSpinner');
        const errDiv  = document.getElementById('idViewError');

        currentBookingId = bookingId || 0;

        document.getElementById('idViewGuestName').textContent = guestName || 'Customer ID';
        document.getElementById('idViewRef').textContent = bookingRef ? ('Ref: ' + bookingRef) : '';

        img.style.display    = 'none';
        spinner.style.display = 'flex';
        errDiv.style.display  = 'none';
        img.src = '';

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        const tmp = new Image();
        tmp.onload = function() {
            img.src = src;
            spinner.style.display = 'none';
            img.style.display = 'block';
        };
        tmp.onerror = function() {
            spinner.style.display = 'none';
            errDiv.style.display  = 'flex';
            
            // Try to determine the error cause by checking the response
            fetch(src, { method: 'HEAD' })
                .then(response => {
                    const errorTitle = document.querySelector('#idViewError h5');
                    const errorText = document.querySelector('#idViewError p');
                    
                    if (response.status === 403) {
                        errorTitle.textContent = 'Access Denied';
                        errorText.innerHTML = 'You do not have permission to view this ID image.<br>Please contact your administrator for access.';
                    } else if (response.status === 404) {
                        errorTitle.textContent = 'Image File Missing';
                        errorText.innerHTML = 'The ID image file could not be found on the server.<br>The customer may need to re-upload their ID.';
                    } else if (response.status === 400) {
                        errorTitle.textContent = 'Invalid Image Format';
                        errorText.innerHTML = 'The ID image is in an invalid format or corrupted.<br>Please ask the customer to upload a valid ID image.';
                    } else {
                        errorTitle.textContent = 'Image Loading Failed';
                        errorText.innerHTML = 'Failed to load the ID image (Error ' + response.status + ').<br>Please try again or contact support if the issue persists.';
                    }
                })
                .catch(() => {
                    const errorTitle = document.querySelector('#idViewError h5');
                    const errorText = document.querySelector('#idViewError p');
                    errorTitle.textContent = 'Network Error';
                    errorText.innerHTML = 'Unable to connect to the image server.<br>Please check your internet connection and try again.';
                });
        };
        tmp.src = src;
    }

    function closeIdModal() {
        document.getElementById('idViewModal').style.display = 'none';
        document.getElementById('idViewImg').src = '';
        document.body.style.overflow = '';
        currentBookingId = 0;
    }

    function deleteIdFromModal() {
        if (currentBookingId > 0) {
            deleteId(currentBookingId, '');
        }
    }

    function deleteId(bookingId, bookingRef) {
        const label = bookingRef ? ' for booking ' + bookingRef : '';
        if (!confirm('Delete the ID image' + label + '?\n\nThe customer will need to re-upload their ID before booking again.')) return;

        fetch('../api/delete_id_image.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ booking_id: bookingId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeIdModal();
                // Reload page to refresh the table
                location.reload();
            } else {
                alert('Failed to delete: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error. Please try again.'));
    }

    document.getElementById('idViewModal').addEventListener('click', function(e) {
        if (e.target === this) closeIdModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeIdModal();
    });
    </script>
</body>
</html>
