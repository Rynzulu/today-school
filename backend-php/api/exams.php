<?php
/**
 * Exams & Grading Scale API
 * Endpoints:
 *   GET  /api/exams.php (list exams or marks or analytics)
 *   POST /api/exams.php (create exam or save marks)
 */

require_once __DIR__ . '/../config/AuthHelper.php';
require_once __DIR__ . '/../config/Database.php';

sendApiHeaders('GET, POST, DELETE, OPTIONS');
$authUser = requireAuthenticatedUser();

$database = new Database();
$db = $database->connect();
$method = $_SERVER['REQUEST_METHOD'];

$staffRoles = ['superadmin', 'admin', 'headteacher', 'deputy_headteacher', 'senior_teacher', 'teacher'];

function convertScoreTo9Point($score) {
    if ($score >= 75.0) return ['grade' => '1', 'points' => 1, 'standard' => 'DISTINCTION'];
    if ($score >= 70.0) return ['grade' => '2', 'points' => 2, 'standard' => 'DISTINCTION'];
    if ($score >= 65.0) return ['grade' => '3', 'points' => 3, 'standard' => 'MERIT'];
    if ($score >= 60.0) return ['grade' => '4', 'points' => 4, 'standard' => 'MERIT'];
    if ($score >= 55.0) return ['grade' => '5', 'points' => 5, 'standard' => 'CREDIT'];
    if ($score >= 50.0) return ['grade' => '6', 'points' => 6, 'standard' => 'CREDIT'];
    if ($score >= 45.0) return ['grade' => '7', 'points' => 7, 'standard' => 'SATISFACTORY'];
    if ($score >= 40.0) return ['grade' => '8', 'points' => 8, 'standard' => 'SATISFACTORY'];
    return ['grade' => '9', 'points' => 9, 'standard' => 'UNSATISFACTORY'];
}

if ($method === 'GET') {
    $role = $authUser['role'] ?? '';
    $action = $_GET['action'] ?? 'list';
    $exam_id = $_GET['exam_id'] ?? null;
    $class_id = $_GET['class_id'] ?? null;

    if ($action === 'marks' && $exam_id) {
        $sql = "SELECT em.*, s.first_name, s.last_name, s.admission_no 
                FROM exam_marks em 
                JOIN students s ON em.student_id = s.student_id 
                WHERE em.exam_id = :eid";
        $params = [':eid' => (int)$exam_id];

        if ($role === 'student') {
            $sql .= " AND s.user_id = :auth_uid";
            $params[':auth_uid'] = (int)$authUser['id'];
        } elseif ($role === 'parent') {
            $sql .= " AND s.parent_id = :auth_pid";
            $params[':auth_pid'] = (int)$authUser['id'];
        } elseif (!in_array($role, $staffRoles, true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden.']);
            exit;
        }

        $sql .= " ORDER BY s.first_name, s.last_name";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'analytics' && $exam_id) {
        requireRoles($staffRoles);
        $stmt = $db->prepare("SELECT marks_obtained, grade FROM exam_marks WHERE exam_id = :eid");
        $stmt->execute([':eid' => (int)$exam_id]);
        $rows = $stmt->fetchAll();

        $scores = array_map(fn($r) => (float)$r['marks_obtained'], $rows);
        $count = count($scores);
        $avg = $count > 0 ? round(array_sum($scores) / $count, 1) : 0;
        $max = $count > 0 ? max($scores) : 0;
        $min = $count > 0 ? min($scores) : 0;
        $passed = count(array_filter($scores, fn($s) => $s >= 40));

        echo json_encode([
            'success' => true,
            'analytics' => [
                'total_students' => $count,
                'average' => $avg,
                'highest' => $max,
                'lowest' => $min,
                'passed' => $passed,
                'failed' => $count - $passed,
                'pass_ratio' => $count > 0 ? round(($passed / $count) * 100) . '%' : '0%'
            ]
        ]);
        exit;
    }

    // List exams
    $sql = "SELECT e.*, c.class_name, c.section 
            FROM exams e 
            JOIN classes c ON e.class_id = c.class_id 
            WHERE 1=1";
    $params = [];
    if ($class_id) {
        $sql .= " AND e.class_id = :cid";
        $params[':cid'] = (int)$class_id;
    }
    $sql .= " ORDER BY e.exam_date DESC, e.exam_id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    requireRoles($staffRoles);
    $action = $_GET['action'] ?? 'create';
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'save_marks') {
        $exam_id = (int)($input['exam_id'] ?? 0);
        $marks = $input['marks'] ?? [];

        if ($exam_id <= 0 || empty($marks) || !is_array($marks)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID and marks array are required.']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO exam_marks (exam_id, student_id, marks_obtained, grade, points, standard, remarks) 
                              VALUES (:eid, :sid, :marks, :gr, :pts, :std, :rem) 
                              ON DUPLICATE KEY UPDATE marks_obtained = :marks_upd, grade = :gr_upd, points = :pts_upd, standard = :std_upd, remarks = :rem_upd");

        $db->beginTransaction();
        try {
            foreach ($marks as $m) {
                $score = max(0.0, min(100.0, (float)$m['marks_obtained']));
                $converted = convertScoreTo9Point($score);
                $remarks = $m['remarks'] ?? null;
                $stmt->execute([
                    ':eid'       => $exam_id,
                    ':sid'       => (int)$m['student_id'],
                    ':marks'     => $score,
                    ':gr'        => $converted['grade'],
                    ':pts'       => $converted['points'],
                    ':std'       => $converted['standard'],
                    ':rem'       => $remarks,
                    ':marks_upd' => $score,
                    ':gr_upd'    => $converted['grade'],
                    ':pts_upd'   => $converted['points'],
                    ':std_upd'   => $converted['standard'],
                    ':rem_upd'   => $remarks,
                ]);
            }
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Exam marks recorded successfully.']);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Create exam
    $validTypes = ['Midterm', 'Final', 'Quiz', 'Unit Assessment', 'Mock', 'Continuous Assessment'];
    $examType = in_array($input['exam_type'] ?? '', $validTypes, true) ? $input['exam_type'] : 'Midterm';

    $stmt = $db->prepare("INSERT INTO exams (title, exam_type, class_id, subject, total_marks, passing_marks, exam_date, start_time, end_time, term, academic_year) 
                          VALUES (:title, :type, :cid, :sub, :total, :pass, :dt, :start, :end, :term, :yr)");
    $stmt->execute([
        ':title' => $input['title'],
        ':type'  => $examType,
        ':cid'   => (int)$input['class_id'],
        ':sub'   => $input['subject'],
        ':total' => (float)($input['total_marks'] ?? 100),
        ':pass'  => (float)($input['passing_marks'] ?? 40),
        ':dt'    => $input['exam_date'],
        ':start' => $input['start_time'] ?? '09:00:00',
        ':end'   => $input['end_time'] ?? '11:00:00',
        ':term'  => $input['term'] ?? 'TERM 2 2026',
        ':yr'    => $input['academic_year'] ?? '2026'
    ]);

    echo json_encode(['success' => true, 'exam_id' => $db->lastInsertId(), 'message' => 'Exam created.']);
    exit;
}
