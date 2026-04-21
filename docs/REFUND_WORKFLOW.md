# 🔄 Complete Refund Workflow - Harar Ras Hotel

## Overview
Complete end-to-end refund workflow from customer cancellation to manager approval and final refund display.

---

## 📊 Workflow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    CUSTOMER SIDE                            │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  Step 1: Customer Clicks "Cancel" Button                   │
│  Location: my-bookings.php                                  │
│  Action: Opens cancellation modal                           │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  Step 2: System Calculates Refund                          │
│  API: api/cancel_booking.php (action: calculate)           │
│  - Checks days before check-in                              │
│  - Applies cancellation policy                              │
│  - Calculates refund percentage                             │
│  - Deducts 5% processing fee                                │
│  - Shows: "You will receive XXX ETB"                        │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  Step 3: Customer Confirms Cancellation                    │
│  API: api/cancel_booking.php (action: confirm)             │
│  Database Updates:                                          │
│  - bookings.status = 'cancelled'                            │
│  - bookings.cancelled_at = NOW()                            │
│  - bookings.payment_status = 'refund_pending'               │
│  - Creates record in refunds table                          │
│  - refunds.refund_status = 'Pending'                        │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  Step 4: Customer Sees Pending Status                      │
│  Location: my-bookings.php                                  │
│  Display:                                                   │
│  ⚠️ Refund Status: Pending                                  │
│  💰 Refund Amount: ETB XXX.XX                               │
│  ⏰ "Your refund request is pending manager approval"       │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                    MANAGER SIDE                             │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  Step 5: Manager Views Refund Request                      │
│  Location: dashboard/manager-refund-management.php          │
│  Display:                                                   │
│  - Refund Reference                                         │
│  - Booking Reference                                        │
│  - Customer Details                                         │
│  - Cancellation Date                                        │
│  - Days Before Check-in                                     │
│  - Original Amount                                          │
│  - Refund Percentage                                        │
│  - Final Refund Amount                                      │
│  - Status: Pending                                          │
│  Actions: [Approve] [Reject]                                │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  Step 6: Manager Approves Refund                           │
│  Action: Clicks "Approve" button                           │
│  Database Updates:                                          │
│  - refunds.refund_status = 'Processed'                      │
│  - refunds.processed_date = NOW()                           │
│  - refunds.processed_by = manager_id                        │
│  - bookings.payment_status = 'refunded'                     │
│  - bookings.refund_amount = final_refund                    │
│  - bookings.refunded_at = NOW()                             │
│  - Logs activity in user_activity table                     │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  Step 7: Staff Processes Payment                           │
│  Action: Manual bank transfer/payment processing           │
│  - Manager confirms refund approved                         │
│  - Finance team processes payment                           │
│  - Money sent to customer's original payment method         │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  Step 8: Customer Sees "Refunded" Status                   │
│  Location: my-bookings.php                                  │
│  Display:                                                   │
│  ✅ Refund Status: Processed                                │
│  💰 Refund Amount: ETB XXX.XX                               │
│  📅 Processed Date: MMM DD, YYYY                            │
│  ✅ "Refunded! Your refund of ETB XXX.XX has been          │
│     processed and will be credited to your original         │
│     payment method within 5-7 business days."               │
│  📋 Refund Reference: REFXXXXXXXXXX                         │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 Detailed Step-by-Step Process

### **CUSTOMER SIDE**

#### Step 1: Initiate Cancellation
**Location:** `my-bookings.php`

**User Action:**
1. Customer logs in
2. Goes to "My Bookings" page
3. Finds booking to cancel
4. Clicks red "Cancel" button

**System Response:**
- Opens modern cancellation modal
- Shows cancellation policy
- Displays refund schedule (95%, 75%, 50%, 25%)

#### Step 2: View Refund Calculation
**API:** `api/cancel_booking.php` (action: calculate)

**Calculation Logic:**
```php
// Days before check-in
$days_before = check_in_date - current_date

// Refund percentage
if ($days_before >= 7) {
    $refund_percentage = 95;
} elseif ($days_before >= 3 && $days_before <= 6) {
    $refund_percentage = 75;
} elseif ($days_before >= 1 && $days_before <= 2) {
    $refund_percentage = 50;
} elseif ($days_before === 0) {
    $refund_percentage = 25;
}

// Calculate amounts
$refund_amount = total_price * (refund_percentage / 100);
$processing_fee = refund_amount * 0.05; // 5%
$final_refund = refund_amount - processing_fee;
```

