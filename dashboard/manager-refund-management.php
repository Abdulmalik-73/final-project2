<?php
ob_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

require_auth_role('manager', '../login.php');

$success = '';
$error   = '';

// Ensure cancellation_requests table and total_amount column exist
try {
    $conn->query("CREATE TABLE IF NOT EXISTS `cancellation_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `booking_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
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

    $chk = $conn->query("SHOW COLUMNS FROM cancellation_requests LIKE 'total_amount'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE cancellation_requests ADD COLUMN total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER user_id");
    }
} catch (Exception $e) {}

// Handle Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
    $request_id    = (int)($_POST['request_id'] ?? 0);
    $action        = $_POST['action'] ?? '';
    $manager_notes = sanitize_input($_POST['manager_notes'] ?? '');
    $manager_id    = (int)$_SESSION['user_id'];

    if (!in_array($action, ['approve', 'reject']) || $request_id <= 0) {
        $error = 'Invalid request.';
    } else {
        $conn->begin_transaction();
        try {
            $req_stmt = $conn->prepare("
                SELECT cr.*, b.booking_reference, b.total_price
                FROM cancellation_requests cr
                JOIN bookings b ON cr.booking_id = b.id
                WHERE cr.id = ? AND cr.status = 'Pending'
            ");
            $req_stmt->bind_param("i", $request_id);
            $req_stmt->execute();
            $req = $req_stmt->get_result()->fetch_assoc();

            if (!$req) throw new Exception('Request not found or already processed.');

            if ($action === 'approve') {
                $conn->prepare("UPDATE cancellation_requests SET status='Approved', manager_notes=?, processed_by=?, processed_at=NOW() WHERE id=?")
                     ->bind_param("sii", $manager_notes, $manager_id, $request_id);
                $upd = $conn->prepare("UPDATE cancellation_requests SET status='Approved', manager_notes=?, processed_by=?, processed_at=NOW() WHERE id=?");
                $upd->bind_param("sii", $manager_notes, $manager_id, $request_id);
                $upd->execute();

                $upd2 = $conn->prepare("UPDATE bookings SET status='cancelled', cancelled_at=NOW(), cancelled_by=?, payment_status=CASE WHEN ?>0 THEN 'refund_pending' ELSE payment_status END WHERE id=?");
                $upd2->bind_param("idi", $manager_id, $req['final_refund'], $req['booking_id']);
                $upd2->execute();

                $conn->commit();
                $success = 'Cancellation approved. Refund of ETB ' . number_format($req['final_refund'], 2) . ' recorded.';
            } else {
                $upd = $conn->prepare("UPDATE cancellation_requests SET status='Rejected', manager_notes=?, processed_by=?, processed_at=NOW() WHERE id=?");
                $upd->bind_param("sii", $manager_notes, $manager_id, $request_id);
                $upd->execute();

                $upd2 = $conn->prepare("UPDATE bookings SET status='confirmed' WHERE id=? AND status IN ('pending_cancellation','Pending Cancellation','pending')");
                $upd2->bind_param("i", $req['booking_id']);
                $upd2->execute();

                $conn->commit();
                $success = 'Cancellation rejected. Booking remains active.';
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Fetch requests
$status_filter = $_GET['status'] ?? 'Pending';
$search        = trim($_GET['search'] ?? '');

$where  = '1=1';
$params = [];
$types  = '';

if ($status_filter !== 'all') {
    $where   .= ' AND cr.status = ?';
    $params[] = $status_filter;
    $types   .= 's';
}
if (!empty($search)) {
    $like     = '%' . $search . '%';
    $where   .= ' AND (b.booking_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    $params   = array_merge($params, [$like, $like, $like, $like]);
    $types   .= 'ssss';
}

$sql = "SELECT cr.*, b.booking_reference, b.check_in_date, b.check_out_date, b.total_price,
               b.status AS booking_status,
               CONCAT(u.first_name,' ',u.last_name) AS customer_name,
               u.email AS customer_email, u.phone AS customer_phone
        FROM cancellation_requests cr
        JOIN bookings b ON cr.booking_id = b.id
        JOIN users u    ON cr.user_id    = u.id
        WHERE $where
        ORDER BY cr.requested_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$stats_row = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(status='Pending')  AS pending_count,
    SUM(status='Approved') AS approved_count,
    SUM(status='Rejected') AS rejected_count,
    SUM(CASE WHEN status='Pending'  THEN final_refund ELSE 0 END) AS pending_amount,
    SUM(CASE WHEN status='Approved' THEN final_refund ELSE 0 END) AS approved_amount
    FROM cancellation_requests")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Management - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #ecf0f1; }
        .navbar-manager { background: linear-gradient(135deg, #d4a574 0%, #c9963d 100%) !important; }
        .sidebar {
            position: fixed; top: 56px; left: 0; width: 220px; height: calc(100vh - 56px);
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            overflow-y: auto; z-index: 100; padding: 1rem 0;
        }
        .sidebar .nav-link { color: rgba(255,255,255,.85); padding: .5rem 1rem; font-size: .9rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(212,165,116,.3); }
        .sidebar h5 { color: #fff; padding: .5rem 1rem; font-size: 1rem; }
        .main-content { margin-left: 220px; padding: 1.5rem; min-height: calc(100vh - 56px); }
        @media(max-width:768px){ .sidebar{display:none;} .main-content{margin-left:0;} }
        .stat-card { border-left: 4px solid; border-radius: 8px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-manager">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="../index.php" style="color:#2c3e50;">
            <i class="fas fa-hotel"></i> Harar Ras Hotel
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span style="color:#2c3e50; font-size:.875rem;"><i class="fas fa-user-tie me-1"></i> Manager</span>
            <a href="../logout.php" class="btn btn-sm" style="background:#2c3e50; color:#fff;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="sidebar">
    <h5><i class="fas fa-user-tie me-2"></i>Manager Panel</h5>
    <nav class="nav flex-column">
        <a href="manager.php"                    class="nav-link"><i class="fas fa-tachometer-alt me-2"></i>Overview</a>
        <a href="manager-bookings.php"           class="nav-link"><i class="fas fa-calendar-check me-2"></i>Manage Bookings</a>
        <a href="manager-approve-bill.php"       class="nav-link"><i class="fas fa-check-circle me-2"></i>Approve Bill</a>
        <a href="manager-feedback.php"           class="nav-link"><i class="fas fa-star me-2"></i>Customer Feedback</a>
        <a href="manager-refund-management.php"  class="nav-link active"><i class="fas fa-undo-alt me-2"></i>Refund Management</a>
        <a href="manager-rooms.php"              class="nav-link"><i class="fas fa-bed me-2"></i>Room Management</a>
        <a href="manager-staff.php"              class="nav-link"><i class="fas fa-users me-2"></i>Staff Management</a>
        <a href="manager-reports.php"            class="nav-link"><i class="fas fa-chart-bar me-2"></i>Reports</a>
        <a href="../logout.php"                  class="nav-link mt-3"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </nav>
</div>

<div class="main-content">

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

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-undo-alt me-2"></i>Refund Management</h3>
        <a href="manager.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>

    <!-- Stats -->
    <div class="row mb-4 g-3">
        <div class="col-6 col-md-3">
            <div class="card stat-card border-primary p-3">
                <div class="text-muted small">Total Requests</div>
                <div class="fs-3 fw-bold"><?php echo (int)($stats_row['total'] ?? 0); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card border-warning p-3">
                <div class="text-muted small">Pending</div>
                <div class="fs-3 fw-bold text-warning"><?php echo (int)($stats_row['pending_count'] ?? 0); ?></div>
                <small class="text-muted">ETB <?php echo number_format($stats_row['pending_amount'] ?? 0, 2); ?></small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card border-success p-3">
                <div class="text-muted small">Approved</div>
                <div class="fs-3 fw-bold text-success"><?php echo (int)($stats_row['approved_count'] ?? 0); ?></div>
                <small class="text-muted">ETB <?php echo number_format($stats_row['approved_amount'] ?? 0, 2); ?></small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card border-danger p-3">
                <div class="text-muted small">Rejected</div>
                <div class="fs-3 fw-bold text-danger"><?php echo (int)($stats_row['rejected_count'] ?? 0); ?></div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold">Search (Booking Ref / Name / Email)</label>
                    <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="e.g. HRH20240101">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <option value="Pending"  <?php echo $status_filter==='Pending'  ? 'selected':''; ?>>Pending</option>
                        <option value="Approved" <?php echo $status_filter==='Approved' ? 'selected':''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $status_filter==='Rejected' ? 'selected':''; ?>>Rejected</option>
                        <option value="all"      <?php echo $status_filter==='all'      ? 'selected':''; ?>>All</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Search</button>
                </div>
                <div class="col-md-2">
                    <a href="manager-refund-management.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Cancellation Requests
                <span class="badge bg-light text-dark ms-2"><?php echo $status_filter !== 'all' ? $status_filter : 'All'; ?></span>
            </h5>
            <span class="badge bg-light text-dark"><?php echo count($requests); ?> record(s)</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($requests)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                <p class="text-muted mb-1">No <?php echo $status_filter !== 'all' ? strtolower($status_filter) : ''; ?> cancellation requests found.</p>
                <?php if ($status_filter === 'Pending'): ?>
                <small class="text-muted">When customers submit cancellation requests, they will appear here.</small>
                <?php else: ?>
                <a href="?status=all" class="btn btn-sm btn-outline-primary mt-2">View All Requests</a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Booking Ref</th>
                            <th>Customer</th>
                            <th>Check-in</th>
                            <th>Days Before</th>
                            <th>Total</th>
                            <th>Refund %</th>
                            <th>Fee</th>
                            <th>Final Refund</th>
                            <th>Requested</th>
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
                        <td><span class="badge bg-info"><?php echo (int)$req['days_before_checkin']; ?>d</span></td>
                        <td>ETB <?php echo number_format($req['total_price'] ?? $req['total_amount'] ?? 0, 2); ?></td>
                        <td>
                            <?php $pct = (int)$req['refund_percentage']; ?>
                            <span class="badge bg-<?php echo $pct>=75?'success':($pct>=50?'warning':'danger'); ?>"><?php echo $pct; ?>%</span>
                        </td>
                        <td class="text-danger small">ETB <?php echo number_format($req['processing_fee'], 2); ?></td>
                        <td><strong class="text-success">ETB <?php echo number_format($req['final_refund'], 2); ?></strong></td>
                        <td><small><?php echo date('M d, Y H:i', strtotime($req['requested_at'])); ?></small></td>
                        <td>
                            <?php $badge = ['Pending'=>'warning','Approved'=>'success','Rejected'=>'danger'][$req['status']] ?? 'secondary'; ?>
                            <span class="badge bg-<?php echo $badge; ?>"><?php echo $req['status']; ?></span>
                        </td>
                        <td>
                            <?php if ($req['status'] === 'Pending'): ?>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-success"
                                        onclick="openModal(<?php echo $req['id']; ?>,'approve','<?php echo htmlspecialchars($req['booking_reference']); ?>','<?php echo number_format($req['final_refund'],2); ?>')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn btn-sm btn-danger"
                                        onclick="openModal(<?php echo $req['id']; ?>,'reject','<?php echo htmlspecialchars($req['booking_reference']); ?>','<?php echo number_format($req['final_refund'],2); ?>')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">Processed</span>
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

<!-- Modal -->
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="process_refund" value="1">
                <input type="hidden" name="request_id" id="modal_rid">
                <input type="hidden" name="action"     id="modal_action">
                <div class="modal-header" id="modal_header">
                    <h5 class="modal-title" id="modal_title">Process Cancellation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert" id="modal_info"></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Manager Notes <small class="text-muted">(optional)</small></label>
                        <textarea name="manager_notes" class="form-control" rows="3" placeholder="Add notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="modal_btn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal(id, action, ref, amount) {
    document.getElementById('modal_rid').value    = id;
    document.getElementById('modal_action').value = action;
    const h = document.getElementById('modal_header');
    const i = document.getElementById('modal_info');
    const b = document.getElementById('modal_btn');
    const t = document.getElementById('modal_title');
    if (action === 'approve') {
        h.className = 'modal-header bg-success text-white';
        t.textContent = 'Approve Cancellation';
        i.className = 'alert alert-success';
        i.innerHTML = `Approving will cancel booking <strong>${ref}</strong> and record refund of <strong>ETB ${amount}</strong>.`;
        b.className = 'btn btn-success';
        b.textContent = 'Approve';
    } else {
        h.className = 'modal-header bg-danger text-white';
        t.textContent = 'Reject Cancellation';
        i.className = 'alert alert-warning';
        i.innerHTML = `Rejecting will keep booking <strong>${ref}</strong> active.`;
        b.className = 'btn btn-danger';
        b.textContent = 'Reject';
    }
    new bootstrap.Modal(document.getElementById('processModal')).show();
}
</script>
</body>
</html>
