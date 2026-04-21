# 🎨 Visual Guide - Cancellation Feature

## Before & After Comparison

### ❌ BEFORE (Without Cancellation Feature)

```
┌─────────────────────────────────────────────┐
│  My Bookings                                │
├─────────────────────────────────────────────┤
│                                             │
│  Booking: HRH20260421001                    │
│  Room: Deluxe Double Room #15               │
│  Check-in: Apr 28, 2026                     │
│  Status: Confirmed                          │
│                                             │
│  [View Details]  [Print]                    │
│                                             │
└─────────────────────────────────────────────┘

❌ No way to cancel booking
❌ Customer must call hotel
❌ Manual refund calculation
❌ No transparency
```

### ✅ AFTER (With Modern Cancellation Feature)

```
┌─────────────────────────────────────────────┐
│  My Bookings                                │
├─────────────────────────────────────────────┤
│                                             │
│  Booking: HRH20260421001                    │
│  Room: Deluxe Double Room #15               │
│  Check-in: Apr 28, 2026                     │
│  Status: Confirmed                          │
│                                             │
│  [View Details]  [Print]  [Cancel] ← NEW!   │
│                                             │
└─────────────────────────────────────────────┘

✅ One-click cancellation
✅ Automatic refund calculation
✅ Transparent policy
✅ Instant processing
```

---

## 🎬 User Journey Visualization

### Step 1: Cancel Button Click
```
┌─────────────────────────────────────────────┐
│  Deluxe Double Room #15                     │
│  ✓ Verified                                 │
│                                             │
│  Reference: HRH20260421001                  │
│  Check-in: Apr 28, 2026                     │
│  Total: ETB 5,000.00                        │
│                                             │
│  [👁️ View Details]  [🖨️ Print]  [❌ Cancel] │
│                      ↑                      │
│                   CLICK HERE                │
└─────────────────────────────────────────────┘
```

### Step 2: Policy Display (1.5 seconds)
```
╔═══════════════════════════════════════════════╗
║  ⚠️ Harar Ras Hotel - Cancellation Policy    ║
╠═══════════════════════════════════════════════╣
║                                               ║
║  📋 Refund Schedule:                          ║
║                                               ║
║  ┌─────────┐  ┌─────────┐                    ║
║  │  95%    │  │  75%    │                    ║
║  │ 7+ days │  │ 3-6 days│                    ║
║  └─────────┘  └─────────┘                    ║
║                                               ║
║  ┌─────────┐  ┌─────────┐                    ║
║  │  50%    │  │  25%    │                    ║
║  │ 1-2 days│  │Same day │                    ║
║  └─────────┘  └─────────┘                    ║
║                                               ║
║  ⚠️ All refunds subject to 5% processing fee ║
║                                               ║
║  ⏳ Calculating your refund...                ║
║                                               ║
╚═══════════════════════════════════════════════╝
```

### Step 3: Refund Calculation Display
```
╔═══════════════════════════════════════════════╗
║  ⚠️ Harar Ras Hotel - Cancellation Policy    ║
╠═══════════════════════════════════════════════╣
║                                               ║
║  🧮 Your Refund Calculation                   ║
║                                               ║
║  Booking Reference: HRH20260421001            ║
║  Room: Deluxe Double Room                     ║
║  Check-in: April 28, 2026                     ║
║  Days Before: 7 days                          ║
║                                               ║
║  ─────────────────────────────────────────    ║
║                                               ║
║  Total Paid:          ETB 5,000.00            ║
║  Refund %:            95%                     ║
║  Refund Amount:       ETB 4,750.00            ║
║  Processing Fee (5%): - ETB 237.50            ║
║                                               ║
║  ╔═══════════════════════════════════════╗   ║
║  ║ 💰 Final Refund: ETB 4,512.50         ║   ║
║  ╚═══════════════════════════════════════╝   ║
║                                               ║
║  ⏰ Refund processed in 5-7 business days     ║
║                                               ║
║  [Close]  [✓ Confirm Cancellation]            ║
║                      ↑                        ║
║                   CLICK HERE                  ║
╚═══════════════════════════════════════════════╝
```

### Step 4: Confirmation Dialog
```
┌─────────────────────────────────────────────┐
│  ⚠️ Confirm Cancellation                     │
├─────────────────────────────────────────────┤
│                                             │
│  Are you sure you want to cancel this       │
│  booking?                                   │
│                                             │
│  This action cannot be undone. Your refund  │
│  will be processed according to our         │
│  cancellation policy.                       │
│                                             │
│  [Cancel]  [OK]                             │
│              ↑                              │
│           CLICK HERE                        │
└─────────────────────────────────────────────┘
```

