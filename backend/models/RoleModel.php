<?php
/**
 * DigitalEdgeSolutions - Role Model
 * Role and permission management
 */

require_once __DIR__ . '/../config/database.php';

class RoleModel {
    
    private static string $table = 'roles';
    private static string $permissionsTable = 'permissions';
    private static string $rolePermissionsTable = 'role_permissions';
    private static string $userRolesTable = 'user_roles';
    
    /**
     * Find role by ID
     */
    public static function findById(int $roleId): ?array {
        $sql = "SELECT * FROM " . self::$table . " WHERE role_id = ?";
        return Database::fetchOne($sql, [$roleId]);
    }
    
    /**
     * Find role by slug
     */
    public static function findBySlug(string $slug): ?array {
        $sql = "SELECT * FROM " . self::$table . " WHERE role_slug = ?";
        return Database::fetchOne($sql, [$slug]);
    }
    
    /**
     * Get all roles
     */
    public static function getAll(bool $includeSystem = true): array {
        $sql = "SELECT * FROM " . self::$table;
        
        if (!$includeSystem) {
            $sql .= " WHERE is_system = 0";
        }
        
        $sql .= " ORDER BY hierarchy_level ASC";
        
        return Database::fetchAll($sql);
    }
    
    /**
     * Create role
     */
    public static function create(array $data): int {
        $sql = "INSERT INTO " . self::$table . " (role_name, role_slug, description, hierarchy_level) VALUES (?, ?, ?, ?)";
        
        Database::query($sql, [
            $data['role_name'],
            $data['role_slug'],
            $data['description'] ?? null,
            $data['hierarchy_level'] ?? 0
        ]);
        
        return (int)Database::getInstance()->lastInsertId();
    }
    
    /**
     * Update role
     */
    public static function update(int $roleId, array $data): bool {
        $fields = [];
        $params = [];
        
        if (isset($data['role_name'])) {
            $fields[] = "role_name = ?";
            $params[] = $data['role_name'];
        }
        
        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $params[] = $data['description'];
        }
        
