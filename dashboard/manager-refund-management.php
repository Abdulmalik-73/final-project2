<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a manager
if (!is_logged_in() || $_SESSION['role'] !== 'manager') {
    header('Location: ../login.php');
    exit();
}

$success = '';
$error = '';

// Handle refund processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
    $refund_id = (int)$_POST['refund_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $admin_notes = sanitize_input($_POST['admin_notes'] ?? '');
    
    if ($action === 'approve') {
        $conn->begin_transaction();
        
        try {
            // Get refund details
            $refund_query = $conn->prepare("SELECT * FROM refunds WHERE id = ?");
            $refund_query->bind_param("i", $refund_id);
            $refund_query->execute();
            $refund_data = $refund_query->get_result()->fetch_assoc();
            
            // Update refund status
            $stmt = $conn->prepare("
                UPDATE refunds 
                SET refund_status = 'Processed',
                    processed_date = NOW(),
                    processed_by = ?,
                    admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n', ?)
                WHERE id = ?
            ");
            $stmt->bind_param("isi", $_SESSION['user_id'], $admin_notes, $refund_id);
            $stmt->execute();
            
            // Update booking payment status and refund amount
            $update_booking = $conn->prepare("
                UPDATE bookings 
                SET payment_status = 'refunded',
                    refund_amount = ?,
                    refunded_at = NOW()
                WHERE id = ?
            ");
            $update_booking->bind_param("di", $refund_data['final_refund'], $refund_data['booking_id']);
            $update_booking->execute();
            
            // Log activity
            log_user_activity(
                $_SESSION['user_id'],
                'refund_approved',
                "Refund approved for booking {$refund_data['booking_reference']}. Amount: ETB " . number_format($refund_data['final_refund'], 2),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
            
            $conn->commit();
            $success = 'Refund approved and processed successfully! Amount: ETB ' . number_format($refund_data['final_refund'], 2);
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to process refund: ' . $e->getMessage();
        }
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("
            UPDATE refunds 
            SET refund_status = 'Rejected',
                processed_date = NOW(),
                processed_by = ?,
                admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n', ?)
            WHERE id = ?
        ");
        $stmt->bind_param("isi", $_SESSION['user_id'], $admin_notes, $refund_id);
        
        if ($stmt->execute()) {
            $success = 'Refund rejected successfully!';
        } else {
            $error = 'Failed to reject refund';
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT 
        r.*,
        b.booking_reference,
        b.check_in_date,
        b.check_out_date,
        b.status as booking_status,
        CONCAT(u.first_name, ' ', u.last_name) as customer_name,
        u.email as customer_email,
        u.phone as customer_phone,
        CONCAT(p.first_name, ' ', p.last_name) as processed_by_name
    FROM refunds r
    JOIN bookings b ON r.booking_id = b.id
    JOIN users u ON r.customer_id = u.id
    LEFT JOIN users p ON r.processed_by = p.id
    WHERE 1=1
";

if ($status_filter !== 'all') {
    $query .= " AND r.refund_status = '" . $conn->real_escape_string($status_filter) . "'";
}

if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $query .= " AND (r.booking_reference LIKE '%$search_term%' 
                OR r.refund_reference LIKE '%$search_term%'
                OR r.customer_name LIKE '%$search_term%'
                OR r.customer_email LIKE '%$search_term%')";
}

$query .= " ORDER BY r.created_at DESC";

$refunds = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_refunds,
        SUM(CASE WHEN refund_status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN refund_status = 'Processed' THEN 1 ELSE 0 END) as processed_count,
        SUM(CASE WHEN refund_status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN refund_status = 'Pending' THEN final_refund ELSE 0 END) as pending_amount,
        SUM(CASE WHEN refund_status = 'Processed' THEN final_refund ELSE 0 END) as processed_amount
    FROM refunds
";
$stats = $conn->query($stats_query)->fetch_assoc();
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
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .policy-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="manager.php">
                <i class="fas fa-hotel"></i> Harar Ras Hotel - Refund Management
            </a>
            <div class="d-flex">
                <a href="manager.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="../logout.php" class="btn btn-outline-light">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Cancellation Policy -->
        <div class="card mb-4 border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Harar Ras Hotel Cancellation Policy</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Refund Schedule:</h6>
                        <ul class="list-unstyled">
                            <li><span class="badge bg-success policy-badge">95% Refund</span> 7+ days before check-in</li>
                            <li><span class="badge bg-warning policy-badge">75% Refund</span> 3-6 days before check-in</li>
                            <li><span class="badge bg-warning policy-badge">50% Refund</span> 1-2 days before check-in</li>
                            <li><span class="badge bg-danger policy-badge">25% Refund</span> Same day cancellation</li>
                            <li><span class="badge bg-dark policy-badge">No Refund</span> Past check-in date</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Important Notes:</h6>
                        <ul>
                            <li>All refunds are subject to a 5% processing fee</li>
                            <li>Refunds will be processed within 5-7 business days</li>
                            <li>Refunds will be made to the original payment method</li>
                            <li>Special circumstances may be considered by management</li>
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
                        <h6 class="text-muted">Total Refunds</h6>
                        <h3><?php echo $stats['total_refunds']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-warning">
                    <div class="card-body">
                        <h6 class="text-muted">Pending</h6>
                        <h3><?php echo $stats['pending_count']; ?></h3>
                        <small>ETB <?php echo number_format($stats['pending_amount'], 2); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-success">
                    <div class="card-body">
                        <h6 class="text-muted">Processed</h6>
                        <h3><?php echo $stats['processed_count']; ?></h3>
                        <small>ETB <?php echo number_format($stats['processed_amount'], 2); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-danger">
                    <div class="card-body">
                        <h6 class="text-muted">Rejected</h6>
                        <h3><?php echo $stats['rejected_count']; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-search"></i> Search Booking for Refund</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Booking Reference:</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Enter booking reference (e.g., HRH20240101)" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status:</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Processed" <?php echo $status_filter === 'Processed' ? 'selected' : ''; ?>>Processed</option>
                            <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
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

        <!-- Recent Refunds -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-history"></i> Recent Refunds</h5>
            </div>
            <div class="card-body">
                <?php if (empty($refunds)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No refund requests found</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Refund Ref</th>
                                <th>Booking Ref</th>
                                <th>Customer</th>
                                <th>Check-in Date</th>
                                <th>Cancelled Date</th>
                                <th>Days Before</th>
                                <th>Original Amount</th>
                                <th>Refund %</th>
                                <th>Final Refund</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($refunds as $refund): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($refund['refund_reference']); ?></strong></td>
                                <td><?php echo htmlspecialchars($refund['booking_reference']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($refund['customer_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($refund['customer_email']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($refund['check_in_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($refund['cancellation_date'])); ?></td>
                                <td><span class="badge bg-info"><?php echo $refund['days_before_checkin']; ?> days</span></td>
                                <td>ETB <?php echo number_format($refund['original_amount'], 2); ?></td>
                                <td><span class="badge bg-primary"><?php echo $refund['refund_percentage']; ?>%</span></td>
                                <td><strong>ETB <?php echo number_format($refund['final_refund'], 2); ?></strong></td>
                                <td>
                                    <?php
                                    $badge_class = match($refund['refund_status']) {
                                        'Pending' => 'warning',
                                        'Processed' => 'success',
                                        'Rejected' => 'danger',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo $refund['refund_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($refund['refund_status'] === 'Pending'): ?>
                                    <button class="btn btn-sm btn-success" 
                                            onclick="processRefund(<?php echo $refund['id']; ?>, 'approve')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="processRefund(<?php echo $refund['id']; ?>, 'reject')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $refund['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
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
    </div>

    <!-- Process Refund Modal -->
    <div class="modal fade" id="processRefundModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Process Refund</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="refund_id" id="refund_id">
                        <input type="hidden" name="action" id="refund_action">
                        <input type="hidden" name="process_refund" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Notes:</label>
                            <textarea name="admin_notes" class="form-control" rows="3" 
                                      placeholder="Add notes about this refund processing..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <span id="refund_action_text"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="confirm_button">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function processRefund(refundId, action) {
            document.getElementById('refund_id').value = refundId;
            document.getElementById('refund_action').value = action;
            
            if (action === 'approve') {
                document.getElementById('refund_action_text').textContent = 
                    'This will approve the refund and mark it as processed. The customer will be notified.';
                document.getElementById('confirm_button').className = 'btn btn-success';
                document.getElementById('confirm_button').innerHTML = '<i class="fas fa-check"></i> Approve Refund';
            } else {
                document.getElementById('refund_action_text').textContent = 
                    'This will reject the refund request. Please provide a reason in the notes.';
                document.getElementById('confirm_button').className = 'btn btn-danger';
                document.getElementById('confirm_button').innerHTML = '<i class="fas fa-times"></i> Reject Refund';
            }
            
            new bootstrap.Modal(document.getElementById('processRefundModal')).show();
        }
        
        function viewDetails(refundId) {
            // Implement view details functionality
            alert('View details for refund ID: ' + refundId);
        }
    </script>
</body>
</html>
