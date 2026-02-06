<?php
/**
 * DigitalEdgeSolutions - Session Model
 * Manage user sessions and tokens
 */

require_once __DIR__ . '/../config/database.php';

class SessionModel {
    
    private static string $table = 'user_sessions';
    private static string $tokensTable = 'verification_tokens';
    
    /**
     * Create new session
     */
    public static function create(array $data): string {
        $sql = "INSERT INTO " . self::$table . " 
                (session_id, user_id, token, refresh_token, ip_address, user_agent, device_info, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $sessionId = bin2hex(random_bytes(32));
        
        Database::query($sql, [
            $sessionId,
            $data['user_id'],
            $data['token'],
            $data['refresh_token'] ?? null,
            $data['ip_address'] ?? null,
            $data['user_agent'] ?? null,
            isset($data['device_info']) ? json_encode($data['device_info']) : null,
            $data['expires_at']
        ]);
        
        return $sessionId;
    }
    
    /**
     * Find session by token
     */
    public static function findByToken(string $token): ?array {
        $sql = "SELECT * FROM " . self::$table . " WHERE token = ? AND is_valid = 1 AND expires_at > NOW()";
        return Database::fetchOne($sql, [$token]);
    }
    
    /**
     * Find session by refresh token
     */
    public static function findByRefreshToken(string $refreshToken): ?array {
        $sql = "SELECT * FROM " . self::$table . " WHERE refresh_token = ? AND is_valid = 1";
        return Database::fetchOne($sql, [$refreshToken]);
    }
    
    /**
     * Invalidate session
     */
    public static function invalidate(string $sessionId): bool {
        $sql = "UPDATE " . self::$table . " SET is_valid = 0 WHERE session_id = ?";
        return Database::execute($sql, [$sessionId]) > 0;
    }
    
    /**
     * Invalidate token
     */
    public static function invalidateToken(string $jti, int $expiry): bool {
        // Store in blacklist
        $sql = "INSERT INTO token_blacklist (jti, expires_at) VALUES (?, FROM_UNIXTIME(?))";
        try {
            Database::query($sql, [$jti, $expiry]);
            return true;
        } catch (Exception $e) {
            error_log("Failed to blacklist token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if token is blacklisted
     */
    public static function isTokenBlacklisted(string $jti): bool {
        $sql = "SELECT COUNT(*) as count FROM token_blacklist WHERE jti = ? AND expires_at > NOW()";
        $result = Database::fetchOne($sql, [$jti]);
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * Invalidate all user sessions
     */
    public static function invalidateAllUserSessions(int $userId, string $exceptToken = null): bool {
        $sql = "UPDATE " . self::$table . " SET is_valid = 0 WHERE user_id = ?";
        $params = [$userId];
        
        if ($exceptToken) {
            $sql .= " AND token != ?";
            $params[] = $exceptToken;
        }
        
        return Database::execute($sql, $params) > 0;
    }
    
    /**
     * Store verification token
     */
    public static function storeVerificationToken(int $userId, string $type, string $token, int $expiryHours = 24): bool {
        // Create table if not exists
        self::createVerificationTokensTable();
        
        $sql = "INSERT INTO verification_tokens (user_id, token, type, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))";
        
        try {
            Database::query($sql, [$userId, $token, $type, $expiryHours]);
            return true;
        } catch (Exception $e) {
            error_log("Failed to store verification token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get verification token
     */
    public static function getVerificationToken(string $token, string $type): ?array {
        self::createVerificationTokensTable();
        
        $sql = "SELECT * FROM verification_tokens WHERE token = ? AND type = ? AND used = 0 AND expires_at > NOW()";
        return Database::fetchOne($sql, [$token, $type]);
    }
    
    /**
     * Get verification token by user
     */
    public static function getVerificationTokenByUser(int $userId, string $type): ?array {
        self::createVerificationTokensTable();
        
        $sql = "SELECT * FROM verification_tokens WHERE user_id = ? AND type = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1";
        return Database::fetchOne($sql, [$userId, $type]);
    }
    
    /**
     * Invalidate verification token
     */
    public static function invalidateVerificationToken(string $token): bool {
        self::createVerificationTokensTable();
        
        $sql = "UPDATE verification_tokens SET used = 1 WHERE token = ?";
        return Database::execute($sql, [$token]) > 0;
    }
    
    /**
     * Create verification tokens table
     */
    private static function createVerificationTokensTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS verification_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            used TINYINT(1) DEFAULT 0,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_user_type (user_id, type),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            Database::query($sql);
        } catch (Exception $e) {
            // Table might already exist
        }
        
        // Also create token_blacklist table
        $sql2 = "CREATE TABLE IF NOT EXISTS token_blacklist (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            jti VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_jti (jti),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            Database::query($sql2);
        } catch (Exception $e) {
            // Table might already exist
        }
    }
    
    /**
     * Clean up expired sessions and tokens
     */
    public static function cleanup(): void {
        // Delete expired sessions
        $sql = "DELETE FROM " . self::$table . " WHERE expires_at < NOW() - INTERVAL 7 DAY";
        Database::query($sql);
        
        // Delete expired verification tokens
        $sql = "DELETE FROM verification_tokens WHERE expires_at < NOW() - INTERVAL 1 DAY";
        Database::query($sql);
        
        // Delete expired blacklisted tokens
        $sql = "DELETE FROM token_blacklist WHERE expires_at < NOW()";
        Database::query($sql);
    }
    
    /**
     * Get active sessions for user
     */
    public static function getUserSessions(int $userId): array {
        $sql = "SELECT session_id, ip_address, user_agent, created_at, last_activity, expires_at 
                FROM " . self::$table . " 
                WHERE user_id = ? AND is_valid = 1 AND expires_at > NOW() 
                ORDER BY last_activity DESC";
        return Database::fetchAll($sql, [$userId]);
    }
    
    /**
     * Update last activity
     */
    public static function updateLastActivity(string $sessionId): bool {
        $sql = "UPDATE " . self::$table . " SET last_activity = NOW() WHERE session_id = ?";
        return Database::execute($sql, [$sessionId]) > 0;
    }
}
