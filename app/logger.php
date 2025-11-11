<?php
/**
 * Application Logger
 * Handles logging to storage/logs/app.log
 */

class Logger {
    private static $logFile = null;
    private static $logDir = null;
    
    /**
     * Initialize logger
     */
    private static function init() {
        if (self::$logDir === null) {
            self::$logDir = __DIR__ . '/../storage/logs/';
            self::$logDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, self::$logDir);
            self::$logDir = rtrim(self::$logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            
            // Create logs directory if it doesn't exist
            if (!is_dir(self::$logDir)) {
                $oldUmask = umask(0);
                @mkdir(self::$logDir, 0755, true);
                umask($oldUmask);
            }
            
            self::$logFile = self::$logDir . 'app.log';
        }
    }
    
    /**
     * Write log entry
     * 
     * @param string $level Log level (ERROR, WARNING, INFO, DEBUG)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private static function write($level, $message, $context = []) {
        self::init();
        
        // Check if logging is disabled
        if (getenv('APP_LOG_ENABLED') === 'false') {
            return;
        }
        
        // Format log entry
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        // Write to log file
        if (is_writable(self::$logDir) || is_writable(self::$logFile)) {
            @file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
            @chmod(self::$logFile, 0644);
        }
        
        // Also write to PHP error log (for server monitoring)
        error_log("[{$level}] {$message}{$contextStr}");
    }
    
    /**
     * Log error
     * 
     * @param string $message Error message
     * @param array $context Additional context
     */
    public static function error($message, $context = []) {
        self::write('ERROR', $message, $context);
    }
    
    /**
     * Log warning
     * 
     * @param string $message Warning message
     * @param array $context Additional context
     */
    public static function warning($message, $context = []) {
        self::write('WARNING', $message, $context);
    }
    
    /**
     * Log info
     * 
     * @param string $message Info message
     * @param array $context Additional context
     */
    public static function info($message, $context = []) {
        self::write('INFO', $message, $context);
    }
    
    /**
     * Log debug (only if debug mode is enabled)
     * 
     * @param string $message Debug message
     * @param array $context Additional context
     */
    public static function debug($message, $context = []) {
        if (getenv('APP_DEBUG') === 'true' || !empty($_ENV['APP_DEBUG'])) {
            self::write('DEBUG', $message, $context);
        }
    }
    
    /**
     * Get log file path
     * 
     * @return string|null Log file path or null if not initialized
     */
    public static function getLogFile() {
        self::init();
        return self::$logFile;
    }
    
    /**
     * Clear log file
     * 
     * @return bool True on success, false on failure
     */
    public static function clear() {
        self::init();
        if (file_exists(self::$logFile)) {
            return @file_put_contents(self::$logFile, '') !== false;
        }
        return true;
    }
    
    /**
     * Get log file size
     * 
     * @return int File size in bytes
     */
    public static function getSize() {
        self::init();
        if (file_exists(self::$logFile)) {
            return filesize(self::$logFile);
        }
        return 0;
    }
}

