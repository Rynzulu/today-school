<?php
/**
 * Classes API - Add & List Classes
 */
require_once __DIR__ . '/../config/AuthHelper.php';
require_once __DIR__ . '/../config/Database.php';

sendApiHeaders('GET, POST, DELETE, OPTIONS');
requireAuthenticatedUser();

$database = new Database();
$db = $database->connect();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query("SELECT c.*, 
                        (SELECT COUNT(*) FROM students s WHERE s.class_id = c.class_id) AS total_students 
                        FROM classes c 
                        ORDER BY c.class_name, c.section");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    requireRoles(['superadmin', 'admin', 'headteacher', 'deputy_headteacher', 'senior_teacher']);
    $input = json_decode(file_get_contents('php://input'), true);
    $class_name = trim($input['class_name'] ?? '');
    $section = trim($input['section'] ?? '');
    $room_number = trim($input['room_number'] ?? '');
    $capacity = max(1, (int)($input['capacity'] ?? 35));

    if (empty($class_name) || empty($section)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Class name and section are required.']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO classes (class_name, section, room_number, capacity) VALUES (:cn, :sec, :room, :cap)");
    $stmt->execute([':cn' => $class_name, ':sec' => $section, ':room' => $room_number, ':cap' => $capacity]);
    echo json_encode(['success' => true, 'message' => 'Class created.', 'class_id' => $db->lastInsertId()]);
    exit;
}
