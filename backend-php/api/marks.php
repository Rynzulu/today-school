<?php
/**
 * Marks / Gradebook API (Aligned to Zambian ECZ 9-Point Grading Scale)
 */
require_once __DIR__ . '/../config/AuthHelper.php';
require_once __DIR__ . '/../config/Database.php';

sendApiHeaders('GET, POST, OPTIONS');
$authUser = requireAuthenticatedUser();

$database = new Database();
$db = $database->connect();
$method = $_SERVER['REQUEST_METHOD'];

function calculateGrade(float $score): string {
    if ($score >= 75.0) return '1';
    if ($score >= 70.0) return '2';
    if ($score >= 65.0) return '3';
    if ($score >= 60.0) return '4';
    if ($score >= 55.0) return '5';
    if ($score >= 50.0) return '6';
    if ($score >= 45.0) return '7';
    if ($score >= 40.0) return '8';
    return '9';
}

if ($method === 'GET') {
    $student_id = $_GET['student_id'] ?? null;
    $class_id = $_GET['class_id'] ?? null;
    $term = $_GET['term'] ?? null;
    $subject = $_GET['subject'] ?? null;

    // Students and parents may only inspect their own / their children's marks
    if ($authUser['role'] === 'student') {
        $sLookup = $db->prepare("SELECT student_id FROM students WHERE user_id = :uid LIMIT 1");
        $sLookup->execute([':uid' => (int)$authUser['id']]);
        $sRow = $sLookup->fetch();
        $student_id = $sRow ? $sRow['student_id'] : -1;
        $class_id = null;
    } elseif ($authUser['role'] === 'parent') {
        $pLookup = $db->prepare("SELECT student_id FROM students WHERE parent_id = :pid");
        $pLookup->execute([':pid' => (int)$authUser['id']]);
        $childIds = array_map(fn($r) => (int)$r['student_id'], $pLookup->fetchAll());
        if ($student_id && !in_array((int)$student_id, $childIds, true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden: Student is not linked to your parent account.']);
            exit;
        }
        if (!$student_id) {
            $student_id = $childIds[0] ?? -1;
        }
        $class_id = null;
    }

    if ($student_id !== null) {
        $sql = "SELECT m.*, s.first_name, s.last_name, c.class_name, c.section 
                FROM marks m
                JOIN students s ON m.student_id = s.student_id
                LEFT JOIN classes c ON s.class_id = c.class_id
                WHERE m.student_id = :sid";
        $params = [':sid' => (int)$student_id];
        if ($term) {
            $sql .= " AND m.term = :term";
            $params[':term'] = $term;
        }
        $sql .= " ORDER BY m.term, m.subject";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $totalScore = 0;
        $count = count($rows);
        foreach ($rows as $r) { $totalScore += (float)$r['score']; }
        $avg = $count > 0 ? round($totalScore / $count, 2) : 0;

        echo json_encode([
            'success' => true,
            'data' => $rows,
            'summary' => [
                'total_subjects' => $count,
                'average_score' => $avg,
                'overall_grade' => calculateGrade($avg)
            ]
        ]);
        exit;
    }

    if ($class_id && $subject) {
        $stmt = $db->prepare("SELECT s.student_id, s.first_name, s.last_name, s.admission_no,
                                     m.mark_id, m.score, m.grade, m.teacher_comment, m.term
                              FROM students s
                              LEFT JOIN marks m ON s.student_id = m.student_id AND m.subject = :sub AND m.term = :term
                              WHERE s.class_id = :cid
                              ORDER BY s.first_name, s.last_name");
        $stmt->execute([':sub' => $subject, ':term' => $term ?? 'TERM 2 2026', ':cid' => (int)$class_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    $stmt = $db->query("SELECT m.*, s.first_name, s.last_name FROM marks m JOIN students s ON m.student_id = s.student_id ORDER BY m.mark_id DESC LIMIT 100");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    requireRoles(['superadmin', 'admin', 'headteacher', 'deputy_headteacher', 'senior_teacher', 'teacher']);

    $input = json_decode(file_get_contents('php://input'), true);
    $records = $input['records'] ?? [];
    $term = $input['term'] ?? 'TERM 2 2026';
    $subject = trim($input['subject'] ?? '');

    if (empty($records) || empty($subject)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Records and subject are required.']);
        exit;
    }

    if ($authUser['role'] === 'teacher') {
        $tCheck = $db->prepare("SELECT t.teacher_id FROM teachers t WHERE t.user_id = :uid LIMIT 1");
        $tCheck->execute([':uid' => (int)$authUser['id']]);
        $tRow = $tCheck->fetch();
        if ($tRow) {
            $asCheck = $db->prepare("SELECT COUNT(*) as cnt FROM subject_assignments WHERE teacher_id = :tid AND UPPER(subject_name) = UPPER(:sub)");
            $asCheck->execute([':tid' => (int)$tRow['teacher_id'], ':sub' => $subject]);
            $asRow = $asCheck->fetch();
            if ((int)($asRow['cnt'] ?? 0) === 0) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Access Restricted: You are only permitted to enter marks for subjects and classes assigned to you.'
                ]);
                exit;
            }
        }
    }

    $stmt = $db->prepare("INSERT INTO marks (student_id, subject, score, term, grade, teacher_comment)
                          VALUES (:sid, :sub, :score, :term, :gr, :com)
                          ON DUPLICATE KEY UPDATE score = :score_upd, grade = :gr_upd, teacher_comment = :com_upd");

    $db->beginTransaction();
    try {
        foreach ($records as $r) {
            $score = max(0, min(100, (float)$r['score']));
            $grade = calculateGrade($score);
            $comment = $r['teacher_comment'] ?? null;
            $stmt->execute([
                ':sid'       => (int)$r['student_id'],
                ':sub'       => $subject,
                ':score'     => $score,
                ':term'      => $term,
                ':gr'        => $grade,
                ':com'       => $comment,
                ':score_upd' => $score,
                ':gr_upd'    => $grade,
                ':com_upd'   => $comment,
            ]);
        }
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Grades saved successfully.']);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save marks.']);
    }
    exit;
}
