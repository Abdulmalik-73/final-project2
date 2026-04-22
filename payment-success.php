<?php
/**
 * Booking Confirmation & Feedback Page
 * Shown after successful Chapa payment (or any confirmed booking).
 * Replaces the old "Payment Submitted" page.
 */

// Start session FIRST (same pattern as my-bookings.php) before config.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/language.php';

// Must be logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $proto = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    header("Location: $proto://$host/login.php");
    exit();
}

$booking_id = (int)($_GET['booking'] ?? 0);
if (!$booking_id) {
    header('Location: my-bookings.php');
    exit();
}

// ── Fetch booking with all related data ───────────────────────────────────────
$query = "SELECT b.*,
          COALESCE(r.name, '') as room_name,
          COALESCE(r.room_number, '') as room_number,
          CONCAT(u.first_name, ' ', u.last_name) as customer_name,
          u.email,
          cf.id as feedback_id,
          sb.service_name, sb.service_date, sb.service_time, sb.quantity as service_quantity,
          fo.order_reference, fo.table_reservation, fo.reservation_date,
          fo.reservation_time, fo.guests as food_guests, fo.special_requests as food_special,
          fo.id as food_order_id
          FROM bookings b
          LEFT JOIN rooms r ON b.room_id = r.id
          JOIN users u ON b.user_id = u.id
          LEFT JOIN customer_feedback cf ON b.id = cf.booking_id
          LEFT JOIN service_bookings sb ON b.id = sb.booking_id
          LEFT JOIN food_orders fo ON b.id = fo.booking_id
          WHERE b.id = ? AND b.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: my-bookings.php');
    exit();
}

$btype = $booking['booking_type'] ?? 'room';

