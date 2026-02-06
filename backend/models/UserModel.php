<?php
/**
 * DigitalEdgeSolutions - User Model
 * User data access and manipulation
 */

require_once __DIR__ . '/../config/database.php';

class UserModel {
    
    private static string $table = 'users';
    
    /**
     * Find user by ID
     */
    public static function findById(int $userId): ?array {
        $sql = "SELECT * FROM " . self::$table . " WHERE user_id = ? AND deleted_at IS NULL";
        return Database::fetchOne($sql, [$userId]);
    }
    
    /**
     * Find user by email
     */
    public static function findByEmail(string $email): ?array {
        $sql = "SELECT * FROM " . self::$table . " WHERE email = ? AND deleted_at IS NULL";
        return Database::fetchOne($sql, [$email]);
    }
    
    /**
     * Find user by phone
     */
    public static function findByPhone(string $phone): ?array {
        $sql = "SELECT * FROM " . self::$table . " WHERE phone = ? AND deleted_at IS NULL";
        return Database::fetchOne($sql, [$phone]);
    }
    
    /**
     * Create new user
     */
    public static function create(array $data): int {
        $fields = [];
        $values = [];
        $params = [];
        
        $allowedFields = [
            'email', 'password_hash', 'role', 'first_name', 'last_name',
            'phone', 'profile_image', 'country', 'city', 'timezone', 'language',
            'date_of_birth', 'gender', 'bio', 'is_active', 'email_verified',
            'phone_verified', 'two_factor_enabled', 'two_factor_secret', 'blockchain_wallet'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = $field;
                $values[] = '?';
                $params[] = $data[$field];
            }
        }
        
