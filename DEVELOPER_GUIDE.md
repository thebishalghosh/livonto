# Developer Quick Reference Guide

## 🚀 Quick Start

### 1. Initial Setup (5 minutes)

```bash
# 1. Install dependencies
composer install

# 2. Create .env file (copy from .env.example)
cp .env.example .env

# 3. Update .env with your database credentials
# Edit .env file

# 4. Import database
mysql -u root -p livonto_db < sql/schema.sql

# 5. Set permissions
chmod -R 755 storage/

# 6. Create admin user (see README.md)
```

### 2. Common Tasks

#### Adding a New Route

1. Open `index.php`
2. Add route to appropriate array:
   ```php
   $routes = [
       'my-new-page' => 'public/my_new_page.php',
   ];
   ```
3. Create the file in appropriate directory

#### Creating a New API Endpoint

1. Create file in `app/` directory: `app/my_api.php`
2. Set JSON header:
   ```php
   header('Content-Type: application/json');
   ```
3. Return JSON:
   ```php
   jsonSuccess('Success message', ['data' => $data]);
   // or
   jsonError('Error message', ['field' => 'error'], 400);
   ```

#### Adding a Database Query

```php
$db = db();

// Fetch one row
$user = $db->fetchOne(
    "SELECT * FROM users WHERE id = ?",
    [$userId]
);

// Fetch all rows
$users = $db->fetchAll(
    "SELECT * FROM users WHERE role = ?",
    ['user']
);

// Execute query (INSERT/UPDATE/DELETE)
$db->execute(
    "UPDATE users SET name = ? WHERE id = ?",
    [$name, $userId]
);

// Get last insert ID
$newId = $db->lastInsertId();
```

#### Sending an Email

```php
require_once __DIR__ . '/email_helper.php';

sendEmail(
    $toEmail,
    $subject,
    $htmlMessage,
    $fromEmail,  // optional
    $fromName    // optional
);
```

#### Checking Authentication

```php
// Check if logged in
if (!isLoggedIn()) {
    header('Location: ' . app_url('login'));
    exit;
}

// Check if admin
if (!isAdmin()) {
    header('Location: ' . app_url('admin/login'));
    exit;
}
```

#### Building URLs

```php
// Simple URL
app_url('profile')  // → /Livonto/profile

// With query string
app_url('book?id=123')  // → /Livonto/book?id=123

// Storage path
app_url('storage/uploads/image.jpg')  // → /Livonto/storage/uploads/image.jpg
```

---

## 📁 File Organization

### Where to Put Code

| Type | Location | Example |
|------|----------|---------|
| User-facing page | `public/` | `public/profile.php` |
| Admin page | `admin/` | `admin/users_manage.php` |
| Owner page | `owner/` | `owner/dashboard.php` |
| API endpoint | `app/` | `app/book_api.php` |
| Helper function | `app/functions.php` | Add to existing file |
| Email template | `app/email_helper.php` | Add function |
| Database query | Same file or `app/functions.php` | Inline or helper |

---

## 🔑 Key Functions Reference

### Database

```php
db()                    // Get database instance
db()->fetchOne()        // Get single row
db()->fetchAll()        // Get all rows
db()->fetchValue()      // Get single value
db()->execute()         // Execute query
db()->lastInsertId()    // Get last insert ID
db()->beginTransaction() // Start transaction
db()->commit()          // Commit transaction
db()->rollback()        // Rollback transaction
```

### Authentication

```php
isLoggedIn()            // Check if user logged in
isAdmin()               // Check if user is admin
isOwnerLoggedIn()       // Check if owner logged in
requireLogin()          // Redirect if not logged in
requireAdmin()          // Redirect if not admin
requireOwnerLogin()     // Redirect if not owner
getCurrentUserId()      // Get current user ID
```

### Utilities

```php
sanitize($input)        // Sanitize string
isValidEmail($email)    // Validate email
app_url($path)          // Build URL
jsonSuccess($msg, $data) // JSON success response
jsonError($msg, $errors, $code) // JSON error response
getSetting($key, $default) // Get site setting
```

### Email

```php
sendEmail($to, $subject, $message)  // Send email
sendWelcomeEmail($email, $name, $code, $referred) // Welcome email
sendAdminNotification($subject, $title, $message, $details) // Admin email
sendInvoiceEmail($invoiceId, $email, $name) // Invoice email
```

---

## 🗄️ Database Schema Quick Reference

