<?php
/**
 * DigitalEdgeSolutions - Environment Loader
 * Load environment variables from .env file
 */

class Environment {
    
    /**
     * Load .env file
     */
    public static function load(string $path = null): void {
        $path = $path ?? dirname(__DIR__, 2) . '/.env';
        
        if (!file_exists($path)) {
            return;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse line
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }
                
                // Set in $_ENV if not already set
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                }
                
                // Also set in $_SERVER
                if (!isset($_SERVER[$key])) {
                    $_SERVER[$key] = $value;
                }
                
                // Set as environment variable
                putenv("$key=$value");
            }
        }
    }
    
    /**
     * Get environment variable
     */
    public static function get(string $key, $default = null) {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }
    
    /**
     * Set environment variable
     */
    public static function set(string $key, string $value): void {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("$key=$value");
    }
    
    /**
     * Check if environment variable exists
     */
    public static function has(string $key): bool {
        return isset($_ENV[$key]) || isset($_SERVER[$key]) || getenv($key) !== false;
    }
    
    /**
     * Get all environment variables
     */
    public static function all(): array {
        return $_ENV;
    }
}
