<?php
/**
 * Fees & Billing API - Invoice Generation, Payment Tracking & Receipts
 */
require_once __DIR__ . '/../config/AuthHelper.php';
require_once __DIR__ . '/../config/Database.php';

sendApiHeaders('GET, POST, PUT, OPTIONS');
$authUser = requireAuthenticatedUser();

$database = new Database();
$db = $database->connect();
$method = $_SERVER['REQUEST_METHOD'];

$staffRoles = ['superadmin', 'admin', 'headteacher', 'deputy_headteacher', 'senior_teacher', 'teacher'];
$adminRoles = ['superadmin', 'admin', 'headteacher', 'deputy_headteacher'];

if ($method === 'GET') {
    $role = $authUser['role'] ?? '';
    $student_id = $_GET['student_id'] ?? null;
    $parent_id = $_GET['parent_id'] ?? null;
    $fee_id = $_GET['fee_id'] ?? null;

    if ($fee_id) {
        $sql = "SELECT f.*, s.first_name, s.last_name, s.admission_no, s.user_id AS student_user_id, s.parent_id AS student_parent_id,
                       c.class_name, c.section, u.email as student_email
                FROM fees f
                JOIN students s ON f.student_id = s.student_id
                JOIN users u ON s.user_id = u.id
                LEFT JOIN classes c ON s.class_id = c.class_id
                WHERE f.fee_id = :fid LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':fid' => (int)$fee_id]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Fee invoice not found.']);
            exit;
        }
        if ($role === 'student' && (int)$row['student_user_id'] !== (int)$authUser['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden.']);
            exit;
        }
        if ($role === 'parent' && (int)$row['student_parent_id'] !== (int)$authUser['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden.']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $row]);
        exit;
    }

    $sql = "SELECT f.*, s.first_name, s.last_name, s.admission_no, c.class_name, c.section
            FROM fees f
            JOIN students s ON f.student_id = s.student_id
            LEFT JOIN classes c ON s.class_id = c.class_id
            WHERE 1=1";
    $params = [];

    if ($role === 'student') {
        $sql .= " AND s.user_id = :auth_uid";
        $params[':auth_uid'] = (int)$authUser['id'];
    } elseif ($role === 'parent') {
        $sql .= " AND s.parent_id = :auth_pid";
        $params[':auth_pid'] = (int)$authUser['id'];
    } elseif (in_array($role, $staffRoles, true)) {
        if ($student_id) {
            $sql .= " AND f.student_id = :sid";
            $params[':sid'] = (int)$student_id;
        }
        if ($parent_id) {
            $sql .= " AND s.parent_id = :pid";
            $params[':pid'] = (int)$parent_id;
        }
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden.']);
        exit;
    }

    $sql .= " ORDER BY f.due_date ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    $totalBilled = 0;
    $totalPaid = 0;
    foreach ($records as $r) {
        $totalBilled += (float)$r['total_amount'];
        $totalPaid += (float)$r['paid_amount'];
    }

    echo json_encode([
        'success' => true,
        'data' => $records,
        'summary' => [
            'total_billed' => $totalBilled,
            'total_paid' => $totalPaid,
            'outstanding' => $totalBilled - $totalPaid
        ]
    ]);
    exit;
}

if ($method === 'POST') {
    requireRoles($adminRoles);
    $action = $_GET['action'] ?? 'create';
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'pay') {
        $fee_id = (int)($input['fee_id'] ?? 0);
        $amount_to_pay = (float)($input['amount'] ?? 0);

        if ($fee_id <= 0 || $amount_to_pay <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A valid fee ID and positive payment amount are required.']);
            exit;
        }

        $fStmt = $db->prepare("SELECT total_amount, paid_amount FROM fees WHERE fee_id = :fid LIMIT 1");
        $fStmt->execute([':fid' => $fee_id]);
        $fee = $fStmt->fetch();

        if (!$fee) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Fee invoice not found.']);
            exit;
        }

        $newPaid = (float)$fee['paid_amount'] + $amount_to_pay;
        $status = ($newPaid >= (float)$fee['total_amount']) ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
        $receipt = 'REC-' . date('Y') . '-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

        $uStmt = $db->prepare("UPDATE fees SET paid_amount = :paid, status = :st, receipt_no = :rec WHERE fee_id = :fid");
        $uStmt->execute([':paid' => $newPaid, ':st' => $status, ':rec' => $receipt, ':fid' => $fee_id]);

        echo json_encode(['success' => true, 'message' => 'Payment recorded successfully.', 'receipt_no' => $receipt]);
        exit;
    }

    // Create Invoice
    $student_id = (int)($input['student_id'] ?? 0);
    $title = trim($input['title'] ?? 'School Tuition Fee');
    $total_amount = (float)($input['total_amount'] ?? 0);
    $paid_amount = max(0.0, (float)($input['paid_amount'] ?? 0));
    $due_date = $input['due_date'] ?? date('Y-m-d', strtotime('+30 days'));

    if ($student_id <= 0 || $total_amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid student ID and positive total amount are required.']);
        exit;
    }

    $status = ($paid_amount >= $total_amount && $total_amount > 0) ? 'paid' : ($paid_amount > 0 ? 'partial' : 'unpaid');
    $receipt_no = $paid_amount > 0 ? 'REC-' . date('Y') . '-' . random_int(1000, 9999) : null;

    $stmt = $db->prepare("INSERT INTO fees (student_id, title, total_amount, paid_amount, due_date, status, receipt_no)
                          VALUES (:sid, :title, :total, :paid, :due, :st, :rec)");
    $stmt->execute([
        ':sid'   => $student_id,
        ':title' => $title,
        ':total' => $total_amount,
        ':paid'  => $paid_amount,
        ':due'   => $due_date,
        ':st'    => $status,
        ':rec'   => $receipt_no
    ]);

    echo json_encode(['success' => true, 'message' => 'Fee invoice created.', 'fee_id' => $db->lastInsertId()]);
    exit;
}
