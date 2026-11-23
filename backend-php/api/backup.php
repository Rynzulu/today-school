<?php
/**
 * Database Backup & Restore Utility API
 * Provides SQL dump export and SQL script execution
 * SECURED: Requires authenticated Superadmin or Administrator session / configured ADMIN_API_KEY
 */

require_once __DIR__ . '/../config/AuthHelper.php';
require_once __DIR__ . '/../config/Database.php';

sendApiHeaders('GET, POST, OPTIONS');

$action = $_GET['action'] ?? 'backup';
if ($action === 'restore') {
    requireRoles(['superadmin']);
} else {
    requireRoles(['superadmin', 'admin']);
}

$database = new Database();
$db = $database->connect();

if ($action === 'backup') {
    $tables = ['users', 'campuses', 'academic_years', 'school_sections', 'classes', 'teachers', 'students', 'subjects', 'subject_assignments', 'assessment_columns', 'result_locks', 'attendance', 'marks', 'subject_scores', 'exams', 'exam_marks', 'fees', 'timetable', 'hostel_blocks', 'hostel_allocations', 'exeat_permits', 'meal_cards', 'meal_logs', 'transport_vehicles', 'transport_routes', 'notices', 'calendar_events', 'learning_materials', 'homework_submissions', 'school_state_snapshots'];

    $sqlDump = "-- ========================================================\n";
    $sqlDump .= "-- RYN School Management System - Database Backup Dump\n";
    $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- ========================================================\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        try {
            $createTableStmt = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            if ($createTableStmt) {
                $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sqlDump .= $createTableStmt[1] . ";\n\n";

                $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                if (count($rows) > 0) {
                    $cols = array_keys($rows[0]);
                    $colList = implode('`, `', $cols);

                    $sqlDump .= "INSERT INTO `{$table}` (`{$colList}`) VALUES\n";
                    $valRows = [];
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($row as $val) {
                            $values[] = is_null($val) ? 'NULL' : $db->quote($val);
                        }
                        $valRows[] = "(" . implode(', ', $values) . ")";
                    }
                    $sqlDump .= implode(",\n", $valRows) . ";\n\n";
                }
            }
        } catch (Exception $e) {
            // Skip tables that haven't been created
        }
    }

    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    if (isset($_GET['download']) && $_GET['download'] === '1') {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="school_management_backup_' . date('Ymd_His') . '.sql"');
        echo $sqlDump;
        exit;
    }

    echo json_encode(['success' => true, 'sql' => $sqlDump, 'filename' => 'school_management_backup_' . date('Ymd_His') . '.sql']);
    exit;
}

if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $sql = $input['sql'] ?? '';

    if (empty($sql)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'SQL script is required.']);
        exit;
    }

    try {
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1);
        $db->exec($sql);
        echo json_encode(['success' => true, 'message' => 'Database successfully restored from SQL script.']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'SQL execution error occurred during database restoration.']);
    }
    exit;
}
