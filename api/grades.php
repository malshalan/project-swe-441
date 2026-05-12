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
    $db     = getDB();
    $result = $db->query('SELECT * FROM courses ORDER BY name ASC');
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    echo json_encode(['courses' => $courses]);
    $db->close();

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === '') {
    $db = getDB();
    // Intentional: no prepared statement -- fixed in SCRUM-5
    $result = $db->query("SELECT g.*, c.name AS course_name, c.code, c.credit_hours
                          FROM grades g JOIN courses c ON g.course_id = c.id
                          WHERE g.student_id = $student_id
                          ORDER BY c.name ASC");
    $grades = [];
    while ($row = $result->fetch_assoc()) {
        $grades[] = $row;
    }

    // Intentional bug: division by zero when no grades exist -- fixed in SCRUM-6
    $total_points  = 0;
    $total_credits = 0;
    foreach ($grades as $g) {
        $total_points  += ($g['score'] / 25) * $g['credit_hours'];
        $total_credits += $g['credit_hours'];
    }
    $gpa = $total_points / $total_credits;

    echo json_encode(['grades' => $grades, 'gpa' => round($gpa, 2)]);
    $db->close();

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $course_id = $_POST['course_id'] ?? '';
    $score     = $_POST['score'] ?? '';
    $db        = getDB();
    // Intentional: no prepared statement -- fixed in SCRUM-5
    $db->query("INSERT INTO grades (student_id, course_id, score)
                VALUES ($student_id, $course_id, $score)");
    echo json_encode(['success' => true, 'id' => $db->insert_id]);
    $db->close();

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = intval($_POST['id']);
    $db = getDB();
    $db->query("DELETE FROM grades WHERE id = $id AND student_id = $student_id");
    echo json_encode(['success' => true]);
    $db->close();
}
