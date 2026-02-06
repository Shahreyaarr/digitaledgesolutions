<?php
/**
 * DigitalEdgeSolutions - Main Configuration
 * Centralized configuration management
 */

// Load environment variables
require_once __DIR__ . '/../utils/Environment.php';
Environment::load();

// Application Configuration
define('APP_NAME', $_ENV['APP_NAME'] ?? 'DigitalEdgeSolutions');
define('APP_VERSION', '1.0.0');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', ($_ENV['APP_DEBUG'] ?? 'false') === 'true');
define('APP_URL', $_ENV['APP_URL'] ?? 'https://digitaledgesolutions.com');
define('APP_TIMEZONE', $_ENV['APP_TIMEZONE'] ?? 'UTC');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Security Configuration
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-in-production');
define('JWT_ISSUER', APP_URL);
define('JWT_AUDIENCE', APP_URL);
define('JWT_EXPIRY', (int)($_ENV['JWT_EXPIRY'] ?? 86400)); // 24 hours
define('JWT_REFRESH_EXPIRY', (int)($_ENV['JWT_REFRESH_EXPIRY'] ?? 604800)); // 7 days

define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? 'your-encryption-key-32-chars-long!!');
define('CSRF_TOKEN_SECRET', $_ENV['CSRF_TOKEN_SECRET'] ?? 'csrf-secret-key-change-in-production');

// Password Configuration
define('PASSWORD_MIN_LENGTH', (int)($_ENV['PASSWORD_MIN_LENGTH'] ?? 8));
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_HASH_OPTIONS', ['cost' => 12]);

// Session Configuration
define('SESSION_TIMEOUT', (int)($_ENV['SESSION_TIMEOUT'] ?? 1800)); // 30 minutes
define('SESSION_NAME', 'des_session');
define('SESSION_SECURE', APP_ENV === 'production');
define('SESSION_HTTP_ONLY', true);
define('SESSION_SAME_SITE', 'Strict');

// Rate Limiting
define('RATE_LIMIT_REQUESTS', (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? 100));
define('RATE_LIMIT_WINDOW', (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60)); // seconds

define('LOGIN_MAX_ATTEMPTS', (int)($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5));
define('LOGIN_LOCKOUT_TIME', (int)($_ENV['LOGIN_LOCKOUT_TIME'] ?? 900)); // 15 minutes

// File Upload Configuration
define('UPLOAD_MAX_SIZE', (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 104857600)); // 100MB
define('UPLOAD_ALLOWED_TYPES', [
    'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    'video' => ['mp4', 'webm', 'ogg', 'mov'],
    'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],
    'code' => ['js', 'php', 'py', 'java', 'cpp', 'c', 'html', 'css', 'sql', 'json', 'xml']
]);
define('UPLOAD_PATH', __DIR__ . '/../../frontend/public/uploads/');

// Email Configuration
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? '');
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? '');
define('SMTP_ENCRYPTION', $_ENV['SMTP_ENCRYPTION'] ?? 'tls');
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@digitaledgesolutions.com');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'DigitalEdgeSolutions');

// Payment Gateway Configuration
define('RAZORPAY_KEY_ID', $_ENV['RAZORPAY_KEY_ID'] ?? '');
define('RAZORPAY_KEY_SECRET', $_ENV['RAZORPAY_KEY_SECRET'] ?? '');
define('STRIPE_PUBLISHABLE_KEY', $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '');
define('STRIPE_SECRET_KEY', $_ENV['STRIPE_SECRET_KEY'] ?? '');

// Social Login Configuration
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
define('LINKEDIN_CLIENT_ID', $_ENV['LINKEDIN_CLIENT_ID'] ?? '');
define('LINKEDIN_CLIENT_SECRET', $_ENV['LINKEDIN_CLIENT_SECRET'] ?? '');
define('GITHUB_CLIENT_ID', $_ENV['GITHUB_CLIENT_ID'] ?? '');
define('GITHUB_CLIENT_SECRET', $_ENV['GITHUB_CLIENT_SECRET'] ?? '');

// Firebase Configuration
define('FIREBASE_API_KEY', $_ENV['FIREBASE_API_KEY'] ?? '');
define('FIREBASE_AUTH_DOMAIN', $_ENV['FIREBASE_AUTH_DOMAIN'] ?? '');
define('FIREBASE_PROJECT_ID', $_ENV['FIREBASE_PROJECT_ID'] ?? '');

