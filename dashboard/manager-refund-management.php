<?php
/**
 * Manager Refund Management
 * Shows pending cancellation requests; manager can Approve or Reject each one.
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Auth: manager or admin only
if (!is_logged_in() || !in_array($_SESSION['user_role'] ?? $_SESSION['role'] ?? '', ['manager', 'admin', 'super_admin'])) {
    header('Location: ../login.php');
    exit();
}

$success = '';
$error   = '';

// ── Ensure cancellation_requests table exists ─────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `cancellation_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `refund_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `refund_percentage` INT NOT NULL DEFAULT 0,
    `processing_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `final_refund` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `days_before_checkin` INT NOT NULL DEFAULT 0,
    `status` ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    `manager_notes` TEXT DEFAULT NULL,
    `processed_by` INT DEFAULT NULL,
    `processed_at` DATETIME DEFAULT NULL,
    `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_booking_id` (`booking_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Ensure bookings status enum supports 'Pending Cancellation' and 'Cancelled'
$conn->query("ALTER TABLE bookings MODIFY COLUMN status ENUM(
    'pending','confirmed','verified','checked_in','checked_out',
    'cancelled','Cancelled','Pending Cancellation','no_show'
) DEFAULT 'pending'");

// ── Handle Approve / Reject ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
    $request_id   = (int)($_POST['request_id'] ?? 0);
    $action       = $_POST['action'] ?? '';
    $manager_notes = sanitize_input($_POST['manager_notes'] ?? '');
    $manager_id   = (int)$_SESSION['user_id'];

    if (!in_array($action, ['approve', 'reject'])) {
        $error = 'Invalid action.';
    } elseif ($request_id <= 0) {
        $error = 'Invalid request ID.';
    } else {
        $conn->begin_transaction();
        try {
            // Fetch the cancellation request
            $req_stmt = $conn->prepare("
                SELECT cr.*, b.booking_reference, b.total_price
                FROM cancellation_requests cr
                JOIN bookings b ON cr.booking_id = b.id
                WHERE cr.id = ? AND cr.status = 'Pending'
            ");
            $req_stmt->bind_param("i", $request_id);
            $req_stmt->execute();
            $req = $req_stmt->get_result()->fetch_assoc();

            if (!$req) {
                throw new Exception('Cancellation request not found or already processed.');
            }

            if ($action === 'approve') {
                // Update cancellation_requests
                $upd_req = $conn->prepare("
                    UPDATE cancellation_requests
                    SET status       = 'Approved',
                        manager_notes = ?,
                        processed_by  = ?,
                        processed_at  = NOW()
                    WHERE id = ?
                ");
                $upd_req->bind_param("sii", $manager_notes, $manager_id, $request_id);
                if (!$upd_req->execute()) throw new Exception('Failed to update request: ' . $upd_req->error);

                // Update booking status to Cancelled
                $upd_bk = $conn->prepare("
                    UPDATE bookings
                    SET status        = 'cancelled',
                        cancelled_at  = NOW(),
                        cancelled_by  = ?,
                        payment_status = CASE WHEN ? > 0 THEN 'refund_pending' ELSE payment_status END
                    WHERE id = ?
                ");
                $upd_bk->bind_param("idi", $manager_id, $req['final_refund'], $req['booking_id']);
                if (!$upd_bk->execute()) throw new Exception('Failed to update booking: ' . $upd_bk->error);

                // Insert into refunds table for tracking
                $refund_ref = 'REF' . date('Ymd') . str_pad($req['booking_id'], 6, '0', STR_PAD_LEFT);
                $check_ref = $conn->prepare("SELECT id FROM refunds WHERE refund_reference = ?");
                $check_ref->bind_param("s", $refund_ref);
                $check_ref->execute();
                if ($check_ref->get_result()->num_rows === 0) {
                    $ins_ref = $conn->prepare("
                        INSERT INTO refunds
                            (booking_id, booking_reference, customer_id, original_amount,
                             check_in_date, cancellation_date, days_before_checkin,
                             refund_percentage, refund_amount, processing_fee,
                             processing_fee_percentage, final_refund,
                             refund_status, refund_reference, processed_by, processed_at, admin_notes)
                        SELECT
                            cr.booking_id, b.booking_reference, cr.user_id, b.total_price,
                            b.check_in_date, cr.requested_at, cr.days_before_checkin,
                            cr.refund_percentage, cr.refund_amount, cr.processing_fee,
                            5.00, cr.final_refund,
                            'Approved', ?, ?, NOW(), ?
                        FROM cancellation_requests cr
                        JOIN bookings b ON cr.booking_id = b.id
                        WHERE cr.id = ?
                    ");
                    $ins_ref->bind_param("siis", $refund_ref, $manager_id, $manager_notes, $request_id);
                    if (!$ins_ref->execute()) {
                        error_log("Warning: could not insert refund record: " . $ins_ref->error);
                    }
                }

                if (function_exists('log_user_activity')) {
                    log_user_activity($manager_id, 'refund_approved',
                        "Approved cancellation for booking {$req['booking_reference']}. Refund: ETB " . number_format($req['final_refund'], 2),
                        $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                }

                $conn->commit();
                $success = 'Cancellation approved. Booking cancelled and refund of ETB ' . number_format($req['final_refund'], 2) . ' recorded.';

            } else { // reject
                $upd_req = $conn->prepare("
                    UPDATE cancellation_requests
                    SET status        = 'Rejected',
                        manager_notes = ?,
                        processed_by  = ?,
                        processed_at  = NOW()
                    WHERE id = ?
                ");
                $upd_req->bind_param("sii", $manager_notes, $manager_id, $request_id);
                if (!$upd_req->execute()) throw new Exception('Failed to update request: ' . $upd_req->error);

                // Restore booking to confirmed (it was set to Pending Cancellation)
                $upd_bk = $conn->prepare("
                    UPDATE bookings
                    SET status = 'confirmed'
                    WHERE id = ? AND status IN ('Pending Cancellation', 'pending')
                ");
                $upd_bk->bind_param("i", $req['booking_id']);
                $upd_bk->execute();

                if (function_exists('log_user_activity')) {
                    log_user_activity($manager_id, 'refund_rejected',
                        "Rejected cancellation for booking {$req['booking_reference']}.",
                        $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                }

                $conn->commit();
                $success = 'Cancellation request rejected. Booking remains active.';
            }

        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$status_filter = $_GET['status'] ?? 'Pending';
$search        = trim($_GET['search'] ?? '');

// ── Fetch cancellation requests ───────────────────────────────────────────────
$query = "
    SELECT
        cr.*,
        b.booking_reference,
        b.check_in_date,
        b.check_out_date,
        b.total_price,
        b.status AS booking_status,
        CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
        u.email  AS customer_email,
        u.phone  AS customer_phone,
        CONCAT(COALESCE(m.first_name,''), ' ', COALESCE(m.last_name,'')) AS processed_by_name
    FROM cancellation_requests cr
    JOIN bookings b ON cr.booking_id = b.id
    JOIN users u    ON cr.user_id    = u.id
    LEFT JOIN users m ON cr.processed_by = m.id
    WHERE 1=1
";

$params      = [];
$param_types = '';

if ($status_filter !== 'all') {
    $query        .= " AND cr.status = ?";
    $params[]      = $status_filter;
    $param_types  .= 's';
}

if (!empty($search)) {
    $like          = '%' . $search . '%';
    $query        .= " AND (b.booking_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[]      = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $param_types  .= 'ssss';
}

$query .= " ORDER BY cr.requested_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Statistics ────────────────────────────────────────────────────────────────
$stats_res = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Pending')  AS pending_count,
        SUM(status = 'Approved') AS approved_count,
        SUM(status = 'Rejected') AS rejected_count,
        SUM(CASE WHEN status = 'Pending'  THEN final_refund ELSE 0 END) AS pending_amount,
        SUM(CASE WHEN status = 'Approved' THEN final_refund ELSE 0 END) AS approved_amount
    FROM cancellation_requests
");
$stats = $stats_res ? $stats_res->fetch_assoc() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Management - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stat-card { border-left: 4px solid; transition: transform .2s; }
        .stat-card:hover { transform: translateY(-4px); }
        .policy-badge { font-size: .75rem; padding: .25rem .5rem; }
        .table th { white-space: nowrap; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="manager.php">
            <i class="fas fa-hotel"></i> Harar Ras Hotel – Refund Management
        </a>
        <div class="d-flex gap-2">
            <a href="manager.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid py-4">

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Cancellation Policy -->
    <div class="card mb-4 border-info">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Harar Ras Hotel Cancellation Policy</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary">Refund Schedule:</h6>
                    <ul class="list-unstyled">
                        <li class="mb-1"><span class="badge bg-success policy-badge">95% Refund</span> &nbsp;7+ days before check-in</li>
                        <li class="mb-1"><span class="badge bg-warning text-dark policy-badge">75% Refund</span> &nbsp;3–6 days before check-in</li>
                        <li class="mb-1"><span class="badge bg-warning text-dark policy-badge">50% Refund</span> &nbsp;1–2 days before check-in</li>
                        <li class="mb-1"><span class="badge bg-danger policy-badge">25% Refund</span> &nbsp;Same day cancellation</li>
                        <li class="mb-1"><span class="badge bg-dark policy-badge">No Refund</span> &nbsp;Past check-in date</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-primary">Important Notes:</h6>
                    <ul>
                        <li>All refunds are subject to a 5% processing fee (deducted from refund amount)</li>
                        <li>Formula: <code>(total × refund%) − (total × 5%)</code></li>
                        <li>Refunds processed within 5–7 business days after approval</li>
                        <li>Refunds made to the original payment method</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card border-primary">
                <div class="card-body">
                    <h6 class="text-muted">Total Requests</h6>
                    <h3><?php echo (int)($stats['total'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-warning">
                <div class="card-body">
                    <h6 class="text-muted">Pending</h6>
                    <h3><?php echo (int)($stats['pending_count'] ?? 0); ?></h3>
                    <small class="text-muted">ETB <?php echo number_format($stats['pending_amount'] ?? 0, 2); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-success">
                <div class="card-body">
                    <h6 class="text-muted">Approved</h6>
                    <h3><?php echo (int)($stats['approved_count'] ?? 0); ?></h3>
                    <small class="text-muted">ETB <?php echo number_format($stats['approved_amount'] ?? 0, 2); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-danger">
                <div class="card-body">
                    <h6 class="text-muted">Rejected</h6>
                    <h3><?php echo (int)($stats['rejected_count'] ?? 0); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search Cancellation Requests</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Booking Reference / Customer Name / Email:</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="e.g. HRH20240101 or John Doe"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status:</label>
                    <select name="status" class="form-select">
                        <option value="Pending"  <?php echo $status_filter === 'Pending'  ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="all"      <?php echo $status_filter === 'all'      ? 'selected' : ''; ?>>All</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancellation Requests Table -->
    <div class="card">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Cancellation Requests
                <?php if ($status_filter !== 'all'): ?>
                <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($status_filter); ?></span>
                <?php endif; ?>
            </h5>
            <span class="badge bg-light text-dark"><?php echo count($requests); ?> record(s)</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($requests)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">No cancellation requests found</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Booking Ref</th>
                            <th>Customer</th>
                            <th>Check-in Date</th>
                            <th>Days Before</th>
                            <th>Total Amount</th>
                            <th>Refund %</th>
                            <th>Processing Fee</th>
                            <th>Final Refund</th>
                            <th>Requested At</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($req['booking_reference']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($req['customer_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($req['customer_email']); ?></small>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($req['check_in_date'])); ?></td>
                            <td><span class="badge bg-info"><?php echo (int)$req['days_before_checkin']; ?> days</span></td>
                            <td>ETB <?php echo number_format($req['total_price'], 2); ?></td>
                            <td>
                                <?php
                                $pct = (int)$req['refund_percentage'];
                                $pct_class = $pct >= 75 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
                                ?>
                                <span class="badge bg-<?php echo $pct_class; ?>"><?php echo $pct; ?>%</span>
                            </td>
                            <td class="text-danger">ETB <?php echo number_format($req['processing_fee'], 2); ?></td>
                            <td><strong class="text-success">ETB <?php echo number_format($req['final_refund'], 2); ?></strong></td>
                            <td><small><?php echo date('M d, Y H:i', strtotime($req['requested_at'])); ?></small></td>
                            <td>
                                <?php
                                $badge = match($req['status']) {
                                    'Pending'  => 'warning',
                                    'Approved' => 'success',
                                    'Rejected' => 'danger',
                                    default    => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?php echo $badge; ?>"><?php echo $req['status']; ?></span>
                                <?php if ($req['status'] !== 'Pending' && !empty($req['processed_by_name'])): ?>
                                <br><small class="text-muted">by <?php echo htmlspecialchars(trim($req['processed_by_name'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'Pending'): ?>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-success"
                                            onclick="openModal(<?php echo $req['id']; ?>, 'approve',
                                                '<?php echo htmlspecialchars($req['booking_reference']); ?>',
                                                '<?php echo number_format($req['final_refund'], 2); ?>')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-sm btn-danger"
                                            onclick="openModal(<?php echo $req['id']; ?>, 'reject',
                                                '<?php echo htmlspecialchars($req['booking_reference']); ?>',
                                                '<?php echo number_format($req['final_refund'], 2); ?>')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary"
                                        onclick="viewNotes('<?php echo htmlspecialchars(addslashes($req['manager_notes'] ?? 'No notes.')); ?>')">
                                    <i class="fas fa-eye"></i> Notes
                                </button>
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

</div><!-- /container -->

<!-- Process Modal -->
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="process_refund" value="1">
                <input type="hidden" name="request_id" id="modal_request_id">
                <input type="hidden" name="action"     id="modal_action">

                <div class="modal-header" id="modal_header">
                    <h5 class="modal-title" id="modal_title">Process Cancellation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert" id="modal_info"></div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Manager Notes <small class="text-muted">(optional)</small>:</label>
                        <textarea name="manager_notes" class="form-control" rows="3"
                                  placeholder="Add notes about this decision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="modal_confirm_btn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Notes Modal -->
<div class="modal fade" id="notesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manager Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="notes_content" class="mb-0"></p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal(requestId, action, bookingRef, refundAmount) {
    document.getElementById('modal_request_id').value = requestId;
    document.getElementById('modal_action').value     = action;

    const header  = document.getElementById('modal_header');
    const info    = document.getElementById('modal_info');
    const btn     = document.getElementById('modal_confirm_btn');
    const title   = document.getElementById('modal_title');

    if (action === 'approve') {
        header.className = 'modal-header bg-success text-white';
        title.textContent = 'Approve Cancellation';
        info.className = 'alert alert-success';
        info.innerHTML = `<i class="fas fa-check-circle me-2"></i>
            Approving will <strong>cancel booking ${bookingRef}</strong> and record a refund of
            <strong>ETB ${refundAmount}</strong>. This action cannot be undone.`;
        btn.className = 'btn btn-success';
        btn.innerHTML = '<i class="fas fa-check me-1"></i> Approve & Cancel Booking';
    } else {
        header.className = 'modal-header bg-danger text-white';
        title.textContent = 'Reject Cancellation';
        info.className = 'alert alert-warning';
        info.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>
            Rejecting will <strong>keep booking ${bookingRef} active</strong>.
            The customer's cancellation request will be marked as Rejected.`;
        btn.className = 'btn btn-danger';
        btn.innerHTML = '<i class="fas fa-times me-1"></i> Reject Request';
    }

    new bootstrap.Modal(document.getElementById('processModal')).show();
}

function viewNotes(notes) {
    document.getElementById('notes_content').textContent = notes || 'No notes recorded.';
    new bootstrap.Modal(document.getElementById('notesModal')).show();
}
</script>
</body>
</html>