        $sql = "INSERT INTO " . self::$table . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        
        return (int)Database::insert($sql, $params);
    }
    
    /**
     * Update user
     */
    public static function update(int $userId, array $data): bool {
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'email', 'password_hash', 'role', 'first_name', 'last_name',
            'phone', 'profile_image', 'country', 'city', 'timezone', 'language',
            'date_of_birth', 'gender', 'bio', 'is_active', 'email_verified',
            'phone_verified', 'two_factor_enabled', 'two_factor_secret', 'blockchain_wallet',
            'last_login', 'login_attempts', 'locked_until'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $userId;
        $sql = "UPDATE " . self::$table . " SET " . implode(', ', $fields) . " WHERE user_id = ?";
        
        return Database::execute($sql, $params) > 0;
    }
    
    /**
     * Soft delete user
     */
    public static function delete(int $userId): bool {
        $sql = "UPDATE " . self::$table . " SET deleted_at = NOW() WHERE user_id = ?";
        return Database::execute($sql, [$userId]) > 0;
    }
    
    /**
     * Hard delete user (use with caution)
     */
    public static function hardDelete(int $userId): bool {
        $sql = "DELETE FROM " . self::$table . " WHERE user_id = ?";
        return Database::execute($sql, [$userId]) > 0;
    }
    
    /**
     * Get all users with pagination and filters
     */
    public static function getAll(array $filters = [], int $page = 1, int $perPage = 20): array {
        $where = ["deleted_at IS NULL"];
        $params = [];
        
        if (!empty($filters['role'])) {
            $where[] = "role = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['is_active'])) {
            $where[] = "is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['country'])) {
            $where[] = "country = ?";
            $params[] = $filters['country'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM " . self::$table . " WHERE $whereClause";
        $countResult = Database::fetchOne($countSql, $params);
        $total = $countResult['total'] ?? 0;
        
        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT user_id, email, role, first_name, last_name, phone, profile_image, 
                       country, city, is_active, email_verified, last_login, created_at 
                FROM " . self::$table . " 
                WHERE $whereClause 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $users = Database::fetchAll($sql, $params);
        
        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }
    
    /**
     * Check if email exists
     */
    public static function emailExists(string $email, ?int $excludeUserId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM " . self::$table . " WHERE email = ? AND deleted_at IS NULL";
        $params = [$email];
        
        if ($excludeUserId) {
            $sql .= " AND user_id != ?";
            $params[] = $excludeUserId;
        }
        
        $result = Database::fetchOne($sql, $params);
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * Check if phone exists
     */
    public static function phoneExists(string $phone, ?int $excludeUserId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM " . self::$table . " WHERE phone = ? AND deleted_at IS NULL";
        $params = [$phone];
        
        if ($excludeUserId) {
            $sql .= " AND user_id != ?";
            $params[] = $excludeUserId;
        }
        
        $result = Database::fetchOne($sql, $params);
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * Update last login
     */
    public static function updateLastLogin(int $userId): bool {
        return self::update($userId, [
            'last_login' => date('Y-m-d H:i:s'),
            'login_attempts' => 0,
            'locked_until' => null
        ]);
    }
    
    /**
     * Increment login attempts
     */
    public static function incrementLoginAttempts(int $userId): bool {
        $user = self::findById($userId);
        if (!$user) return false;
        
        $attempts = ($user['login_attempts'] ?? 0) + 1;
        $lockedUntil = null;
        
        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
        }
        
        return self::update($userId, [
            'login_attempts' => $attempts,
            'locked_until' => $lockedUntil
        ]);
    }
    
    /**
     * Check if account is locked
     */
    public static function isLocked(int $userId): bool {
        $user = self::findById($userId);
        if (!$user || !$user['locked_until']) return false;
        
        return strtotime($user['locked_until']) > time();
    }
    
    /**
     * Get user statistics
     */
    public static function getStatistics(): array {
        $stats = [];
        
        // Total users
        $result = Database::fetchOne("SELECT COUNT(*) as total FROM " . self::$table . " WHERE deleted_at IS NULL");
        $stats['total_users'] = $result['total'] ?? 0;
        
        // Users by role
        $roles = Database::fetchAll("SELECT role, COUNT(*) as count FROM " . self::$table . " WHERE deleted_at IS NULL GROUP BY role");
        $stats['users_by_role'] = array_column($roles, 'count', 'role');
        
        // Active users (logged in last 30 days)
        $result = Database::fetchOne("SELECT COUNT(*) as count FROM " . self::$table . " WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND deleted_at IS NULL");
        $stats['active_users_30d'] = $result['count'] ?? 0;
        
        // New users today
        $result = Database::fetchOne("SELECT COUNT(*) as count FROM " . self::$table . " WHERE DATE(created_at) = CURDATE() AND deleted_at IS NULL");
        $stats['new_users_today'] = $result['count'] ?? 0;
        
        // New users this month
        $result = Database::fetchOne("SELECT COUNT(*) as count FROM " . self::$table . " WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND deleted_at IS NULL");
        $stats['new_users_this_month'] = $result['count'] ?? 0;
        
        return $stats;
    }
    
    /**
     * Get user profile with related data
     */
    public static function getProfile(int $userId): ?array {
        $user = self::findById($userId);
        if (!$user) return null;
        
        // Remove sensitive data
        unset($user['password_hash'], $user['two_factor_secret']);
        
        // Get user roles
        $rolesSql = "SELECT r.role_name, r.role_slug FROM roles r 
                     JOIN user_roles ur ON r.role_id = ur.role_id 
                     WHERE ur.user_id = ?";
        $user['roles'] = Database::fetchAll($rolesSql, [$userId]);
        
        // Get social accounts
        $socialSql = "SELECT provider, email FROM user_social_accounts WHERE user_id = ?";
        $user['social_accounts'] = Database::fetchAll($socialSql, [$userId]);
        
        return $user;
    }
    
    /**
     * Search users
     */
    public static function search(string $query, int $limit = 10): array {
        $sql = "SELECT user_id, email, first_name, last_name, profile_image, role 
                FROM " . self::$table . " 
                WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?) 
                AND deleted_at IS NULL 
                AND is_active = 1 
                LIMIT ?";
        
        $searchTerm = '%' . $query . '%';
        return Database::fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm, $limit]);
    }
    
    /**
     * Get users by role
     */
    public static function getByRole(string $role, int $page = 1, int $perPage = 20): array {
        return self::getAll(['role' => $role], $page, $perPage);
    }
    
    /**
     * Verify email
     */
    public static function verifyEmail(int $userId): bool {
        return self::update($userId, ['email_verified' => 1]);
    }
    
    /**
     * Verify phone
     */
    public static function verifyPhone(int $userId): bool {
        return self::update($userId, ['phone_verified' => 1]);
    }
    
    /**
     * Enable/disable 2FA
     */
    public static function setTwoFactor(int $userId, bool $enabled, ?string $secret = null): bool {
        return self::update($userId, [
            'two_factor_enabled' => $enabled ? 1 : 0,
            'two_factor_secret' => $secret
        ]);
    }
    
    /**
     * Update password
     */
    public static function updatePassword(int $userId, string $passwordHash): bool {
        return self::update($userId, ['password_hash' => $passwordHash]);
    }
    
    /**
     * Update profile image
     */
    public static function updateProfileImage(int $userId, string $imageUrl): bool {
        return self::update($userId, ['profile_image' => $imageUrl]);
    }
}
