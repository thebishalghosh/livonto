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
	// fallback to default subdir name if running under xampp
	$baseUrl = '/Livonto';
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
			return $base . '/';
		}
		
		// Separate path and query string
		$queryString = '';
		if (strpos($path, '?') !== false) {
			$parts = explode('?', $path, 2);
			$path = $parts[0];
			$queryString = '?' . $parts[1];
		}
		
		// Remove leading slash if present
		$path = ltrim($path, '/');
		
		// Remove .php extension and /public/ prefix for clean URLs
		$path = str_replace('public/', '', $path);
		$path = preg_replace('/\.php$/', '', $path);
		
		// Handle special cases
		if (empty($path) || $path === 'index') {
			return $base . '/' . $queryString;
		}
		
		return $base . '/' . $path . $queryString;
	}
}

return [
	'base_url' => $baseUrl,
];