        if (isset($data['hierarchy_level'])) {
            $fields[] = "hierarchy_level = ?";
            $params[] = $data['hierarchy_level'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $roleId;
        $sql = "UPDATE " . self::$table . " SET " . implode(', ', $fields) . " WHERE role_id = ? AND is_system = 0";
        
        return Database::execute($sql, $params) > 0;
    }
    
    /**
     * Delete role
     */
    public static function delete(int $roleId): bool {
        $sql = "DELETE FROM " . self::$table . " WHERE role_id = ? AND is_system = 0";
        return Database::execute($sql, [$roleId]) > 0;
    }
    
    /**
     * Get role permissions
     */
    public static function getPermissions(int $roleId): array {
        $sql = "SELECT p.* FROM " . self::$permissionsTable . " p 
                JOIN " . self::$rolePermissionsTable . " rp ON p.permission_id = rp.permission_id 
                WHERE rp.role_id = ?";
        return Database::fetchAll($sql, [$roleId]);
    }
    
    /**
     * Assign permission to role
     */
    public static function assignPermission(int $roleId, int $permissionId, int $grantedBy = null): bool {
        $sql = "INSERT IGNORE INTO " . self::$rolePermissionsTable . " (role_id, permission_id, granted_by) VALUES (?, ?, ?)";
        
        try {
            Database::query($sql, [$roleId, $permissionId, $grantedBy]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Remove permission from role
     */
    public static function removePermission(int $roleId, int $permissionId): bool {
        $sql = "DELETE FROM " . self::$rolePermissionsTable . " WHERE role_id = ? AND permission_id = ?";
        return Database::execute($sql, [$roleId, $permissionId]) > 0;
    }
    
    /**
     * Get all permissions
     */
    public static function getAllPermissions(): array {
        $sql = "SELECT * FROM " . self::$permissionsTable . " ORDER BY module, action";
        return Database::fetchAll($sql);
    }
    
    /**
     * Get permissions by module
     */
    public static function getPermissionsByModule(string $module): array {
        $sql = "SELECT * FROM " . self::$permissionsTable . " WHERE module = ?";
        return Database::fetchAll($sql, [$module]);
    }
    
    /**
     * Create permission
     */
    public static function createPermission(array $data): int {
        $sql = "INSERT INTO " . self::$permissionsTable . " (permission_name, permission_slug, module, action, description) 
                VALUES (?, ?, ?, ?, ?)";
        
        Database::query($sql, [
            $data['permission_name'],
            $data['permission_slug'],
            $data['module'],
            $data['action'],
            $data['description'] ?? null
        ]);
        
        return (int)Database::getInstance()->lastInsertId();
    }
    
    /**
     * Assign role to user
     */
    public static function assignToUser(int $userId, int $roleId, int $assignedBy = null, ?string $expiresAt = null): bool {
        $sql = "INSERT INTO " . self::$userRolesTable . " (user_id, role_id, assigned_by, expires_at) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE assigned_by = ?, expires_at = ?";
        
        try {
            Database::query($sql, [$userId, $roleId, $assignedBy, $expiresAt, $assignedBy, $expiresAt]);
            return true;
        } catch (Exception $e) {
            error_log("Failed to assign role: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove role from user
     */
    public static function removeFromUser(int $userId, int $roleId): bool {
        $sql = "DELETE FROM " . self::$userRolesTable . " WHERE user_id = ? AND role_id = ?";
        return Database::execute($sql, [$userId, $roleId]) > 0;
    }
    
    /**
     * Get user roles
     */
    public static function getUserRoles(int $userId): array {
        $sql = "SELECT r.* FROM " . self::$table . " r 
                JOIN " . self::$userRolesTable . " ur ON r.role_id = ur.role_id 
                WHERE ur.user_id = ? AND (ur.expires_at IS NULL OR ur.expires_at > NOW())";
        return Database::fetchAll($sql, [$userId]);
    }
    
    /**
     * Get user role slugs
     */
    public static function getUserRoleSlugs(int $userId): array {
        $roles = self::getUserRoles($userId);
        return array_column($roles, 'role_slug');
    }
    
    /**
     * Check if user has permission
     */
    public static function hasPermission(int $userId, string $permission): bool {
        // Super admin has all permissions
        $user = Database::fetchOne("SELECT role FROM users WHERE user_id = ?", [$userId]);
        if ($user && $user['role'] === 'super_admin') {
            return true;
        }
        
        $sql = "SELECT COUNT(*) as count FROM " . self::$rolePermissionsTable . " rp 
                JOIN " . self::$userRolesTable . " ur ON rp.role_id = ur.role_id 
                JOIN " . self::$permissionsTable . " p ON rp.permission_id = p.permission_id 
                WHERE ur.user_id = ? AND p.permission_slug = ? 
                AND (ur.expires_at IS NULL OR ur.expires_at > NOW())";
        
        $result = Database::fetchOne($sql, [$userId, $permission]);
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * Check if user has role
     */
    public static function hasRole(int $userId, string $roleSlug): bool {
        $sql = "SELECT COUNT(*) as count FROM " . self::$userRolesTable . " ur 
                JOIN " . self::$table . " r ON ur.role_id = r.role_id 
                WHERE ur.user_id = ? AND r.role_slug = ? 
                AND (ur.expires_at IS NULL OR ur.expires_at > NOW())";
        
        $result = Database::fetchOne($sql, [$userId, $roleSlug]);
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * Get users by role
     */
    public static function getUsersByRole(int $roleId, int $page = 1, int $perPage = 20): array {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT u.user_id, u.email, u.first_name, u.last_name, u.profile_image, u.is_active 
                FROM users u 
                JOIN " . self::$userRolesTable . " ur ON u.user_id = ur.user_id 
                WHERE ur.role_id = ? AND u.deleted_at IS NULL 
                AND (ur.expires_at IS NULL OR ur.expires_at > NOW()) 
                LIMIT ? OFFSET ?";
        
        return Database::fetchAll($sql, [$roleId, $perPage, $offset]);
    }
    
    /**
     * Get role statistics
     */
    public static function getStatistics(): array {
        $stats = [];
        
        // Total roles
        $result = Database::fetchOne("SELECT COUNT(*) as total FROM " . self::$table);
        $stats['total_roles'] = $result['total'] ?? 0;
        
        // Users per role
        $sql = "SELECT r.role_name, COUNT(ur.user_id) as user_count 
                FROM " . self::$table . " r 
                LEFT JOIN " . self::$userRolesTable . " ur ON r.role_id = ur.role_id 
                GROUP BY r.role_id";
        $stats['users_per_role'] = Database::fetchAll($sql);
        
        return $stats;
    }
}