**Display:**
```
┌─────────────────────────────────────────┐
│  Your Refund Calculation                │
├─────────────────────────────────────────┤
│  Total Paid:          ETB 5,000.00      │
│  Refund %:            95%               │
│  Refund Amount:       ETB 4,750.00      │
│  Processing Fee (5%): - ETB 237.50      │
│  ─────────────────────────────────────  │
│  💰 Final Refund:     ETB 4,512.50      │
└─────────────────────────────────────────┘
```

#### Step 3: Confirm Cancellation
**API:** `api/cancel_booking.php` (action: confirm)

**Database Updates:**
```sql
-- Update bookings table
UPDATE bookings 
SET status = 'cancelled',
    cancelled_at = NOW(),
    cancelled_by = user_id,
    cancellation_reason = 'Customer initiated cancellation',
    payment_status = 'refund_pending'
WHERE id = booking_id;

-- Insert into refunds table
INSERT INTO refunds (
    booking_id, booking_reference, customer_id,
    original_amount, refund_percentage, refund_amount,
    processing_fee, final_refund, refund_status,
    refund_reference, created_at
) VALUES (...);
```

**Success Message:**
```
✅ Booking Cancelled Successfully!

Your booking has been cancelled and your refund 
will be processed within 5-7 business days.

Refund Reference: REF20260421000001
Refund Amount: ETB 4,512.50
```

#### Step 4: View Pending Status
**Location:** `my-bookings.php`

**Display in Booking Card:**
```
┌─────────────────────────────────────────┐
│  Booking: HRH20260421001                │
│  Status: ❌ Cancelled                    │
├─────────────────────────────────────────┤
│  ℹ️ Refund Status                        │
│                                         │
│  Status: ⚠️ Pending                      │
│  Refund Amount: ETB 4,512.50            │
│                                         │
│  ⏰ Your refund request is pending      │
│     manager approval. You will be       │
│     notified once it's processed.       │
│                                         │
│  Refund Reference: REF20260421000001    │
└─────────────────────────────────────────┘
```

---

### **MANAGER SIDE**

#### Step 5: View Refund Requests
**Location:** `dashboard/manager-refund-management.php`

**Dashboard Display:**
```
┌─────────────────────────────────────────────────────────────┐
│  📊 Refund Management Dashboard                             │
├─────────────────────────────────────────────────────────────┤
│  Statistics:                                                │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐      │
│  │ Total: 15│ │Pending: 3│ │Processed:│ │Rejected: │      │
│  │          │ │ETB 12,500│ │    10    │ │    2     │      │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘      │
├─────────────────────────────────────────────────────────────┤
│  Recent Refunds:                                            │
│                                                             │
│  Refund Ref    | Booking Ref  | Customer | Amount | Status │
│  ─────────────────────────────────────────────────────────│
│  REF20260421001| HRH20260421  | John Doe | 4,512  |⚠️Pending│
│                                                   [Approve] │
│                                                   [Reject]  │
└─────────────────────────────────────────────────────────────┘
```

**Refund Details:**
- Refund Reference: REF20260421000001
- Booking Reference: HRH20260421001
- Customer: John Doe (john@example.com)
- Check-in Date: Apr 28, 2026
- Cancelled Date: Apr 21, 2026
- Days Before: 7 days
- Original Amount: ETB 5,000.00
- Refund %: 95%
- Final Refund: ETB 4,512.50
- Status: Pending

#### Step 6: Approve Refund
**Action:** Manager clicks "Approve" button

**Approval Modal:**
```
┌─────────────────────────────────────────┐
│  Process Refund                         │
├─────────────────────────────────────────┤
│  Admin Notes:                           │
│  ┌─────────────────────────────────┐   │
│  │ Refund approved. Customer       │   │
│  │ cancelled 7 days before check-in│   │
│  └─────────────────────────────────┘   │
│                                         │
│  ℹ️ This will approve the refund and    │
│     mark it as processed. The customer  │
│     will be notified.                   │
│                                         │
│  [Cancel]  [✓ Approve Refund]           │
└─────────────────────────────────────────┘
```

**Database Updates:**
```sql
-- Update refunds table
UPDATE refunds 
SET refund_status = 'Processed',
    processed_date = NOW(),
    processed_by = manager_id,
    admin_notes = 'Refund approved...'
WHERE id = refund_id;

-- Update bookings table
UPDATE bookings 
SET payment_status = 'refunded',
    refund_amount = 4512.50,
    refunded_at = NOW()
WHERE id = booking_id;

-- Log activity
INSERT INTO user_activity (
    user_id, activity_type, description
) VALUES (
    manager_id, 
    'refund_approved',
    'Refund approved for booking HRH20260421001. Amount: ETB 4,512.50'
);
```

**Success Message:**
```
✅ Refund approved and processed successfully!
   Amount: ETB 4,512.50
```

#### Step 7: Staff Processes Payment
**Manual Process:**

