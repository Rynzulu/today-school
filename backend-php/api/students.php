<?php
/**
 * Students API - CRUD & Sibling Querying
 */
require_once __DIR__ . '/../config/AuthHelper.php';
require_once __DIR__ . '/../config/Database.php';

sendApiHeaders('GET, POST, PUT, DELETE, OPTIONS');
$authUser = requireAuthenticatedUser();

$database = new Database();
$db = $database->connect();
$method = $_SERVER['REQUEST_METHOD'];

$staffRoles = ['superadmin', 'admin', 'headteacher', 'deputy_headteacher', 'senior_teacher', 'teacher'];
$adminRoles = ['superadmin', 'admin', 'headteacher', 'deputy_headteacher'];

// GET: Retrieve students (scoped strictly by role)
if ($method === 'GET') {
    $role = $authUser['role'] ?? '';
    $class_id = $_GET['class_id'] ?? null;
    $parent_id = $_GET['parent_id'] ?? null;
    $search = $_GET['search'] ?? null;

    $sql = "SELECT s.*, u.username, u.email, c.class_name, c.section, 
                   pu.username AS parent_username, pu.email AS parent_email
            FROM students s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN classes c ON s.class_id = c.class_id
            LEFT JOIN users pu ON s.parent_id = pu.id
            WHERE 1=1";
    $params = [];

    if ($role === 'student') {
        $sql .= " AND s.user_id = :auth_uid";
        $params[':auth_uid'] = (int)$authUser['id'];
    } elseif ($role === 'parent') {
        $sql .= " AND s.parent_id = :auth_pid";
        $params[':auth_pid'] = (int)$authUser['id'];
    } elseif (in_array($role, $staffRoles, true)) {
        if ($class_id) {
            $sql .= " AND s.class_id = :class_id";
            $params[':class_id'] = (int)$class_id;
        }
        if ($parent_id) {
            $sql .= " AND s.parent_id = :parent_id";
            $params[':parent_id'] = (int)$parent_id;
        }
        if ($search) {
            $sql .= " AND (s.first_name LIKE :s OR s.last_name LIKE :s OR s.admission_no LIKE :s OR u.email LIKE :s)";
            $params[':s'] = "%{$search}%";
        }
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden.']);
        exit;
    }

    $sql .= " ORDER BY s.student_id ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

// POST: Add new student + create user account (staff only)
if ($method === 'POST') {
    requireRoles($staffRoles);
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? 'password123';
    $first_name = trim($input['first_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $gender = in_array($input['gender'] ?? '', ['Male', 'Female', 'Other'], true) ? $input['gender'] : 'Male';
    $dob = $input['dob'] ?? date('Y-m-d');
    $class_id = !empty($input['class_id']) ? (int)$input['class_id'] : null;
    $parent_id = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
    $admission_no = trim($input['admission_no'] ?? ('STU-' . time()));

    if (empty($first_name) || empty($last_name) || empty($username) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Create User
        $pHash = password_hash($password, PASSWORD_BCRYPT);
        $uStmt = $db->prepare("INSERT INTO users (username, password_hash, role, email) VALUES (:u, :p, 'student', :e)");
        $uStmt->execute([':u' => $username, ':p' => $pHash, ':e' => $email]);
        $user_id = $db->lastInsertId();

        // 2. Create Student Record
        $sStmt = $db->prepare("INSERT INTO students (user_id, first_name, last_name, gender, dob, class_id, parent_id, admission_no) 
                               VALUES (:uid, :fn, :ln, :g, :dob, :cid, :pid, :adm)");
        $sStmt->execute([
            ':uid' => $user_id,
            ':fn'  => $first_name,
            ':ln'  => $last_name,
            ':g'   => $gender,
            ':dob' => $dob,
            ':cid' => $class_id,
            ':pid' => $parent_id,
            ':adm' => $admission_no
        ]);
        $student_id = $db->lastInsertId();

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Student enrolled successfully.', 'student_id' => $student_id]);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to enroll student: ' . $e->getMessage()]);
    }
    exit;
}

// DELETE: Remove student (admin leadership only)
if ($method === 'DELETE') {
    requireRoles($adminRoles);
    $student_id = $_GET['id'] ?? null;
    if (!$student_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student ID required']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM students WHERE student_id = :id");
    $stmt->execute([':id' => (int)$student_id]);
    echo json_encode(['success' => true, 'message' => 'Student removed.']);
    exit;
}
