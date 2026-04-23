<?php
/**
 * Booking Cancellation API
 * Flow: calculate → confirm (creates cancellation_request, sets booking to pending_cancellation)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cancel_booking_errors.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    require_once '../includes/config.php';
    require_once '../includes/functions.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection not available');
    }

    // ── Step 1: Fix the bookings.status ENUM to include pending_cancellation ──
    // Use a safe VARCHAR approach to avoid ENUM truncation errors entirely
    $conn->query("ALTER TABLE bookings MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'");

    // ── Step 2: Ensure cancellation_requests table exists ────────────────────
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

    // Add total_amount column if missing (migration)
    $chk = $conn->query("SHOW COLUMNS FROM cancellation_requests LIKE 'total_amount'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE cancellation_requests ADD COLUMN total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER user_id");
    }

    // ── Step 3: Ensure bookings has cancellation columns ─────────────────────
    $needed = [
        'cancelled_at'        => "ALTER TABLE bookings ADD COLUMN cancelled_at DATETIME NULL",
        'cancelled_by'        => "ALTER TABLE bookings ADD COLUMN cancelled_by INT NULL",
        'cancellation_reason' => "ALTER TABLE bookings ADD COLUMN cancellation_reason TEXT NULL",
        'refund_amount'       => "ALTER TABLE bookings ADD COLUMN refund_amount DECIMAL(10,2) DEFAULT 0.00",
        'penalty_amount'      => "ALTER TABLE bookings ADD COLUMN penalty_amount DECIMAL(10,2) DEFAULT 0.00",
    ];
    foreach ($needed as $col => $sql) {
        $chk = $conn->query("SHOW COLUMNS FROM bookings LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query($sql);
        }
    }

    // ── Parse input ───────────────────────────────────────────────────────────
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    $booking_reference = trim($input['booking_reference'] ?? '');
    $action            = $input['action'] ?? 'calculate';

    if (empty($booking_reference)) {
        throw new Exception('Booking reference is required');
    }

    $user_id = (int)$_SESSION['user_id'];

    // ── Fetch booking (verify ownership) ─────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT b.*, r.name AS room_name, u.first_name, u.last_name, u.email
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        JOIN users u ON b.user_id = u.id
        WHERE b.booking_reference = ? AND b.user_id = ?
    ");
    if (!$stmt) throw new Exception('Query prepare failed: ' . $conn->error);
    $stmt->bind_param("si", $booking_reference, $user_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        throw new Exception('Booking not found or access denied');
    }

    // ── Status guards ─────────────────────────────────────────────────────────
    $current_status = strtolower($booking['status']);
    if (in_array($current_status, ['cancelled', 'pending_cancellation'])) {
        throw new Exception('A cancellation request already exists for this booking');
    }
    if ($current_status === 'no_show') {
        throw new Exception('Cannot cancel a no-show booking');
    }
    if ($current_status === 'checked_out') {
        throw new Exception('Cannot cancel a completed booking');
    }

    // Check for existing pending request
    $chk_req = $conn->prepare("SELECT id FROM cancellation_requests WHERE booking_id = ? AND status = 'Pending'");
    $chk_req->bind_param("i", $booking['id']);
    $chk_req->execute();
    if ($chk_req->get_result()->num_rows > 0) {
        throw new Exception('A cancellation request is already pending for this booking');
    }

    // ── Date & refund calculation ─────────────────────────────────────────────
    $check_in_date = new DateTime($booking['check_in_date']);
    $check_in_date->setTime(0, 0, 0);

    $today = new DateTime();
    $today->setTime(0, 0, 0);

    // Days from TODAY until check-in (positive = future, 0 = today)
    $diff = (int)$today->diff($check_in_date)->days;
    // diff->days is always positive; check sign via invert
    $days_before_checkin = ($today->diff($check_in_date)->invert === 0) ? $diff : -$diff;

    if ($days_before_checkin < 0) {
        throw new Exception('Cannot cancel a booking after the check-in date has passed');
    }

    // ── Refund policy ─────────────────────────────────────────────────────────
    // Policy: days UNTIL check-in
    //   7+  days → 95%
    //   3-6 days → 75%
    //   1-2 days → 50%
    //   0   days (same day) → 25%
    //
    // EXCEPTION: if the booking was CREATED today (same day as check-in),
    // treat as 95% — customer just booked and is immediately cancelling.
    $booking_created = new DateTime($booking['created_at']);
    $booking_created->setTime(0, 0, 0);
    $booked_today = ($booking_created == $today);

    if ($days_before_checkin >= 7) {
        $refund_percentage = 95;
    } elseif ($days_before_checkin >= 3) {
        $refund_percentage = 75;
    } elseif ($days_before_checkin >= 1) {
        $refund_percentage = 50;
    } elseif ($days_before_checkin === 0 && $booked_today) {
        // Booked today, cancelling same day → full refund (just booked)
        $refund_percentage = 95;
    } elseif ($days_before_checkin === 0) {
        $refund_percentage = 25;
    } else {
        $refund_percentage = 0;
    }

    $total_amount   = (float)$booking['total_price'];
    $refund_amount  = $total_amount * ($refund_percentage / 100);
    $processing_fee = $total_amount * 0.05;          // 5% of total always
    $final_refund   = max(0, $refund_amount - $processing_fee);
    $penalty_amount = $total_amount - $final_refund;

    // ── CALCULATE ─────────────────────────────────────────────────────────────
    if ($action === 'calculate') {
        echo json_encode([
            'success' => true,
            'data' => [
                'booking_reference'         => $booking['booking_reference'],
                'room_name'                 => $booking['room_name'] ?? 'N/A',
                'check_in_date'             => $booking['check_in_date'],
                'total_amount'              => number_format($total_amount, 2),
                'days_before_checkin'       => $days_before_checkin,
                'refund_percentage'         => $refund_percentage,
                'refund_amount'             => number_format($refund_amount, 2),
                'processing_fee'            => number_format($processing_fee, 2),
                'processing_fee_percentage' => 5,
                'final_refund'              => number_format($final_refund, 2),
                'penalty_amount'            => number_format($penalty_amount, 2),
            ]
        ]);
        exit;
    }

    // ── CONFIRM ───────────────────────────────────────────────────────────────
    if ($action === 'confirm') {
        $conn->begin_transaction();
        try {
            // Update booking status — use VARCHAR-safe value
            $upd = $conn->prepare("
                UPDATE bookings
                SET status             = 'pending_cancellation',
                    cancellation_reason = 'Customer initiated cancellation',
                    refund_amount       = ?,
                    penalty_amount      = ?
                WHERE id = ?
            ");
            if (!$upd) throw new Exception('Prepare failed: ' . $conn->error);
            $upd->bind_param("ddi", $final_refund, $penalty_amount, $booking['id']);
            if (!$upd->execute()) throw new Exception('Failed to update booking: ' . $upd->error);

            // Insert cancellation request
            $ins = $conn->prepare("
                INSERT INTO cancellation_requests
                    (booking_id, user_id, total_amount, refund_amount, refund_percentage,
                     processing_fee, final_refund, days_before_checkin, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");
            if (!$ins) throw new Exception('Prepare failed: ' . $conn->error);
            $ins->bind_param("iiddiidi",
                $booking['id'],
                $user_id,
                $total_amount,
                $refund_amount,
                $refund_percentage,
                $processing_fee,
                $final_refund,
                $days_before_checkin
            );
            if (!$ins->execute()) throw new Exception('Failed to create cancellation request: ' . $ins->error);

            if (function_exists('log_user_activity')) {
                log_user_activity(
                    $user_id,
                    'cancellation_requested',
                    "Cancellation requested for {$booking['booking_reference']}. Refund: ETB " . number_format($final_refund, 2),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                );
            }

            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Your cancellation request has been submitted and is waiting for manager approval.',
                'data'    => [
                    'final_refund' => number_format($final_refund, 2),
                    'status'       => 'Pending Cancellation',
                ]
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        exit;
    }

    throw new Exception('Invalid action');

} catch (mysqli_sql_exception $e) {
    error_log("SQL Error in cancel_booking.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in cancel_booking.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
