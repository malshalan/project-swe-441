<?php
// Intentional technical debt: global variables and repeated connection logic
// This will be refactored in SCRUM-4
$db_host = 'localhost';
$db_name = 'gradetracker';
$db_user = 'root';
$db_pass = '';

function getDB() {
    global $db_host, $db_name, $db_user, $db_pass;
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    return $conn;
}