// Blockchain Configuration
define('BLOCKCHAIN_ENABLED', ($_ENV['BLOCKCHAIN_ENABLED'] ?? 'false') === 'true');
define('BLOCKCHAIN_NETWORK', $_ENV['BLOCKCHAIN_NETWORK'] ?? 'polygon');
define('BLOCKCHAIN_RPC_URL', $_ENV['BLOCKCHAIN_RPC_URL'] ?? '');
define('BLOCKCHAIN_CONTRACT_ADDRESS', $_ENV['BLOCKCHAIN_CONTRACT_ADDRESS'] ?? '');
define('BLOCKCHAIN_PRIVATE_KEY', $_ENV['BLOCKCHAIN_PRIVATE_KEY'] ?? '');

// Cloud Storage Configuration
define('CLOUDINARY_CLOUD_NAME', $_ENV['CLOUDINARY_CLOUD_NAME'] ?? '');
define('CLOUDINARY_API_KEY', $_ENV['CLOUDINARY_API_KEY'] ?? '');
define('CLOUDINARY_API_SECRET', $_ENV['CLOUDINARY_API_SECRET'] ?? '');

// Redis Configuration (for caching)
define('REDIS_ENABLED', ($_ENV['REDIS_ENABLED'] ?? 'false') === 'true');
define('REDIS_HOST', $_ENV['REDIS_HOST'] ?? '127.0.0.1');
define('REDIS_PORT', (int)($_ENV['REDIS_PORT'] ?? 6379));
define('REDIS_PASSWORD', $_ENV['REDIS_PASSWORD'] ?? '');
define('REDIS_DB', (int)($_ENV['REDIS_DB'] ?? 0));

// API Configuration
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', (int)($_ENV['API_RATE_LIMIT'] ?? 1000));
define('API_DEFAULT_PER_PAGE', (int)($_ENV['API_DEFAULT_PER_PAGE'] ?? 20));
define('API_MAX_PER_PAGE', (int)($_ENV['API_MAX_PER_PAGE'] ?? 100));

// LMS Configuration
define('COURSE_COMPLETION_THRESHOLD', (int)($_ENV['COURSE_COMPLETION_THRESHOLD'] ?? 80));
define('QUIZ_PASSING_SCORE', (int)($_ENV['QUIZ_PASSING_SCORE'] ?? 60));
define('MAX_QUIZ_ATTEMPTS', (int)($_ENV['MAX_QUIZ_ATTEMPTS'] ?? 3));
define('VIDEO_PROGRESS_INTERVAL', (int)($_ENV['VIDEO_PROGRESS_INTERVAL'] ?? 10)); // seconds

// Certificate Configuration
define('CERTIFICATE_ENABLED', ($_ENV['CERTIFICATE_ENABLED'] ?? 'true') === 'true');
define('CERTIFICATE_TEMPLATE', $_ENV['CERTIFICATE_TEMPLATE'] ?? 'default');
define('CERTIFICATE_ISSUER_NAME', $_ENV['CERTIFICATE_ISSUER_NAME'] ?? 'DigitalEdgeSolutions');
define('CERTIFICATE_ISSUER_TITLE', $_ENV['CERTIFICATE_ISSUER_TITLE'] ?? 'CEO');

// Paths
define('BASE_PATH', dirname(__DIR__, 2));
define('BACKEND_PATH', BASE_PATH . '/backend/');
define('FRONTEND_PATH', BASE_PATH . '/frontend/');
define('UPLOADS_PATH', FRONTEND_PATH . 'public/uploads/');
define('CERTIFICATES_PATH', UPLOADS_PATH . 'certificates/');
define('LOGS_PATH', BASE_PATH . '/logs/');
define('CACHE_PATH', BASE_PATH . '/cache/');
define('TEMP_PATH', BASE_PATH . '/temp/');

// Create directories if they don't exist
$directories = [UPLOADS_PATH, CERTIFICATES_PATH, LOGS_PATH, CACHE_PATH, TEMP_PATH];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOGS_PATH . 'error.log');
}

// CORS Configuration
define('CORS_ALLOWED_ORIGINS', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*');
define('CORS_ALLOWED_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
define('CORS_ALLOWED_HEADERS', 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
define('CORS_MAX_AGE', 86400);

// Security Headers
define('SECURITY_HEADERS', [
    'X-Frame-Options' => 'DENY',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
]);
