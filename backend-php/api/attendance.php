<?php
/**
 * Attendance API - Record Daily Attendance & View Histories
 */
require_once __DIR__ . '/../config/AuthHelper.php';
require_once __DIR__ . '/../config/Database.php';

sendApiHeaders('GET, POST, OPTIONS');
$authUser = requireAuthenticatedUser();

$database = new Database();
$db = $database->connect();
$method = $_SERVER['REQUEST_METHOD'];

$staffRoles = ['superadmin', 'admin', 'headteacher', 'deputy_headteacher', 'senior_teacher', 'teacher'];

if ($method === 'GET') {
    $role = $authUser['role'] ?? '';
    $student_id = $_GET['student_id'] ?? null;
    $class_id = $_GET['class_id'] ?? null;
    $date = $_GET['date'] ?? null;

    if ($role === 'student') {
        $ownStmt = $db->prepare("SELECT student_id FROM students WHERE user_id = :uid LIMIT 1");
        $ownStmt->execute([':uid' => (int)$authUser['id']]);
        $own = $ownStmt->fetch();
        if (!$own) {
            echo json_encode(['success' => true, 'data' => [], 'summary' => ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'rate' => 100.0]]);
            exit;
        }
        $student_id = $own['student_id'];
        $class_id = null;
    } elseif ($role === 'parent') {
        $cStmt = $db->prepare("SELECT student_id FROM students WHERE parent_id = :pid");
        $cStmt->execute([':pid' => (int)$authUser['id']]);
        $childIds = array_map(fn($r) => (int)$r['student_id'], $cStmt->fetchAll());
        if (empty($childIds)) {
            echo json_encode(['success' => true, 'data' => [], 'summary' => ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'rate' => 100.0]]);
            exit;
        }
        if ($student_id && !in_array((int)$student_id, $childIds, true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden: You may only view attendance for linked children.']);
            exit;
        }
        $student_id = $student_id ?: $childIds[0];
        $class_id = null;
    } elseif (!in_array($role, $staffRoles, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden.']);
        exit;
    }

    if ($student_id) {
        $stmt = $db->prepare("SELECT a.*, s.first_name, s.last_name 
                              FROM attendance a 
                              JOIN students s ON a.student_id = s.student_id 
                              WHERE a.student_id = :sid 
                              ORDER BY a.date DESC");
        $stmt->execute([':sid' => (int)$student_id]);
        $rows = $stmt->fetchAll();

        $total = count($rows);
        $present = count(array_filter($rows, fn($r) => $r['status'] === 'present'));
        $absent = count(array_filter($rows, fn($r) => $r['status'] === 'absent'));
        $late = count(array_filter($rows, fn($r) => $r['status'] === 'late'));
        $rate = $total > 0 ? round(($present / $total) * 100, 1) : 100.0;

        echo json_encode([
            'success' => true,
            'data' => $rows,
            'summary' => [
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'rate' => $rate
            ]
        ]);
        exit;
    }

    if ($class_id && $date) {
        $stmt = $db->prepare("SELECT s.student_id, s.first_name, s.last_name, s.admission_no, 
                                     a.attendance_id, a.status, a.remarks 
                              FROM students s
                              LEFT JOIN attendance a ON s.student_id = a.student_id AND a.date = :dt
                              WHERE s.class_id = :cid
                              ORDER BY s.first_name, s.last_name");
        $stmt->execute([':dt' => $date, ':cid' => (int)$class_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    // Default for staff: latest 100 entries
    $stmt = $db->query("SELECT a.*, s.first_name, s.last_name, c.class_name, c.section
                        FROM attendance a
                        JOIN students s ON a.student_id = s.student_id
                        LEFT JOIN classes c ON s.class_id = c.class_id
                        ORDER BY a.date DESC, a.attendance_id DESC LIMIT 100");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    requireRoles($staffRoles);
    $input = json_decode(file_get_contents('php://input'), true);
    $records = $input['records'] ?? [];
    $date = $input['date'] ?? date('Y-m-d');

    if (empty($records) || !is_array($records)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No attendance records provided.']);
        exit;
    }

    $validStatuses = ['present', 'absent', 'late', 'excused'];
    $stmt = $db->prepare("INSERT INTO attendance (student_id, date, status, remarks)
                          VALUES (:sid, :dt, :st, :rem)
                          ON DUPLICATE KEY UPDATE status = :st_upd, remarks = :rem_upd");

    $db->beginTransaction();
    try {
        foreach ($records as $rec) {
            $status = in_array($rec['status'] ?? '', $validStatuses, true) ? $rec['status'] : 'present';
            $remarks = $rec['remarks'] ?? null;
            $stmt->execute([
                ':sid'     => (int)$rec['student_id'],
                ':dt'      => $date,
                ':st'      => $status,
                ':rem'     => $remarks,
                ':st_upd'  => $status,
                ':rem_upd' => $remarks,
            ]);
        }
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Attendance saved successfully.']);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