// ── Fetch food order items ────────────────────────────────────────────────────
$food_items = [];
if ($btype === 'food_order' && !empty($booking['food_order_id'])) {
    $fi = $conn->prepare(
        "SELECT item_name, quantity, price FROM food_order_items WHERE order_id = ? ORDER BY id"
    );
    $fi->bind_param("i", $booking['food_order_id']);
    $fi->execute();
    $food_items = $fi->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ── Handle feedback submission ────────────────────────────────────────────────
$feedback_submitted = false;
$feedback_error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
    $overall  = (int)($_POST['overall_rating']  ?? 0);
    $quality  = (int)($_POST['quality_rating']  ?? 0);
    $clean    = (int)($_POST['value_rating']    ?? 0);
    $comments = trim($_POST['comments'] ?? '');

    if ($overall < 1 || $overall > 5 || $quality < 1 || $quality > 5 || $clean < 1 || $clean > 5) {
        $feedback_error = 'Please provide all three ratings (1–5 stars).';
    } else {
        // Check not already submitted
        $chk = $conn->prepare("SELECT id FROM customer_feedback WHERE booking_id = ?");
        $chk->bind_param("i", $booking_id);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            $ins = $conn->prepare(
                "INSERT INTO customer_feedback
                 (booking_id, customer_id, overall_rating, service_quality, cleanliness, comments, booking_type, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $ins->bind_param("iiiiiss",
                $booking_id, $_SESSION['user_id'],
                $overall, $quality, $clean, $comments, $btype
            );
            if ($ins->execute()) {
                $feedback_submitted  = true;
                $booking['feedback_id'] = $ins->insert_id; // mark as submitted
            } else {
                $feedback_error = 'Failed to submit feedback. Please try again.';
            }
        } else {
            $feedback_submitted = true;
            $booking['feedback_id'] = 1; // already exists
        }
    }
}

// ── Page-level labels per booking type ───────────────────────────────────────
$type_config = [
    'room' => [
        'icon'       => 'fa-bed',
        'title'      => 'Booking Confirmed!',
        'subtitle'   => 'Your room has been successfully reserved',
        'ref_label'  => 'Booking Reference',
        'next_steps' => [
            'A confirmation email has been sent to your inbox.',
            'Please arrive at the hotel on your check-in date.',
            'Bring a valid ID and your booking reference.',
            'Contact us if you need to make any changes.',
        ],
    ],
    'food_order' => [
        'icon'       => 'fa-utensils',
        'title'      => 'Food Order Confirmed!',
        'subtitle'   => 'Your food order has been placed successfully',
        'ref_label'  => 'Food Order Reference',
        'next_steps' => [
            'A confirmation email has been sent to your inbox.',
            'Our kitchen team is preparing your order.',
            'You will be notified when your order is ready.',
            'Contact us if you have any special requests.',
        ],
    ],
    'spa_service' => [
        'icon'       => 'fa-spa',
        'title'      => 'Spa & Wellness Confirmed!',
        'subtitle'   => 'Your spa session has been booked successfully',
        'ref_label'  => 'Spa Service Reference',
        'next_steps' => [
            'A confirmation email has been sent to your inbox.',
            'Please arrive 10 minutes before your scheduled time.',
            'Bring your booking reference for check-in.',
            'Contact us if you need to reschedule.',
        ],
    ],
    'laundry_service' => [
        'icon'       => 'fa-tshirt',
        'title'      => 'Laundry Service Confirmed!',
        'subtitle'   => 'Your laundry service has been booked successfully',
        'ref_label'  => 'Laundry Service Reference',
        'next_steps' => [
            'A confirmation email has been sent to your inbox.',
            'Our team will collect your items at the scheduled time.',
            'Items will be returned clean and neatly folded.',
            'Contact us if you need to make any changes.',
        ],
    ],
];
$cfg = $type_config[$btype] ?? $type_config['room'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($cfg['title']); ?> - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --green: #27ae60; --light-green: #2ecc71; }
        body {
            background: linear-gradient(135deg, var(--light-green) 0%, var(--green) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .page-wrap {
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 24px 12px;
        }
        .conf-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 10px 32px rgba(0,0,0,0.15);
            overflow: hidden;
            max-width: 580px;
            width: 100%;
            animation: slideUp .4s ease-out;
        }
        @keyframes slideUp {
            from { opacity:0; transform:translateY(24px); }
            to   { opacity:1; transform:translateY(0); }
        }
        /* ── Green header ── */
        .conf-header {
            background: linear-gradient(135deg, var(--light-green) 0%, var(--green) 100%);
            color: white;
            padding: 28px 24px 22px;
            text-align: center;
        }
        .conf-header .check-icon {
            width: 64px; height: 64px;
            background: rgba(255,255,255,0.25);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.8rem;
        }
        .conf-header h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 6px; }
        .conf-header p  { font-size: 0.88rem; margin: 0; opacity: .9; }

        /* ── Body ── */
        .conf-body { padding: 20px 24px; }

        /* ── Details card ── */
        .details-box {
            background: #f8fffe;
            border: 1px solid #d4edda;
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 16px;
        }
        .details-box h5 {
            color: var(--green);
            font-size: .95rem;
            margin-bottom: 12px;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 20px;
        }
        .detail-grid.full { grid-template-columns: 1fr; }
        .d-label { font-size: .75rem; color: #666; font-weight: 600; margin-bottom: 2px; }
        .d-value { font-size: .92rem; font-weight: 700; color: #1a1a1a; }
        .d-value.green { color: var(--green); }

        /* ── Next steps ── */
        .next-box {
            background: #f0faf4;
            border-left: 4px solid var(--green);
            border-radius: 0 8px 8px 0;
            padding: 12px 16px;
            margin-bottom: 16px;
        }
        .next-box h6 { color: var(--green); font-size: .88rem; margin-bottom: 8px; }
        .next-box ul { margin: 0; padding-left: 18px; }
        .next-box li { font-size: .82rem; color: #333; margin-bottom: 4px; }

        /* ── Feedback ── */
        .feedback-box {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 16px;
        }
        .feedback-box h5 { font-size: .95rem; color: #333; margin-bottom: 4px; }
        .feedback-box p  { font-size: .8rem; color: #777; margin-bottom: 12px; }
        .rating-group { margin-bottom: 12px; }
        .rating-label { font-size: .8rem; font-weight: 600; color: #444; margin-bottom: 5px; display:flex; align-items:center; gap:6px; }
        .rating-label i { color: var(--green); }
        .star-row { display:flex; gap:4px; margin-bottom:2px; }
        .star { font-size: 1.5rem; color: #ddd; cursor: pointer; transition: color .15s, transform .1s; }
        .star:hover, .star.active { color: #f39c12; transform: scale(1.15); }
        .rating-hint { font-size: .72rem; color: #aaa; }
        textarea.form-control { font-size: .85rem; border-radius: 8px; border: 1.5px solid #ddd; }
        textarea.form-control:focus { border-color: var(--green); box-shadow: 0 0 0 .15rem rgba(39,174,96,.2); }

        /* ── Buttons ── */
        .btn-green {
            background: linear-gradient(135deg, var(--light-green), var(--green));
            color: white; border: none;
            padding: 9px 24px; border-radius: 22px;
            font-weight: 600; font-size: .88rem;
            transition: all .2s;
        }
        .btn-green:hover { color:white; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(39,174,96,.4); }
        .btn-outline-green {
            border: 2px solid var(--green); color: var(--green);
            padding: 9px 24px; border-radius: 22px;
            font-weight: 600; font-size: .88rem; background: transparent;
            transition: all .2s;
        }
        .btn-outline-green:hover { background: var(--green); color: white; }

        /* ── Thank you state ── */
        .thanks-box { text-align: center; padding: 20px 10px; }
        .thanks-box i { font-size: 2.8rem; color: var(--green); margin-bottom: 10px; display:block; }
        .thanks-box h4 { font-size: 1.1rem; color: #333; }
        .thanks-box p  { font-size: .85rem; color: #777; }

        @media (max-width: 480px) {
            .conf-body { padding: 14px 14px; }
            .detail-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="page-wrap">
<div class="conf-card">

    <!-- ── Green Header ── -->
    <div class="conf-header">
        <div class="check-icon">
            <i class="fas fa-check"></i>
        </div>
        <h1><?php echo htmlspecialchars($cfg['title']); ?></h1>
        <p><?php echo htmlspecialchars($cfg['subtitle']); ?></p>
    </div>

    <div class="conf-body">

        <!-- ── Booking Details ── -->
        <div class="details-box">
            <h5><i class="fas <?php echo $cfg['icon']; ?> me-2"></i>
                <?php
                $detail_titles = [
                    'room'            => 'Room Booking Details',
                    'food_order'      => 'Food Order Details',
                    'spa_service'     => 'Spa & Wellness Details',
                    'laundry_service' => 'Laundry Service Details',
                ];
                echo $detail_titles[$btype] ?? 'Booking Details';
                ?>
            </h5>

            <!-- Reference + Customer -->
            <div class="detail-grid mb-2">
                <div>
                    <div class="d-label"><?php echo htmlspecialchars($cfg['ref_label']); ?></div>
                    <div class="d-value"><?php echo htmlspecialchars($booking['booking_reference']); ?></div>
                </div>
                <div>
                    <div class="d-label">Customer Name</div>
                    <div class="d-value"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                </div>
            </div>

            <?php if ($btype === 'room'): ?>
            <div class="detail-grid mb-2">
                <div>
                    <div class="d-label">Room</div>
                    <div class="d-value"><?php echo htmlspecialchars($booking['room_name'] ?: 'N/A'); ?></div>
                </div>
                <div>
                    <div class="d-label">Room Number</div>
                    <div class="d-value"><?php echo htmlspecialchars($booking['room_number'] ?: 'N/A'); ?></div>
                </div>
            </div>
            <div class="detail-grid mb-2">
                <div>
                    <div class="d-label">Check-in</div>
                    <div class="d-value"><?php echo $booking['check_in_date'] ? date('M j, Y', strtotime($booking['check_in_date'])) : 'N/A'; ?></div>
                </div>
                <div>
                    <div class="d-label">Check-out</div>
                    <div class="d-value"><?php echo $booking['check_out_date'] ? date('M j, Y', strtotime($booking['check_out_date'])) : 'N/A'; ?></div>
                </div>
            </div>

            <?php elseif ($btype === 'food_order'): ?>
            <?php if (!empty($food_items)): ?>
            <div class="detail-grid full mb-2">
                <div>
                    <div class="d-label">Items Ordered</div>
                    <div class="d-value">
                        <?php foreach ($food_items as $item): ?>
                        <?php echo htmlspecialchars($item['quantity'] . 'x ' . $item['item_name']); ?> — ETB <?php echo number_format($item['price'], 2); ?><br>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="detail-grid mb-2">
                <div>
                    <div class="d-label">Guests</div>
                    <div class="d-value"><?php echo (int)($booking['food_guests'] ?? 1); ?> person(s)</div>
                </div>
                <div>
                    <div class="d-label">Table Reserved</div>
                    <div class="d-value"><?php echo $booking['table_reservation'] ? 'Yes' : 'No'; ?></div>
                </div>
            </div>
            <?php if (!empty($booking['reservation_date'])): ?>
            <div class="detail-grid mb-2">
                <div>
                    <div class="d-label">Reservation Date</div>
                    <div class="d-value"><?php echo date('M j, Y', strtotime($booking['reservation_date'])); ?></div>
                </div>
                <div>
                    <div class="d-label">Reservation Time</div>
                    <div class="d-value"><?php echo $booking['reservation_time'] ? date('g:i A', strtotime($booking['reservation_time'])) : 'N/A'; ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php elseif (in_array($btype, ['spa_service', 'laundry_service'])): ?>
            <div class="detail-grid mb-2">
                <div>
                    <div class="d-label">Service</div>
                    <div class="d-value"><?php echo htmlspecialchars($booking['service_name'] ?? 'N/A'); ?></div>
                </div>
                <div>
                    <div class="d-label">Category</div>
                    <div class="d-value"><?php echo $btype === 'spa_service' ? 'Spa & Wellness' : 'Laundry'; ?></div>
                </div>
            </div>
            <div class="detail-grid mb-2">
                <div>
                    <div class="d-label"><?php echo $btype === 'laundry_service' ? 'Collection Date' : 'Service Date'; ?></div>
                    <div class="d-value"><?php echo !empty($booking['service_date']) ? date('M j, Y', strtotime($booking['service_date'])) : 'To be scheduled'; ?></div>
                </div>
                <div>
                    <div class="d-label"><?php echo $btype === 'laundry_service' ? 'Collection Time' : 'Service Time'; ?></div>
                    <div class="d-value"><?php echo !empty($booking['service_time']) ? date('g:i A', strtotime($booking['service_time'])) : 'To be confirmed'; ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Total + Status — all types -->
            <div class="detail-grid">
                <div>
                    <div class="d-label">Total Amount</div>
                    <div class="d-value green">ETB <?php echo number_format($booking['total_price'], 2); ?></div>
                </div>
                <div>
                    <div class="d-label">Payment Status</div>
                    <div class="d-value">
                        <?php
                        $ps = $booking['payment_status'] ?? 'pending';
                        $vs = $booking['verification_status'] ?? '';
                        if ($ps === 'paid' || $vs === 'verified') {
                            echo '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Paid</span>';
                        } elseif ($vs === 'pending_verification') {
                            echo '<span class="badge bg-warning text-dark">Pending Verification</span>';
                        } else {
                            echo '<span class="badge bg-secondary">' . ucfirst(str_replace('_',' ',$ps)) . '</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── What's Next ── -->
        <div class="next-box">
            <h6><i class="fas fa-list-check me-2"></i>What's Next?</h6>
            <ul>
                <?php foreach ($cfg['next_steps'] as $step): ?>
                <li><?php echo htmlspecialchars($step); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- ── Feedback Section ── -->
        <?php if ($feedback_submitted || !empty($booking['feedback_id'])): ?>
        <!-- Thank you state -->
        <div class="feedback-box">
            <div class="thanks-box">
                <i class="fas fa-heart"></i>
                <h4>Thank You for Your Feedback!</h4>
                <p>Your review helps us improve our services for everyone.</p>
            </div>
        </div>

        <?php else: ?>
        <!-- Feedback form -->
        <div class="feedback-box">
            <h5><i class="fas fa-star me-2" style="color:#f39c12;"></i>Share Your Experience</h5>
            <p>Your feedback helps us serve you better. It only takes a moment!</p>

            <?php if ($feedback_error): ?>
            <div class="alert alert-danger py-2 mb-3" style="font-size:.82rem;">
                <i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($feedback_error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="feedbackForm">
                <input type="hidden" name="action" value="submit_feedback">

                <div class="rating-group">
                    <div class="rating-label"><i class="fas fa-heart"></i> Overall Experience</div>
                    <div class="star-row" data-field="overall_rating">
                        <span class="star" data-value="1">★</span>
                        <span class="star" data-value="2">★</span>
                        <span class="star" data-value="3">★</span>
                        <span class="star" data-value="4">★</span>
                        <span class="star" data-value="5">★</span>
                    </div>
                    <div class="rating-hint" id="hint_overall">Click to rate</div>
                    <input type="hidden" name="overall_rating" id="overall_rating">
                </div>

                <div class="rating-group">
                    <div class="rating-label">
                        <i class="fas fa-concierge-bell"></i>
                        <?php echo $btype === 'food_order' ? 'Food Quality & Taste' : 'Service Quality'; ?>
                    </div>
                    <div class="star-row" data-field="quality_rating">
                        <span class="star" data-value="1">★</span>
                        <span class="star" data-value="2">★</span>
                        <span class="star" data-value="3">★</span>
                        <span class="star" data-value="4">★</span>
                        <span class="star" data-value="5">★</span>
                    </div>
                    <div class="rating-hint" id="hint_quality">Click to rate</div>
                    <input type="hidden" name="quality_rating" id="quality_rating">
                </div>

                <div class="rating-group">
                    <div class="rating-label">
                        <i class="fas fa-broom"></i>
                        <?php echo $btype === 'food_order' ? 'Cleanliness & Presentation' : 'Cleanliness'; ?>
                    </div>
                    <div class="star-row" data-field="value_rating">
                        <span class="star" data-value="1">★</span>
                        <span class="star" data-value="2">★</span>
                        <span class="star" data-value="3">★</span>
                        <span class="star" data-value="4">★</span>
                        <span class="star" data-value="5">★</span>
                    </div>
                    <div class="rating-hint" id="hint_value">Click to rate</div>
                    <input type="hidden" name="value_rating" id="value_rating">
                </div>

                <div class="mb-3">
                    <label class="rating-label"><i class="fas fa-comment"></i> Comments (Optional)</label>
                    <textarea name="comments" class="form-control" rows="2"
                              placeholder="Share your thoughts or suggestions..."></textarea>
                </div>

                <div class="d-flex gap-2 justify-content-center">
                    <button type="submit" class="btn-green">
                        <i class="fas fa-paper-plane me-1"></i> Submit Feedback
                    </button>
                    <a href="my-bookings.php" class="btn-outline-green" style="text-decoration:none; display:inline-flex; align-items:center;">
                        <i class="fas fa-forward me-1"></i> Skip
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- ── Bottom Actions ── -->
        <div class="text-center pt-2 pb-1">
            <a href="my-bookings.php" class="btn-green me-2" style="text-decoration:none; display:inline-block; margin-bottom:8px;">
                <i class="fas fa-list me-1"></i> My Bookings
            </a>
            <a href="index.php" class="btn-outline-green" style="text-decoration:none; display:inline-block;">
                <i class="fas fa-home me-1"></i> Back to Home
            </a>
        </div>

    </div><!-- /conf-body -->
</div><!-- /conf-card -->
</div><!-- /page-wrap -->

<script>
const hints = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

document.querySelectorAll('.star-row').forEach(function(row) {
    const field = row.dataset.field;
    const stars = row.querySelectorAll('.star');
    const input = document.getElementById(field.replace('_rating','') + '_rating') ||
                  document.getElementById(field);
    const hint  = document.getElementById('hint_' + field.replace('_rating',''));

    stars.forEach(function(star, idx) {
        star.addEventListener('mouseenter', function() {
            stars.forEach(function(s, i) {
                s.style.color = i <= idx ? '#f39c12' : '#ddd';
            });
            if (hint) hint.textContent = hints[idx + 1];
        });
        star.addEventListener('click', function() {
            const val = parseInt(star.dataset.value);
            if (input) input.value = val;
            stars.forEach(function(s, i) {
                s.classList.toggle('active', i < val);
            });
            if (hint) hint.textContent = hints[val];
        });
    });

    row.addEventListener('mouseleave', function() {
        const cur = input ? parseInt(input.value) || 0 : 0;
        stars.forEach(function(s, i) {
            s.style.color = i < cur ? '#f39c12' : '#ddd';
        });
        if (hint) hint.textContent = cur ? hints[cur] : 'Click to rate';
    });
});

// Validate before submit
const form = document.getElementById('feedbackForm');
if (form) {
    form.addEventListener('submit', function(e) {
        const fields = ['overall_rating', 'quality_rating', 'value_rating'];
        for (const f of fields) {
            const el = document.getElementById(f);
            if (!el || !el.value || parseInt(el.value) < 1) {
                e.preventDefault();
                alert('Please rate all three categories before submitting.');
                return;
            }
        }
    });
}
</script>
</body>
</html>
