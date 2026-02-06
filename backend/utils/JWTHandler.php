<?php
/**
 * DigitalEdgeSolutions - JWT Handler
 * Secure JWT token generation, validation, and refresh
 */

require_once __DIR__ . '/../config/config.php';

class JWTHandler {
    
    /**
     * Generate JWT token
     */
    public static function generate(array $payload, int $expiry = null): string {
        $expiry = $expiry ?? JWT_EXPIRY;
        
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]);
        
        $time = time();
        $payload['iat'] = $time;
        $payload['exp'] = $time + $expiry;
        $payload['iss'] = JWT_ISSUER;
        $payload['aud'] = JWT_AUDIENCE;
        $payload['jti'] = bin2hex(random_bytes(16)); // Unique token ID
        
        $payloadEncoded = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadEncoded));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Generate refresh token
     */
    public static function generateRefreshToken(array $payload): string {
        return self::generate($payload, JWT_REFRESH_EXPIRY);
    }
    
    /**
     * Validate and decode JWT token
     */
    public static function validate(string $token): ?array {
        try {
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                return null;
            }
            
            list($base64Header, $base64Payload, $base64Signature) = $parts;
            
            // Verify signature
            $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
            $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            
            if (!hash_equals($expectedSignature, $base64Signature)) {
                return null;
            }
            
            // Decode payload
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload)), true);
            
            if (!$payload) {
                return null;
            }
            
            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }
            
            // Check issuer
            if (isset($payload['iss']) && $payload['iss'] !== JWT_ISSUER) {
                return null;
            }
            
            // Check audience
            if (isset($payload['aud']) && $payload['aud'] !== JWT_AUDIENCE) {
                return null;
            }
            
            return $payload;
            
        } catch (Exception $e) {
            error_log("JWT validation error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extract token from Authorization header
     */
    public static function extractFromHeader(): ?string {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        // Check $_SERVER as fallback
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Get token expiry time
     */
    public static function getExpiry(string $token): ?int {
        $payload = self::validate($token);
        return $payload['exp'] ?? null;
    }
    
    /**
     * Check if token is about to expire
     */
    public static function isExpiringSoon(string $token, int $threshold = 300): bool {
        $expiry = self::getExpiry($token);
        if (!$expiry) return true;
        
        return ($expiry - time()) < $threshold;
    }
    
    /**
     * Refresh token with new expiry
     */
    public static function refresh(string $token, int $expiry = null): ?string {
        $payload = self::validate($token);
        if (!$payload) return null;
        
        // Remove JWT-specific claims
        unset($payload['iat'], $payload['exp'], $payload['iss'], $payload['aud'], $payload['jti']);
        
        return self::generate($payload, $expiry);
    }
    
    /**
     * Blacklist token (for logout)
     */
    public static function blacklist(string $token): bool {
        $payload = self::validate($token);
        if (!$payload) return false;
        
        // Store in database or cache
        require_once __DIR__ . '/../models/SessionModel.php';
        return SessionModel::invalidateToken($payload['jti'] ?? '', $payload['exp'] ?? time());
    }
    
    /**
     * Check if token is blacklisted
     */
    public static function isBlacklisted(string $jti): bool {
        require_once __DIR__ . '/../models/SessionModel.php';
        return SessionModel::isTokenBlacklisted($jti);
    }
    
    /**
     * Generate secure random token (for password reset, email verification, etc.)
     */
    public static function generateRandomToken(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Hash token for storage
     */
    public static function hashToken(string $token): string {
        return hash('sha256', $token);
    }
}
