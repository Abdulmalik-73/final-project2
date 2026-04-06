# Chapa Payment Gateway Integration Guide

## Overview
Chapa payment gateway has been successfully integrated into Harar Ras Hotel Management System. Customers can now pay online using Mobile Money, Bank Transfer, or Cards.

## Features
- ✅ Direct online payment through Chapa
- ✅ Automatic payment verification
- ✅ Support for TeleBirr, M-Pesa, and all Ethiopian banks
- ✅ Card payments (Visa, Mastercard)
- ✅ Secure payment processing
- ✅ Automatic booking confirmation
- ✅ Manual payment option still available as backup

## Setup Instructions

### 1. Get Chapa API Keys
1. Go to https://dashboard.chapa.co
2. Sign up or login to your account
3. Navigate to Settings → API
4. Copy your:
   - Public Key (starts with CHAPUBK_)
   - Secret Key (starts with CHASECK_)
   - Encryption Key (optional)

### 2. Configure Environment Variables
Edit your `.env` file and add:

```env
# Chapa Payment Gateway (Ethiopia)
CHAPA_SECRET_KEY=CHASECK_TEST-your-secret-key-here
CHAPA_PUBLIC_KEY=CHAPUBK_TEST-BmIEcz0fI4qYnakEAkGA6cJ7Ya3k3IHqQ
CHAPA_ENCRYPTION_KEY=your-encryption-key-here
CHAPA_TEST_MODE=true
CHAPA_CALLBACK_URL=http://localhost/final-project2/api/chapa/callback.php
CHAPA_RETURN_URL=http://localhost/final-project2/payment-success.php
CHAPA_WEBHOOK_URL=http://localhost/final-project2/api/chapa/webhook.php
```

### 3. Update URLs for Production
When deploying to production, update these URLs in `.env`:

```env
CHAPA_TEST_MODE=false
CHAPA_CALLBACK_URL=https://yourdomain.com/api/chapa/callback.php
CHAPA_RETURN_URL=https://yourdomain.com/payment-success.php
CHAPA_WEBHOOK_URL=https://yourdomain.com/api/chapa/webhook.php
```

### 4. Test the Integration

#### Test Mode (Using Test Keys)
1. Make a booking
2. Click "Pay Now with Chapa" button
3. Use Chapa test credentials:
   - Test Card: 4200 0000 0000 0000
   - CVV: Any 3 digits
   - Expiry: Any future date

#### Live Mode
1. Set `CHAPA_TEST_MODE=false`
2. Use your live API keys
3. Real payments will be processed

## How It Works

### Payment Flow
1. Customer makes a booking
2. On payment page, customer sees two options:
   - **Pay Online with Chapa** (Recommended)
   - Manual payment with transaction ID upload
3. Customer clicks "Pay Now"
4. System initializes payment with Chapa API
5. Customer is redirected to Chapa checkout page
6. Customer completes payment using:
   - Mobile Money (TeleBirr, M-Pesa)
   - Bank Transfer
   - Card (Visa/Mastercard)
7. Chapa processes payment
8. Customer is redirected back to hotel website
9. System verifies payment automatically
10. Booking is confirmed instantly
11. Room status changes to "booked"

### Files Created
- `includes/services/ChapaPaymentService.php` - Main Chapa service class
- `api/chapa/initialize.php` - Payment initialization endpoint
- `api/chapa/callback.php` - Payment verification handler
- `payment-success.php` - Success page after payment
- Updated `payment-upload.php` - Added Chapa payment button

## API Endpoints

### Initialize Payment
**POST** `/api/chapa/initialize.php`

Request:
```json
{
  "booking_id": 123
}
```

Response:
```json
{
  "success": true,
  "checkout_url": "https://checkout.chapa.co/...",
  "tx_ref": "HRH-1234567890-abc123"
}
```

### Callback (Automatic)
**GET** `/api/chapa/callback.php?tx_ref=HRH-1234567890-abc123`

This is called automatically by Chapa after payment.

## Testing

### Test Credentials
- **Test Card Number**: 4200 0000 0000 0000
- **CVV**: Any 3 digits
- **Expiry**: Any future date
- **OTP**: 123456

### Test Scenarios
1. ✅ Successful Payment
2. ✅ Failed Payment
3. ✅ Pending Payment
4. ✅ Cancelled Payment

## Troubleshooting

### Payment Not Working
1. Check if API keys are correct in `.env`
2. Verify callback URL is accessible
3. Check error logs in browser console
4. Ensure CURL is enabled in PHP

### Payment Verified But Booking Not Updated
1. Check database connection
2. Verify callback.php has write permissions
3. Check PHP error logs

### Chapa Button Not Showing
1. Verify ChapaPaymentService.php is loaded
2. Check if `isConfigured()` returns true
3. Ensure JavaScript is not blocked

## Security Notes
- ✅ All API calls use HTTPS in production
- ✅ Secret keys are stored in environment variables
- ✅ Payment verification is done server-side
- ✅ Transaction references are unique and secure
- ✅ No sensitive data is stored in frontend

## Support
For Chapa-specific issues:
- Email: support@chapa.co
- Telegram: @chapasupport
- Documentation: https://developer.chapa.co

## Next Steps
1. Get your live API keys from Chapa
2. Update `.env` with live keys
3. Set `CHAPA_TEST_MODE=false`
4. Test with real payment
5. Monitor transactions in Chapa dashboard

---

**Integration Status**: ✅ Complete
**Last Updated**: <?php echo date('Y-m-d'); ?>