### Main Tables

- `users` - User accounts
- `listings` - Property listings
- `bookings` - Booking records
- `payments` - Payment records
- `referrals` - Referral tracking
- `invoices` - Generated invoices
- `visit_bookings` - Visit bookings
- `reviews` - Property reviews

### Common Queries

```sql
-- Get user with profile
SELECT * FROM users WHERE id = ?;

-- Get listing with location
SELECT l.*, loc.* 
FROM listings l 
LEFT JOIN listing_locations loc ON l.id = loc.listing_id 
WHERE l.id = ?;

-- Get booking with details
SELECT b.*, u.name, l.title 
FROM bookings b 
JOIN users u ON b.user_id = u.id 
JOIN listings l ON b.listing_id = l.id 
WHERE b.id = ?;

-- Get payment for booking
SELECT * FROM payments 
WHERE booking_id = ? 
ORDER BY id DESC 
LIMIT 1;
```

---

## 🔄 Common Workflows

### User Registration

1. Form submission → `app/register_action.php`
2. Validation
3. Create user
4. Create referral (if code provided)
5. Send welcome email
6. Auto-login

### Booking Creation

1. User fills form → `public/book.php`
2. Submit → `app/book_api.php`
3. Validate data
4. Create booking (pending)
5. Create payment (initiated)
6. Redirect to payment page

### Payment Processing

1. User clicks pay → `app/razorpay_api.php`
2. Create Razorpay order
3. User pays on Razorpay
4. Callback → `app/razorpay_callback.php`
5. Verify payment
6. Update booking (confirmed)
7. Generate invoice
8. Send emails

---

## 🐛 Debugging Tips

### Enable Error Display

```php
// At top of file (development only)
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### Check Logs

```php
// Application logs
tail -f storage/logs/app.log

// PHP error log (check server config)
```

### Debug Database Queries

```php
// Add to app/database.php temporarily
error_log("SQL: " . $sql);
error_log("Params: " . print_r($params, true));
```

### Test Email

```php
// Create test file: test_email.php
require __DIR__ . '/app/config.php';
require __DIR__ . '/app/email_helper.php';

$result = sendEmail(
    'test@example.com',
    'Test Email',
    '<h1>Test</h1><p>This is a test email.</p>'
);

var_dump($result);
```

---

## 📝 Code Examples

### Complete API Endpoint

```php
<?php
// app/my_api.php

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load config and functions
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    jsonError('Authentication required', [], 401);
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Invalid request method', [], 405);
}

try {
    $db = db();
    $userId = getCurrentUserId();
    
    // Get input
    $data = json_decode(file_get_contents('php://input'), true);
    $name = trim($data['name'] ?? '');
    
    // Validate
    if (empty($name)) {
        jsonError('Name is required', ['name' => 'Name is required'], 400);
    }
    
    // Process
    $db->execute(
        "UPDATE users SET name = ? WHERE id = ?",
        [$name, $userId]
    );
    
    // Return success
    jsonSuccess('Name updated successfully', [
        'name' => $name
    ]);
    
} catch (Exception $e) {
    error_log("Error in my_api.php: " . $e->getMessage());
    jsonError('An error occurred', [], 500);
}
```

### Complete Page with Authentication

```php
<?php
// public/my_page.php

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load config and functions
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

// Require login
requireLogin();

$userId = getCurrentUserId();

// Get data
try {
    $db = db();
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE id = ?",
        [$userId]
    );
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    header('Location: ' . app_url('profile'));
    exit;
}

// Include header
require __DIR__ . '/../app/includes/header.php';
?>

<div class="container">
    <h1>My Page</h1>
    <p>Welcome, <?= htmlspecialchars($user['name']) ?>!</p>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
```

---

## 🔒 Security Checklist

- [ ] All user input validated
- [ ] All database queries use prepared statements
- [ ] All output escaped with `htmlspecialchars()`
- [ ] File uploads validated (type, size)
- [ ] Authentication checked on protected pages
- [ ] Passwords hashed with `password_hash()`
- [ ] Session security configured
- [ ] Error messages don't expose sensitive info
- [ ] SQL queries don't expose in errors

---

## 📚 Additional Resources

- **Full Documentation**: See `README.md`
- **Database Schema**: See `sql/schema.sql`
- **Configuration**: See `.env.example`
- **Logs**: `storage/logs/app.log`

---

**Quick Help**: Check `README.md` for detailed information on any topic.

