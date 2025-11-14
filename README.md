# Livonto - PG Management System

## 📋 Table of Contents

1. [Project Overview](#project-overview)
2. [Architecture](#architecture)
3. [Directory Structure](#directory-structure)
4. [Database Schema](#database-schema)
5. [Setup Instructions](#setup-instructions)
6. [Configuration](#configuration)
7. [Key Features & Flows](#key-features--flows)
8. [API Endpoints](#api-endpoints)
9. [Authentication System](#authentication-system)
10. [Payment Flow](#payment-flow)
11. [Referral System](#referral-system)
12. [Admin Panel](#admin-panel)
13. [Owner Panel](#owner-panel)
14. [Development Guidelines](#development-guidelines)
15. [Troubleshooting](#troubleshooting)

---

## 🎯 Project Overview

**Livonto** is a comprehensive PG (Paying Guest) accommodation management system that allows users to browse, book, and manage PG accommodations. The system includes:

- **User Portal**: Browse listings, book visits, make reservations, manage profile
- **Admin Panel**: Manage listings, users, bookings, payments, referrals, and system settings
- **Owner Panel**: Property owners can manage their listings and availability
- **Payment Integration**: Razorpay payment gateway for secure transactions
- **Referral System**: Users can refer friends and earn rewards
- **Email Notifications**: Automated emails for bookings, payments, and status updates

### Technology Stack

- **Backend**: PHP 7.4+ (Procedural with OOP patterns)
- **Database**: MySQL 5.7+ / MariaDB 10.2+
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla), Bootstrap 5.3
- **Payment Gateway**: Razorpay API
- **Email**: PHPMailer with SMTP support
- **PDF Generation**: DomPDF
- **OAuth**: Google Sign-In
- **Dependencies**: Composer (vlucas/phpdotenv, dompdf/dompdf, phpmailer/phpmailer)

---

## 🏗️ Architecture

### MVC-like Structure

The application follows a **routing-based architecture** with separation of concerns:

```
index.php (Router)
    ↓
Routes to appropriate handlers:
    - public/*.php (User-facing pages)
    - admin/*.php (Admin panel)
    - owner/*.php (Owner panel)
    - app/*.php (API endpoints & business logic)
```

### Key Components

1. **Router** (`index.php`): Handles clean URLs and routes requests
2. **Config** (`app/config.php`): Loads environment variables and configuration
3. **Database** (`app/database.php`): Singleton PDO connection handler
4. **Functions** (`app/functions.php`): Common utility functions
5. **Email Helper** (`app/email_helper.php`): Email sending functionality
6. **Logger** (`app/logger.php`): Application logging

### Request Flow

```
User Request → index.php (Router)
    ↓
Route Matching (public/admin/owner)
    ↓
Load config.php → Load functions.php
    ↓
Check Authentication (if required)
    ↓
Execute Page Logic
    ↓
Render View (HTML + PHP)
```

---

## 📁 Directory Structure

```
Livonto/
├── admin/                    # Admin panel pages
│   ├── index.php             # Admin dashboard
│   ├── login.php             # Admin login
│   ├── listings_*.php         # Listing management
│   ├── users_*.php           # User management
│   ├── bookings_manage.php   # Booking management
│   ├── payments_manage.php   # Payment management
│   ├── referrals_manage.php  # Referral management
│   └── settings.php          # Site settings
│
├── app/                      # Core application logic
│   ├── config.php            # Configuration loader
│   ├── database.php          # Database singleton
│   ├── functions.php         # Helper functions
│   ├── email_helper.php      # Email functionality
│   ├── logger.php            # Logging system
│   ├── login_action.php      # Login handler
│   ├── register_action.php   # Registration handler
│   ├── google_auth_callback.php  # Google OAuth callback
│   ├── book_api.php          # Booking API
│   ├── razorpay_api.php      # Razorpay order creation
│   ├── razorpay_callback.php # Payment callback handler
│   ├── invoice_generator.php  # Invoice generation
│   ├── visit_book_api.php    # Visit booking API
│   └── includes/             # Shared includes
│       ├── header.php        # Public header
│       ├── footer.php        # Public footer
│       ├── admin_header.php  # Admin header
│       └── send_booking_status_mail.php  # Booking emails
│
├── public/                   # User-facing pages
│   ├── index.php             # Homepage
│   ├── listings.php          # Listing search/browse
│   ├── listing_detail.php    # Property details
│   ├── book.php              # Booking page
│   ├── payment.php           # Payment page
│   ├── profile.php           # User profile
│   ├── login.php             # User login
│   ├── refer.php             # Referral page
│   └── assets/               # CSS, JS, images
│
├── owner/                    # Owner panel
│   ├── login.php             # Owner login
│   ├── dashboard.php         # Owner dashboard
│   └── listings/             # Listing management
│
├── sql/                      # Database schema
│   └── schema.sql            # Complete database schema
│
├── storage/                  # File storage
│   ├── uploads/              # User uploads (KYC, profiles, listings)
│   ├── invoices/             # Generated PDF invoices
│   └── logs/                 # Application logs
│
├── vendor/                   # Composer dependencies
├── index.php                 # Main router
├── composer.json             # PHP dependencies
└── .env                      # Environment variables (create from template)
```

---

## 🗄️ Database Schema

### Core Tables

#### Users
- **users**: User accounts (customers, admins)
  - Supports email/password and Google OAuth
  - Includes referral system fields
  - Profile information (name, email, phone, address, etc.)

#### Listings
- **listings**: Property/PG listings
- **listing_locations**: Location details (address, coordinates, landmarks)
- **listing_images**: Multiple images per listing
- **listing_amenities**: Many-to-many relationship with amenities
- **listing_rules**: Many-to-many relationship with house rules
- **listing_additional_info**: Additional property details
- **room_configurations**: Room types, pricing, availability

#### Bookings
- **bookings**: Booking records
  - Status: `pending`, `confirmed`, `cancelled`, `completed`
  - Includes `duration_months` and `gst_amount`
  - Links to KYC documents
- **payments**: Payment records
  - Status: `initiated`, `success`, `failed`
  - Includes `gst_amount`
  - Razorpay payment IDs
- **invoices**: Generated invoices
- **user_kyc**: KYC document storage

#### Referrals
- **referrals**: Referral tracking
  - Status: `pending`, `credited`
  - `reward_amount`: Admin-managed reward amount
  - Links referrer and referred user

#### Other Tables
- **amenities**: Available amenities (WiFi, AC, etc.)
- **house_rules**: House rules (No smoking, etc.)
- **visit_bookings**: Visit/site tour bookings
- **enquiries**: General enquiries
- **reviews**: Property reviews and ratings
- **site_settings**: System configuration

### Key Relationships

```
users (1) ──→ (N) bookings
listings (1) ──→ (N) bookings
bookings (1) ──→ (N) payments
bookings (1) ──→ (1) invoices
users (1) ──→ (N) referrals (as referrer)
users (1) ──→ (N) referrals (as referred)
```

---

## 🚀 Setup Instructions

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Apache/Nginx web server
- Composer
- SSL certificate (for production)

### Step 1: Clone/Download Project

```bash
# If using Git
git clone <repository-url>
cd Livonto

# Or extract ZIP file to web root
```

### Step 2: Install Dependencies

```bash
composer install
```

This installs:
- `vlucas/phpdotenv` - Environment variable management
- `dompdf/dompdf` - PDF generation
- `phpmailer/phpmailer` - Email sending

### Step 3: Database Setup

1. Create MySQL database:
```sql
CREATE DATABASE livonto_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import schema:
```bash
mysql -u root -p livonto_db < sql/schema.sql
```

Or via phpMyAdmin: Import `sql/schema.sql`

### Step 4: Environment Configuration

Create `.env` file in project root:

```env
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=livonto_db
DB_USER=root
DB_PASS=your_password
DB_CHARSET=utf8mb4

# Application URL
LIVONTO_BASE_URL=/Livonto

# Google OAuth (Optional)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret

# Razorpay Payment Gateway
RAZORPAY_KEY_ID=your_razorpay_key_id
RAZORPAY_KEY_SECRET=your_razorpay_key_secret

# SMTP Email Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_ENCRYPTION=tls
SMTP_FROM_EMAIL=noreply@livonto.com
SMTP_FROM_NAME=Livonto
```

### Step 5: Web Server Configuration

#### Apache (.htaccess)

Ensure mod_rewrite is enabled. The project should work with default Apache configuration.

#### Nginx

```nginx
location / {
    try_files $uri $uri/ /index.php?url=$uri&$args;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

### Step 6: File Permissions

```bash
# Make storage directories writable
chmod -R 755 storage/
chmod -R 755 storage/uploads/
chmod -R 755 storage/invoices/
chmod -R 755 storage/logs/
```

### Step 7: Create Admin User

```sql
INSERT INTO users (name, email, password_hash, role, referral_code)
VALUES (
    'Admin User',
    'admin@livonto.com',
    '$2y$10$YourHashedPasswordHere',
    'admin',
    'ADMIN001'
);
```

Generate password hash:
```php
echo password_hash('your_password', PASSWORD_DEFAULT);
```

### Step 8: Verify Installation

1. Visit: `http://localhost/Livonto/` (or your configured URL)
2. Login to admin panel: `http://localhost/Livonto/admin/login`
3. Check database connection
4. Test email sending (if SMTP configured)

---

## ⚙️ Configuration

### Environment Variables

All configuration is done via `.env` file. Key variables:

| Variable | Description | Required |
|----------|-------------|----------|
| `DB_HOST` | Database host | Yes |
| `DB_NAME` | Database name | Yes |
| `DB_USER` | Database user | Yes |
| `DB_PASS` | Database password | Yes |
| `LIVONTO_BASE_URL` | Base URL path | Yes |
| `GOOGLE_CLIENT_ID` | Google OAuth client ID | No |
| `GOOGLE_CLIENT_SECRET` | Google OAuth secret | No |
| `RAZORPAY_KEY_ID` | Razorpay key ID | Yes (for payments) |
| `RAZORPAY_KEY_SECRET` | Razorpay secret | Yes (for payments) |
| `SMTP_HOST` | SMTP server | Yes (for emails) |
| `SMTP_USERNAME` | SMTP username | Yes (for emails) |
| `SMTP_PASSWORD` | SMTP password | Yes (for emails) |

### Site Settings (Admin Panel)

Additional settings managed via Admin Panel → Settings:

- Site name, logo, contact info
- GST settings (enabled/disabled, percentage)
- Email templates
- Referral reward amounts
- Other system preferences

---

## 🔑 Key Features & Flows

### 1. User Registration Flow

```
User fills registration form
    ↓
POST to /register-action
    ↓
Validate input (name, email, password, referral code)
    ↓
Check email uniqueness
    ↓
Validate referral code (if provided)
    ↓
Generate unique referral code for new user
    ↓
Hash password
    ↓
Begin Transaction
    ↓
Insert user into database
    ↓
Create referral record (if referred)
    ↓
Commit Transaction
    ↓
Send welcome email to user
    ↓
Send admin notification
    ↓
Auto-login user
    ↓
Redirect to profile page
```

**Files:**
- `public/register.php` - Registration form
- `app/register_action.php` - Registration handler
- `app/email_helper.php` - Welcome email function

### 2. Authentication Flow

#### Email/Password Login

```
User submits login form
    ↓
POST to /login-action
    ↓
Validate email and password
    ↓
Query user from database
    ↓
Verify password_hash
    ↓
Check role (if admin login)
    ↓
Regenerate session ID (security)
    ↓
Set session variables
    ↓
Handle "Remember Me" (30-day cookie)
    ↓
Redirect to appropriate dashboard
```

**Files:**
- `public/login.php` - Login form
- `app/login_action.php` - Login handler

#### Google OAuth Login

```
User clicks "Sign in with Google"
    ↓
Redirect to Google OAuth
    ↓
User authorizes
    ↓
Google redirects to /google-auth-callback
    ↓
Exchange code for access token
    ↓
Fetch user info from Google
    ↓
Check if user exists (by email or google_id)
    ↓
If new user:
    - Create account
    - Generate referral code
    - Create referral record (if referred)
    - Send welcome email
    ↓
If existing user:
    - Update profile image (if needed)
    ↓
Set session variables
    ↓
Redirect to profile
```

**Files:**
- `public/login.php` - Google OAuth button
- `app/google_auth_callback.php` - OAuth callback handler

### 3. Booking Flow

```
User browses listings
    ↓
User views listing details
    ↓
User clicks "Book Now"
    ↓
Redirect to /book?id={listing_id}
    ↓
Check if user is logged in
    ↓
Step 1: KYC Upload (if not already done)
    - Upload ID proof front & back
    - Submit KYC
    ↓
Step 2: Booking Details
    - Select room type
    - Select start date
    - Enter duration (months)
    - Enter special requests
    - Accept terms & conditions
    ↓
POST to /book-api
    ↓
Validate booking data
    ↓
Calculate security deposit
    ↓
Calculate GST (if enabled)
    ↓
Begin Transaction
    ↓
Create booking record (status: 'pending')
    ↓
Create payment record (status: 'initiated')
    ↓
Send admin notification
    ↓
Commit Transaction
    ↓
Return booking ID
    ↓
Redirect to /payment?booking_id={id}
    ↓
Display payment summary
    ↓
User clicks "Pay Now"
    ↓
POST to /razorpay-api
    ↓
Create Razorpay order
    ↓
Return order details
    ↓
User completes payment on Razorpay
    ↓
Razorpay redirects to /razorpay-callback
    ↓
Verify payment signature
    ↓
Update payment status to 'success'
    ↓
Update booking status to 'confirmed'
    ↓
Decrease room availability
    ↓
Generate invoice
    ↓
Send confirmation emails (user & admin)
    ↓
Redirect to invoice page
```

**Files:**
- `public/listings.php` - Browse listings
- `public/listing_detail.php` - Property details
- `public/book.php` - Booking form
- `app/book_api.php` - Booking creation
- `public/payment.php` - Payment page
- `app/razorpay_api.php` - Razorpay order creation
- `app/razorpay_callback.php` - Payment verification
- `app/invoice_generator.php` - Invoice generation

### 4. Visit Booking Flow

```
User views listing
    ↓
User clicks "Book a Visit"
    ↓
Redirect to /visit-book?id={listing_id}
    ↓
User fills visit form:
    - Preferred date
    - Preferred time
    - Message (optional)
    ↓
POST to /visit-book-api
    ↓
Validate visit data
    ↓
Create visit_booking record
    ↓
Send admin notification
    ↓
Return success message
```

**Files:**
- `public/visit_book.php` - Visit booking form
- `app/visit_book_api.php` - Visit booking handler

### 5. Referral System Flow

```
User registers with referral code
    ↓
System validates referral code
    ↓
Creates referral record (status: 'pending')
    ↓
User completes booking
    ↓
Admin reviews referral in admin panel
    ↓
Admin credits reward (sets amount manually)
    ↓
Referral status changes to 'credited'
    ↓
Admin transfers money manually (outside system)
```

**Files:**
- `app/register_action.php` - Referral code validation
- `public/refer.php` - User referral dashboard
- `admin/referrals_manage.php` - Admin referral management

---

## 🔌 API Endpoints

### Public APIs

| Endpoint | Method | Description | Auth Required |
|----------|--------|-------------|---------------|
| `/login-action` | POST | User/Admin login | No |
| `/register-action` | POST | User registration | No |
| `/google-auth-callback` | GET | Google OAuth callback | No |
| `/book-api` | POST | Create booking | Yes |
| `/visit-book-api` | POST | Create visit booking | Yes |
| `/razorpay-api` | POST | Create Razorpay order | Yes |
| `/razorpay-callback` | POST | Payment callback | No |
| `/invoice-api` | GET | Get invoice data | Yes |
| `/change-password-api` | POST | Change password | Yes |
| `/profile-update` | POST | Update profile | Yes |
| `/listing-images-api` | GET | Get listing images | No |
| `/listings-search-api` | GET | Search listings | No |
| `/listings-map-api` | GET | Map listings | No |
| `/reviews-api` | GET/POST | Get/Submit reviews | GET: No, POST: Yes |

### Response Format

**Success:**
```json
{
    "success": true,
    "message": "Operation successful",
    "data": { ... }
}
```

**Error:**
```json
{
    "success": false,
    "message": "Error message",
    "errors": { ... }
}
```

---

## 🔐 Authentication System

### User Roles

1. **user**: Regular customer
   - Can browse listings
   - Can book accommodations
   - Can manage profile
   - Can refer friends

2. **admin**: Administrator
   - Full system access
   - Manage listings, users, bookings
   - Manage payments and referrals
   - System settings

3. **owner**: Property owner (separate login)
   - Manage own listings
   - View bookings for own properties
   - Update availability

### Session Management

- Sessions use secure cookies (HttpOnly, SameSite)
- Session regeneration on login (prevents fixation)
- "Remember Me" extends session to 30 days
- Session timeout: 30 days (if remember me) or browser close

### Authentication Checks

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

// Check if owner
if (!isOwnerLoggedIn()) {
    header('Location: ' . app_url('owner/login'));
    exit;
}
```

---

## 💳 Payment Flow

### Razorpay Integration

1. **Order Creation** (`app/razorpay_api.php`)
   - Creates Razorpay order with amount
   - Returns order ID and key

2. **Payment Processing**
   - User completes payment on Razorpay checkout
   - Razorpay processes payment

3. **Callback Verification** (`app/razorpay_callback.php`)
   - Verifies payment signature
   - Updates payment status
   - Confirms booking
   - Generates invoice
   - Sends emails

### Payment States

- `initiated`: Payment order created
- `success`: Payment completed
- `failed`: Payment failed

### Booking States

- `pending`: Awaiting payment
- `confirmed`: Payment received, booking active
- `cancelled`: Booking cancelled
- `completed`: Booking duration ended

---

## 🎁 Referral System

### How It Works

1. **User Registration**
   - User can enter referral code during registration
   - System validates code and creates referral record

2. **Referral Tracking**
   - Each user gets unique referral code
   - Referrals tracked in `referrals` table
   - Status: `pending` → `credited`

3. **Reward Management**
   - Admin manually sets reward amount when crediting
   - Admin can edit reward amount anytime
   - Money transfer done manually (outside system)

### Referral Benefits

- **Referrer**: Gets ₹1,500 (configurable) when referral completes booking
- **Referred User**: Gets ₹500 off on first booking

### Admin Functions

- View all referrals
- Credit rewards (set amount manually)
- Edit reward amounts
- Filter by status

---

## 👨‍💼 Admin Panel

### Access

URL: `/admin/login`

### Features

1. **Dashboard** (`/admin`)
   - Statistics overview
   - Recent activities

2. **Listings Management** (`/admin/listings`)
   - Add/Edit/Delete listings
   - Manage images
   - Set availability
   - Manage room configurations

3. **Users Management** (`/admin/users`)
   - View all users
   - View user details
   - Manage user accounts

4. **Bookings Management** (`/admin/bookings`)
   - View all bookings
   - Update booking status
   - Add admin notes
   - Filter by status

5. **Payments Management** (`/admin/payments`)
   - View payment history
   - Filter by status
   - Payment details

6. **Referrals Management** (`/admin/referrals`)
   - View all referrals
   - Credit rewards
   - Edit reward amounts

7. **Settings** (`/admin/settings`)
   - Site configuration
   - Email settings
   - GST settings
   - Other preferences

---

## 🏠 Owner Panel

### Access

URL: `/owner/login`

### Features

1. **Dashboard** (`/owner/dashboard`)
   - Overview of own listings
   - Booking statistics

2. **Listing Management** (`/owner/listings/edit`)
   - Edit own listings
   - Update availability
   - View bookings

### Authentication

- Separate login system
- Uses `owner_email` and `owner_password_hash` from `listings` table
- Session variable: `owner_logged_in`

---

## 💻 Development Guidelines

### Code Style

- **PHP**: PSR-12 inspired (procedural with OOP where needed)
- **Indentation**: 4 spaces
- **Naming**: camelCase for functions, snake_case for variables
- **Comments**: PHPDoc for functions

### Database Access

Always use prepared statements:

```php
$db = db();
$user = $db->fetchOne(
    "SELECT * FROM users WHERE id = ?",
    [$userId]
);
```

### Error Handling

```php
try {
    // Code
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    // Handle error
}
```

### Security Best Practices

1. **Input Validation**: Always validate and sanitize user input
2. **SQL Injection**: Use prepared statements (always)
3. **XSS Prevention**: Use `htmlspecialchars()` for output
4. **CSRF**: Consider adding CSRF tokens (currently not implemented)
5. **Session Security**: Secure session configuration in `config.php`
6. **File Uploads**: Validate file types and sizes
7. **Password Hashing**: Use `password_hash()` and `password_verify()`

### Adding New Features

1. **New Page**: Add route in `index.php`, create file in appropriate directory
2. **New API**: Create file in `app/`, return JSON using `jsonSuccess()` or `jsonError()`
3. **New Database Table**: Add to `sql/schema.sql`, document in this README
4. **New Email**: Add function to `app/email_helper.php`

### Logging

```php
require_once __DIR__ . '/logger.php';

Logger::info("User logged in", ['user_id' => $userId]);
Logger::error("Payment failed", ['booking_id' => $bookingId]);
```

Logs are stored in `storage/logs/app.log`

---

## 🐛 Troubleshooting

### Common Issues

#### 1. Database Connection Error

**Symptoms**: "Database connection failed"

**Solutions**:
- Check `.env` file has correct database credentials
- Verify MySQL service is running
- Check database exists
- Verify user has proper permissions

#### 2. 404 Errors on Routes

**Symptoms**: Pages return 404

**Solutions**:
- Check `LIVONTO_BASE_URL` in `.env`
- Verify `.htaccess` is present (Apache)
- Check mod_rewrite is enabled (Apache)
- Verify Nginx configuration (if using Nginx)

#### 3. Email Not Sending

**Symptoms**: No emails received

**Solutions**:
- Check SMTP credentials in `.env`
- Verify SMTP server allows connections
- Check firewall settings
- Review `storage/logs/app.log` for errors
- Test with Gmail App Password (if using Gmail)

#### 4. Payment Not Working

**Symptoms**: Razorpay errors

**Solutions**:
- Verify Razorpay keys in `.env`
- Check Razorpay account is active
- Verify callback URL is correct
- Check payment signature verification

#### 5. File Upload Issues

**Symptoms**: Uploads fail

**Solutions**:
- Check `storage/uploads/` permissions (755)
- Verify PHP `upload_max_filesize` and `post_max_size`
- Check disk space
- Verify file type validation

#### 6. Session Issues

**Symptoms**: Logged out frequently

**Solutions**:
- Check session storage permissions
- Verify session configuration in `config.php`
- Check server session timeout settings
- Clear browser cookies

### Debug Mode

Enable error display (development only):

```php
// In app/config.php (temporary)
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### Log Files

- Application logs: `storage/logs/app.log`
- PHP error log: Check server error log
- Database errors: Check application logs

---

## 📝 Additional Notes

### Database Migrations

The system includes auto-migration logic in `app/book_api.php` for `duration_months` and `gst_amount` columns. For production, run migrations manually:

```sql
ALTER TABLE bookings 
ADD COLUMN duration_months INT NULL DEFAULT 1 AFTER booking_start_date;

ALTER TABLE bookings 
ADD COLUMN gst_amount DECIMAL(10,2) NULL DEFAULT 0.00 AFTER total_amount;

ALTER TABLE payments 
ADD COLUMN gst_amount DECIMAL(10,2) NULL DEFAULT 0.00 AFTER amount;
```

### Cron Jobs

Set up cron job for expired bookings (optional):

```bash
# Run daily at midnight
0 0 * * * php /path/to/Livonto/app/complete_expired_bookings.php
```

### Backup Strategy

1. **Database**: Regular MySQL dumps
2. **Files**: Backup `storage/uploads/` and `storage/invoices/`
3. **Configuration**: Backup `.env` file (securely)

### Performance Optimization

1. **Database Indexing**: Key fields are indexed
2. **Image Optimization**: Consider compressing uploaded images
3. **Caching**: Consider implementing Redis/Memcached for sessions
4. **CDN**: Use CDN for static assets in production

---

## 📞 Support

For issues or questions:

1. Check this documentation
2. Review log files
3. Check GitHub issues (if applicable)
4. Contact development team

---

## 📄 License

[Specify your license here]

---

## 🔄 Version History

- **v1.0** - Initial release
  - User registration and authentication
  - Listing management
  - Booking system
  - Payment integration
  - Referral system
  - Admin panel
  - Email notifications

---

**Last Updated**: [Current Date]

**Maintained By**: [Your Name/Team]

