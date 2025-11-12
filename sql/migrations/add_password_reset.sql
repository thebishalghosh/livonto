-- Migration: Add password reset functionality
-- Adds password reset token and expiry columns to users table

ALTER TABLE users
ADD COLUMN password_reset_token VARCHAR(255) NULL AFTER password_hash,
ADD COLUMN password_reset_expires DATETIME NULL AFTER password_reset_token,
ADD INDEX idx_password_reset_token (password_reset_token);

