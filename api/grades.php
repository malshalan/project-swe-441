<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['user_id'];
$action     = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'courses') {
    $db      = getDB();
    $result  = $db->query('SELECT * FROM courses ORDER BY name ASC');
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    echo json_encode(['courses' => $courses]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === '') {
    $db   = getDB();
    $stmt = $db->prepare('SELECT g.*, c.name AS course_name, c.code, c.credit_hours
                          FROM grades g JOIN courses c ON g.course_id = c.id
                          WHERE g.student_id = ?
                          ORDER BY c.name ASC');
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total_points  = 0;
    $total_credits = 0;
    foreach ($grades as $g) {
        $total_points  += ($g['score'] / 25) * $g['credit_hours'];
        $total_credits += $g['credit_hours'];
    }
    $gpa = $total_credits > 0 ? $total_points / $total_credits : 0.0;

    echo json_encode(['grades' => $grades, 'gpa' => round($gpa, 2)]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $course_id = intval($_POST['course_id'] ?? 0);
    $score     = intval($_POST['score'] ?? 0);
    $db        = getDB();
    $stmt      = $db->prepare('INSERT INTO grades (student_id, course_id, score) VALUES (?, ?, ?)');
    $stmt->bind_param('iii', $student_id, $course_id, $score);
    $stmt->execute();
    echo json_encode(['success' => true, 'id' => $db->insert_id]);
    $stmt->close();

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id   = intval($_POST['id']);
    $db   = getDB();
    $stmt = $db->prepare('DELETE FROM grades WHERE id = ? AND student_id = ?');
    $stmt->bind_param('ii', $id, $student_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    $stmt->close();
}
