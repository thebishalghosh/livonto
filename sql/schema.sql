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
  owner_id INT NULL,
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
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
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
  room_type ENUM('single sharing', 'double sharing', 'triple sharing'),
  rent_per_month DECIMAL(10,2),
  total_rooms INT,
  available_rooms INT,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- VISITS
-- =====================================================
CREATE TABLE visits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  listing_id INT NOT NULL,
  visit_date DATE NOT NULL,
  status ENUM('requested','confirmed','completed','cancelled') DEFAULT 'requested',
  remarks TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- BOOKINGS
-- =====================================================
CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  listing_id INT NOT NULL,
  room_config_id INT NULL,
  checkin_date DATE,
  checkout_date DATE,
  total_amount DECIMAL(10,2) DEFAULT 0.00,
  status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  id_proof VARCHAR(1024) NULL,
  special_requests TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (room_config_id) REFERENCES room_configurations(id) ON DELETE SET NULL
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
