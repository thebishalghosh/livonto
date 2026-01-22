<?php
// app/config.php
// Loads environment variables from project .env (if present) and exposes base URL helpers.

if (!function_exists('app_load_env')) {
	function app_load_env($envPath)
	{
		if (!is_file($envPath) || !is_readable($envPath)) return [];
		$vars = [];
		$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '' || $line[0] === '#') continue;
			$pos = strpos($line, '=');
			if ($pos === false) continue;
			$key = trim(substr($line, 0, $pos));
			$val = trim(substr($line, $pos + 1));
			$val = trim($val, "\"' ");
			$vars[$key] = $val;
			if (!getenv($key)) putenv($key . '=' . $val);
			$_ENV[$key] = $val;
		}
		return $vars;
	}
}

// Load .env from project root
$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . DIRECTORY_SEPARATOR . '.env';
app_load_env($envFile);

// Load logger (after .env is loaded)
require_once __DIR__ . '/logger.php';

// Configure secure session settings (before any session_start())
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    
    // Use secure cookies in HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    
    // Prevent session fixation
    ini_set('session.use_strict_mode', '1');
    
    // Set session garbage collection lifetime to 30 days (for remember me)
    ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
}

// Load database connection (lazy load - only when needed)
// Database connection is initialized via Database::getInstance() when first accessed
require_once __DIR__ . '/database.php';

// Determine base URL
$baseUrl = getenv('LIVONTO_BASE_URL');

if ($baseUrl === false || $baseUrl === null) {
    // Auto-detect base URL
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';

    // Method 1: Use dirname of SCRIPT_NAME (Most reliable for standard setups)
    // SCRIPT_NAME is usually /index.php or /subdir/index.php
    $detectedBase = dirname($scriptName);

    // Normalize backslashes to slashes (Windows fix)
    $detectedBase = str_replace('\\', '/', $detectedBase);

    // Remove trailing slash if it's not just root
    if ($detectedBase !== '/') {
        $detectedBase = rtrim($detectedBase, '/');
    }

    // Handle root case
    if ($detectedBase === '.') {
        $detectedBase = '';
    }

    // Special handling: If the script is running from a subdirectory that matches a route
    // (e.g. /admin/index.php), we need to go up one level to find the app root.
    // However, our structure routes everything through root index.php, so SCRIPT_NAME
    // should be /index.php or /subdir/index.php.

    // If we are in a sub-file (like direct access to something in public/), adjust
    // But we generally route via index.php.

    $baseUrl = $detectedBase;

    // Final cleanup: ensure it doesn't end in / unless it is just /
    if ($baseUrl !== '/' && substr($baseUrl, -1) === '/') {
        $baseUrl = rtrim($baseUrl, '/');
    }

    // If baseUrl is just /, make it empty string for cleaner appending
    if ($baseUrl === '/') {
        $baseUrl = '';
    }
}

// Expose a helper to build absolute URLs relative to base
if (!function_exists('app_url')) {
	function app_url($path = '/')
	{
		global $baseUrl;
		
		// Ensure baseUrl doesn't have trailing slash
		$base = rtrim($baseUrl, '/');
		
		// Handle empty path
		if (empty($path) || $path === '/') {
			return empty($base) ? '/' : $base . '/';
		}
		
		// Separate path and query string
		$queryString = '';
		if (strpos($path, '?') !== false) {
			$parts = explode('?', $path, 2);
			$path = $parts[0];
			$queryString = '?' . $parts[1];
		}
		
		// If path is already a full URL (http:// or https://), return as is
		if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
			return $path . $queryString;
		}
		
		// Remove leading slash if present
		$path = ltrim($path, '/');
		
		// For storage paths (storage/uploads/...), don't process them - use as is
		if (strpos($path, 'storage/') === 0) {
			// Ensure we have a leading slash
			$storagePath = '/' . ltrim($path, '/');
			return (empty($base) ? '' : $base) . $storagePath . $queryString;
		}
		
		// Remove .php extension and /public/ prefix for clean URLs
		$path = str_replace('public/', '', $path);
		$path = preg_replace('/\.php$/', '', $path);
		
		// Handle special cases
		if (empty($path) || $path === 'index') {
			return (empty($base) ? '/' : $base . '/') . $queryString;
		}
		
		return (empty($base) ? '/' : $base . '/') . $path . $queryString;
	}
}

// Google OAuth Configuration
$googleClientId = getenv('GOOGLE_CLIENT_ID') ?: '';
$googleClientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: '';

// Razorpay Configuration
$razorpayKeyId = getenv('RAZORPAY_KEY_ID') ?: '';
$razorpayKeySecret = getenv('RAZORPAY_KEY_SECRET') ?: '';

// Email Configuration
$smtpFromEmail = getenv('SMTP_FROM_EMAIL') ?: 'noreply@livonto.com';
$smtpFromName = getenv('SMTP_FROM_NAME') ?: 'Livonto';
$smtpHost = getenv('SMTP_HOST') ?: '';
$smtpPort = getenv('SMTP_PORT') ?: '587';
$smtpUsername = getenv('SMTP_USERNAME') ?: '';
$smtpPassword = getenv('SMTP_PASSWORD') ?: '';
$smtpEncryption = getenv('SMTP_ENCRYPTION') ?: 'tls';

return [
	'base_url' => $baseUrl,
	'google_client_id' => $googleClientId,
	'google_client_secret' => $googleClientSecret,
	'razorpay_key_id' => $razorpayKeyId,
	'razorpay_key_secret' => $razorpayKeySecret,
	'smtp_from_email' => $smtpFromEmail,
	'smtp_from_name' => $smtpFromName,
	'smtp_host' => $smtpHost,
	'smtp_port' => $smtpPort,
	'smtp_username' => $smtpUsername,
	'smtp_password' => $smtpPassword,
	'smtp_encryption' => $smtpEncryption,
];