### Step 5: Processing
```
╔═══════════════════════════════════════════════╗
║  ⚠️ Harar Ras Hotel - Cancellation Policy    ║
╠═══════════════════════════════════════════════╣
║                                               ║
║  ⏳ Processing Cancellation...                ║
║                                               ║
║  [●●●●●●●●●●] 100%                            ║
║                                               ║
║  Please wait...                               ║
║                                               ║
╚═══════════════════════════════════════════════╝
```

### Step 6: Success Message
```
╔═══════════════════════════════════════════════╗
║  ⚠️ Harar Ras Hotel - Cancellation Policy    ║
╠═══════════════════════════════════════════════╣
║                                               ║
║  ✅ Booking Cancelled Successfully!           ║
║                                               ║
║  Your booking has been cancelled and your     ║
║  refund will be processed within 5-7          ║
║  business days.                               ║
║                                               ║
║  ─────────────────────────────────────────    ║
║                                               ║
║  Refund Reference: REF20260421000001          ║
║  Refund Amount: ETB 4,512.50                  ║
║                                               ║
║  [🔄 Refresh Page]                            ║
║                                               ║
║  Auto-refreshing in 3 seconds...              ║
║                                               ║
╚═══════════════════════════════════════════════╝
```

### Step 7: Updated Booking Status
```
┌─────────────────────────────────────────────┐
│  My Bookings                                │
├─────────────────────────────────────────────┤
│                                             │
│  Booking: HRH20260421001                    │
│  Room: Deluxe Double Room #15               │
│  Check-in: Apr 28, 2026                     │
│  Status: ❌ Cancelled                        │
│  Refund: ETB 4,512.50 (Pending)             │
│                                             │
│  [View Details]  [Print]                    │
│  (Cancel button removed)                    │
│                                             │
└─────────────────────────────────────────────┘
```

---

## 🎨 Color Scheme

### Refund Percentage Badges:
```
┌─────────────────────────────────────────────┐
│                                             │
│  🟢 95% - Green (Success)                   │
│     Best refund - 7+ days before            │
│                                             │
│  🔵 75% - Blue (Info)                       │
│     Good refund - 3-6 days before           │
│                                             │
│  🟡 50% - Yellow (Warning)                  │
│     Moderate refund - 1-2 days before       │
│                                             │
│  🔴 25% - Red (Danger)                      │
│     Minimal refund - Same day               │
│                                             │
└─────────────────────────────────────────────┘
```

### Button Colors:
```
┌─────────────────────────────────────────────┐
│                                             │
│  [Cancel] - Red outline (btn-outline-danger)│
│  [Close] - Gray (btn-secondary)             │
│  [Confirm] - Solid red (btn-danger)         │
│  [Refresh] - Green (btn-success)            │
│                                             │
└─────────────────────────────────────────────┘
```

---

## 📱 Responsive Design

### Desktop View (1920px)
```
┌────────────────────────────────────────────────────────────┐
│  ⚠️ Harar Ras Hotel - Cancellation Policy                  │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │
│  │   95%        │  │   75%        │  │   50%        │    │
│  │ 7+ days      │  │ 3-6 days     │  │ 1-2 days     │    │
│  └──────────────┘  └──────────────┘  └──────────────┘    │
│                                                            │
│  Full width modal with 3 columns                          │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

### Tablet View (768px)
```
┌──────────────────────────────────────┐
│  ⚠️ Cancellation Policy               │
├──────────────────────────────────────┤
│                                      │
│  ┌──────────┐  ┌──────────┐         │
│  │  95%     │  │  75%     │         │
│  └──────────┘  └──────────┘         │
│                                      │
│  ┌──────────┐  ┌──────────┐         │
│  │  50%     │  │  25%     │         │
│  └──────────┘  └──────────┘         │
│                                      │
│  2 columns layout                    │
│                                      │
└──────────────────────────────────────┘
```

### Mobile View (375px)
```
┌────────────────────────┐
│  ⚠️ Cancel Booking      │
├────────────────────────┤
│                        │
│  ┌──────────────────┐  │
│  │      95%         │  │
│  │   7+ days        │  │
│  └──────────────────┘  │
│                        │
│  ┌──────────────────┐  │
│  │      75%         │  │
│  │   3-6 days       │  │
│  └──────────────────┘  │
│                        │
│  Single column         │
│  Stacked layout        │
│                        │
└────────────────────────┘
```

---

## 🎭 Animation Effects

### Modal Opening:
```
Fade in + Scale up
Duration: 0.3s
Easing: ease-out

