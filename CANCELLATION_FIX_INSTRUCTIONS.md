# Cancellation System Fix - Instructions

## What Was Fixed

### 1. **Enhanced Error Logging**
- Added comprehensive error logging to `api/cancel_booking.php`
- Errors are now logged to `logs/cancel_booking_errors.log`
- Better error messages returned to the frontend

### 2. **Database Column Auto-Creation**
- Added automatic column creation in `includes/config.php`
- The following columns will be automatically added to the `bookings` table if they don't exist:
  - `cancelled_at` - When the booking was cancelled
  - `cancelled_by` - User ID who cancelled
  - `cancellation_reason` - Reason for cancellation
  - `refund_amount` - Calculated refund amount
  - `penalty_amount` - Penalty charged

### 3. **Migration Script**
- Created `database/migrate_cancellation_columns.php` for manual migration if needed
- Can be run directly to add missing columns

### 4. **Improved Success Message**
- Updated cancellation confirmation message to inform customers about:
  - Manager approval requirement
  - Expected refund timeline (5-7 business days after approval)
  - Refund amount

## How to Test

### Step 1: Wait for Render Deployment
1. Go to your Render dashboard: https://dashboard.render.com
2. Wait for the deployment to complete (usually 2-5 minutes)
3. Check the deployment logs for any errors

### Step 2: Test Cancellation
1. Login to your customer account at: https://harar-ras-hotel-booking.onrender.com/login.php
2. Go to "My Bookings" page
3. Find a **verified** booking (with future check-in date)
4. Click the **Cancel** button next to the Print button
5. Review the cancellation policy modal
6. Click **Confirm Cancellation**

### Step 3: Check for Errors
If you still get an error:
1. Open browser console (Press F12)
2. Go to the "Console" tab
3. Look for error messages
4. Copy the error message and send it to me

### Step 4: Verify Manager Dashboard
1. Login as manager/receptionist
2. Go to "Refund Management" in the dashboard
3. You should see the cancellation request with "Pending" status
4. Click "Approve" to process the refund
5. Go back to customer account and check "My Bookings"
6. The booking should show "Refunded! ETB XXX.XX" message

## Expected Behavior

### Customer View:
1. **Before Cancellation**: Booking shows "Verified" status with Cancel button
2. **After Cancellation**: 
   - Booking status changes to "Cancelled"
   - Shows refund information with "Pending" status
   - Message: "Your refund request is pending manager approval"

### Manager View:
1. **Refund Management Dashboard**: Shows pending refund with customer details
2. **After Approval**: 
   - Refund status changes to "Processed"
   - Customer sees "Refunded! ETB XXX.XX" with green badge

## Troubleshooting

### If you get HTTP 500 error:
1. Check Render logs for PHP errors
2. Run the migration script manually (if database columns are missing)
3. Check browser console for detailed error message

### If cancellation button doesn't appear:
- Make sure the booking status is "verified" or "confirmed"
- Make sure the check-in date is in the future
- Refresh the page

### If refund doesn't appear in manager dashboard:
- Check the `refunds` table in the database
- Verify the refund record was created
- Check for any database errors in Render logs

## Database Migration (If Needed)

If the automatic column creation doesn't work, run this manually:

```bash
# SSH into Render or use Railway CLI
php database/migrate_cancellation_columns.php
```

Or run these SQL commands directly in your database:

```sql
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancelled_by INT NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS refund_amount DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS penalty_amount DECIMAL(10,2) DEFAULT 0.00;
```

## Next Steps

1. **Test the cancellation flow end-to-end**
2. **Verify manager approval workflow**
3. **Check email notifications** (if configured)
4. **Test with different cancellation scenarios**:
   - 7+ days before check-in (95% refund)
   - 3-6 days before check-in (75% refund)
   - 1-2 days before check-in (50% refund)
   - Same day (25% refund)

## Files Changed

- `api/cancel_booking.php` - Enhanced error handling and logging
- `includes/config.php` - Added automatic column creation
- `database/migrate_cancellation_columns.php` - Manual migration script
- `logs/.gitkeep` - Created logs directory
- `my-bookings.php` - Already has correct cancellation UI

## Support

If you encounter any issues:
1. Check browser console (F12) for JavaScript errors
2. Check Render logs for PHP errors
3. Send me the error messages
4. I'll help you fix it immediately

---

**Status**: ✅ All changes pushed to GitHub
**Deployment**: 🔄 Waiting for Render to deploy
**Next**: Test the cancellation flow after deployment completes
