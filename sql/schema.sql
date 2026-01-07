-- =====================================================
-- PG MANAGEMENT SYSTEM (Final Schema v3)
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- USERS
-- =====================================================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NULL,  -- null for Google login
  phone VARCHAR(20) NULL,
  gender ENUM('male', 'female', 'other') NULL,  -- added gender field
  role ENUM('user', 'admin') DEFAULT 'user',

  -- Google login
  google_id VARCHAR(255) NULL UNIQUE,

  -- Referral system
  referral_code VARCHAR(50) UNIQUE,
  referred_by INT NULL,

  -- Profile info
  profile_image VARCHAR(1024) DEFAULT NULL,
  id_proof VARCHAR(1024) DEFAULT NULL,
  address TEXT NULL,
  city VARCHAR(100) NULL,
  state VARCHAR(100) NULL,
  pincode VARCHAR(20) NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- LISTINGS (Basic Property Details)
-- =====================================================
CREATE TABLE listings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_name VARCHAR(150) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  available_for ENUM('boys', 'girls', 'both') DEFAULT 'both',
  preferred_tenants ENUM('anyone', 'students', 'working professionals', 'family') DEFAULT 'anyone',
  security_deposit_amount VARCHAR(50) DEFAULT 'No Deposit',
  notice_period INT DEFAULT 0,
  gender_allowed ENUM('male','female','unisex') DEFAULT 'unisex',
  cover_image VARCHAR(1024) DEFAULT NULL,
  status ENUM('draft','active','inactive') DEFAULT 'draft',

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- LOCATION DETAILS
-- =====================================================
CREATE TABLE listing_locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  complete_address TEXT,
  city VARCHAR(100),
  pin_code VARCHAR(10),
  google_maps_link TEXT,
  latitude DECIMAL(10,7),
  longitude DECIMAL(10,7),
  nearby_landmarks JSON,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- AMENITIES
