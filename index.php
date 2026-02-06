<?php
// Main entry point for Render deployment
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the request path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = trim($path, '/');

// Database connection using environment variable
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    // Parse PostgreSQL URL
    $db_parts = parse_url($database_url);
    $host = $db_parts['host'];
    $port = $db_parts['port'] ?? 5432;
    $user = $db_parts['user'];
    $pass = $db_parts['pass'];
    $dbname = ltrim($db_parts['path'], '/');
    
    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
} else {
    // Fallback to MySQL for local development
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'digitaledgesolutions';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
}

// Simple Router
switch ($path) {
    case '':
    case 'api':
    case 'api/health':
        echo json_encode([
            'status' => 'OK',
            'message' => 'DigitalEdgeSolutions API is running',
            'timestamp' => time(),
            'database' => $database_url ? 'PostgreSQL Connected' : 'MySQL Connected'
        ]);
        break;
        
    case 'api/register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['email']) || empty($data['password']) || empty($data['first_name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            break;
        }
        
        // Check if table exists, if not create it
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100),
                role VARCHAR(50) DEFAULT 'student',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (Exception $e) {
            // Table might already exist
        }
        
        $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?)");
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        
        try {
            $stmt->execute([
                $data['email'], 
                $hashedPassword, 
                $data['first_name'], 
                $data['last_name'] ?? '', 
                $data['role'] ?? 'student'
            ]);
            
            $userId = $pdo->lastInsertId();
            echo json_encode([
                'success' => true,
                'message' => 'User registered successfully',
                'user_id' => $userId
            ]);
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
        }
        break;
        
    case 'api/login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($data['password'], $user['password'])) {
            $token = bin2hex(random_bytes(32));
            echo json_encode([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
        break;
        
    case 'api/courses':
        // Create courses table if not exists
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
                id SERIAL PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                category VARCHAR(100),
                level VARCHAR(50),
                duration_hours INT,
                price DECIMAL(10,2) DEFAULT 0,
                is_published BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Insert sample data if empty
            $count = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
            if ($count == 0) {
                $pdo->exec("INSERT INTO courses (title, description, category, level, duration_hours) VALUES 
                    ('Full Stack Web Development', 'Learn React, Node.js, and Database', 'Development', 'beginner', 40),
                    ('Data Science with Python', 'Machine Learning and Data Analysis', 'Data Science', 'intermediate', 60),
                    ('UI/UX Design Masterclass', 'Figma, Adobe XD, and Design Principles', 'Design', 'beginner', 30)
                ");
            }
        } catch (Exception $e) {
            // Ignore errors
        }
        
        $stmt = $pdo->query("SELECT * FROM courses WHERE is_published = TRUE");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'courses' => $courses]);
        break;
        
    default:
        // Serve frontend files
        $file = __DIR__ . '/frontend/public/' . $path;
        if (empty($path)) {
            $file = __DIR__ . '/frontend/public/index.html';
        }
        
        if (file_exists($file)) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            switch ($ext) {
                case 'html': header('Content-Type: text/html'); break;
                case 'css': header('Content-Type: text/css'); break;
                case 'js': header('Content-Type: application/javascript'); break;
                case 'json': header('Content-Type: application/json'); break;
                case 'png': header('Content-Type: image/png'); break;
                case 'jpg': header('Content-Type: image/jpeg'); break;
            }
            readfile($file);
        } else {
            // SPA routing - serve index.html for all routes
            header('Content-Type: text/html');
            readfile(__DIR__ . '/frontend/public/index.html');
        }
}
