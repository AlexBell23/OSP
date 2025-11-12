
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
$firstName = sanitizeInput($_POST['firstname'] ?? '');
$lastName = sanitizeInput($_POST['lastname'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');
$phone = sanitizeInput($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';
$membershipType = sanitizeInput($_POST['membershipType'] ?? '');
$newsletterSignup = isset($_POST['newsletter']) ? 1 : 0;

// Validation
$errors = [];

if (empty($firstName)) {
    $errors['firstname'] = 'First name is required';
}

if (empty($lastName)) {
    $errors['lastname'] = 'Last name is required';
}

if (empty($email) || !validateEmail($email)) {
    $errors['email'] = 'Valid email is required';
} elseif (emailExists($email)) {
    $errors['email'] = 'Email already exists';
}

if (empty($phone)) {
    $errors['phone'] = 'Phone number is required';
}

if (empty($password) || !validatePassword($password)) {
    $errors['password'] = 'Password must be at least 8 characters with uppercase, lowercase, and number';
}

if ($password !== $confirmPassword) {
    $errors['confirmPassword'] = 'Passwords do not match';
}

if (empty($membershipType)) {
    $errors['membershipType'] = 'Please select a membership type';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, phone, password_hash, membership_type, newsletter_signup) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $hashedPassword = hashPassword($password);
    $stmt->execute([$firstName, $lastName, $email, $phone, $hashedPassword, $membershipType, $newsletterSignup]);
    
    $userId = $pdo->lastInsertId();
    
    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Registration successful! Welcome to ToKa Fitness.',
        'redirect' => 'dashboard.php'
    ]);
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}
?>

