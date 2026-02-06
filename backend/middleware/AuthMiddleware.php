<?php
/**
 * DigitalEdgeSolutions - Authentication Middleware
 * JWT validation, role checking, and security enforcement
 */

require_once __DIR__ . '/../utils/JWTHandler.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../utils/Response.php';

class AuthMiddleware {
    
    private static array $currentUser = [];
    
    /**
     * Authenticate request and validate JWT token
     */
    public static function authenticate(bool $strict = true): ?array {
        $token = JWTHandler::extractFromHeader();
        
        if (!$token) {
            if ($strict) {
                Response::error('Authentication required', 401);
            }
            return null;
        }
        
        $payload = JWTHandler::validate($token);
        
        if (!$payload) {
            if ($strict) {
                Response::error('Invalid or expired token', 401);
            }
            return null;
        }
        
        // Check if token is blacklisted
        if (isset($payload['jti']) && JWTHandler::isBlacklisted($payload['jti'])) {
            if ($strict) {
                Response::error('Token has been revoked', 401);
            }
            return null;
        }
        
        // Get fresh user data from database
        if (isset($payload['sub'])) {
            $user = UserModel::findById($payload['sub']);
            
            if (!$user || !$user['is_active']) {
                if ($strict) {
                    Response::error('User account is inactive or deleted', 401);
                }
                return null;
            }
            
            self::$currentUser = $user;
            return $user;
        }
        
        if ($strict) {
            Response::error('Invalid token payload', 401);
        }
        return null;
    }
    
    /**
     * Check if user has required role
     */
    public static function requireRole(array $allowedRoles): void {
        $user = self::authenticate();
        
        if (!in_array($user['role'], $allowedRoles)) {
            Response::error('Access denied. Insufficient permissions.', 403);
        }
    }
    
    /**
     * Check if user has required permission
     */
    public static function requirePermission(string $permission): void {
        $user = self::authenticate();
        
        require_once __DIR__ . '/../models/RoleModel.php';
        
        if (!RoleModel::hasPermission($user['user_id'], $permission)) {
            Response::error('Access denied. Missing required permission: ' . $permission, 403);
        }
    }
    
    /**
     * Check if user is admin or higher
     */
    public static function requireAdmin(): void {
        self::requireRole(['super_admin', 'admin', 'sub_admin']);
    }
    
    /**
     * Check if user is super admin
     */
    public static function requireSuperAdmin(): void {
        self::requireRole(['super_admin']);
    }
    
    /**
     * Get current authenticated user
     */
    public static function getCurrentUser(): ?array {
        if (empty(self::$currentUser)) {
            return self::authenticate(false);
        }
        return self::$currentUser;
    }
    
    /**
     * Get current user ID
     */
    public static function getCurrentUserId(): ?int {
        $user = self::getCurrentUser();
        return $user['user_id'] ?? null;
    }
    
    /**
     * Check if user owns resource
     */
    public static function requireOwnership(int $resourceOwnerId): void {
        $user = self::authenticate();
        
        if ($user['user_id'] != $resourceOwnerId && !in_array($user['role'], ['super_admin', 'admin'])) {
            Response::error('Access denied. You do not own this resource.', 403);
        }
    }
    
    /**
     * Check if user can access user data
     */
    public static function canAccessUser(int $targetUserId): bool {
        $user = self::getCurrentUser();
        
        if (!$user) return false;
        
        // User can access own data
        if ($user['user_id'] == $targetUserId) return true;
        
        // Admins can access all data
        if (in_array($user['role'], ['super_admin', 'admin'])) return true;
        
        // Sub-admins can access their department
        if ($user['role'] === 'sub_admin') {
            // Add department check logic here
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCsrf(string $token): bool {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCsrf(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Check rate limit for IP
     */
    public static function checkRateLimit(string $identifier = null, int $maxRequests = null, int $window = null): bool {
        $identifier = $identifier ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $maxRequests = $maxRequests ?? RATE_LIMIT_REQUESTS;
        $window = $window ?? RATE_LIMIT_WINDOW;
        
        require_once __DIR__ . '/../utils/Cache.php';
        
        $key = 'rate_limit:' . $identifier;
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $maxRequests) {
            return false;
        }
        
        Cache::set($key, $attempts + 1, $window);
        return true;
    }
    
    /**
     * Apply security headers
     */
    public static function applySecurityHeaders(): void {
        foreach (SECURITY_HEADERS as $header => $value) {
            header("$header: $value");
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://unpkg.com; ";
        $csp .= "font-src 'self' https://fonts.gstatic.com; ";
        $csp .= "img-src 'self' data: https: blob:; ";
        $csp .= "media-src 'self' https: blob:; ";
        $csp .= "connect-src 'self' https: wss:; ";
        $csp .= "frame-ancestors 'none'; ";
        $csp .= "base-uri 'self'; ";
        $csp .= "form-action 'self';";
        
        header("Content-Security-Policy: $csp");
    }
    
    /**
     * Handle CORS preflight
     */
    public static function handleCORS(): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = explode(',', CORS_ALLOWED_ORIGINS);
        
        if (CORS_ALLOWED_ORIGINS === '*' || in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: " . (CORS_ALLOWED_ORIGINS === '*' ? '*' : $origin));
        }
        
        header("Access-Control-Allow-Methods: " . CORS_ALLOWED_METHODS);
        header("Access-Control-Allow-Headers: " . CORS_ALLOWED_HEADERS);
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: " . CORS_MAX_AGE);
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
    
    /**
     * Log authentication attempt
     */
    public static function logAuthAttempt(string $action, bool $success, array $details = []): void {
        require_once __DIR__ . '/../models/AuditLogModel.php';
        
        AuditLogModel::create([
            'user_id' => $details['user_id'] ?? null,
            'action' => $action,
            'entity_type' => 'authentication',
            'entity_id' => $details['user_id'] ?? null,
            'new_values' => json_encode([
                'success' => $success,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'details' => $details
            ])
        ]);
    }
}
