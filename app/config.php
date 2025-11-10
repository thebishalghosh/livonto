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
if (!$baseUrl) {
	// Auto-detect base URL from document root
	$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
	$requestUri = $_SERVER['REQUEST_URI'] ?? '';
	$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
	$projectRoot = dirname(__DIR__);
	
	// Get the directory of index.php relative to document root
	$scriptDir = dirname($scriptName);
	
	// Method 1: Check if REQUEST_URI contains a subdirectory path (like /Livonto/)
	if (preg_match('#^/([^/]+)/#', $requestUri, $matches)) {
		$potentialBase = '/' . $matches[1];
		// Verify this directory exists in document root and contains index.php
		if (is_dir($documentRoot . $potentialBase) && file_exists($documentRoot . $potentialBase . '/index.php')) {
			$baseUrl = $potentialBase;
		}
	}
	
	// Method 2: Calculate from project root vs document root
	if (empty($baseUrl)) {
		// Get relative path from document root to project root
		$relativePath = str_replace($documentRoot, '', $projectRoot);
		$relativePath = str_replace('\\', '/', $relativePath);
		$relativePath = trim($relativePath, '/');
		
		if (!empty($relativePath)) {
			$baseUrl = '/' . $relativePath;
		} else {
			$baseUrl = '';
		}
	}
	
	// Method 3: If not detected, try from SCRIPT_NAME
	if (empty($baseUrl) || $baseUrl === '/') {
		if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
			$baseUrl = '';
		} else {
			$baseUrl = rtrim($scriptDir, '/');
		}
	}
	
	// Final fallback: check for known subdirectory in REQUEST_URI
	if (empty($baseUrl) && strpos($requestUri, '/Livonto/') !== false) {
		$baseUrl = '/Livonto';
	}
}
$baseUrl = rtrim($baseUrl, '/');

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

return [
	'base_url' => $baseUrl,
	'google_client_id' => $googleClientId,
	'google_client_secret' => $googleClientSecret,
	'razorpay_key_id' => $razorpayKeyId,
	'razorpay_key_secret' => $razorpayKeySecret,
];
