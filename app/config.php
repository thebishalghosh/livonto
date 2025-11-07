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
		$path = $path ?: '/';
		
		// Remove leading slash if present
		$path = ltrim($path, '/');
		
		// Remove .php extension and /public/ prefix for clean URLs
		$path = str_replace('public/', '', $path);
		$path = preg_replace('/\.php$/', '', $path);
		
		// Handle special cases
		if (empty($path) || $path === 'index') {
			return $baseUrl . '/';
		}
		
		return $baseUrl . '/' . $path;
	}
}

return [
	'base_url' => $baseUrl,
];
