# Hotel Booking Cancellation Feature

## Overview
Modern cancellation system for Harar Ras Hotel with automatic refund calculation based on cancellation policy.

## Features

### 1. Cancellation Policy
- **7+ days before check-in**: 95% refund
- **3-6 days before check-in**: 75% refund
- **1-2 days before check-in**: 50% refund
- **Same day cancellation**: 25% refund
- **5% processing fee** applied to all refunds

### 2. User Interface

#### Cancel Button Location
- Located in "My Bookings" page
- Appears next to "Print" and "View Details" buttons
- Only visible for eligible bookings

#### Eligibility Criteria
- Booking status must be: pending, confirmed, verified, pending_payment, or pending_verification
- Check-in date must be in the future
- Not available for food orders
- Cannot cancel already cancelled or completed bookings

#### Modern Modal Design
1. **Policy Display** (Step 1)
   - Visual refund schedule with color-coded badges
   - 95% (Green), 75% (Blue), 50% (Yellow), 25% (Red)
   - Processing fee notice

2. **Refund Calculation** (Step 2)
   - Booking details summary
   - Days before check-in counter
   - Itemized refund breakdown
   - Final refund amount highlighted

3. **Success Confirmation** (Step 3)
   - Success message with checkmark
   - Refund reference number
   - Processing timeline (5-7 business days)
   - Auto-refresh after 3 seconds

### 3. Backend Logic

#### API Endpoint
**File**: `api/cancel_booking.php`

**Actions**:
- `calculate`: Calculate refund without cancelling
- `confirm`: Process cancellation and create refund record

#### Database Updates
1. **bookings table**:
   - status → 'cancelled'
   - cancelled_at → current timestamp
   - cancelled_by → user_id
   - refund_amount → calculated amount
   - penalty_amount → deducted amount
   - payment_status → 'refund_pending'

2. **refunds table**:
   - Creates new refund record
   - Generates unique refund reference (REF + date + booking ID)
   - Stores all calculation details
   - Status set to 'Pending'

3. **Activity Log**:
   - Logs cancellation action
   - Records refund amount
   - Tracks user activity

### 4. Security Features
- User authentication required
- Ownership verification (user can only cancel their own bookings)
- Transaction-based database updates (rollback on error)
- Input validation and sanitization
- JSON-based API communication

### 5. User Experience Flow

```
User clicks "Cancel" button
    ↓
Modal opens with policy display
    ↓
Auto-calculates refund (1.5s delay)
    ↓
Shows detailed refund breakdown
    ↓
User clicks "Confirm Cancellation"
    ↓
Confirmation dialog appears
    ↓
Backend processes cancellation
    ↓
Success message with refund details
    ↓
Page auto-refreshes (3s)
```

### 6. Error Handling
- Booking not found
- Already cancelled
- Check-in date passed
- Network errors
- Database transaction failures
- User-friendly error messages

### 7. Responsive Design
- Mobile-optimized modal
- Touch-friendly buttons
- Readable on all screen sizes
- Bootstrap 5 components

## Technical Stack
- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Bootstrap 5
- **Backend**: PHP 8+, MySQL
- **AJAX**: Fetch API
- **Icons**: Font Awesome 6

## Files Modified/Created
1. `my-bookings.php` - Enhanced UI with modern modal
2. `api/cancel_booking.php` - Backend cancellation logic
3. `database/setup.sql` - Refunds table schema

## Testing Checklist
- [ ] Cancel booking 7+ days before check-in (95% refund)
- [ ] Cancel booking 3-6 days before (75% refund)
- [ ] Cancel booking 1-2 days before (50% refund)
- [ ] Cancel booking same day (25% refund)
- [ ] Try cancelling already cancelled booking (should fail)
- [ ] Try cancelling past check-in date (should fail)
- [ ] Verify refund record created in database
- [ ] Check activity log entry
- [ ] Test on mobile devices
- [ ] Verify email notification (if implemented)

## Future Enhancements
- [ ] Email notification to customer
- [ ] SMS notification option
- [ ] Admin approval for refunds
- [ ] Automatic refund processing integration
- [ ] Cancellation reason dropdown
- [ ] Partial cancellation (for multiple rooms)
- [ ] Rescheduling option instead of cancellation

## Support
For issues or questions, contact the development team.

---
**Last Updated**: April 21, 2026
**Version**: 1.0
