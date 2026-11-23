<?php
/**
 * Authentication & Role-Based Access Control (RBAC) API
 * Endpoints:
 *   POST /api/auth.php?action=login
 *   POST /api/auth.php?action=logout
 *   POST /api/auth.php?action=change_password
 *   GET  /api/auth.php?action=me
 */

require_once __DIR__ . '/../config/AuthHelper.php';
require_once __DIR__ . '/../config/Database.php';

sendApiHeaders('GET, POST, OPTIONS');
startSecureSession();

$action = $_GET['action'] ?? '';

if ($action === 'logout') {
    $_SESSION = [];
    clearPhpAuthCookie();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        @setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    @session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
    exit;
}

$database = new Database();
try {
    $db = $database->connect(false);
} catch (DatabaseConnectionException $e) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'database_error' => true,
        'connected' => false,
        'message' => $e->getMessage(),
        'hint' => $e->getHint(),
        'database_name' => $e->getDbName(),
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'database_error' => true,
        'connected' => false,
        'message' => 'Database connection failed. Please verify backend-php/config/Database.php.',
    ]);
    exit;
}

function isMysqlStoreInitialized(PDO $db): bool {
    try {
        $snapStmt = $db->query("SELECT id FROM school_state_snapshots WHERE id = 1 LIMIT 1");
        if ($snapStmt && $snapStmt->fetch()) {
            return true;
        }
    } catch (Exception $e) {
        // Snapshot table may not exist yet
    }
    try {
        $stuStmt = $db->query("SELECT COUNT(*) AS cnt FROM students");
        $row = $stuStmt ? $stuStmt->fetch() : null;
        if ($row && (int)($row['cnt'] ?? 0) > 0) {
            return true;
        }
    } catch (Exception $e) {
        // Ignore
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
        exit;
    }

    try {
        try {
            $stmt = $db->prepare('SELECT id, username, password_hash, role, email, campus_id, privileges FROM users WHERE LOWER(username) = LOWER(:username) OR LOWER(email) = LOWER(:email) LIMIT 1');
            $stmt->execute([':username' => $username, ':email' => $username]);
            $user = $stmt->fetch();
        } catch (PDOException $colErr) {
            if (strpos($colErr->getMessage(), '42S22') !== false || stripos($colErr->getMessage(), 'unknown column') !== false) {
                $stmt = $db->prepare('SELECT id, username, password_hash, role, email FROM users WHERE LOWER(username) = LOWER(:username) OR LOWER(email) = LOWER(:email) LIMIT 1');
                $stmt->execute([':username' => $username, ':email' => $username]);
                $user = $stmt->fetch();
            } else {
                throw $colErr;
            }
        }

        if (!$user) {
            try {
                $admStmt = $db->prepare("SELECT u.id, u.username, u.password_hash, u.role, u.email, u.campus_id, u.privileges FROM users u INNER JOIN students s ON s.user_id = u.id WHERE LOWER(s.admission_no) = LOWER(:adm) AND u.role = 'student' LIMIT 1");
                $admStmt->execute([':adm' => $username]);
                $user = $admStmt->fetch();
            } catch (PDOException $admErr) {
                if (strpos($admErr->getMessage(), '42S22') !== false || stripos($admErr->getMessage(), 'unknown column') !== false) {
                    $admStmt = $db->prepare("SELECT u.id, u.username, u.password_hash, u.role, u.email FROM users u INNER JOIN students s ON s.user_id = u.id WHERE LOWER(s.admission_no) = LOWER(:adm) AND u.role = 'student' LIMIT 1");
                    $admStmt->execute([':adm' => $username]);
                    $user = $admStmt->fetch();
                } else {
                    throw $admErr;
                }
            }
        }
    } catch (PDOException $e) {
        error_log('RYN Login Query Error: ' . $e->getMessage());
        $msgLower = strtolower($e->getMessage());
        if (strpos($msgLower, '42s02') !== false || strpos($msgLower, 'doesn\'t exist') !== false || strpos($msgLower, 'base table') !== false) {
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'database_error' => true,
                'message' => 'Connected to MySQL database "' . $database->getDatabaseName() . '", but required tables are missing. Please import schema.sql in phpMyAdmin first.'
            ]);
            exit;
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'database_error' => true,
            'message' => 'Database query error during login (' . $e->getCode() . '). Please verify schema.sql was imported cleanly.'
        ]);
        exit;
    }

    if ($user && password_verify($password, $user['password_hash'])) {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }
        unset($user['password_hash']);

        if (!empty($user['privileges']) && is_string($user['privileges'])) {
            $decoded = json_decode($user['privileges'], true);
            $user['privileges'] = is_array($decoded) ? $decoded : null;
        }

        // Fetch role specific metadata
        $profile = [];
        try {
            if ($user['role'] === 'student') {
                $sStmt = $db->prepare('SELECT s.*, c.class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id = c.class_id WHERE s.user_id = :uid LIMIT 1');
                $sStmt->execute([':uid' => $user['id']]);
                $profile = $sStmt->fetch() ?: [];
            } elseif ($user['role'] === 'teacher') {
                $tStmt = $db->prepare('SELECT * FROM teachers WHERE user_id = :uid LIMIT 1');
                $tStmt->execute([':uid' => $user['id']]);
                $profile = $tStmt->fetch() ?: [];
            } elseif ($user['role'] === 'parent') {
                $pStmt = $db->prepare('SELECT s.*, c.class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id = c.class_id WHERE s.parent_id = :pid');
                $pStmt->execute([':pid' => $user['id']]);
                $profile['children'] = $pStmt->fetchAll();
            }
        } catch (Exception $e) {
            $profile = [];
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['user'] = $user;
            $_SESSION['profile'] = $profile;
        }
        $token = createPhpAuthToken($user);
        setPhpAuthCookie($token, !empty($input['rememberMe']));

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'profile' => $profile,
            'token' => $token,
            'initialized' => isMysqlStoreInitialized($db),
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'change_password') {
    $sessionUser = requireAuthenticatedUser();
    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = trim($input['current_password'] ?? '');
    $newPassword = trim($input['new_password'] ?? '');

    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
        exit;
    }

    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$sessionUser['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $uStmt = $db->prepare('UPDATE users SET password_hash = :ph WHERE id = :id');
    $uStmt->execute([':ph' => $newHash, ':id' => (int)$sessionUser['id']]);

    echo json_encode(['success' => true, 'message' => 'Password updated successfully on server.']);
    exit;
}

if ($action === 'me') {
    $authUser = getAuthenticatedUser();
    if ($authUser) {
        // Refresh user attributes from DB if user still exists in MySQL
        try {
            $uStmt = $db->prepare('SELECT id, username, role, email, campus_id, privileges FROM users WHERE id = :id LIMIT 1');
            $uStmt->execute([':id' => (int)($authUser['id'] ?? 0)]);
            $freshUser = $uStmt->fetch();
            if ($freshUser) {
                if (!empty($freshUser['privileges']) && is_string($freshUser['privileges'])) {
                    $decoded = json_decode($freshUser['privileges'], true);
                    $freshUser['privileges'] = is_array($decoded) ? $decoded : null;
                }
                $authUser = $freshUser;
            }
        } catch (Exception $e) {
            // Keep token-verified user if DB read fails transiently
        }

        $refreshedToken = createPhpAuthToken($authUser);
        echo json_encode([
            'success' => true,
            'user' => $authUser,
            'profile' => $_SESSION['profile'] ?? [],
            'token' => $refreshedToken,
            'initialized' => isMysqlStoreInitialized($db),
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    }
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Action not found.']);