[Hidden] → [Visible]
opacity: 0 → 1
transform: scale(0.9) → scale(1)
```

### Loading Spinner:
```
Rotate animation
Duration: 1s
Infinite loop

⟳ Spinning continuously
```

### Success Checkmark:
```
Scale bounce
Duration: 0.6s
Easing: cubic-bezier

✓ Bounces in with emphasis
```

### Auto-refresh Countdown:
```
Number decreases: 3... 2... 1...
Then page reloads
```

---

## 🔍 Error States

### Network Error:
```
╔═══════════════════════════════════════════════╗
║  ⚠️ Harar Ras Hotel - Cancellation Policy    ║
╠═══════════════════════════════════════════════╣
║                                               ║
║  ❌ Error                                     ║
║                                               ║
║  Network error. Please check your connection  ║
║  and try again.                               ║
║                                               ║
║  [Close]  [Retry]                             ║
║                                               ║
╚═══════════════════════════════════════════════╝
```

### Already Cancelled:
```
╔═══════════════════════════════════════════════╗
║  ⚠️ Harar Ras Hotel - Cancellation Policy    ║
╠═══════════════════════════════════════════════╣
║                                               ║
║  ❌ Error                                     ║
║                                               ║
║  This booking has already been cancelled.     ║
║                                               ║
║  [Close]                                      ║
║                                               ║
╚═══════════════════════════════════════════════╝
```

### Past Check-in Date:
```
╔═══════════════════════════════════════════════╗
║  ⚠️ Harar Ras Hotel - Cancellation Policy    ║
╠═══════════════════════════════════════════════╣
║                                               ║
║  ❌ Error                                     ║
║                                               ║
║  Cannot cancel booking after check-in date    ║
║  has passed.                                  ║
║                                               ║
║  [Close]                                      ║
║                                               ║
╚═══════════════════════════════════════════════╝
```

---

## 📊 Data Flow Diagram

```
┌─────────────┐
│   Customer  │
└──────┬──────┘
       │ Clicks Cancel
       ↓
┌─────────────────┐
│  my-bookings.php│
│  (Frontend)     │
└──────┬──────────┘
       │ AJAX Request
       │ (calculate)
       ↓
┌──────────────────────┐
│ api/cancel_booking.php│
│ (Backend)            │
└──────┬───────────────┘
       │ Query Database
       ↓
┌──────────────┐
│   Database   │
│  (bookings)  │
└──────┬───────┘
       │ Return Data
       ↓
┌──────────────────────┐
│ Calculate Refund     │
│ - Days before        │
│ - Apply policy       │
│ - Deduct fee         │
└──────┬───────────────┘
       │ Return JSON
       ↓
┌─────────────────┐
│  Display Modal  │
│  Show Breakdown │
└──────┬──────────┘
       │ User Confirms
       ↓
┌──────────────────────┐
│ AJAX Request         │
│ (confirm)            │
└──────┬───────────────┘
       │
       ↓
┌──────────────────────┐
│ Update Database      │
│ - bookings table     │
│ - refunds table      │
│ - activity log       │
└──────┬───────────────┘
       │ Success
       ↓
┌─────────────────┐
│ Show Success    │
│ Auto-refresh    │
└─────────────────┘
```

---

## 🎯 Key Visual Elements

### 1. Gradient Header
```css
background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
color: white;
```

### 2. Badge Circles
```css
width: 50px;
height: 50px;
border-radius: 50%;
display: flex;
align-items: center;
justify-content: center;
```

### 3. Final Refund Highlight
```css
background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
color: white;
padding: 1.5rem;
font-size: 1.5rem;
```

### 4. Border Accents
```css
border-left: 4px solid #007bff;
padding-left: 1rem;
```

---

## ✨ Conclusion

This visual guide shows the complete user journey from clicking the cancel button to receiving confirmation. The design is:

- ✅ Modern and professional
- ✅ Clear and intuitive
- ✅ Responsive on all devices
- ✅ Accessible and user-friendly
- ✅ Visually appealing

**The cancellation feature provides an excellent user experience!**

---

**Created:** April 21, 2026  
**Version:** 1.0
