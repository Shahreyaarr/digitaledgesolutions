<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    echo json_encode(['error' => 'Database not configured']);
    exit;
}

$parsed = parse_url($dbUrl);
$host = $parsed['host'];
$port = $parsed['port'] ?? 5432;
$dbname = ltrim($parsed['path'], '/');
$username = $parsed['user'];
$password = $parsed['pass'];

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($path) {
    case 'health':
        echo json_encode(['status' => 'OK']);
        break;
        
    case 'login':
        if ($method !== 'POST') {
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['email']) || !isset($data['password'])) {
            echo json_encode(['error' => 'Email and password required']);
            break;
        }
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($data['password'], $user['password'])) {
            echo json_encode(['success' => true, 'token' => bin2hex(random_bytes(32)), 'user' => ['id' => $user['id'], 'email' => $user['email'], 'first_name' => $user['first_name'], 'last_name' => $user['last_name'], 'role' => $user['role']]]);
        } else {
            echo json_encode(['error' => 'Invalid credentials']);
        }
        break;
        
    case 'register':
        if ($method !== 'POST') {
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['email']) || !isset($data['password'])) {
            echo json_encode(['error' => 'Email and password required']);
            break;
        }
        $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?)");
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        try {
            $stmt->execute([$data['email'], $hashedPassword, $data['first_name'] ?? '', $data['last_name'] ?? '', $data['role'] ?? 'student']);
            echo json_encode(['success' => true, 'user_id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Email already exists']);
        }
        break;
        
    case 'courses':
        $stmt = $pdo->query("SELECT * FROM courses");
        echo json_encode(['success' => true, 'courses' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;
        
    case 'internships':
        $stmt = $pdo->query("SELECT * FROM internships");
        echo json_encode(['success' => true, 'internships' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;
        
    default:
        echo json_encode(['error' => 'Not found']);
}
