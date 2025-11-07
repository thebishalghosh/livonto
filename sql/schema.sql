-- =====================================================
-- PG MANAGEMENT SYSTEM - FINAL DATABASE SCHEMA
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. USERS TABLE
-- =====================================================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(30),
  role ENUM('user','host','admin') NOT NULL DEFAULT 'user',
  
  -- Email verification
  email_verified_at TIMESTAMP NULL DEFAULT NULL,
  email_verification_token VARCHAR(64) NULL UNIQUE,
  
  -- Referral system
  referral_code VARCHAR(50) UNIQUE,
  referred_by INT NULL,
  referral_reward_earned DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  
  -- Wallet
  wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  
  -- Profile
  profile_image VARCHAR(1024) DEFAULT NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_role (role),
  INDEX idx_phone (phone),
  INDEX idx_referred_by (referred_by),
  INDEX idx_email_verified (email_verified_at),
  
  FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. PASSWORD RESETS
-- =====================================================
CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  
  INDEX idx_email (email),
  INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. LISTINGS (PG Properties)
-- =====================================================
CREATE TABLE listings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  host_id INT NOT NULL,
  
  -- Basic info
  title VARCHAR(255) NOT NULL,
  description TEXT,
  
  -- Location
  address VARCHAR(500),
  city VARCHAR(100),
  state VARCHAR(100),
  pincode VARCHAR(20),
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  
  -- Pricing & rules
  price_per_month DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  gender_allowed ENUM('male','female','unisex') DEFAULT 'unisex',
  
  -- Food options
  food_available ENUM('included','available','not_available') DEFAULT 'not_available',
  meal_type ENUM('veg','non_veg','both') DEFAULT 'both',
  
  -- Status & approval
  status ENUM('draft','pending','active','suspended','rejected') DEFAULT 'draft',
  approved_by INT NULL,
  approved_at TIMESTAMP NULL,
  rejection_reason TEXT NULL,
  
  -- Featured
  is_featured BOOLEAN DEFAULT FALSE,
  featured_until TIMESTAMP NULL,
  
  -- Media
  cover_image VARCHAR(1024) DEFAULT NULL,
  
  -- Statistics
  views_count INT DEFAULT 0,
  bookings_count INT DEFAULT 0,
  avg_rating DECIMAL(3,2) DEFAULT 0.00,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_city (city),
  INDEX idx_status (status),
  INDEX idx_host_id (host_id),
  INDEX idx_location (latitude, longitude),
  INDEX idx_search (city, status, gender_allowed, price_per_month),
  INDEX idx_featured (is_featured, status),
  
  FULLTEXT INDEX ft_search (title, description, address, city),
  
  FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. ROOMS (Optional breakdown per listing)
-- =====================================================
CREATE TABLE rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  title VARCHAR(150),
  total_rooms INT DEFAULT 1,
  price DECIMAL(10,2) DEFAULT NULL,
  amenities JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_listing (listing_id),
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. IMAGES
-- =====================================================
CREATE TABLE images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  url VARCHAR(1024) NOT NULL,
  alt_text VARCHAR(255) DEFAULT NULL,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_listing (listing_id),
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. AMENITIES (Master list)
-- =====================================================
CREATE TABLE amenities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  icon VARCHAR(50) DEFAULT NULL,
  category VARCHAR(50) DEFAULT 'general',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. LISTING_AMENITIES (Pivot table)