-- =====================================================
CREATE TABLE amenities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_amenities (
  listing_id INT,
  amenity_id INT,
  PRIMARY KEY (listing_id, amenity_id),
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- HOUSE RULES
-- =====================================================
CREATE TABLE house_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_rules (
  listing_id INT,
  rule_id INT,
  PRIMARY KEY (listing_id, rule_id),
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (rule_id) REFERENCES house_rules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- ADDITIONAL INFO
-- =====================================================
CREATE TABLE listing_additional_info (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT,
  electricity_charges ENUM('included', 'as per usage', 'as per usage of AC', 'separate meter'),
  food_availability ENUM('vegetarian', 'non-vegetarian', 'both', 'not available'),
  gate_closing_time TIME NULL,
  total_beds INT DEFAULT 0,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- ROOM CONFIGURATIONS
-- =====================================================
CREATE TABLE room_configurations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT,
  room_type ENUM('single sharing', 'double sharing', 'triple sharing', '4 sharing'),
  rent_per_month DECIMAL(10,2),
  total_rooms INT,
  available_rooms INT,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- VISIT BOOKINGS (replaces old 'visits' table)
-- =====================================================
-- Note: The old 'visits' table has been replaced by 'visit_bookings'
-- which has more features (preferred_time, message, admin_notes, etc.)


-- =====================================================
-- USER KYC (Know Your Customer)
-- =====================================================
CREATE TABLE user_kyc (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  document_type ENUM('aadhar', 'pan', 'passport', 'driving_license', 'voter_id', 'other') NOT NULL,
  document_number VARCHAR(100) NOT NULL,
  document_front VARCHAR(1024) NULL,
  document_back VARCHAR(1024) NULL,
  status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
  verified_by INT NULL,
  verified_at TIMESTAMP NULL,
  rejection_reason TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- BOOKINGS
-- =====================================================
CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  listing_id INT NOT NULL,
  room_config_id INT NULL,
  booking_start_date DATE NOT NULL,
  total_amount DECIMAL(10,2) DEFAULT 0.00,
  status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  kyc_id INT NULL,
  agreed_to_tnc BOOLEAN DEFAULT FALSE,
  tnc_accepted_at TIMESTAMP NULL,
  guardian_kyc_id INT NULL,
  guardian_noc VARCHAR(1024) NULL,
  special_requests TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (room_config_id) REFERENCES room_configurations(id) ON DELETE SET NULL,
  FOREIGN KEY (kyc_id) REFERENCES user_kyc(id) ON DELETE SET NULL,
  FOREIGN KEY (guardian_kyc_id) REFERENCES user_kyc(id) ON DELETE SET NULL,
  INDEX idx_booking_start_date (booking_start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- PAYMENTS
-- =====================================================
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  provider VARCHAR(50),
  provider_payment_id VARCHAR(255),
  status ENUM('initiated','success','failed') DEFAULT 'initiated',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- INVOICES
-- =====================================================
CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(50) UNIQUE NOT NULL,
  booking_id INT NOT NULL,
  payment_id INT NOT NULL,
  user_id INT NOT NULL,
  invoice_date DATE NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  status ENUM('draft','sent','paid','cancelled') DEFAULT 'sent',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_invoice_number (invoice_number),
  INDEX idx_booking_id (booking_id),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- REVIEWS
-- =====================================================
CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  listing_id INT NOT NULL,
  rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_listing (user_id, listing_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- REFERRALS
-- =====================================================
CREATE TABLE referrals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  referrer_id INT NOT NULL,
  referred_id INT NOT NULL,
  code VARCHAR(64) NOT NULL,
  status ENUM('pending','credited') DEFAULT 'pending',
  reward_amount DECIMAL(10,2) DEFAULT 0.00,
  credited_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_referral (referrer_id, referred_id),
  FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- ADMIN ACTIONS
-- =====================================================
CREATE TABLE admin_actions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  action VARCHAR(255),
  target_type VARCHAR(50),
  target_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- SITE SETTINGS
-- =====================================================
CREATE TABLE site_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) UNIQUE,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================
-- LISTING IMAGES TABLE
-- =====================================================
-- This table stores multiple images for each listing
CREATE TABLE IF NOT EXISTS listing_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  image_path VARCHAR(1024) NOT NULL,
  image_order INT DEFAULT 0,
  is_cover TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  INDEX idx_listing_id (listing_id),
  INDEX idx_image_order (image_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contacts table for storing contact form submissions
CREATE TABLE IF NOT EXISTS contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new', 'read', 'replied') DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VISIT BOOKINGS
-- =====================================================
-- Stores visit/site visit requests for PG listings
-- All user information (name, email, phone, etc.) comes from users table via user_id
CREATE TABLE IF NOT EXISTS visit_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    user_id INT NOT NULL,
    preferred_date DATE NOT NULL,
    preferred_time TIME NOT NULL,
    message TEXT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_listing_id (listing_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_preferred_date (preferred_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alter bookings table to use monthly booking system
-- Remove checkin_date and checkout_date
-- Add booking_start_date (start date of booking)
-- The booking will be for 1 month from booking_start_date

-- First, add the new column
ALTER TABLE bookings 
ADD COLUMN booking_start_date DATE NULL AFTER room_config_id;

-- Update existing records to use checkin_date as booking_start_date if it exists
UPDATE bookings 
SET booking_start_date = checkin_date 
WHERE booking_start_date IS NULL AND checkin_date IS NOT NULL;

-- Now drop old columns
ALTER TABLE bookings 
DROP COLUMN checkin_date,
DROP COLUMN checkout_date;

-- Make booking_start_date NOT NULL now that we've populated it
ALTER TABLE bookings 
MODIFY COLUMN booking_start_date DATE NOT NULL;

-- Add index for booking_start_date
CREATE INDEX idx_booking_start_date ON bookings(booking_start_date);

-- Migration: Add owner email and password to listings table
-- This allows owners to login and manage their listings

ALTER TABLE listings 
ADD COLUMN owner_email VARCHAR(255) NULL AFTER owner_name,
ADD COLUMN owner_password_hash VARCHAR(255) NULL AFTER owner_email,
ADD UNIQUE KEY unique_owner_email (owner_email);

-- Note: owner_email is unique to prevent duplicate owner accounts
-- owner_password_hash can be NULL for existing listings without owner accounts

-- =====================================================
-- Migration: Add duration_months and gst_amount columns
-- =====================================================
-- This migration adds the duration_months and gst_amount columns
-- to the bookings and payments tables for production server
-- =====================================================

-- Add duration_months to bookings table
-- Position: After booking_start_date
ALTER TABLE bookings 
ADD COLUMN duration_months INT NULL DEFAULT 1 
AFTER booking_start_date;

-- Add gst_amount to bookings table
-- Position: After total_amount
ALTER TABLE bookings 
ADD COLUMN gst_amount DECIMAL(10,2) NULL DEFAULT 0.00 
AFTER total_amount;

-- Add gst_amount to payments table
-- Position: After amount
ALTER TABLE payments 
ADD COLUMN gst_amount DECIMAL(10,2) NULL DEFAULT 0.00 
AFTER amount;

-- =====================================================
-- Verification queries (run these to verify the columns were added)
-- =====================================================
-- DESCRIBE bookings;
-- DESCRIBE payments;
-- =====================================================

