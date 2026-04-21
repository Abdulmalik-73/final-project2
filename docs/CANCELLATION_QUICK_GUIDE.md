# Cancellation Feature - Quick Reference Guide

## 🎯 Quick Overview

Modern booking cancellation system with automatic refund calculation for Harar Ras Hotel.

## 📋 Cancellation Policy

| Days Before Check-in | Refund Percentage | Example (ETB 5,000) |
|---------------------|-------------------|---------------------|
| 7+ days             | 95%               | ETB 4,750.00        |
| 3-6 days            | 75%               | ETB 3,750.00        |
| 1-2 days            | 50%               | ETB 2,500.00        |
| Same day            | 25%               | ETB 1,250.00        |

**Note:** All refunds subject to 5% processing fee

## 🚀 How to Cancel a Booking

### For Customers:

1. **Login** to your account
2. Go to **"My Bookings"** page
3. Find the booking you want to cancel
4. Click the **"Cancel"** button (red button with X icon)
5. Review the **cancellation policy** in the modal
6. Wait for **automatic refund calculation** (1.5 seconds)
7. Review your **refund breakdown**
8. Click **"Confirm Cancellation"**
9. Confirm in the dialog box
10. **Success!** Your refund will be processed in 5-7 business days

### Cancel Button Visibility:

✅ **Visible when:**
- Booking status: pending, confirmed, verified, pending_payment, pending_verification
- Check-in date is in the future
- Not a food order

❌ **Hidden when:**
- Booking already cancelled
- Check-in date has passed
- Booking is checked out or no-show
- Food order (different cancellation policy)

## 💻 Technical Implementation

### Frontend Files:
- `my-bookings.php` - Main booking list page with cancel button and modal

### Backend Files:
- `api/cancel_booking.php` - Cancellation API endpoint

### Database Tables:
- `bookings` - Updated with cancellation details
- `refunds` - New refund record created
- `user_activity` - Activity logged

## 🔧 API Endpoints

### Calculate Refund
```javascript
POST /api/cancel_booking.php
{
    "booking_reference": "HRH20260421001",
    "action": "calculate"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "booking_reference": "HRH20260421001",
        "room_name": "Deluxe Double Room",
        "check_in_date": "2026-04-28",
        "total_amount": "5000.00",
        "days_before_checkin": 7,
        "refund_percentage": 95,
        "refund_amount": "4750.00",
        "processing_fee": "237.50",
        "final_refund": "4512.50"
    }
}
```

### Confirm Cancellation
```javascript
POST /api/cancel_booking.php
{
    "booking_reference": "HRH20260421001",
    "action": "confirm"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Booking cancelled successfully. Your refund will be processed within 5-7 business days.",
    "data": {
        "refund_reference": "REF20260421000001",
        "final_refund": "4512.50"
    }
}
```

## 🎨 UI Components

### Modal Structure:
1. **Header** - Red gradient with warning icon
2. **Policy Card** - Visual refund schedule with badges
3. **Loading State** - Spinner with message
4. **Refund Calculation** - Detailed breakdown
5. **Success Message** - Confirmation with refund details
6. **Footer** - Close and Confirm buttons

### Color Coding:
- 🟢 **Green (95%)** - Best refund
- 🔵 **Blue (75%)** - Good refund
- 🟡 **Yellow (50%)** - Moderate refund
- 🔴 **Red (25%)** - Minimal refund

## 🔒 Security Features

- ✅ User authentication required
- ✅ Ownership verification
- ✅ Transaction-based updates
- ✅ Input validation
- ✅ SQL injection prevention
- ✅ XSS protection

## 📱 Responsive Design

- ✅ Mobile-optimized modal
- ✅ Touch-friendly buttons
- ✅ Readable on all screens
- ✅ Bootstrap 5 responsive grid

## 🐛 Error Handling

### Common Errors:
- "Booking not found or access denied"
- "This booking has already been cancelled"
- "Cannot cancel booking after check-in date has passed"
- "Cannot cancel a no-show booking"
- "Cannot cancel a completed booking"

### Network Errors:
- Connection timeout
- Server error
- Invalid response

## 📊 Database Schema

### bookings table updates:
```sql
status = 'cancelled'
cancelled_at = NOW()
cancelled_by = user_id
refund_amount = calculated_amount
penalty_amount = deducted_amount
payment_status = 'refund_pending'
```

### refunds table insert:
```sql
booking_id, booking_reference, customer_id,
original_amount, refund_percentage, refund_amount,
processing_fee, final_refund, refund_status,
refund_reference, created_at
```

## 🧪 Testing Scenarios

1. ✅ Cancel 7+ days before (95% refund)
2. ✅ Cancel 3-6 days before (75% refund)
3. ✅ Cancel 1-2 days before (50% refund)
4. ✅ Cancel same day (25% refund)
5. ❌ Try cancelling already cancelled booking
6. ❌ Try cancelling past check-in date
7. ✅ Verify database updates
8. ✅ Check activity log
9. ✅ Test on mobile devices

## 📞 Support

For technical issues or questions:
- Check error messages in modal
- Review browser console for JavaScript errors
- Check PHP error logs for backend issues
- Contact development team

## 🔄 Future Enhancements

- [ ] Email notifications
- [ ] SMS notifications
- [ ] Admin approval workflow
- [ ] Automatic refund processing
- [ ] Cancellation reason selection
- [ ] Partial cancellation
- [ ] Rescheduling option

---

**Version:** 1.0  
**Last Updated:** April 21, 2026  
**Maintained by:** Harar Ras Hotel Development Team
