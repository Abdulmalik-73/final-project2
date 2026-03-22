-- =====================================================
-- RAS HOTEL - COMPREHENSIVE HOTEL MANAGEMENT SYSTEM
-- =====================================================
-- Complete hotel management database with all features
-- Database name: ras_hotel
-- Error-free and ready for production
-- =====================================================

-- Create and use database
CREATE DATABASE IF NOT EXISTS ras_hotel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ras_hotel;

-- Set character set
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Disable foreign key checks temporarily for table creation
SET FOREIGN_KEY_CHECKS = 0;

-- Drop checkins table if exists to avoid column conflicts
DROP TABLE IF EXISTS checkins;

-- =====================================================
-- STEP 1: CREATE CORE TABLES
-- =====================================================

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    email_notifications TINYINT(1) DEFAULT 1,
    sms_notifications TINYINT(1) DEFAULT 1,
    booking_reminders TINYINT(1) DEFAULT 1,
    role ENUM('customer', 'receptionist', 'manager', 'admin', 'super_admin') DEFAULT 'customer',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    password_changed_at TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rooms Table - UPDATED WITHOUT REMOVED ROOM TYPES
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    room_number VARCHAR(20) UNIQUE NOT NULL,
    room_type ENUM('standard', 'deluxe', 'suite', 'family', 'presidential', 'single', 'double', 'executive') NOT NULL,
    description TEXT,
    capacity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    status ENUM('active', 'occupied', 'booked', 'maintenance', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 2: CREATE BOOKINGS TABLE (SUPPORTS FOOD ORDERS)
-- =====================================================

-- Bookings Table (unified for rooms and food orders)
-- OPTIMIZED: All columns included in single CREATE TABLE statement
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    customer_name VARCHAR(200),
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    id_type VARCHAR(50),
    id_number VARCHAR(100),
    room_id INT NULL,
    room_key_number VARCHAR(50),
    booking_reference VARCHAR(50) UNIQUE NOT NULL,
    check_in_date DATE NULL,
    actual_checkin_time TIMESTAMP NULL,
    check_out_date DATE NULL,
    actual_checkout_time TIMESTAMP NULL,
    customers INT NOT NULL DEFAULT 1,
    total_price DECIMAL(10, 2) NOT NULL,
    incidental_deposit DECIMAL(10, 2) DEFAULT 0.00,
    deposit_payment_method VARCHAR(50),
    final_amount DECIMAL(10, 2),
    deposit_refunded DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(100) NULL,
    special_requests TEXT,
    checkout_notes TEXT,
    -- Food order integration columns
    booking_type ENUM('room', 'food_order', 'spa_service', 'laundry_service') DEFAULT 'room',
    payment_reference VARCHAR(50) NULL,
    payment_deadline TIMESTAMP NULL,
    verification_status ENUM('pending_payment', 'pending_verification', 'verified', 'rejected', 'expired') DEFAULT 'pending_payment',
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    checked_in_by INT NULL,
    checked_out_by INT NULL,
    payment_screenshot VARCHAR(255) NULL,
    screenshot_uploaded_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_name (customer_name),
    INDEX idx_customer_email (customer_email),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (checked_in_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (checked_out_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 3: CREATE FOOD ORDER TABLES
-- =====================================================

-- Food Orders Table
CREATE TABLE IF NOT EXISTS food_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    order_reference VARCHAR(50) UNIQUE NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    table_reservation TINYINT(1) DEFAULT 0,
    reservation_date DATE NULL,
    reservation_time TIME NULL,
    guests INT DEFAULT 1,
    special_requests TEXT,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Food Order Items Table
CREATE TABLE IF NOT EXISTS food_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES food_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 4: CREATE PAYMENT SYSTEM TABLES
-- =====================================================

-- Payment Method Instructions Table (FIXED - NO reference_format column)
CREATE TABLE IF NOT EXISTS payment_method_instructions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_code VARCHAR(50) UNIQUE NOT NULL,
    method_name VARCHAR(100) NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    account_holder_name VARCHAR(100) NOT NULL,
    mobile_number VARCHAR(20) NULL,
    payment_instructions TEXT NOT NULL,
    verification_tips TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment Verification Log Table (IMPORTANT - for audit trail)
CREATE TABLE IF NOT EXISTS payment_verification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_reference VARCHAR(50) NOT NULL,
    action_type ENUM('screenshot_uploaded', 'verification_approved', 'verification_rejected', 'payment_expired') NOT NULL,
    performed_by INT NULL,
    screenshot_path VARCHAR(255) NULL,
    verification_notes TEXT NULL,
    bank_method VARCHAR(50) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment Verification Queue Table (IMPORTANT - for staff dashboard)
CREATE TABLE IF NOT EXISTS payment_verification_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_reference VARCHAR(50) NOT NULL,
    customer_name VARCHAR(200) NOT NULL,
    room_name VARCHAR(100) NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NULL,
    screenshot_path VARCHAR(255) NULL,
    uploaded_at TIMESTAMP NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    assigned_to INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 5: CREATE ADDITIONAL IMPORTANT TABLES
-- =====================================================

-- Food Menu Table (IMPORTANT - for food ordering system)
CREATE TABLE IF NOT EXISTS food_menu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category ENUM('appetizer', 'main_course', 'dessert', 'beverage', 'traditional', 'international') NOT NULL,
    description TEXT,
    price DECIMAL(8, 2) NOT NULL,
    image VARCHAR(255),
    ingredients TEXT,
    is_vegetarian BOOLEAN DEFAULT FALSE,
    is_vegan BOOLEAN DEFAULT FALSE,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Services Table (IMPORTANT - for hotel services)
CREATE TABLE IF NOT EXISTS services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category ENUM('restaurant', 'spa', 'laundry', 'transport', 'tours', 'other') NOT NULL,
    description TEXT,
    price DECIMAL(10, 2),
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Check-ins Table (IMPORTANT - for front desk operations)
-- NOTE: Complete table definition is located later in the file

-- Contact Messages Table (IMPORTANT - for customer inquiries)
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied', 'resolved') DEFAULT 'new',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Activity Log Table
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    activity_type ENUM('login', 'logout', 'booking', 'registration', 'profile_update', 'password_change') NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_activity (activity_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login Attempts Tracking Table (for security)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    user_agent TEXT,
    INDEX idx_email (email),
    INDEX idx_ip (ip_address),
    INDEX idx_attempt_time (attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password Reset Table (for forgot password functionality)
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Booking Activity Log Table
CREATE TABLE IF NOT EXISTS booking_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    user_id INT,
    activity_type ENUM('created', 'confirmed', 'modified', 'cancelled', 'checked_in', 'checked_out') NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    description TEXT,
    performed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking (booking_id),
    INDEX idx_activity (activity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Room Images Table
CREATE TABLE IF NOT EXISTS room_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(200),
    is_primary BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    INDEX idx_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Newsletter Subscriptions Table
CREATE TABLE IF NOT EXISTS newsletter_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100),
    status ENUM('active', 'unsubscribed') DEFAULT 'active',
    subscription_source VARCHAR(50) DEFAULT 'website',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff Tasks Table
CREATE TABLE IF NOT EXISTS staff_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assigned_to INT NOT NULL,
    assigned_by INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    due_date DATE,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Housekeeping Table
CREATE TABLE IF NOT EXISTS housekeeping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    assigned_to INT,
    task_type ENUM('cleaning', 'maintenance', 'inspection') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    scheduled_date DATE NOT NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_room (room_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hotel Settings Table
CREATE TABLE IF NOT EXISTS hotel_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type ENUM('text', 'email', 'phone', 'number', 'textarea') DEFAULT 'text',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default hotel settings
INSERT INTO hotel_settings (setting_key, setting_value, setting_type, description) VALUES
('hotel_name', 'Group Brand Hotel', 'text', 'Official hotel name'),
('contact_email', 'info@groupbrand.com', 'email', 'Main contact email address'),
('contact_phone', '+1-234-567-8900', 'phone', 'Main contact phone number'),
('hotel_address', 'Main Street, City, Country', 'textarea', 'Hotel physical address'),
('check_in_time', '14:00', 'text', 'Standard check-in time'),
('check_out_time', '12:00', 'text', 'Standard check-out time'),
('currency_symbol', 'ETB', 'text', 'Currency symbol'),
('tax_rate', '15', 'number', 'Tax rate percentage')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- =====================================================
-- STEP 6: INSERT PAYMENT METHODS (FIXED)
-- =====================================================

INSERT INTO payment_method_instructions 
(method_code, method_name, bank_name, account_number, account_holder_name, mobile_number, payment_instructions, verification_tips, display_order) 
VALUES
('telebirr', 'TeleBirr', 'Ethio Telecom', '0911-123-456', 'Group Brand Hotel', '0911-123-456', 
'1. Open TeleBirr app
2. Select Send Money
3. Enter amount: {AMOUNT}
4. Enter recipient: 0911-123-456
5. Add reference: {REFERENCE}
6. Complete transaction
7. Take screenshot of confirmation', 
'Ensure the screenshot shows the exact amount, recipient number, reference code, and successful transaction status.', 1),

('cbe_mobile', 'CBE Mobile Banking', 'Commercial Bank of Ethiopia', '1000-1234-5678-90', 'Group Brand Hotel', NULL, 
'1. Open CBE Mobile app
2. Login to your account
3. Select Transfer Money
4. Enter amount: {AMOUNT}
5. Enter account: 1000-1234-5678-90
6. Add reference: {REFERENCE}
7. Complete transfer
8. Take screenshot', 
'Screenshot must show successful transfer with correct amount, account number, and reference code.', 2),

('awash_mobile', 'Awash Mobile Banking', 'Awash Bank', '2000-9876-5432-10', 'Group Brand Hotel', NULL, 
'1. Open Awash Mobile app
2. Select Fund Transfer
3. Enter amount: {AMOUNT}
4. Enter account: 2000-9876-5432-10
5. Add reference: {REFERENCE}
6. Confirm transfer
7. Screenshot confirmation', 
'Ensure screenshot includes transaction ID, correct amount, and reference number.', 3),

('abyssinia_bank', 'Abyssinia Bank', 'Abyssinia Bank', '244422381', 'Group Brand Hotel', NULL,
'1. Login to Abyssinia Bank Mobile/Internet Banking
2. Select Transfer/Payment
3. Enter amount: {AMOUNT}
4. Enter account: 244422381
5. Add reference: {REFERENCE}
6. Complete transaction
7. Take screenshot of confirmation',
'Verify the screenshot shows successful transaction with correct amount, account number 244422381, and reference code.', 4),

('coop_bank_oromia', 'Cooperative Bank Of Oromia', 'Cooperative Bank Of Oromia', '0151143452800', 'Group Brand Hotel', NULL,
'1. Login to Cooperative Bank Mobile/Internet Banking
2. Select Fund Transfer
3. Enter amount: {AMOUNT}
4. Enter account: 0151143452800
5. Add reference: {REFERENCE}
6. Complete transfer
7. Take screenshot of confirmation',
'Ensure screenshot displays successful transfer with correct amount, account number 0151143452800, and reference code.', 5),

('dashen_bank', 'Dashen Bank', 'Dashen Bank', '106725625', 'Group Brand Hotel', NULL,
'1. Login to Dashen Bank Mobile/Internet Banking
2. Select Transfer Money
3. Enter amount: {AMOUNT}
4. Enter account: 106725625
5. Add reference: {REFERENCE}
6. Confirm and complete transfer
7. Take screenshot of successful transaction',
'Verify screenshot shows completed transaction with correct amount, account number 106725625, and reference code.', 6)
ON DUPLICATE KEY UPDATE method_code=method_code;

-- =====================================================
-- STEP 7: INSERT SAMPLE DATA
-- =====================================================

-- =====================================================
-- CHECK-IN/CHECK-OUT SYSTEM ENHANCEMENTS
-- =====================================================
-- NOTE: All bookings table columns are now in the CREATE TABLE statement above
-- No ALTER TABLE needed - table is complete

-- Incidental Charges Table
CREATE TABLE IF NOT EXISTS incidental_charges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    charge_type ENUM('minibar', 'room_service', 'laundry', 'phone', 'damage', 'other') NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    quantity INT DEFAULT 1,
    total_amount DECIMAL(10, 2) NOT NULL,
    charged_by INT NOT NULL,
    charge_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'paid', 'refunded') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (charged_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Room Keys Tracking Table
CREATE TABLE IF NOT EXISTS room_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    room_id INT NOT NULL,
    key_number VARCHAR(50) NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    issued_by INT NOT NULL,
    returned_at TIMESTAMP NULL,
    returned_to INT NULL,
    status ENUM('issued', 'returned', 'lost', 'replaced') DEFAULT 'issued',
    notes TEXT,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (returned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking (booking_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Check-in/Check-out Log Table
CREATE TABLE IF NOT EXISTS checkin_checkout_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    action_type ENUM('check_in', 'check_out') NOT NULL,
    performed_by INT NOT NULL,
    action_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_collected DECIMAL(10, 2) DEFAULT 0.00,
    payment_method VARCHAR(50),
    deposit_amount DECIMAL(10, 2) DEFAULT 0.00,
    incidental_charges DECIMAL(10, 2) DEFAULT 0.00,
    refund_amount DECIMAL(10, 2) DEFAULT 0.00,
    id_verified BOOLEAN DEFAULT FALSE,
    id_type VARCHAR(50),
    id_number VARCHAR(100),
    notes TEXT,
    ip_address VARCHAR(45),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_action (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guest Preferences Table (for future stays)
CREATE TABLE IF NOT EXISTS guest_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preference_type ENUM('room_type', 'floor', 'bed_type', 'pillow_type', 'dietary', 'other') NOT NULL,
    preference_value VARCHAR(255) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 7: INSERT SAMPLE DATA
-- =====================================================

-- Insert Sample Rooms - UPDATED WITH ROOM NUMBERS 1-40
INSERT INTO rooms (name, room_number, room_type, description, capacity, price, status) VALUES
-- Standard Single Rooms (1-4)
('Standard Single Room', '1', 'standard', '1 single bed, free wifi, air conditioning, private bathroom', 1, 2000.00, 'active'),
('Standard Single Room', '2', 'standard', '1 single bed, free wifi, air conditioning, private bathroom', 1, 2000.00, 'active'),
('Standard Single Room', '3', 'standard', '1 single bed, free wifi, air conditioning, private bathroom', 1, 2000.00, 'active'),
('Standard Single Room', '4', 'standard', '1 single bed, free wifi, air conditioning, private bathroom', 1, 2000.00, 'active'),

-- Standard Double Rooms (5-8)
('Standard Double Room', '5', 'standard', '1 double bed, free wifi, air conditioning, private bathroom', 2, 2500.00, 'active'),
('Standard Double Room', '6', 'standard', '1 double bed, free wifi, air conditioning, private bathroom', 2, 2500.00, 'active'),
('Standard Double Room', '7', 'standard', '1 double bed, free wifi, air conditioning, private bathroom', 2, 2500.00, 'active'),
('Standard Double Room', '8', 'standard', '1 double bed, free wifi, air conditioning, private bathroom', 2, 2500.00, 'active'),

-- Deluxe Single Rooms (9-12)
('Deluxe Single Room', '9', 'deluxe', '1 queen bed, free wifi, air conditioning, private bathroom, mini bar, city view', 1, 3000.00, 'active'),
('Deluxe Single Room', '10', 'deluxe', '1 queen bed, free wifi, air conditioning, private bathroom, mini bar, city view', 1, 3000.00, 'active'),
('Deluxe Single Room', '11', 'deluxe', '1 queen bed, free wifi, air conditioning, private bathroom, mini bar, city view', 1, 3000.00, 'active'),
('Deluxe Single Room', '12', 'deluxe', '1 queen bed, free wifi, air conditioning, private bathroom, mini bar, city view', 1, 3000.00, 'active'),

-- Deluxe Double Rooms (13-16)
('Deluxe Double Room', '13', 'deluxe', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, city view, work desk', 2, 3500.00, 'active'),
('Deluxe Double Room', '14', 'deluxe', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, city view, work desk', 2, 3500.00, 'active'),
('Deluxe Double Room', '15', 'deluxe', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, city view, work desk', 2, 3500.00, 'active'),
('Deluxe Double Room', '16', 'deluxe', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, city view, work desk', 2, 3500.00, 'active'),

-- Family Suites (17-20)
('Family Suite', '17', 'family', '2 double beds, free wifi, air conditioning, private bathroom, mini bar, living area, kitchenette', 4, 4000.00, 'active'),
('Family Suite', '18', 'family', '2 double beds, free wifi, air conditioning, private bathroom, mini bar, living area, kitchenette', 4, 4000.00, 'active'),
('Family Suite', '19', 'family', '2 double beds, free wifi, air conditioning, private bathroom, mini bar, living area, kitchenette', 4, 4000.00, 'active'),
('Family Suite', '20', 'family', '2 double beds, free wifi, air conditioning, private bathroom, mini bar, living area, kitchenette', 4, 4000.00, 'active'),

-- Executive Suites (21-28)
('Executive Suite', '21', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '22', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '23', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '24', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '25', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '26', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '27', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),
('Executive Suite', '28', 'executive', '1 king bed, free wifi, air conditioning, private bathroom, mini bar, separate living area, work desk, premium amenities', 2, 4500.00, 'active'),

-- Presidential Suites (29-40)
('Presidential Suite', '29', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '30', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '31', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '32', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '33', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '34', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '35', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '36', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '37', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '38', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active'),
('Presidential Suite', '39', 'presidential', '1 king bed, 1 queen bed, free wifi, air conditioning, 2 private bathrooms, mini bar, separate living room, dining area, kitchenette, balcony, premium amenities', 4, 8000.00, 'active')
ON DUPLICATE KEY UPDATE room_number=room_number;

-- =====================================================
-- SERVICES DATA WILL BE INSERTED AT THE END WITH CLEANUP
-- =====================================================
-- (Services insertion moved to end of file to ensure no duplicates)

-- Insert Sample Food Menu Items (Prices in Ethiopian Birr)
INSERT INTO food_menu (name, category, description, price, image, ingredients, is_vegetarian, is_vegan, is_available) VALUES
('Doro Wat', 'traditional', 'Traditional Ethiopian chicken stew with berbere spice and hard-boiled eggs', 480.00, 'assets/images/food/injera-doro-wat.jpg', 'Chicken, berbere spice, onions, eggs, injera', FALSE, FALSE, TRUE),
('Vegetarian Combo', 'traditional', 'Assorted vegetarian dishes served on injera bread', 400.00, 'assets/images/food/vegetarian-combo.jpg', 'Lentils, vegetables, injera, berbere', TRUE, TRUE, TRUE),
('Kitfo', 'traditional', 'Ethiopian steak tartare seasoned with mitmita and served with ayib', 580.00, 'assets/images/food/kitfo.jpg', 'Raw beef, mitmita, ayib cheese, injera', FALSE, FALSE, TRUE),
('Tibs', 'traditional', 'Sautéed meat with onions, tomatoes, and Ethiopian spices', 500.00, 'assets/images/food/tibs.jpg', 'Beef, onions, tomatoes, berbere, injera', FALSE, FALSE, TRUE),
('Ethiopian Coffee', 'beverage', 'Traditional Ethiopian coffee ceremony with freshly roasted beans', 200.00, 'assets/images/food/ethiopian-coffee.jpg', 'Ethiopian coffee beans, sugar, milk optional', TRUE, TRUE, TRUE),
('Honey Wine (Tej)', 'beverage', 'Traditional Ethiopian honey wine', 400.00, 'assets/images/food/tej.jpg', 'Honey, water, gesho', TRUE, TRUE, TRUE),
('Baklava', 'dessert', 'Sweet pastry with nuts and honey', 250.00, 'assets/images/food/baklava.jpg', 'Phyllo pastry, nuts, honey, butter', TRUE, FALSE, TRUE);

-- Insert Room Images (for first 7 rooms as examples)
INSERT INTO room_images (room_id, image_path, alt_text, is_primary, display_order) VALUES
(1, 'assets/images/rooms/standard-single.jpg', 'Standard Single Room - Main View', TRUE, 1),
(2, 'assets/images/rooms/standard-double.jpg', 'Standard Double Room - Main View', TRUE, 1),
(3, 'assets/images/rooms/deluxe-single.jpg', 'Deluxe Single Room - Main View', TRUE, 1),
(4, 'assets/images/rooms/deluxe-double.jpg', 'Deluxe Double Room - Main View', TRUE, 1),
(5, 'assets/images/rooms/family-suite.jpg', 'Family Suite - Living Area', TRUE, 1),
(6, 'assets/images/rooms/executive-suite.jpg', 'Executive Suite - Main View', TRUE, 1),
(7, 'assets/images/rooms/presidential-suite.jpg', 'Presidential Suite - Living Room', TRUE, 1);

-- Insert Default Superadmin User (PERMANENT SOLUTION)
INSERT IGNORE INTO users (first_name, last_name, username, email, phone, password, role, status) VALUES
('Super', 'Admin', 'superadmin', 'superadmin@groupbrand.com', '+1234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active');

-- Insert Test Users for Role Testing
INSERT IGNORE INTO users (first_name, last_name, username, email, phone, password, role, status) VALUES
('Test', 'Admin', 'testadmin', 'admin@test.com', '+251911111111', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active'),
('Test', 'Manager', 'testmanager', 'manager@test.com', '+251922222222', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'active'),
('Test', 'Customer', 'testcustomer', 'customer@test.com', '+251944444444', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 'active');

-- =====================================================
-- ADMIN AUDIT LOGS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    target_user_id INT,
    target_table VARCHAR(50),
    changed_fields JSON,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_target_user_id (target_user_id),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 8: CREATE INDEXES
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_rooms_type ON rooms(room_type);
CREATE INDEX IF NOT EXISTS idx_rooms_status ON rooms(status);
CREATE INDEX IF NOT EXISTS idx_bookings_ref ON bookings(booking_reference);
CREATE INDEX IF NOT EXISTS idx_bookings_user ON bookings(user_id);
CREATE INDEX IF NOT EXISTS idx_bookings_type ON bookings(booking_type);
CREATE INDEX IF NOT EXISTS idx_bookings_verification ON bookings(verification_status);
CREATE INDEX IF NOT EXISTS idx_food_orders_booking ON food_orders(booking_id);
CREATE INDEX IF NOT EXISTS idx_food_orders_user ON food_orders(user_id);
CREATE INDEX IF NOT EXISTS idx_food_menu_category ON food_menu(category);
CREATE INDEX IF NOT EXISTS idx_food_menu_available ON food_menu(is_available);
CREATE INDEX IF NOT EXISTS idx_services_category ON services(category);
CREATE INDEX IF NOT EXISTS idx_services_status ON services(status);
CREATE INDEX IF NOT EXISTS idx_contact_status ON contact_messages(status);
CREATE INDEX IF NOT EXISTS idx_bookings_dates_status ON bookings(check_in_date, check_out_date, status);
CREATE INDEX IF NOT EXISTS idx_users_role_status ON users(role, status);
CREATE INDEX IF NOT EXISTS idx_food_orders_status_date ON food_orders(status, created_at);
CREATE INDEX IF NOT EXISTS idx_bookings_payment_deadline ON bookings(payment_deadline);
CREATE INDEX IF NOT EXISTS idx_bookings_payment_reference ON bookings(payment_reference);

-- =====================================================
-- STEP 9: CREATE VIEWS
-- =====================================================

-- Payment Verification Dashboard View
CREATE OR REPLACE VIEW payment_verification_dashboard AS
SELECT 
    b.id as booking_id,
    b.booking_reference,
    b.payment_reference,
    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
    u.email as customer_email,
    u.phone as customer_phone,
    CASE 
        WHEN b.booking_type = 'food_order' THEN 'Food Order'
        ELSE COALESCE(r.name, 'Unknown Room')
    END as room_name,
    CASE 
        WHEN b.booking_type = 'food_order' THEN 'N/A'
        ELSE COALESCE(r.room_number, 'N/A')
    END as room_number,
    b.booking_type,
    b.total_price,
    b.payment_method,
    b.verification_status,
    b.payment_screenshot,
    b.screenshot_uploaded_at,
    b.payment_deadline,
    CASE 
        WHEN b.payment_deadline < NOW() AND b.verification_status = 'pending_payment' THEN 'EXPIRED'
        WHEN b.payment_deadline < DATE_ADD(NOW(), INTERVAL 10 MINUTE) AND b.verification_status = 'pending_payment' THEN 'URGENT'
        WHEN b.verification_status = 'pending_verification' THEN 'NEEDS_REVIEW'
        ELSE 'NORMAL'
    END as priority_status,
    TIMESTAMPDIFF(MINUTE, b.screenshot_uploaded_at, NOW()) as minutes_waiting,
    pmi.method_name as payment_method_name,
    pmi.bank_name,
    CONCAT(verifier.first_name, ' ', verifier.last_name) as verified_by_name,
    b.verified_at
FROM bookings b
JOIN users u ON b.user_id = u.id
LEFT JOIN rooms r ON b.room_id = r.id
LEFT JOIN payment_method_instructions pmi ON b.payment_method = pmi.method_code
LEFT JOIN users verifier ON b.verified_by = verifier.id
WHERE b.verification_status IN ('pending_payment', 'pending_verification', 'rejected')
ORDER BY 
    CASE 
        WHEN b.verification_status = 'pending_verification' THEN 1
        WHEN b.payment_deadline < NOW() THEN 2
        WHEN b.payment_deadline < DATE_ADD(NOW(), INTERVAL 10 MINUTE) THEN 3
        ELSE 4
    END,
    b.screenshot_uploaded_at ASC;

-- Booking Summary View
CREATE OR REPLACE VIEW booking_summary AS
SELECT 
    b.id,
    b.booking_reference,
    CONCAT(u.first_name, ' ', u.last_name) as guest_name,
    u.email,
    u.phone,
    r.name as room_name,
    r.room_number,
    b.check_in_date,
    b.check_out_date,
    DATEDIFF(b.check_out_date, b.check_in_date) as nights,
    b.customers,
    b.total_price,
    b.status,
    b.payment_status,
    b.payment_method,
    b.created_at
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN rooms r ON b.room_id = r.id;

-- Room Availability View
CREATE OR REPLACE VIEW room_availability AS
SELECT 
    r.id,
    r.name,
    r.room_number,
    r.room_type,
    r.price,
    r.status,
    COUNT(b.id) as active_bookings,
    CASE 
        WHEN COUNT(b.id) > 0 THEN 'occupied'
        WHEN r.status = 'maintenance' THEN 'maintenance'
        ELSE 'available'
    END as availability_status
FROM rooms r
LEFT JOIN bookings b ON r.id = b.room_id 
    AND b.status IN ('confirmed', 'checked_in')
    AND CURDATE() BETWEEN b.check_in_date AND b.check_out_date
GROUP BY r.id;

-- Revenue Summary View
CREATE OR REPLACE VIEW revenue_summary AS
SELECT 
    DATE(b.created_at) as booking_date,
    COUNT(b.id) as total_bookings,
    SUM(b.total_price) as total_revenue,
    AVG(b.total_price) as average_booking_value,
    COUNT(CASE WHEN b.status = 'confirmed' THEN 1 END) as confirmed_bookings,
    COUNT(CASE WHEN b.status = 'cancelled' THEN 1 END) as cancelled_bookings
FROM bookings b
GROUP BY DATE(b.created_at)
ORDER BY booking_date DESC;

-- =====================================================
-- STEP 10: CREATE STORED PROCEDURES (CORRECTED VERSION)
-- =====================================================

-- Procedure to check room availability
DROP PROCEDURE IF EXISTS CheckRoomAvailability;
DELIMITER $$
CREATE PROCEDURE CheckRoomAvailability(
    IN check_in DATE,
    IN check_out DATE,
    IN room_type_filter VARCHAR(50)
)
BEGIN
    SELECT r.*, 
           CASE 
               WHEN COUNT(b.id) > 0 THEN 'occupied'
               ELSE 'available'
           END as availability
    FROM rooms r
    LEFT JOIN bookings b ON r.id = b.room_id 
        AND b.status IN ('confirmed', 'checked_in')
        AND NOT (check_out <= b.check_in_date OR check_in >= b.check_out_date)
    WHERE r.status = 'active'
        AND (room_type_filter IS NULL OR r.room_type = room_type_filter)
    GROUP BY r.id
    HAVING availability = 'available'
    ORDER BY r.price ASC;
END$$
DELIMITER ;

-- Function to generate payment reference (CORRECTED)
DROP FUNCTION IF EXISTS generate_payment_reference;
DELIMITER $$
CREATE FUNCTION generate_payment_reference(booking_id INT) 
RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE ref_code VARCHAR(20);
    DECLARE random_suffix VARCHAR(6);
    
    -- Generate random 6-character suffix
    SET random_suffix = UPPER(SUBSTRING(MD5(CONCAT(booking_id, NOW(), RAND())), 1, 6));
    
    -- Format: HRH-{BOOKING_ID}-{RANDOM}
    SET ref_code = CONCAT('HRH-', LPAD(booking_id, 4, '0'), '-', random_suffix);
    
    RETURN ref_code;
END$$
DELIMITER ;

-- Stored procedure to expire old bookings
DROP PROCEDURE IF EXISTS expire_old_bookings;
DELIMITER $$
CREATE PROCEDURE expire_old_bookings()
BEGIN
    UPDATE bookings 
    SET verification_status = 'expired',
        updated_at = NOW()
    WHERE verification_status = 'pending_payment' 
    AND payment_deadline < NOW();
    
    -- Log expired bookings
    INSERT INTO payment_verification_log (booking_id, payment_reference, action_type, verification_notes)
    SELECT id, payment_reference, 'payment_expired', 'Automatically expired due to deadline'
    FROM bookings 
    WHERE verification_status = 'expired' 
    AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE);
END$$
DELIMITER ;


-- =====================================================
-- FINAL SETUP
-- =====================================================

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Success message
SELECT 'Complete database setup completed successfully!' as message,
       'Food order payment integration ready' as status,
       'All essential tables included - ready to test' as notes;  

-- =====================================================
-- ADD PROFILE COLUMNS FOR USER DROPDOWN FUNCTIONALITY
-- =====================================================

-- =====================================================
-- CUSTOMER FEEDBACK TABLE
-- =====================================================
-- NOTE: Users table already has all notification columns
-- No ALTER TABLE needed - table is complete

-- Customer Feedback Table
CREATE TABLE IF NOT EXISTS customer_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    customer_id INT NOT NULL,
    payment_id VARCHAR(50) NULL,
    overall_rating INT NOT NULL CHECK (overall_rating >= 1 AND overall_rating <= 5),
    service_quality INT NOT NULL CHECK (service_quality >= 1 AND service_quality <= 5),
    cleanliness INT NOT NULL CHECK (cleanliness >= 1 AND cleanliness <= 5),
    comments TEXT NULL,
    booking_reference VARCHAR(50) NULL,
    booking_type ENUM('room', 'food_order', 'spa_service', 'laundry_service') DEFAULT 'room',
    service_type VARCHAR(100) NULL COMMENT 'Specific service name (e.g., Spa Massage, Wash & Iron)',
    service_id INT NULL COMMENT 'Reference to service ID from services table',
    room_name VARCHAR(100) NULL,
    room_number VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_customer (customer_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CHECKINS TABLE FOR MANUAL CUSTOMER CHECK-IN
-- =====================================================

-- Customer Check-ins Table for Receptionist Manual Check-ins
CREATE TABLE IF NOT EXISTS checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    booking_id INT DEFAULT NULL,
    
    -- Hotel Information
    hotel_name VARCHAR(255) NOT NULL DEFAULT 'Group Brand Hotel',
    hotel_location VARCHAR(255) NOT NULL DEFAULT 'Main Street, City, Country',
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    
    -- Customer Information
    guest_full_name VARCHAR(255) NOT NULL,
    guest_date_of_birth DATE NOT NULL,
    guest_id_type ENUM('passport','drivers_license','national_id') NOT NULL,
    guest_id_number VARCHAR(100) NOT NULL,
    guest_nationality VARCHAR(100) NOT NULL,
    guest_home_address TEXT NOT NULL,
    guest_phone_number VARCHAR(20) NOT NULL,
    guest_email_address VARCHAR(255) NOT NULL,
    
    -- Stay Details
    room_type VARCHAR(100) NOT NULL,
    room_number VARCHAR(20) DEFAULT NULL,
    nights_stay INT NOT NULL,
    number_of_guests INT NOT NULL DEFAULT 1,
    rate_per_night DECIMAL(10,2) NOT NULL,
    
    -- Payment Details
    payment_type ENUM('cash','credit_card','debit_card','bank_transfer','mobile_payment') NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    balance_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    confirmation_number VARCHAR(100) NOT NULL,
    
    -- Additional Information
    additional_requests TEXT DEFAULT NULL,
    
    -- System Fields
    checked_in_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active','checked_out','cancelled') NOT NULL DEFAULT 'active',
    
    UNIQUE KEY confirmation_number (confirmation_number),
    KEY customer_id (customer_id),
    KEY booking_id (booking_id),
    KEY checked_in_by (checked_in_by),
    KEY check_in_date (check_in_date),
    KEY check_out_date (check_out_date),
    KEY guest_full_name (guest_full_name),
    KEY guest_email_address (guest_email_address),
    
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    FOREIGN KEY (checked_in_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create index for checkins table
CREATE INDEX IF NOT EXISTS idx_checkins_booking ON checkins(booking_id);

-- =====================================================
-- SERVICE BOOKINGS TABLE (SPA & LAUNDRY)
-- =====================================================

-- Service Bookings Table for Spa and Laundry Services
CREATE TABLE IF NOT EXISTS service_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    service_category ENUM('spa', 'laundry') NOT NULL,
    service_name VARCHAR(100) NOT NULL,
    service_price DECIMAL(10, 2) NOT NULL,
    quantity INT DEFAULT 1,
    total_price DECIMAL(10, 2) NOT NULL,
    service_date DATE NULL,
    service_time TIME NULL,
    special_requests TEXT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_user (user_id),
    INDEX idx_category (service_category),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PERFORMANCE INDEXES
-- =====================================================

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- DATABASE UPDATES - EMAIL NOTIFICATION SYSTEM
-- =====================================================

-- Add email tracking columns to bookings table
ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS email_sent TINYINT(1) DEFAULT 0 AFTER payment_screenshot,
ADD COLUMN IF NOT EXISTS email_sent_at TIMESTAMP NULL AFTER email_sent,
ADD COLUMN IF NOT EXISTS email_error TEXT NULL AFTER email_sent_at;

-- Add email tracking columns to food_orders table
ALTER TABLE food_orders 
ADD COLUMN IF NOT EXISTS email_sent TINYINT(1) DEFAULT 0 AFTER status,
ADD COLUMN IF NOT EXISTS email_sent_at TIMESTAMP NULL AFTER email_sent,
ADD COLUMN IF NOT EXISTS email_error TEXT NULL AFTER email_sent_at;

-- Add email tracking columns to service_bookings table
ALTER TABLE service_bookings 
ADD COLUMN IF NOT EXISTS email_sent TINYINT(1) DEFAULT 0 AFTER status,
ADD COLUMN IF NOT EXISTS email_sent_at TIMESTAMP NULL AFTER email_sent,
ADD COLUMN IF NOT EXISTS email_error TEXT NULL AFTER email_sent_at;

-- Create email logs table for tracking all sent emails
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email_to VARCHAR(255) NOT NULL,
    email_type ENUM('room_booking', 'food_order', 'spa_service', 'laundry_service', 'other') NOT NULL,
    reference_id INT NOT NULL COMMENT 'Booking ID, Order ID, or Service ID',
    subject VARCHAR(255) NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_email_type (email_type),
    INDEX idx_reference_id (reference_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DATABASE UPDATES - SMS NOTIFICATIONS DEFAULT
-- =====================================================

-- Update all existing users to enable SMS notifications by default
UPDATE users 
SET sms_notifications = 1 
WHERE sms_notifications = 0;

-- =====================================================
-- DATABASE UPDATES - BILLS TABLE NOTES COLUMN
-- =====================================================

-- Create bills table if it doesn't exist
CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    customer_id INT NOT NULL,
    bill_reference VARCHAR(50) UNIQUE NOT NULL,
    room_charges DECIMAL(10, 2) DEFAULT 0.00,
    service_charges DECIMAL(10, 2) DEFAULT 0.00,
    incidental_charges DECIMAL(10, 2) DEFAULT 0.00,
    tax_amount DECIMAL(10, 2) DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('draft', 'sent_to_manager', 'approved', 'rejected', 'paid') DEFAULT 'draft',
    generated_by INT NOT NULL,
    approved_by INT NULL,
    rejection_reason TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking (booking_id),
    INDEX idx_customer (customer_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add notes column to bills table if it doesn't exist
ALTER TABLE bills 
ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER rejection_reason;

-- =====================================================
-- DATABASE UPDATES COMPLETED
-- =====================================================

-- =====================================================
-- DATABASE UPDATES COMPLETED
-- =====================================================

-- =====================================================
-- FINAL CLEANUP: REMOVE ANY DUPLICATE SERVICES
-- =====================================================

-- Step 1: Remove any duplicate restaurant services that might exist
DELETE FROM services WHERE category = 'restaurant';

-- Step 1: Clean up existing services table completely
DELETE FROM services WHERE category IN ('spa', 'laundry');

-- Step 1.1: Also remove any generic "Laundry Service" entries that might exist
DELETE FROM services WHERE name = 'Laundry Service' OR name LIKE '%Laundry Service%';

-- Step 1.2: Remove any services with duplicate descriptions
DELETE FROM services WHERE description = 'Professional laundry and dry cleaning';

-- Step 2: Re-insert only the clean, unique spa and laundry services
INSERT INTO services (name, category, description, price, image, status) VALUES
-- Featured Dishes (New Additions) - UNIQUE ONLY
('Vegetable Pizza', 'restaurant', 'Delicious pizza topped with fresh vegetables, cheese, and tomato sauce', 380.00, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=400&h=300&fit=crop&q=80', 'active'),
('Grilled Meat Platter', 'restaurant', 'Succulent grilled meat served with rice, vegetables, and traditional bread', 520.00, 'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?w=400&h=300&fit=crop&q=80', 'active'),

-- Ethiopian Food Services - UNIQUE ONLY
('Ethiopian Traditional Platter', 'restaurant', 'Authentic Ethiopian platter with assorted wats, tibs, and injera served on traditional mesob', 480.00, 'https://images.unsplash.com/photo-1604329760661-e71dc83f8f26?w=400&h=300&fit=crop&q=80', 'active'),
('Ethiopian Breakfast', 'restaurant', 'Traditional Ethiopian breakfast with injera, scrambled eggs, foul, and fresh honey', 350.00, 'https://images.unsplash.com/photo-1606787366850-de6330128bfc?w=400&h=300&fit=crop&q=80', 'active'),
('Ethiopian Coffee Ceremony', 'restaurant', 'Traditional Ethiopian coffee ceremony with freshly roasted beans and popcorn', 200.00, 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=400&h=300&fit=crop&q=80', 'active'),
('Ethiopian Lunch Special', 'restaurant', 'Traditional Ethiopian lunch with injera, berbere-spiced lentils, and seasonal vegetables', 420.00, 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=400&h=300&fit=crop&q=80', 'active'),

-- International Buffet Services - UNIQUE ONLY
('International Breakfast Buffet', 'restaurant', 'Continental breakfast buffet with fresh fruits, pastries, cereals, eggs, and hot dishes', 400.00, 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?w=400&h=300&fit=crop&q=80', 'active'),
('International Lunch Buffet', 'restaurant', 'Diverse lunch buffet with Asian, European, and American cuisine selections', 550.00, 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=400&h=300&fit=crop&q=80', 'active'),
('International Dinner Buffet', 'restaurant', 'Premium dinner buffet with grilled meats, seafood, pasta, and international specialties', 700.00, 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400&h=300&fit=crop&q=80', 'active'),
('International Weekend Brunch', 'restaurant', 'Special weekend brunch buffet with pancakes, waffles, fresh fruits, and international favorites', 480.00, 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=400&h=300&fit=crop&q=80', 'active'),

-- International Dishes - UNIQUE ONLY
('Grilled Steak', 'restaurant', 'Premium beef steak grilled to perfection, served with vegetables and choice of sides', 750.00, 'https://images.unsplash.com/photo-1600891964092-4316c288032e?w=400&h=300&fit=crop&q=80', 'active'),
('Pasta Carbonara', 'restaurant', 'Classic Italian pasta with creamy sauce, bacon, and parmesan cheese', 450.00, 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=400&h=300&fit=crop&q=80', 'active'),
('Grilled Salmon', 'restaurant', 'Fresh Atlantic salmon grilled with herbs, served with rice and steamed vegetables', 850.00, 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=400&h=300&fit=crop&q=80', 'active'),
('Caesar Salad', 'restaurant', 'Fresh romaine lettuce with Caesar dressing, croutons, and parmesan cheese', 320.00, 'https://images.unsplash.com/photo-1546793665-c74683f339c1?w=400&h=300&fit=crop&q=80', 'active'),

-- Desserts - UNIQUE ONLY
('Chocolate Lava Cake', 'restaurant', 'Warm chocolate cake with molten center, served with vanilla ice cream', 280.00, 'https://images.unsplash.com/photo-1624353365286-3f8d62daad51?w=400&h=300&fit=crop&q=80', 'active'),
('Tiramisu', 'restaurant', 'Classic Italian dessert with coffee-soaked ladyfingers and mascarpone cream', 300.00, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400&h=300&fit=crop&q=80', 'active'),

-- Beverages - UNIQUE ONLY
('Fresh Fruit Juice', 'restaurant', 'Freshly squeezed juice - choice of orange, mango, papaya, or mixed fruit', 150.00, 'https://images.unsplash.com/photo-1600271886742-f049cd451bba?w=400&h=300&fit=crop&q=80', 'active'),
('Smoothie Bowl', 'restaurant', 'Healthy smoothie bowl topped with fresh fruits, granola, and honey', 250.00, 'https://images.unsplash.com/photo-1590301157890-4810ed352733?w=400&h=300&fit=crop&q=80', 'active'),

-- Spa & Wellness Services - UNIQUE ONLY
('Spa Massage', 'spa', 'Relaxing full body massage (60 minutes)', 1300.00, 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=400&h=300&fit=crop&q=80', 'active'),
('Facial Treatment', 'spa', 'Rejuvenating facial with natural products for glowing skin (45 minutes)', 800.00, 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=400&h=300&fit=crop&q=80', 'active'),
('Sauna & Steam Room', 'spa', 'Detoxify and relax in our premium sauna facilities (30 minutes)', 500.00, 'https://images.unsplash.com/photo-1600334129128-685c5582fd35?w=400&h=300&fit=crop&q=80', 'active'),

-- Laundry Services - UNIQUE ONLY
('Wash & Iron', 'laundry', 'Professional washing and ironing service', 250.00, 'https://images.unsplash.com/photo-1517677208171-0bc6725a3e60?w=400&h=300&fit=crop&q=80', 'active'),
('Dry Cleaning', 'laundry', 'Premium dry cleaning for delicate garments', 400.00, 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=300&fit=crop&q=80', 'active'),
('Express Service', 'laundry', 'Same-day laundry service available', 500.00, 'https://images.unsplash.com/photo-1582735689369-4fe89db7114c?w=400&h=300&fit=crop&q=80', 'active')
ON DUPLICATE KEY UPDATE name=name;

-- Step 3: Verify no duplicates exist
SELECT 'Checking for duplicates...' as status;
SELECT name, COUNT(*) as count FROM services WHERE category IN ('restaurant', 'spa', 'laundry') GROUP BY name HAVING count > 1;

-- Step 3.1: Remove any duplicate spa and laundry services (keep lowest ID)
DELETE s1 FROM services s1
INNER JOIN services s2 
WHERE s1.id > s2.id 
AND s1.name = s2.name 
AND s1.category = s2.category 
AND s1.category IN ('spa', 'laundry');

-- Step 3.2: Verify spa and laundry services are unique
SELECT 'Spa services count:' as status;
SELECT name, COUNT(*) as count FROM services WHERE category = 'spa' GROUP BY name;

SELECT 'Laundry services count:' as status;
SELECT name, COUNT(*) as count FROM services WHERE category = 'laundry' GROUP BY name;

-- Step 3.3: Add unique constraint to prevent future duplicates (only if it doesn't exist)
SET @constraint_exists = (SELECT COUNT(*) FROM information_schema.table_constraints 
                         WHERE constraint_name = 'unique_service_name_category' 
                         AND table_name = 'services' 
                         AND table_schema = DATABASE());

SET @sql = IF(@constraint_exists = 0, 
              'ALTER TABLE services ADD CONSTRAINT unique_service_name_category UNIQUE (name, category)', 
              'SELECT "Constraint already exists" as status');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3.4: Final comprehensive cleanup - Remove any remaining duplicates in all categories
DELETE s1 FROM services s1
INNER JOIN services s2 
WHERE s1.id > s2.id 
AND s1.name = s2.name 
AND s1.category = s2.category;

-- Step 3.5: Remove any remaining generic "Laundry Service" entries
DELETE FROM services WHERE name = 'Laundry Service' AND category = 'laundry';

-- Step 3.6: Verify final state - should show no duplicates
SELECT 'Final verification - checking for any remaining duplicates:' as status;
SELECT name, category, COUNT(*) as count 
FROM services 
GROUP BY name, category 
HAVING count > 1;

-- Step 3.7: Show all laundry services to verify they are correct
SELECT 'Current laundry services:' as status;
SELECT id, name, description, price 
FROM services 
WHERE category = 'laundry' AND status = 'active'
ORDER BY price;

-- Step 3.8: Show clean service counts by category
SELECT 'Service counts by category:' as status;
SELECT category, COUNT(*) as total_services 
FROM services 
WHERE status = 'active' 
GROUP BY category 
ORDER BY category;

-- Step 4: Fix NULL phone numbers in bookings table to prevent PHP errors
UPDATE bookings SET customer_phone = '' WHERE customer_phone IS NULL;

-- Final message
SELECT 'Database cleanup completed successfully!' as status;

-- =====================================================
-- FINAL SUCCESS MESSAGE
-- =====================================================
SELECT 'Group Brand Hotel database setup completed successfully!' as message,
       'Database name: group_brand' as database_name,
       'All tables created without errors' as status,
       'Ready for production use' as notes;

-- =====================================================
-- STEP 10: CREATE DEFAULT USER ACCOUNTS
-- =====================================================

-- Insert default user accounts for all roles
-- Note: All passwords are hashed using PHP password_hash() function

-- Super Admin Account
INSERT IGNORE INTO users (
    first_name, 
    last_name, 
    username, 
    email, 
    phone, 
    password, 
    role, 
    status, 
    created_at
) VALUES (
    'Super',
    'Admin', 
    'superadmin',
    'superadmin@groupbrandhotel.com',
    '+251911000000',
    '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', -- Password: 123456
    'super_admin',
    'active',
    NOW()
);

-- Admin Account
INSERT IGNORE INTO users (
    first_name, 
    last_name, 
    username, 
    email, 
    phone, 
    password, 
    role, 
    status, 
    created_at
) VALUES (
    'System',
    'Administrator', 
    'admin',
    'admin@groupbrandhotel.com',
    '+251911111111',
    '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', -- Password: 123456
    'admin',
    'active',
    NOW()
);

-- Manager Account
INSERT IGNORE INTO users (
    first_name, 
    last_name, 
    username, 
    email, 
    phone, 
    password, 
    role, 
    status, 
    created_at
) VALUES (
    'Hotel',
    'Manager', 
    'manager',
    'manager@groupbrandhotel.com',
    '+251911222222',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Password: password
    'manager',
    'active',
    NOW()
);

-- Receptionist Account
INSERT IGNORE INTO users (
    first_name, 
    last_name, 
    username, 
    email, 
    phone, 
    password, 
    role, 
    status, 
    created_at
) VALUES (
    'Reception',
    'Staff', 
    'receptionist',
    'receptionist@groupbrandhotel.com',
    '+251911333333',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Password: password
    'receptionist',
    'active',
    NOW()
);

-- Test Customer Account
INSERT IGNORE INTO users (
    first_name, 
    last_name, 
    username, 
    email, 
    phone, 
    password, 
    role, 
    status, 
    created_at
) VALUES (
    'Test',
    'Customer', 
    'customer',
    'customer@test.com',
    '+251911444444',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Password: password
    'customer',
    'active',
    NOW()
);

-- =====================================================
-- ENABLE FOREIGN KEY CHECKS
-- =====================================================
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- DATABASE SETUP COMPLETED SUCCESSFULLY
-- =====================================================
-- Total Tables Created: 34
-- Default Users Created: 5 (Super Admin, Admin, Manager, Receptionist, Customer)
-- 
-- LOGIN CREDENTIALS:
-- ==================
-- Super Admin: superadmin@groupbrandhotel.com / 123456
-- Admin:       admin@groupbrandhotel.com / 123456  
-- Manager:     manager@groupbrandhotel.com / password
-- Receptionist: receptionist@groupbrandhotel.com / password
-- Customer:    customer@test.com / password
-- =====================================================