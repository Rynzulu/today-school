<?php
/**
 * Teachers & Subject Assignment API
 */
require_once __DIR__ . '/../config/AuthHelper.php';
require_once __DIR__ . '/../config/Database.php';

sendApiHeaders('GET, POST, PUT, DELETE, OPTIONS');
requireAuthenticatedUser();

$database = new Database();
$db = $database->connect();
$method = $_SERVER['REQUEST_METHOD'];

$adminRoles = ['superadmin', 'admin', 'headteacher', 'deputy_headteacher'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'all';

    if ($action === 'assignments') {
        $stmt = $db->query("SELECT sa.*, t.first_name, t.last_name, c.class_name, c.section 
                            FROM subject_assignments sa
                            JOIN teachers t ON sa.teacher_id = t.teacher_id
                            JOIN classes c ON sa.class_id = c.class_id
                            ORDER BY c.class_name, sa.subject_name");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    $stmt = $db->query("SELECT t.*, u.username, u.email 
                        FROM teachers t 
                        JOIN users u ON t.user_id = u.id 
                        ORDER BY t.teacher_id ASC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    requireRoles($adminRoles);
    $action = $_GET['action'] ?? 'create';
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'assign') {
        $teacher_id = (int)($input['teacher_id'] ?? 0);
        $class_id = (int)($input['class_id'] ?? 0);
        $subject_name = trim($input['subject_name'] ?? '');

        if ($teacher_id <= 0 || $class_id <= 0 || $subject_name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Teacher ID, class ID, and subject name are required.']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO subject_assignments (teacher_id, class_id, subject_name) 
                             VALUES (:tid, :cid, :sub) 
                             ON DUPLICATE KEY UPDATE subject_name = :sub_upd");
        $stmt->execute([':tid' => $teacher_id, ':cid' => $class_id, ':sub' => $subject_name, ':sub_upd' => $subject_name]);
        echo json_encode(['success' => true, 'message' => 'Subject assigned successfully.']);
        exit;
    }

    // Create Teacher
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? 'password123';
    $first_name = trim($input['first_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $subject = trim($input['subject'] ?? 'General');

    if (empty($first_name) || empty($last_name) || empty($username) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'First name, last name, username, and email are required.']);
        exit;
    }

    try {
        $db->beginTransaction();

        $pHash = password_hash($password, PASSWORD_BCRYPT);
        $uStmt = $db->prepare("INSERT INTO users (username, password_hash, role, email) VALUES (:u, :p, 'teacher', :e)");
        $uStmt->execute([':u' => $username, ':p' => $pHash, ':e' => $email]);
        $user_id = $db->lastInsertId();

        $tStmt = $db->prepare("INSERT INTO teachers (user_id, first_name, last_name, phone, subject) VALUES (:uid, :fn, :ln, :ph, :sub)");
        $tStmt->execute([
            ':uid' => $user_id,
            ':fn'  => $first_name,
            ':ln'  => $last_name,
            ':ph'  => $phone,
            ':sub' => $subject
        ]);

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Teacher profile created.']);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create teacher profile: ' . $e->getMessage()]);
    }
    exit;
}
