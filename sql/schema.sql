-- =====================================================
-- PG MANAGEMENT SYSTEM (Simplified v2)
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- USERS TABLE
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
-- LISTINGS (PG Properties)
-- =====================================================
CREATE TABLE listings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  address VARCHAR(500),
  city VARCHAR(100),
  state VARCHAR(100),
  pincode VARCHAR(20),
  price_per_month DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  gender_allowed ENUM('male','female','unisex') DEFAULT 'unisex',
  cover_image VARCHAR(1024) DEFAULT NULL,
  status ENUM('draft','active','inactive') DEFAULT 'draft',

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- VISITS (Users requesting property visits)
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
  checkin_date DATE,
  checkout_date DATE,
  total_amount DECIMAL(10,2) DEFAULT 0.00,
  status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',

  id_proof VARCHAR(1024) NULL, -- required during booking
  special_requests TEXT NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
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
