# 🏨 Harar Ras Hotel — Hotel Management System

A full-featured hotel management web application built with **PHP**, **MySQL**, **Bootstrap 5**, and **Chapa Payment Gateway**. Designed and deployed for **Harar Ras Hotel**, Harar, Ethiopia.

🌐 **Live Site:** [https://harar-ras-hotel-booking.onrender.com](https://harar-ras-hotel-booking.onrender.com)

---

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [User Roles](#user-roles)
- [Payment Flow](#payment-flow)
- [Check-in Flow](#check-in-flow)
- [Multi-Language Support](#multi-language-support)
- [Security](#security)
- [Email Notifications](#email-notifications)
- [Deployment](#deployment)
- [Environment Configuration](#environment-configuration)
- [License](#license)

---

## 🌟 Overview

Harar Ras Hotel Management System is a complete web-based solution that manages the full hotel operation cycle — from customer registration and room booking to receptionist check-in, manager reporting, and super admin control.

**Core capabilities:**
- Online room booking with Chapa payment (TeleBirr, CBE, Abyssinia, Cooperative Bank)
- Food ordering, Spa & Wellness, and Laundry service bookings
- Walk-in customer check-in by receptionist (manual form)
- Booking-based check-in for online customers
- Room availability check with double-booking prevention and 30-minute hold
- Manager reports, refund management, and staff oversight
- Multi-language support (English, Amharic, Afan Oromo)
- Real-time staff notifications for new paid bookings
- Email confirmations via Brevo SMTP (PHPMailer)
- Google OAuth for customer registration
- Deployed on Render (Docker) with Railway MySQL database

---

## ✨ Features

### 👤 Customer
- Register with email or Google OAuth
- Login / Logout with session security
- Browse rooms with images, prices, and capacity
- Browse services: food menu, spa, laundry
- Book rooms — select dates, upload ID, pay via Chapa
- Order food with table reservation option
- Book spa and laundry services
- View all bookings and order history (`My Bookings`)
- Print booking confirmation receipts
- Receive email confirmation after successful payment
- Switch language (English / Amharic / Afan Oromo)
- Update profile, change password, manage settings
- Submit customer feedback

### 🛎 Receptionist
- Dashboard with today's check-ins (Room, Food, Spa, Laundry — separated by type)
- Walk-in customer check-in form (manual — creates account automatically)
- Booking-based check-in for online customers (search by reference, name, or phone)
- Walk-in check-ins appear in Today's Check-ins on the dashboard
- Process customer check-out
- Manage rooms (view status, update)
- Manage food and services
- Real-time notification bell for new paid bookings
- Generate and print bills
- Payment verification dashboard
- View pending bookings

### 📊 Manager
- Overview dashboard with statistics
- View and manage all bookings
- Approve or reject bills
- Customer feedback management
- Refund management with printable refund receipt
- Room management
- Staff management
- Reports (bookings, revenue, occupancy)
- Payment verification

### 🔧 Admin
- Full user management (create, edit, delete, view)
- Manage rooms, services, bookings
- View all system data (bookings, users, activity logs)
- Payment verification
- System settings

### 👑 Super Admin
- All admin capabilities
- System-level settings (isolated from admin settings)
- User role management across the entire system
- Full access to all dashboards

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.x |
| Database | MySQL (MySQLi — Railway hosted) |
| Frontend | HTML5, CSS3, Bootstrap 5.3, JavaScript |
| Payment | Chapa API (TeleBirr, CBE, Abyssinia, Cooperative Bank) |
| Email | PHPMailer + Brevo SMTP |
| Icons | Font Awesome 6.5 |
| Authentication | Session-based + Google OAuth |
| Deployment | Docker on Render |
| Version Control | Git + GitHub |
| Currency | ETB (Ethiopian Birr) |
| Timezone | Africa/Addis_Ababa (UTC+3) |

---

## 📁 Project Structure

```
harar-ras-hotel/
├── api/                          # AJAX & API endpoints
│   ├── chapa/                    # Chapa payment (initiate, callback, verify)
│   │   ├── initiate.php          # Start Chapa payment session
│   │   ├── initialize.php        # Alternative initializer via service class
│   │   ├── callback.php          # Chapa webhook (silent background confirm)
│   │   └── verify.php            # Verify payment after customer redirect
│   ├── cancel_booking.php
│   ├── check_room_availability.php
│   ├── get_booking_details.php
│   ├── get_rooms.php
│   ├── get_services.php
│   ├── notifications.php
│   ├── room_lock_api.php
│   ├── serve_id_image.php
│   ├── staff_notifications.php
│   ├── submit_payment.php
│   ├── switch_language.php
│   ├── upload_id.php
│   └── verify_payment.php
│
├── assets/
│   ├── css/
│   │   ├── style.css             # Main site styles
│   │   └── print.css             # Print-only styles (receipts, check-in forms)
│   ├── images/                   # Hotel, room, food images
│   └── js/
│       ├── main.js
│       ├── room-booking-queue.js
│       └── star-rating.js
│
├── config/
│   └── database.php              # MySQLi connection
│
├── dashboard/                    # Staff dashboards
│   ├── receptionist.php          # Receptionist main dashboard
│   ├── receptionist-checkin.php  # Booking-based check-in
│   ├── receptionist-checkout.php # Check-out processing
│   ├── receptionist-pending.php  # Pending bookings
│   ├── receptionist-rooms.php    # Room management
│   ├── receptionist-services.php # Services management
│   ├── customer-checkin.php      # Walk-in check-in form
│   ├── checkin-details.php       # Printable check-in record
│   ├── manager.php               # Manager dashboard
│   ├── manager-bookings.php
│   ├── manager-reports.php
│   ├── manager-refund.php
│   ├── manager-refund-management.php
│   ├── manager-payment-verification.php
│   ├── manager-rooms.php
│   ├── manager-staff.php
│   ├── manager-feedback.php
│   ├── admin.php                 # Admin dashboard
│   ├── manage-bookings.php
│   ├── manage-rooms.php
│   ├── manage-services.php
│   ├── manage-users.php
│   ├── super-admin.php           # Super Admin dashboard
│   ├── super-admin-users.php
│   ├── super-admin-settings.php
│   ├── payment-verification.php
│   ├── verify-id.php
│   ├── view-booking.php
│   ├── view-data.php
│   └── reports.php
│
├── database/
│   └── setup.sql                 # Full database schema (all tables)
│
├── includes/
│   ├── auth.php                  # Authentication & role guards
│   ├── config.php                # App config, loads .env, auto DB setup
│   ├── functions.php             # Helper functions
│   ├── language.php              # Multi-language system
│   ├── Mailer.php                # PHPMailer wrapper
│   ├── navbar.php                # Global navigation bar
│   ├── footer.php                # Global footer
│   ├── image_helper.php          # Image path helpers
│   ├── payment-component.php     # Reusable payment UI component
│   ├── RoomLockManager.php       # Room hold/lock management
│   ├── session_security.php      # Session security helpers
│   ├── phpmailer/                # PHPMailer library
│   └── services/
│       ├── ChapaPaymentService.php
│       ├── EmailService.php
│       ├── EmailTemplates.php
│       ├── GoogleOAuthService.php
│       ├── NotificationService.php
│       ├── PaymentGatewayService.php
│       ├── PaymentVerificationService.php
│       ├── RefundService.php
│       └── RoomAvailabilityService.php
│
├── languages/
│   ├── en.php                    # English translations
│   ├── am.php                    # Amharic translations
│   └── om.php                    # Afan Oromo translations
│
├── uploads/
│   ├── ids/                      # Customer ID images (secured)
│   └── profiles/                 # Profile photos
│
├── index.php                     # Home page
├── about.php                     # About page
├── rooms.php                     # Rooms listing
├── services.php                  # Services (food, spa, laundry)
├── booking.php                   # Room booking form
├── booking-confirmation.php      # Post-booking confirmation
├── booking-details.php           # Booking detail + print receipt
├── food-booking.php              # Food order form
├── spa-booking.php               # Spa booking form
├── laundry-booking.php           # Laundry booking form
├── payment-upload.php            # Chapa payment page
├── payment-transaction.php       # Payment transaction handler
├── payment-verification.php      # Payment verification page
├── payment-success.php           # Payment success page
├── chapa-return.php              # Chapa redirect return handler
├── my-bookings.php               # Customer bookings history
├── cart.php                      # Cart page
├── contact.php                   # Contact page with map
├── login.php                     # Login page
├── register.php                  # Registration page
├── logout.php                    # Logout handler
├── profile.php                   # Customer profile
├── settings.php                  # Customer settings
├── notifications.php             # Customer notifications
├── forgot-password.php           # Password reset request
├── reset-password.php            # Password reset form
├── oauth-callback.php            # Google OAuth callback
├── generate_bill.php             # Bill generation
├── receipt.php                   # Printable receipt
├── refund.php                    # Refund request page
├── customer-feedback.php         # Feedback form
├── health.php                    # Server health check
├── sitemap.xml                   # Google Search Console sitemap
├── Dockerfile                    # Docker deployment config
├── render.yaml                   # Render deployment config
├── .env                          # Environment variables (not committed)
└── .env.example                  # Environment template
```

---

## 👥 User Roles

| Role | Access |
|---|---|
| `customer` | Register, browse, book rooms/services, pay, view history |
| `receptionist` | Check-in/out, walk-in check-in, manage rooms & services, notifications |
| `manager` | Reports, refunds, staff, bookings, feedback, payment verification |
| `admin` | Full hotel management, user management, settings |
| `super_admin` | System-level control, all admin features, role management |

---

## 💳 Payment Flow

```
Customer selects room + dates
         ↓
Upload ID document
         ↓
Click "Book Now" → booking created (status: pending)
         ↓
Redirect to payment-upload.php
         ↓
Click "Pay with Chapa"
         ↓
api/chapa/initiate.php → calls Chapa API → gets checkout_url
         ↓
Customer redirected to Chapa payment page
(TeleBirr / CBE / Abyssinia / Cooperative Bank)
         ↓
Customer completes payment
         ↓
    ┌────────────────────────────────┐
    │                                │
api/chapa/callback.php        api/chapa/verify.php
(Chapa calls silently)        (Customer redirected back)
    │                                │
Both verify with Chapa API independently
Both update booking → paid + verified
Both send confirmation email
    │                                │
    └────────────────────────────────┘
         ↓
Booking confirmed ✅ — Email sent to customer
```

---

## 🏨 Check-in Flow

### Online Booking Check-in
```
Receptionist searches booking (reference / name / phone)
         ↓
Booking found → check-in form displayed
         ↓
Fill: customer name, email, phone, ID type/number, room key, deposit
         ↓
Click "Complete Check-in"
         ↓
bookings table → status = checked_in
rooms table    → status = occupied
checkin_checkout_log → action recorded
         ↓
Success message displayed ✅
```

### Walk-in Customer Check-in
```
Receptionist fills walk-in form
(hotel info, guest info, room, payment)
         ↓
System looks up email in users table
If not found → creates new customer account automatically
         ↓
checkins table → new record inserted
rooms table    → status = occupied
         ↓
Confirmation number generated (CHK-YYYYMMDD-XXXXXX)
Walk-in appears in Today's Check-ins on dashboard ✅
```

---

## 🌍 Multi-Language Support

| Code | Language |
|---|---|
| `en` | English (default) |
| `am` | አማርኛ — Amharic |
| `om` | Afaan Oromoo — Afan Oromo |

- Language preference saved per user in the database
- Switchable from the user profile menu
- All UI text translated via `languages/` files

---

## 🔐 Security

- Session-based authentication with role guards on every protected page
- `require_auth_role()` enforced on all dashboard pages
- Prepared statements (MySQLi) throughout — SQL injection prevention
- `htmlspecialchars()` on all output — XSS prevention
- Cache-control headers — prevents back-button access after logout
- `.htaccess` blocks direct access to `uploads/`, `.env`, `.sql` files
- PHP execution blocked inside `uploads/` and `assets/images/` folders
- ID images served only through `api/serve_id_image.php` (not directly)
- Room double-booking prevented via `SELECT FOR UPDATE` transaction lock
- 30-minute booking hold — auto-expires unpaid bookings

---

## 📧 Email Notifications

Sent automatically after successful Chapa payment via **Brevo SMTP**:

| Booking Type | Email Content |
|---|---|
| Room Booking | Booking reference, room name, check-in/out dates, total |
| Food Order | Items ordered, reservation date/time, number of guests |
| Spa & Wellness | Service name, date, time |
| Laundry Service | Service name, collection date/time |

---

## 🔔 Staff Notifications

Receptionists receive real-time bell notifications when a customer completes a Chapa payment:
- Booking type (Room / Food / Spa / Laundry)
- Customer name and email
- Service details and amount
- Time elapsed since payment

---

## 🚀 Deployment

The system is deployed using **Docker on Render** with a **Railway MySQL** database.

| Service | Provider |
|---|---|
| Web Server | Render (Docker container) |
| Database | Railway (MySQL) |
| Email | Brevo SMTP |
| Payment | Chapa API |
| Domain | https://harar-ras-hotel-booking.onrender.com |

---

## 🔧 Environment Configuration

Copy `.env.example` to `.env` and fill in:

```env
# Database (Railway)
DB_HOST=monorail.proxy.rlwy.net
DB_PORT=39882
DB_USER=root
DB_PASS=your_password
DB_NAME=railway

# Site
SITE_URL=https://harar-ras-hotel-booking.onrender.com
SITE_NAME=Harar Ras Hotel

# Chapa Payment
CHAPA_PUBLIC_KEY=CHAPUBK_TEST-...
CHAPA_SECRET_KEY=CHASECK_TEST-...
CHAPA_BASE_URL=https://api.chapa.co/v1
CHAPA_CALLBACK_URL=https://harar-ras-hotel-booking.onrender.com/api/chapa/callback.php
CHAPA_RETURN_URL=https://harar-ras-hotel-booking.onrender.com/chapa-return.php

# Email (Brevo SMTP)
EMAIL_ENABLED=true
EMAIL_HOST=smtp-relay.brevo.com
EMAIL_PORT=587
EMAIL_ENCRYPTION=tls
EMAIL_USERNAME=your-brevo-smtp-user
EMAIL_PASSWORD=your-brevo-smtp-password
EMAIL_FROM_ADDRESS=info@hararrashotel.com
EMAIL_FROM_NAME=Harar Ras Hotel

# Google OAuth
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=https://harar-ras-hotel-booking.onrender.com/oauth-callback.php

# App
APP_ENV=production
TIMEZONE=Africa/Addis_Ababa
CURRENCY_SYMBOL=ETB
```

---

## 📄 License

This project is developed for **Harar Ras Hotel**, Harar, Ethiopia.

© 2026 Harar Ras Hotel. All rights reserved.

---

## 👨‍💻 Developers

**Group 10 — 4th Year Information System Department Students**
- GitHub: [https://github.com/Abdulmalik-73/final-project2](https://github.com/Abdulmalik-73/final-project2)