-- =====================================================
CREATE TABLE listing_amenities (
  listing_id INT NOT NULL,
  amenity_id INT NOT NULL,
  PRIMARY KEY (listing_id, amenity_id),
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. BOOKINGS
-- =====================================================
CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  room_id INT NULL,
  user_id INT NOT NULL,
  
  -- Booking details
  status ENUM('pending','confirmed','cancelled','completed','rejected') DEFAULT 'pending',
  checkin_date DATE,
  checkout_date DATE,
  total_amount DECIMAL(10,2) DEFAULT 0.00,
  
  -- Guest contact info
  guest_name VARCHAR(150) NOT NULL,
  guest_phone VARCHAR(30) NOT NULL,
  guest_email VARCHAR(255) NOT NULL,
  special_requests TEXT NULL,
  
  -- Cancellation
  cancelled_at TIMESTAMP NULL,
  cancellation_reason TEXT NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_user (user_id),
  INDEX idx_listing (listing_id),
  INDEX idx_status (status),
  INDEX idx_dates (listing_id, status, checkin_date, checkout_date),
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. PAYMENTS
-- =====================================================
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NULL,
  user_id INT NOT NULL,
  
  -- Payment details
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(10) DEFAULT 'INR',
  
  -- Payment gateway
  provider VARCHAR(100) NULL,
  provider_payment_id VARCHAR(255) NULL,
  provider_order_id VARCHAR(255) NULL,
  
  -- Status
  status ENUM('initiated','pending','success','failed','refunded') DEFAULT 'initiated',
  
  -- Metadata
  metadata JSON DEFAULT NULL,
  payment_method VARCHAR(50) NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_booking (booking_id),
  INDEX idx_user (user_id),
  INDEX idx_status (status),
  INDEX idx_provider_payment_id (provider_payment_id),
  
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. REVIEWS
-- =====================================================
CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  user_id INT NOT NULL,
  booking_id INT NULL,
  
  rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment TEXT,
  
  -- Admin moderation
  is_approved BOOLEAN DEFAULT TRUE,
  moderated_by INT NULL,
  moderated_at TIMESTAMP NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_listing (listing_id),
  INDEX idx_user (user_id),
  UNIQUE KEY unique_user_listing (user_id, listing_id),
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. REFERRALS (Tracking)
-- =====================================================
CREATE TABLE referrals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  referrer_id INT NOT NULL,
  referred_id INT NOT NULL,
  code VARCHAR(64) NOT NULL,
  
  -- Reward tracking
  reward_type ENUM('signup','first_booking','milestone') DEFAULT 'signup',
  reward_amount DECIMAL(10,2) DEFAULT 0.00,
  
  -- Status
  status ENUM('pending','credited','expired') DEFAULT 'pending',
  credited_at TIMESTAMP NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_referrer (referrer_id),
  INDEX idx_referred (referred_id),
  UNIQUE KEY unique_referral (referrer_id, referred_id),
  
  FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. TRANSACTIONS (Wallet ledger)
-- =====================================================
CREATE TABLE transactions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  
  type ENUM('credit','debit') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  
  -- Source tracking
  reason VARCHAR(255),
  source_type ENUM('referral','booking','refund','withdrawal','admin_adjustment') NULL,
  source_id INT NULL,
  
  -- Balance snapshot
  balance_before DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  balance_after DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  
  metadata JSON DEFAULT NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_user (user_id),
  INDEX idx_type (type),
  INDEX idx_source (source_type, source_id),
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 13. FAVORITES (Wishlist)
-- =====================================================
CREATE TABLE favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  listing_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_user (user_id),
  INDEX idx_listing (listing_id),
  UNIQUE KEY unique_favorite (user_id, listing_id),
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. NOTIFICATIONS
-- =====================================================
CREATE TABLE notifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  
  title VARCHAR(255) NOT NULL,
  message TEXT,
  
  type ENUM('booking','payment','referral','review','system','listing') DEFAULT 'system',
  
  -- Links
  link_type VARCHAR(50) NULL,
  link_id INT NULL,
  
  is_read BOOLEAN DEFAULT FALSE,
  read_at TIMESTAMP NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_user_read (user_id, is_read),
  INDEX idx_created (created_at),
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 15. SUPPORT TICKETS
-- =====================================================
CREATE TABLE support_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  booking_id INT NULL,
  
  subject VARCHAR(255) NOT NULL,
  description TEXT,
  
  status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
  priority ENUM('low','medium','high') DEFAULT 'medium',
  
  assigned_to INT NULL,
  resolved_at TIMESTAMP NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_user (user_id),
  INDEX idx_status (status),
  INDEX idx_assigned (assigned_to),
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 16. SUPPORT REPLIES
-- =====================================================
CREATE TABLE support_replies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  is_staff BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_ticket (ticket_id),
  
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 17. ADMIN ACTIONS (Audit log)
-- =====================================================
CREATE TABLE admin_actions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  
  action VARCHAR(255) NOT NULL,
  target_type VARCHAR(50) NULL,
  target_id INT NULL,
  
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  
  meta JSON DEFAULT NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_admin (admin_id),
  INDEX idx_target (target_type, target_id),
  INDEX idx_created (created_at),
  
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 18. SITE SETTINGS
-- =====================================================
CREATE TABLE site_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT,
  setting_type ENUM('text','number','boolean','json') DEFAULT 'text',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SEED DATA: DEFAULT AMENITIES
-- =====================================================
INSERT INTO amenities (name, icon, category) VALUES
('WiFi', 'wifi', 'basic'),
('AC', 'wind', 'comfort'),
('Parking', 'car', 'facilities'),
('TV', 'tv', 'entertainment'),
('Washing Machine', 'washing-machine', 'appliances'),
('Refrigerator', 'refrigerator', 'appliances'),
('Gym', 'dumbbell', 'facilities'),
('Swimming Pool', 'waves', 'facilities'),
('Power Backup', 'zap', 'basic'),
('Security Guard', 'shield', 'safety'),
('CCTV', 'camera', 'safety'),
('Attached Bathroom', 'droplet', 'room'),
('Balcony', 'home', 'room'),
('Study Table', 'book-open', 'furniture'),
('Wardrobe', 'cabinet', 'furniture'),
('Hot Water', 'flame', 'comfort'),
('Geyser', 'thermometer', 'comfort'),
('Laundry Service', 'shirt', 'services'),
('Housekeeping', 'broom', 'services'),
('Meal Service', 'utensils', 'food');

-- =====================================================
-- SEED DATA: DEFAULT SITE SETTINGS
-- =====================================================
INSERT INTO site_settings (setting_key, setting_value, setting_type) VALUES
('site_name', 'PG Finder', 'text'),
('site_email', 'support@pgfinder.com', 'text'),
('referral_signup_reward', '50', 'number'),
('referral_booking_reward', '100', 'number'),
('commission_percentage', '10', 'number'),
('featured_listing_price', '999', 'number'),
('max_images_per_listing', '10', 'number');

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- Active listings with host info
CREATE VIEW v_active_listings AS
SELECT 
  l.*,
  u.name as host_name,
  u.email as host_email,
  u.phone as host_phone
FROM listings l
JOIN users u ON l.host_id = u.id
WHERE l.status = 'active';

-- User booking history
CREATE VIEW v_user_bookings AS
SELECT 
  b.*,
  l.title as listing_title,
  l.city as listing_city,
  l.cover_image as listing_image,
  u.name as host_name,
  u.phone as host_phone
FROM bookings b
JOIN listings l ON b.listing_id = l.id
JOIN users u ON l.host_id = u.id;

-- =====================================================
-- END OF SCHEMA
-- =====================================================