1. **Finance Team Receives Notification**
   - Manager approval triggers notification
   - Finance team reviews refund details

2. **Payment Processing**
   - Check original payment method
   - Process bank transfer/card refund
   - Update internal records

3. **Confirmation**
   - Verify payment sent
   - Update any additional tracking systems

---

### **CUSTOMER SIDE (FINAL)**

#### Step 8: View "Refunded" Status
**Location:** `my-bookings.php`

**Display in Booking Card:**
```
┌─────────────────────────────────────────┐
│  Booking: HRH20260421001                │
│  Status: ❌ Cancelled                    │
├─────────────────────────────────────────┤
│  ℹ️ Refund Status                        │
│                                         │
│  Status: ✅ Processed                    │
│  Refund Amount: ETB 4,512.50            │
│  Processed Date: Apr 21, 2026 2:30 PM  │
│                                         │
│  ✅ Refunded! Your refund of            │
│     ETB 4,512.50 has been processed     │
│     and will be credited to your        │
│     original payment method within      │
│     5-7 business days.                  │
│                                         │
│  Refund Reference: REF20260421000001    │
└─────────────────────────────────────────┘
```

---

## 📋 Database Schema

### bookings table
```sql
status VARCHAR(50)           -- 'cancelled'
cancelled_at DATETIME        -- Cancellation timestamp
cancelled_by INT             -- User ID who cancelled
payment_status VARCHAR(50)   -- 'refund_pending' → 'refunded'
refund_amount DECIMAL(10,2)  -- Final refund amount
refunded_at DATETIME         -- When refund was processed
```

### refunds table
```sql
id INT PRIMARY KEY
booking_id INT
booking_reference VARCHAR(50)
customer_id INT
customer_name VARCHAR(255)
customer_email VARCHAR(255)
original_amount DECIMAL(10,2)
check_in_date DATE
cancellation_date DATETIME
days_before_checkin INT
refund_percentage INT
refund_amount DECIMAL(10,2)
processing_fee DECIMAL(10,2)
processing_fee_percentage DECIMAL(5,2)
final_refund DECIMAL(10,2)
refund_status VARCHAR(50)    -- 'Pending', 'Processed', 'Rejected'
refund_reference VARCHAR(50)
processed_date DATETIME
processed_by INT
admin_notes TEXT
created_at DATETIME
```

---

## 🎨 Status Display Colors

### Customer View (my-bookings.php)
```
Pending:   ⚠️ Yellow badge  (bg-warning)
Processed: ✅ Green badge   (bg-success)
Rejected:  ❌ Red badge     (bg-danger)
```

### Manager View (manager-refund-management.php)
```
Pending:   ⚠️ Yellow badge  (bg-warning)
Processed: ✅ Green badge   (bg-success)
Rejected:  ❌ Red badge     (bg-danger)
```

---

## 🔔 Notifications (Future Enhancement)

### Email Notifications:
1. **Customer - Cancellation Confirmed**
   - Subject: "Booking Cancelled - Refund Pending"
   - Content: Cancellation details, refund amount, reference number

2. **Manager - New Refund Request**
   - Subject: "New Refund Request - Action Required"
   - Content: Booking details, refund amount, approval link

3. **Customer - Refund Approved**
   - Subject: "Refund Processed - ETB XXX.XX"
   - Content: Refund details, processing timeline, reference number

4. **Customer - Refund Rejected**
   - Subject: "Refund Request Update"
   - Content: Rejection reason, contact information

---

## ✅ Testing Checklist

### Customer Flow:
- [ ] Cancel booking 7+ days before (95% refund)
- [ ] Cancel booking 3-6 days before (75% refund)
- [ ] Cancel booking 1-2 days before (50% refund)
- [ ] Cancel booking same day (25% refund)
- [ ] View pending refund status
- [ ] View processed refund status
- [ ] View rejected refund status

### Manager Flow:
- [ ] View pending refunds list
- [ ] Approve refund
- [ ] Reject refund
- [ ] Add admin notes
- [ ] View refund statistics
- [ ] Search refunds by reference
- [ ] Filter by status

### Database:
- [ ] Verify refund record created
- [ ] Verify booking status updated
- [ ] Verify payment status updated
- [ ] Verify activity logged
- [ ] Verify refund amount stored

---

## 🚀 Deployment

### Files Modified:
1. `my-bookings.php` - Added refund status display
2. `dashboard/manager-refund-management.php` - Enhanced approval process
3. `api/cancel_booking.php` - Already working

### Database:
- Ensure `refunds` table exists
- Ensure `bookings` table has refund columns
- Verify foreign key relationships

---

**Version:** 2.0  
**Last Updated:** April 21, 2026  
**Status:** ✅ Complete with Manager Approval Workflow
