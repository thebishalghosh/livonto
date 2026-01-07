-- Add security_deposit_months column to listings table
-- Default to 1 month for existing listings
ALTER TABLE listings
ADD COLUMN security_deposit_months INT DEFAULT 1 AFTER security_deposit_amount;

-- Optional: You might want to keep security_deposit_amount for backward compatibility
-- or for fixed-amount deposits if you want to support both methods.
-- For this request, we are moving to month-based, so we'll primarily use the new column.
