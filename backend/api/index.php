<?php
/**
 * DigitalEdgeSolutions - API Router
 * Main entry point for all API requests
 */

// Set headers
header('Content-Type: application/json; charset=utf-8');

// Load configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

// Handle CORS
AuthMiddleware::handleCORS();

// Apply security headers
AuthMiddleware::applySecurityHeaders();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api/', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);

// Get input data
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$_REQUEST = array_merge($_REQUEST, $input);

// Route mapping
$routes = [
    // Auth routes
    'auth/register' => ['POST', 'AuthController', 'register', 'auth/AuthController.php'],
    'auth/login' => ['POST', 'AuthController', 'login', 'auth/AuthController.php'],
    'auth/logout' => ['POST', 'AuthController', 'logout', 'auth/AuthController.php'],
    'auth/refresh' => ['POST', 'AuthController', 'refresh', 'auth/AuthController.php'],
    'auth/me' => ['GET', 'AuthController', 'me', 'auth/AuthController.php'],
    'auth/forgot-password' => ['POST', 'AuthController', 'forgotPassword', 'auth/AuthController.php'],
    'auth/reset-password' => ['POST', 'AuthController', 'resetPassword', 'auth/AuthController.php'],
    'auth/verify-email' => ['GET', 'AuthController', 'verifyEmail', 'auth/AuthController.php'],
    'auth/resend-verification' => ['POST', 'AuthController', 'resendVerification', 'auth/AuthController.php'],
    'auth/change-password' => ['POST', 'AuthController', 'changePassword', 'auth/AuthController.php'],
    'auth/2fa/setup' => ['GET', 'AuthController', 'setup2FA', 'auth/AuthController.php'],
    'auth/2fa/enable' => ['POST', 'AuthController', 'enable2FA', 'auth/AuthController.php'],
    'auth/2fa/disable' => ['POST', 'AuthController', 'disable2FA', 'auth/AuthController.php'],
    'auth/2fa/verify' => ['POST', 'AuthController', 'verify2FA', 'auth/AuthController.php'],
    'auth/social' => ['POST', 'AuthController', 'socialLogin', 'auth/AuthController.php'],
    
    // User routes
    'users' => ['GET', 'UserController', 'index', 'users/UserController.php'],
    'users/create' => ['POST', 'UserController', 'create', 'users/UserController.php'],
    'users/profile' => ['GET', 'UserController', 'profile', 'users/UserController.php'],
    'users/update' => ['PUT', 'UserController', 'update', 'users/UserController.php'],
    'users/delete' => ['DELETE', 'UserController', 'delete', 'users/UserController.php'],
    'users/upload-avatar' => ['POST', 'UserController', 'uploadAvatar', 'users/UserController.php'],
    'users/search' => ['GET', 'UserController', 'search', 'users/UserController.php'],
    'users/stats' => ['GET', 'UserController', 'stats', 'users/UserController.php'],
    
    // Course routes
    'courses' => ['GET', 'CourseController', 'index', 'courses/CourseController.php'],
    'courses/featured' => ['GET', 'CourseController', 'featured', 'courses/CourseController.php'],
    'courses/categories' => ['GET', 'CourseController', 'categories', 'courses/CourseController.php'],
    'courses/detail' => ['GET', 'CourseController', 'detail', 'courses/CourseController.php'],
    'courses/create' => ['POST', 'CourseController', 'create', 'courses/CourseController.php'],
    'courses/update' => ['PUT', 'CourseController', 'update', 'courses/CourseController.php'],
    'courses/delete' => ['DELETE', 'CourseController', 'delete', 'courses/CourseController.php'],
    'courses/enroll' => ['POST', 'CourseController', 'enroll', 'courses/CourseController.php'],
    'courses/enrollments' => ['GET', 'CourseController', 'enrollments', 'courses/CourseController.php'],
    'courses/progress' => ['GET', 'CourseController', 'progress', 'courses/CourseController.php'],
    'courses/update-progress' => ['POST', 'CourseController', 'updateProgress', 'courses/CourseController.php'],
    'courses/reviews' => ['GET', 'CourseController', 'reviews', 'courses/CourseController.php'],
    'courses/add-review' => ['POST', 'CourseController', 'addReview', 'courses/CourseController.php'],
    
    // Lesson routes
    'lessons' => ['GET', 'LessonController', 'index', 'courses/LessonController.php'],
    'lessons/detail' => ['GET', 'LessonController', 'detail', 'courses/LessonController.php'],
    'lessons/create' => ['POST', 'LessonController', 'create', 'courses/LessonController.php'],
    'lessons/update' => ['PUT', 'LessonController', 'update', 'courses/LessonController.php'],
    'lessons/delete' => ['DELETE', 'LessonController', 'delete', 'courses/LessonController.php'],
    'lessons/complete' => ['POST', 'LessonController', 'complete', 'courses/LessonController.php'],
    'lessons/notes' => ['GET', 'LessonController', 'notes', 'courses/LessonController.php'],
    'lessons/save-notes' => ['POST', 'LessonController', 'saveNotes', 'courses/LessonController.php'],
    
    // Quiz routes
    'quizzes' => ['GET', 'QuizController', 'index', 'courses/QuizController.php'],
    'quizzes/start' => ['POST', 'QuizController', 'start', 'courses/QuizController.php'],
    'quizzes/submit' => ['POST', 'QuizController', 'submit', 'courses/QuizController.php'],
    'quizzes/result' => ['GET', 'QuizController', 'result', 'courses/QuizController.php'],
    'quizzes/attempts' => ['GET', 'QuizController', 'attempts', 'courses/QuizController.php'],
    
    // Certificate routes
    'certificates' => ['GET', 'CertificateController', 'index', 'certificates/CertificateController.php'],
    'certificates/detail' => ['GET', 'CertificateController', 'detail', 'certificates/CertificateController.php'],
    'certificates/generate' => ['POST', 'CertificateController', 'generate', 'certificates/CertificateController.php'],
    'certificates/download' => ['GET', 'CertificateController', 'download', 'certificates/CertificateController.php'],
    'certificates/verify' => ['GET', 'CertificateController', 'verify', 'certificates/CertificateController.php'],
    'certificates/share' => ['POST', 'CertificateController', 'share', 'certificates/CertificateController.php'],
    
    // Internship routes
    'internships' => ['GET', 'InternshipController', 'index', 'internships/InternshipController.php'],
    'internships/featured' => ['GET', 'InternshipController', 'featured', 'internships/InternshipController.php'],
    'internships/detail' => ['GET', 'InternshipController', 'detail', 'internships/InternshipController.php'],
    'internships/create' => ['POST', 'InternshipController', 'create', 'internships/InternshipController.php'],
    'internships/update' => ['PUT', 'InternshipController', 'update', 'internships/InternshipController.php'],
    'internships/apply' => ['POST', 'InternshipController', 'apply', 'internships/InternshipController.php'],
    'internships/applications' => ['GET', 'InternshipController', 'applications', 'internships/InternshipController.php'],
    'internships/application-status' => ['PUT', 'InternshipController', 'updateStatus', 'internships/InternshipController.php'],
    'internships/schedule-interview' => ['POST', 'InternshipController', 'scheduleInterview', 'internships/InternshipController.php'],
    'internships/send-offer' => ['POST', 'InternshipController', 'sendOffer', 'internships/InternshipController.php'],
    
    // Employee routes
    'employees' => ['GET', 'EmployeeController', 'index', 'employees/EmployeeController.php'],
    'employees/detail' => ['GET', 'EmployeeController', 'detail', 'employees/EmployeeController.php'],
    'employees/create' => ['POST', 'EmployeeController', 'create', 'employees/EmployeeController.php'],
    'employees/update' => ['PUT', 'EmployeeController', 'update', 'employees/EmployeeController.php'],
    'employees/attendance' => ['GET', 'EmployeeController', 'attendance', 'employees/EmployeeController.php'],
    'employees/mark-attendance' => ['POST', 'EmployeeController', 'markAttendance', 'employees/EmployeeController.php'],
    'employees/leaves' => ['GET', 'EmployeeController', 'leaves', 'employees/EmployeeController.php'],
    'employees/apply-leave' => ['POST', 'EmployeeController', 'applyLeave', 'employees/EmployeeController.php'],
    'employees/payslips' => ['GET', 'EmployeeController', 'payslips', 'employees/EmployeeController.php'],
    
    // Communication routes
    'chat/rooms' => ['GET', 'ChatController', 'rooms', 'communications/ChatController.php'],
    'chat/room' => ['GET', 'ChatController', 'room', 'communications/ChatController.php'],
    'chat/create-room' => ['POST', 'ChatController', 'createRoom', 'communications/ChatController.php'],
    'chat/messages' => ['GET', 'ChatController', 'messages', 'communications/ChatController.php'],
    'chat/send' => ['POST', 'ChatController', 'send', 'communications/ChatController.php'],
    'chat/typing' => ['POST', 'ChatController', 'typing', 'communications/ChatController.php'],
    
    // Notification routes
    'notifications' => ['GET', 'NotificationController', 'index', 'communications/NotificationController.php'],
    'notifications/mark-read' => ['POST', 'NotificationController', 'markRead', 'communications/NotificationController.php'],
    'notifications/settings' => ['GET', 'NotificationController', 'settings', 'communications/NotificationController.php'],
    'notifications/update-settings' => ['PUT', 'NotificationController', 'updateSettings', 'communications/NotificationController.php'],
    
    // Portfolio routes
    'portfolio/projects' => ['GET', 'PortfolioController', 'projects', 'portfolio/PortfolioController.php'],
    'portfolio/project-detail' => ['GET', 'PortfolioController', 'projectDetail', 'portfolio/PortfolioController.php'],
    'portfolio/team' => ['GET', 'PortfolioController', 'team', 'portfolio/PortfolioController.php'],
    'portfolio/testimonials' => ['GET', 'PortfolioController', 'testimonials', 'portfolio/PortfolioController.php'],
    'portfolio/contact' => ['POST', 'PortfolioController', 'contact', 'portfolio/PortfolioController.php'],
    
    // Blog routes
    'blog/posts' => ['GET', 'BlogController', 'posts', 'blog/BlogController.php'],
    'blog/post-detail' => ['GET', 'BlogController', 'postDetail', 'blog/BlogController.php'],
    'blog/categories' => ['GET', 'BlogController', 'categories', 'blog/BlogController.php'],
    'blog/create' => ['POST', 'BlogController', 'create', 'blog/BlogController.php'],
    'blog/update' => ['PUT', 'BlogController', 'update', 'blog/BlogController.php'],
    'blog/delete' => ['DELETE', 'BlogController', 'delete', 'blog/BlogController.php'],
    'blog/comments' => ['GET', 'BlogController', 'comments', 'blog/BlogController.php'],
    'blog/add-comment' => ['POST', 'BlogController', 'addComment', 'blog/BlogController.php'],
    
    // Admin routes
    'admin/dashboard' => ['GET', 'AdminController', 'dashboard', 'admin/AdminController.php'],
    'admin/users' => ['GET', 'AdminController', 'users', 'admin/AdminController.php'],
    'admin/courses' => ['GET', 'AdminController', 'courses', 'admin/AdminController.php'],
    'admin/internships' => ['GET', 'AdminController', 'internships', 'admin/AdminController.php'],
    'admin/reports' => ['GET', 'AdminController', 'reports', 'admin/AdminController.php'],
    'admin/settings' => ['GET', 'AdminController', 'settings', 'admin/AdminController.php'],
    'admin/update-settings' => ['PUT', 'AdminController', 'updateSettings', 'admin/AdminController.php'],
    
    // Payment routes
    'payments/methods' => ['GET', 'PaymentController', 'methods', 'payments/PaymentController.php'],
    'payments/create-order' => ['POST', 'PaymentController', 'createOrder', 'payments/PaymentController.php'],
    'payments/verify' => ['POST', 'PaymentController', 'verify', 'payments/PaymentController.php'],
    'payments/history' => ['GET', 'PaymentController', 'history', 'payments/PaymentController.php'],
    'payments/invoices' => ['GET', 'PaymentController', 'invoices', 'payments/PaymentController.php'],
];

// Find matching route
$routeKey = implode('/', array_slice($segments, 0, 2));
if (count($segments) > 2) {
    $routeKey = implode('/', $segments);
}

// Handle single segment routes
if (count($segments) === 1) {
    $routeKey = $segments[0];
}

// Check if route exists
if (!isset($routes[$routeKey])) {
    Response::notFound('API endpoint');
}

$route = $routes[$routeKey];

// Check HTTP method
if ($route[0] !== $method && $route[0] !== '*') {
    Response::error('Method not allowed', 405);
}

// Load controller
$controllerFile = __DIR__ . '/' . $route[3];
if (!file_exists($controllerFile)) {
    Response::error('Controller not implemented', 501);
}

require_once $controllerFile;

// Instantiate controller and call method
$controllerName = $route[1];
$methodName = $route[2];

if (!class_exists($controllerName)) {
    Response::error('Controller class not found', 500);
}

$controller = new $controllerName();

if (!method_exists($controller, $methodName)) {
    Response::error('Method not implemented', 501);
}

// Call the method
try {
    $controller->$methodName();
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    Response::serverError('An error occurred while processing your request');
}
