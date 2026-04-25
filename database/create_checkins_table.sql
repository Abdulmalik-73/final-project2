-- =====================================================
-- CREATE CHECKINS TABLE IF IT DOESN'T EXIST
-- =====================================================

CREATE TABLE IF NOT EXISTS checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    booking_id INT DEFAULT NULL,
    
    -- Hotel Information
    hotel_name VARCHAR(255) NOT NULL DEFAULT 'Harar Ras Hotel',
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
