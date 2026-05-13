<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'login') {
    // Intentional bug: missing input validation -- fixed in SCRUM-1
    $username = $_POST['username'];
    $password = $_POST['password'];

    $db = getDB();
    // Intentional SQL injection vulnerability -- fixed in SCRUM-5
    $result = $db->query("SELECT * FROM users WHERE username = '$username'");
    $user = $result ? $result->fetch_assoc() : null;

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        echo json_encode(['success' => true, 'username' => $user['username']]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
    $db->close();

} elseif ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
        exit;
    }

    $db = getDB();
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $username, $email, $hashed);
    $stmt->execute();
    echo json_encode(['success' => true]);
    $stmt->close();
    $db->close();

} elseif ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);

} elseif ($action === 'check') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode(['logged_in' => true, 'username' => $_SESSION['username']]);
    } else {
        echo json_encode(['logged_in' => false]);
    }
}
