-- Update room_type ENUM to include '4 sharing'
ALTER TABLE room_configurations
MODIFY COLUMN room_type ENUM('single sharing', 'double sharing', 'triple sharing', '4 sharing');
