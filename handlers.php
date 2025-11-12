
<?php
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// Get and sanitize input data
$email = sanitizeInput($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$rememberMe = isset($_POST['remember']);

// Validation
$errors = [];

if (empty($email) || !validateEmail($email)) {
    $errors['email'] = 'Valid email is required';
}

if (empty($password)) {
    $errors['password'] = 'Password is required';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !verifyPassword($password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit();
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    
    // Handle remember me
    if ($rememberMe) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Store session token in database
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $token, $expires]);
        
        // Set cookie
        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Sign in successful! Welcome back.',
        'redirect' => 'dashboard.php'
    ]);
    
} catch (Exception $e) {
    error_log("Sign in error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Sign in failed. Please try again.']);
}
?>

