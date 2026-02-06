-- Remove UNIQUE constraint from owner_email in listings table
-- This allows one owner (email) to manage multiple listings
ALTER TABLE listings DROP INDEX unique_owner_email;
