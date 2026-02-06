<?php
/**
 * DigitalEdgeSolutions - Authentication Controller
 * Handles login, registration, logout, password reset, 2FA
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/JWTHandler.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../utils/EmailService.php';
require_once __DIR__ . '/../../models/UserModel.php';
require_once __DIR__ . '/../../models/SessionModel.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

class AuthController {
    
    /**
     * User registration
     */
    public function register(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate input
            $validator = new Validator($data);
            $validator->required(['email', 'password', 'first_name', 'last_name'])
                     ->email('email')
                     ->minLength('password', PASSWORD_MIN_LENGTH)
                     ->maxLength('first_name', 100)
                     ->maxLength('last_name', 100);
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            // Check if email exists
            if (UserModel::emailExists($data['email'])) {
                Response::error('Email already registered', 409);
            }
            
            // Check rate limit
            if (!AuthMiddleware::checkRateLimit('register_' . ($_SERVER['REMOTE_ADDR'] ?? ''), 5, 3600)) {
                Response::error('Too many registration attempts. Please try again later.', 429);
            }
            
            // Hash password
            $passwordHash = password_hash($data['password'], PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS);
            
            // Create user
            $userData = [
                'email' => strtolower(trim($data['email'])),
                'password_hash' => $passwordHash,
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'phone' => $data['phone'] ?? null,
                'role' => $data['role'] ?? 'student',
                'country' => $data['country'] ?? null,
                'is_active' => 1,
                'email_verified' => 0
            ];
            
            $userId = UserModel::create($userData);
            
            if (!$userId) {
                Response::serverError('Failed to create user account');
            }
            
            // Generate email verification token
            $verificationToken = JWTHandler::generateRandomToken();
            SessionModel::storeVerificationToken($userId, 'email_verification', $verificationToken);
            
            // Send welcome email with verification
            $verificationUrl = APP_URL . '/verify-email?token=' . $verificationToken;
            EmailService::sendTemplate('welcome_email', $userData['email'], [
                'first_name' => $userData['first_name'],
                'verification_url' => $verificationUrl,
                'login_url' => APP_URL . '/login'
            ]);
            
            // Generate JWT tokens
            $tokens = $this->generateTokens($userId, $userData['email'], $userData['role']);
            
            // Log the registration
            AuthMiddleware::logAuthAttempt('register', true, ['user_id' => $userId]);
            
            Response::success([
                'user' => [
                    'id' => $userId,
                    'email' => $userData['email'],
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'role' => $userData['role'],
                    'email_verified' => false
                ],
                'tokens' => $tokens,
                'message' => 'Registration successful. Please verify your email.'
            ], 'User registered successfully', 201);
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            Response::serverError('Registration failed. Please try again.');
        }
    }
    
    /**
     * User login
     */
    public function login(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate input
            $validator = new Validator($data);
            $validator->required(['email', 'password'])->email('email');
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            // Check rate limit
            if (!AuthMiddleware::checkRateLimit('login_' . ($_SERVER['REMOTE_ADDR'] ?? ''), LOGIN_MAX_ATTEMPTS, 900)) {
                Response::error('Too many login attempts. Please try again later.', 429);
            }
            
            // Find user
            $user = UserModel::findByEmail($data['email']);
            
            if (!$user) {
                AuthMiddleware::logAuthAttempt('login', false, ['email' => $data['email']]);
                Response::error('Invalid email or password', 401);
            }
            
            // Check if account is locked
            if (UserModel::isLocked($user['user_id'])) {
                $lockedUntil = strtotime($user['locked_until']);
                $minutes = ceil(($lockedUntil - time()) / 60);
                Response::error("Account is locked. Please try again in {$minutes} minutes.", 423);
            }
            
            // Verify password
            if (!password_verify($data['password'], $user['password_hash'])) {
                UserModel::incrementLoginAttempts($user['user_id']);
                AuthMiddleware::logAuthAttempt('login', false, ['user_id' => $user['user_id']]);
                Response::error('Invalid email or password', 401);
            }
            
            // Check if account is active
            if (!$user['is_active']) {
                Response::error('Account is deactivated. Please contact support.', 403);
            }
            
            // Check if 2FA is enabled
            if ($user['two_factor_enabled']) {
                $tempToken = JWTHandler::generate([
                    'sub' => $user['user_id'],
                    'email' => $user['email'],
                    'type' => '2fa_pending'
                ], 300); // 5 minutes
                
                Response::success([
                    'two_factor_required' => true,
                    'temp_token' => $tempToken
                ], 'Two-factor authentication required');
            }
            
            // Update last login
            UserModel::updateLastLogin($user['user_id']);
            
            // Generate tokens
            $tokens = $this->generateTokens($user['user_id'], $user['email'], $user['role']);
            
            // Store session
            SessionModel::create([
                'user_id' => $user['user_id'],
                'token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'expires_at' => date('Y-m-d H:i:s', time() + JWT_EXPIRY)
            ]);
            
            // Log successful login
            AuthMiddleware::logAuthAttempt('login', true, ['user_id' => $user['user_id']]);
            
            Response::success([
                'user' => [
                    'id' => $user['user_id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $user['role'],
                    'profile_image' => $user['profile_image'],
                    'email_verified' => (bool)$user['email_verified']
                ],
                'tokens' => $tokens
            ], 'Login successful');
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            Response::serverError('Login failed. Please try again.');
        }
    }
    
    /**
     * Verify 2FA and complete login
     */
    public function verify2FA(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator($data);
            $validator->required(['temp_token', 'code']);
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            // Validate temp token
            $payload = JWTHandler::validate($data['temp_token']);
            
            if (!$payload || ($payload['type'] ?? '') !== '2fa_pending') {
                Response::error('Invalid or expired session', 401);
            }
            
            $userId = $payload['sub'];
            $user = UserModel::findById($userId);
            
            if (!$user) {
                Response::error('User not found', 404);
            }
            
            // Verify 2FA code
            require_once __DIR__ . '/../../utils/TwoFactorAuth.php';
            
            if (!TwoFactorAuth::verify($user['two_factor_secret'], $data['code'])) {
                Response::error('Invalid verification code', 401);
            }
            
            // Update last login
            UserModel::updateLastLogin($userId);
            
            // Generate tokens
            $tokens = $this->generateTokens($userId, $user['email'], $user['role']);
            
            // Store session
            SessionModel::create([
                'user_id' => $userId,
                'token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'expires_at' => date('Y-m-d H:i:s', time() + JWT_EXPIRY)
            ]);
            
            Response::success([
                'user' => [
                    'id' => $user['user_id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $user['role'],
                    'profile_image' => $user['profile_image']
                ],
                'tokens' => $tokens
            ], 'Two-factor authentication successful');
            
        } catch (Exception $e) {
            error_log("2FA verification error: " . $e->getMessage());
            Response::serverError('Verification failed. Please try again.');
        }
    }
    
    /**
     * Logout user
     */
    public function logout(): void {
        try {
            $token = JWTHandler::extractFromHeader();
            
            if ($token) {
                JWTHandler::blacklist($token);
            }
            
            Response::success([], 'Logout successful');
            
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            Response::success([], 'Logout successful');
        }
    }
    
    /**
     * Refresh access token
     */
    public function refresh(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['refresh_token'])) {
                Response::error('Refresh token required', 400);
            }
            
            $payload = JWTHandler::validate($data['refresh_token']);
            
            if (!$payload) {
                Response::error('Invalid or expired refresh token', 401);
            }
            
            $userId = $payload['sub'];
            $user = UserModel::findById($userId);
            
            if (!$user || !$user['is_active']) {
                Response::error('User not found or inactive', 401);
            }
            
            // Generate new tokens
            $tokens = $this->generateTokens($userId, $user['email'], $user['role']);
            
            Response::success([
                'tokens' => $tokens
            ], 'Token refreshed successfully');
            
        } catch (Exception $e) {
            error_log("Token refresh error: " . $e->getMessage());
            Response::serverError('Token refresh failed');
        }
    }
    
    /**
     * Forgot password
     */
    public function forgotPassword(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator($data);
            $validator->required(['email'])->email('email');
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            $user = UserModel::findByEmail($data['email']);
            
            // Always return success to prevent email enumeration
            if (!$user) {
                Response::success([], 'If the email exists, a reset link has been sent');
            }
            
            // Check rate limit
            if (!AuthMiddleware::checkRateLimit('forgot_' . $user['user_id'], 3, 3600)) {
                Response::success([], 'If the email exists, a reset link has been sent');
            }
            
            // Generate reset token
            $resetToken = JWTHandler::generateRandomToken();
            SessionModel::storeVerificationToken($user['user_id'], 'password_reset', $resetToken);
            
            // Send reset email
            $resetUrl = APP_URL . '/reset-password?token=' . $resetToken;
            EmailService::sendTemplate('password_reset', $user['email'], [
                'first_name' => $user['first_name'],
                'reset_url' => $resetUrl
            ]);
            
            Response::success([], 'If the email exists, a reset link has been sent');
            
        } catch (Exception $e) {
            error_log("Forgot password error: " . $e->getMessage());
            Response::success([], 'If the email exists, a reset link has been sent');
        }
    }
    
    /**
     * Reset password
     */
    public function resetPassword(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator($data);
            $validator->required(['token', 'password'])
                     ->minLength('password', PASSWORD_MIN_LENGTH);
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            // Verify token
            $tokenData = SessionModel::getVerificationToken($data['token'], 'password_reset');
            
            if (!$tokenData) {
                Response::error('Invalid or expired reset token', 400);
            }
            
            $userId = $tokenData['user_id'];
            
            // Update password
            $passwordHash = password_hash($data['password'], PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS);
            UserModel::updatePassword($userId, $passwordHash);
            
            // Invalidate token
            SessionModel::invalidateVerificationToken($data['token']);
            
            // Invalidate all sessions
            SessionModel::invalidateAllUserSessions($userId);
            
            Response::success([], 'Password reset successful. Please login with your new password.');
            
        } catch (Exception $e) {
            error_log("Reset password error: " . $e->getMessage());
            Response::serverError('Password reset failed');
        }
    }
    
    /**
     * Verify email
     */
    public function verifyEmail(): void {
        try {
            $token = $_GET['token'] ?? '';
            
            if (empty($token)) {
                Response::error('Verification token required', 400);
            }
            
            $tokenData = SessionModel::getVerificationToken($token, 'email_verification');
            
            if (!$tokenData) {
                Response::error('Invalid or expired verification token', 400);
            }
            
            // Update user
            UserModel::verifyEmail($tokenData['user_id']);
            
            // Invalidate token
            SessionModel::invalidateVerificationToken($token);
            
            Response::success([], 'Email verified successfully');
            
        } catch (Exception $e) {
            error_log("Email verification error: " . $e->getMessage());
            Response::serverError('Email verification failed');
        }
    }
    
    /**
     * Resend verification email
     */
    public function resendVerification(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator($data);
            $validator->required(['email'])->email('email');
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            $user = UserModel::findByEmail($data['email']);
            
            if (!$user || $user['email_verified']) {
                Response::success([], 'If applicable, a verification email has been sent');
            }
            
            // Check rate limit
            if (!AuthMiddleware::checkRateLimit('verify_' . $user['user_id'], 3, 3600)) {
                Response::success([], 'If applicable, a verification email has been sent');
            }
            
            // Generate new token
            $verificationToken = JWTHandler::generateRandomToken();
            SessionModel::storeVerificationToken($user['user_id'], 'email_verification', $verificationToken);
            
            // Send email
            $verificationUrl = APP_URL . '/verify-email?token=' . $verificationToken;
            EmailService::sendTemplate('welcome_email', $user['email'], [
                'first_name' => $user['first_name'],
                'verification_url' => $verificationUrl
            ]);
            
            Response::success([], 'If applicable, a verification email has been sent');
            
        } catch (Exception $e) {
            error_log("Resend verification error: " . $e->getMessage());
            Response::success([], 'If applicable, a verification email has been sent');
        }
    }
    
    /**
     * Get current user profile
     */
    public function me(): void {
        try {
            $user = AuthMiddleware::authenticate();
            
            $profile = UserModel::getProfile($user['user_id']);
            
            if (!$profile) {
                Response::notFound('User');
            }
            
            Response::success(['user' => $profile]);
            
        } catch (Exception $e) {
            error_log("Get profile error: " . $e->getMessage());
            Response::serverError('Failed to get profile');
        }
    }
    
    /**
     * Setup 2FA
     */
    public function setup2FA(): void {
        try {
            $user = AuthMiddleware::authenticate();
            
            require_once __DIR__ . '/../../utils/TwoFactorAuth.php';
            
            $secret = TwoFactorAuth::generateSecret();
            $qrCodeUrl = TwoFactorAuth::getQRCodeUrl($user['email'], $secret, APP_NAME);
            
            // Store secret temporarily (not enabled until verified)
            SessionModel::storeVerificationToken($user['user_id'], '2fa_setup', $secret);
            
            Response::success([
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
                'manual_entry_key' => TwoFactorAuth::getManualEntryKey($secret)
            ]);
            
        } catch (Exception $e) {
            error_log("2FA setup error: " . $e->getMessage());
            Response::serverError('Failed to setup 2FA');
        }
    }
    
    /**
     * Enable 2FA
     */
    public function enable2FA(): void {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator($data);
            $validator->required(['code']);
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            // Get stored secret
            $tokenData = SessionModel::getVerificationTokenByUser($user['user_id'], '2fa_setup');
            
            if (!$tokenData) {
                Response::error('2FA setup session expired. Please start again.', 400);
            }
            
            $secret = $tokenData['token'];
            
            // Verify code
            require_once __DIR__ . '/../../utils/TwoFactorAuth.php';
            
            if (!TwoFactorAuth::verify($secret, $data['code'])) {
                Response::error('Invalid verification code', 400);
            }
            
            // Enable 2FA
            UserModel::setTwoFactor($user['user_id'], true, $secret);
            
            // Invalidate setup token
            SessionModel::invalidateVerificationToken($tokenData['token']);
            
            Response::success([], 'Two-factor authentication enabled successfully');
            
        } catch (Exception $e) {
            error_log("Enable 2FA error: " . $e->getMessage());
            Response::serverError('Failed to enable 2FA');
        }
    }
    
    /**
     * Disable 2FA
     */
    public function disable2FA(): void {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Require password confirmation
            if (empty($data['password'])) {
                Response::error('Password confirmation required', 400);
            }
            
            if (!password_verify($data['password'], $user['password_hash'])) {
                Response::error('Invalid password', 401);
            }
            
            UserModel::setTwoFactor($user['user_id'], false, null);
            
            Response::success([], 'Two-factor authentication disabled successfully');
            
        } catch (Exception $e) {
            error_log("Disable 2FA error: " . $e->getMessage());
            Response::serverError('Failed to disable 2FA');
        }
    }
    
    /**
     * Change password
     */
    public function changePassword(): void {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator($data);
            $validator->required(['current_password', 'new_password'])
                     ->minLength('new_password', PASSWORD_MIN_LENGTH);
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            // Verify current password
            if (!password_verify($data['current_password'], $user['password_hash'])) {
                Response::error('Current password is incorrect', 401);
            }
            
            // Update password
            $passwordHash = password_hash($data['new_password'], PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS);
            UserModel::updatePassword($user['user_id'], $passwordHash);
            
            // Invalidate all sessions except current
            SessionModel::invalidateAllUserSessions($user['user_id'], JWTHandler::extractFromHeader());
            
            Response::success([], 'Password changed successfully');
            
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            Response::serverError('Failed to change password');
        }
    }
    
    /**
     * Generate JWT tokens
     */
    private function generateTokens(int $userId, string $email, string $role): array {
        $payload = [
            'sub' => $userId,
            'email' => $email,
            'role' => $role
        ];
        
        return [
            'access_token' => JWTHandler::generate($payload),
            'refresh_token' => JWTHandler::generateRefreshToken($payload),
            'token_type' => 'Bearer',
            'expires_in' => JWT_EXPIRY
        ];
    }
    
    /**
     * Social login callback
     */
    public function socialLogin(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $validator = new Validator($data);
            $validator->required(['provider', 'access_token']);
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            $provider = $data['provider'];
            $accessToken = $data['access_token'];
            
            // Verify social token and get user info
            $socialUser = $this->verifySocialToken($provider, $accessToken);
            
            if (!$socialUser) {
                Response::error('Invalid social token', 401);
            }
            
            // Check if user exists with this social account
            $sql = "SELECT u.* FROM users u 
                    JOIN user_social_accounts usa ON u.user_id = usa.user_id 
                    WHERE usa.provider = ? AND usa.provider_user_id = ? AND u.deleted_at IS NULL";
            $user = Database::fetchOne($sql, [$provider, $socialUser['id']]);
            
            if ($user) {
                // Existing user - login
                UserModel::updateLastLogin($user['user_id']);
                $tokens = $this->generateTokens($user['user_id'], $user['email'], $user['role']);
                
                Response::success([
                    'user' => [
                        'id' => $user['user_id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'role' => $user['role'],
                        'profile_image' => $user['profile_image']
                    ],
                    'tokens' => $tokens
                ], 'Login successful');
            }
            
            // Check if email already exists
            $existingUser = UserModel::findByEmail($socialUser['email']);
            
            if ($existingUser) {
                // Link social account to existing user
                $sql = "INSERT INTO user_social_accounts (user_id, provider, provider_user_id, email, profile_data) 
                        VALUES (?, ?, ?, ?, ?)";
                Database::query($sql, [
                    $existingUser['user_id'],
                    $provider,
                    $socialUser['id'],
                    $socialUser['email'],
                    json_encode($socialUser)
                ]);
                
                UserModel::updateLastLogin($existingUser['user_id']);
                $tokens = $this->generateTokens($existingUser['user_id'], $existingUser['email'], $existingUser['role']);
                
                Response::success([
                    'user' => [
                        'id' => $existingUser['user_id'],
                        'email' => $existingUser['email'],
                        'first_name' => $existingUser['first_name'],
                        'last_name' => $existingUser['last_name'],
                        'role' => $existingUser['role'],
                        'profile_image' => $existingUser['profile_image']
                    ],
                    'tokens' => $tokens
                ], 'Social account linked successfully');
            }
            
            // Create new user
            $userData = [
                'email' => $socialUser['email'],
                'password_hash' => password_hash(JWTHandler::generateRandomToken(), PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS),
                'first_name' => $socialUser['first_name'] ?? '',
                'last_name' => $socialUser['last_name'] ?? '',
                'profile_image' => $socialUser['picture'] ?? null,
                'role' => 'student',
                'email_verified' => 1 // Social logins are pre-verified
            ];
            
            $userId = UserModel::create($userData);
            
            // Store social account
            $sql = "INSERT INTO user_social_accounts (user_id, provider, provider_user_id, email, profile_data) 
                    VALUES (?, ?, ?, ?, ?)";
            Database::query($sql, [
                $userId,
                $provider,
                $socialUser['id'],
                $socialUser['email'],
                json_encode($socialUser)
            ]);
            
            $tokens = $this->generateTokens($userId, $userData['email'], $userData['role']);
            
            Response::success([
                'user' => [
                    'id' => $userId,
                    'email' => $userData['email'],
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'role' => $userData['role'],
                    'profile_image' => $userData['profile_image']
                ],
                'tokens' => $tokens
            ], 'Account created successfully', 201);
            
        } catch (Exception $e) {
            error_log("Social login error: " . $e->getMessage());
            Response::serverError('Social login failed');
        }
    }
    
    /**
     * Verify social provider token
     */
    private function verifySocialToken(string $provider, string $token): ?array {
        switch ($provider) {
            case 'google':
                return $this->verifyGoogleToken($token);
            case 'github':
                return $this->verifyGitHubToken($token);
            case 'linkedin':
                return $this->verifyLinkedInToken($token);
            default:
                return null;
        }
    }
    
    /**
     * Verify Google token
     */
    private function verifyGoogleToken(string $token): ?array {
        $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token);
        $response = @file_get_contents($url);
        
        if (!$response) return null;
        
        $data = json_decode($response, true);
        
        if (isset($data['error']) || $data['aud'] !== GOOGLE_CLIENT_ID) {
            return null;
        }
        
        return [
            'id' => $data['sub'],
            'email' => $data['email'],
            'first_name' => $data['given_name'] ?? '',
            'last_name' => $data['family_name'] ?? '',
            'picture' => $data['picture'] ?? null
        ];
    }
    
    /**
     * Verify GitHub token
     */
    private function verifyGitHubToken(string $token): ?array {
        $opts = [
            'http' => [
                'header' => "Authorization: token $token\r\nUser-Agent: " . APP_NAME
            ]
        ];
        
        $context = stream_context_create($opts);
        $response = @file_get_contents('https://api.github.com/user', false, $context);
        
        if (!$response) return null;
        
        $data = json_decode($response, true);
        
        // Get email
        $emailsResponse = @file_get_contents('https://api.github.com/user/emails', false, $context);
        $emails = $emailsResponse ? json_decode($emailsResponse, true) : [];
        $primaryEmail = '';
        foreach ($emails as $email) {
            if ($email['primary']) {
                $primaryEmail = $email['email'];
                break;
            }
        }
        
        return [
            'id' => $data['id'],
            'email' => $primaryEmail,
            'first_name' => $data['name'] ?? $data['login'],
            'last_name' => '',
            'picture' => $data['avatar_url'] ?? null
        ];
    }
    
    /**
     * Verify LinkedIn token
     */
    private function verifyLinkedInToken(string $token): ?array {
        $opts = [
            'http' => [
                'header' => "Authorization: Bearer $token"
            ]
        ];
        
        $context = stream_context_create($opts);
        $response = @file_get_contents('https://api.linkedin.com/v2/me', false, $context);
        
        if (!$response) return null;
        
        $data = json_decode($response, true);
        
        // Get email
        $emailResponse = @file_get_contents('https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))', false, $context);
        $emailData = $emailResponse ? json_decode($emailResponse, true) : [];
        $email = $emailData['elements'][0]['handle~']['emailAddress'] ?? '';
        
        $firstName = $data['localizedFirstName'] ?? '';
        $lastName = $data['localizedLastName'] ?? '';
        
        return [
            'id' => $data['id'],
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'picture' => null
        ];
    }
}
