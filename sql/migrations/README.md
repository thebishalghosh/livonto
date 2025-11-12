# Migration Scripts

## Recalculate Availability

### Purpose
This script recalculates the `available_rooms` (available beds) for all room configurations based on actual bookings in the database. This fixes existing data after implementing the bed-based availability system.

### How to Run

#### Method 1: Via Command Line (SSH/Terminal)
```bash
cd /path/to/your/project
php sql/migrations/recalculate_availability.php
```

#### Method 2: Via Web Browser
1. Upload the script to your server
2. Access it via browser: `https://yourdomain.com/sql/migrations/recalculate_availability.php`
3. Make sure to delete the file after running for security

#### Method 3: Via PHP CLI (if you have access)
```bash
php sql/migrations/recalculate_availability.php
```

### What It Does
- Finds all room configurations in the database
- Counts actual booked beds (pending + confirmed bookings) for each room
- Calculates total beds based on room type (single=1, double=2, triple=3)
- Updates `available_rooms` = total beds - booked beds
- Shows progress and summary

### Output
The script will show:
- Total room configurations found
- Progress for each room configuration
- Final summary with success/error counts

### Example Output
```
Starting availability recalculation...

Found 5 room configurations to process.

✓ Updated room_config_id 1 (Listing 7, triple sharing): 3 total beds, 1 booked, 2 available
✓ Updated room_config_id 2 (Listing 7, single sharing): 5 total beds, 0 booked, 5 available
...

========================================
Recalculation Complete!
========================================
Total configurations: 5
Successfully updated: 5
Errors: 0
```

### Important Notes
- This script is safe to run multiple times
- It only updates the `available_rooms` column
- It does not modify bookings or other data
- Make sure to backup your database before running (recommended)

