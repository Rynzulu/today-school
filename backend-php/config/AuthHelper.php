<?php
/**
 * Shared Authentication, RBAC & CORS Security Helper
 */

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');

function isHttpsRequest(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
}

function sendApiHeaders(string $methods = 'GET, POST, OPTIONS'): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header("Access-Control-Allow-Methods: {$methods}");
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Ryn-Token, X-Educore-Token, X-Auth-Token, X-Admin-Key');
        header('Access-Control-Allow-Credentials: true');

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (!empty($origin)) {
            $parsed = parse_url($origin);
            $originHost = preg_replace('/^www\./i', '', $parsed['host'] ?? '');
            $serverHost = preg_replace('/^www\./i', '', explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
            $allowedEnv = trim((string)(getenv('ALLOWED_ORIGIN') ?: ''));
            $isLocalPair = in_array(strtolower($originHost), ['localhost', '127.0.0.1'], true)
                && in_array(strtolower($serverHost), ['localhost', '127.0.0.1'], true);

            if (($originHost && $serverHost && strcasecmp($originHost, $serverHost) === 0)
                || $isLocalPair
                || ($allowedEnv && hash_equals($allowedEnv, $origin))) {
                header("Access-Control-Allow-Origin: {$origin}");
                header('Vary: Origin');
            }
        }
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        $isHttps = isHttpsRequest();
        @session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => $isHttps ? 'None' : 'Lax',
        ]);
        @session_start();
    }
}

function getPhpAuthSecret(): string {
    $envSecret = trim((string)(getenv('SESSION_SECRET') ?: ''));
    if (strlen($envSecret) >= 16) {
        return $envSecret;
    }

    // Store inside a .php file so Nginx/Apache executes it (empty output) rather than serving plain text
    $phpSecretFile = __DIR__ . '/.ryn_php_secret.php';
    if (is_file($phpSecretFile)) {
        $loaded = @include $phpSecretFile;
        if (is_string($loaded) && strlen(trim($loaded)) >= 32) {
            return trim($loaded);
        }
    }

    // Legacy secret file support
    $legacySecretPhp = __DIR__ . '/.educore_php_secret.php';
    if (is_file($legacySecretPhp)) {
        $loadedLegacy = @include $legacySecretPhp;
        if (is_string($loadedLegacy) && strlen(trim($loadedLegacy)) >= 32) {
            return trim($loadedLegacy);
        }
    }
    $legacySecretFile = __DIR__ . '/.educore_php_secret';
    if (is_file($legacySecretFile)) {
        $saved = trim((string)@file_get_contents($legacySecretFile));
        if (strlen($saved) >= 32) {
            return $saved;
        }
    }

    // Try system temp dir fallback if config directory is read-only
    $tmpSecretFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ryn_php_secret_' . substr(md5(__DIR__), 0, 12) . '.php';
    if (is_file($tmpSecretFile)) {
        $loadedTmp = @include $tmpSecretFile;
        if (is_string($loadedTmp) && strlen(trim($loadedTmp)) >= 32) {
            return trim($loadedTmp);
        }
    }

    try {
        $generated = bin2hex(random_bytes(32));
        $phpContent = "<?php\nreturn " . var_export($generated, true) . ";\n";
        foreach ([$phpSecretFile, $tmpSecretFile] as $target) {
            $written = @file_put_contents($target, $phpContent, LOCK_EX);
            @chmod($target, 0600);
            if ($written !== false && $written > 32) {
                $verify = @include $target;
                if (is_string($verify) && hash_equals($generated, $verify)) {
                    return $generated;
                }
            }
        }
    } catch (Exception $e) {
        // Fallback deterministic secret if both directories are read-only
    }

    return hash('sha256', __FILE__ . '|' . __DIR__ . '|' . php_uname('n'));
}

function createPhpAuthToken(array $user): string {
    $payloadArr = [
        'uid' => (int)($user['id'] ?? 0),
        'username' => (string)($user['username'] ?? ''),
        'role' => (string)($user['role'] ?? 'student'),
        'email' => (string)($user['email'] ?? ''),
        'campus_id' => $user['campus_id'] ?? 'all',
        'exp' => time() + (14 * 24 * 60 * 60),
    ];
    $payloadB64 = rtrim(strtr(base64_encode(json_encode($payloadArr)), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $payloadB64, getPhpAuthSecret(), true);
    $sigB64 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    return $payloadB64 . '.' . $sigB64;
}

function verifyPhpAuthToken(string $token): ?array {
    $parts = explode('.', trim($token));
    if (count($parts) !== 2) return null;
    [$payloadB64, $sigB64] = $parts;
    $expectedSig = hash_hmac('sha256', $payloadB64, getPhpAuthSecret(), true);
    $expectedSigB64 = rtrim(strtr(base64_encode($expectedSig), '+/', '-_'), '=');
    if (!hash_equals($expectedSigB64, $sigB64)) {
        return null;
    }
    $padded = strtr($payloadB64, '-_', '+/');
    $mod = strlen($padded) % 4;
    if ($mod > 0) $padded .= str_repeat('=', 4 - $mod);
    $decoded = json_decode((string)base64_decode($padded), true);
    if (!is_array($decoded) || empty($decoded['uid']) || ((int)($decoded['exp'] ?? 0) < time())) {
        return null;
    }
    return [
        'id' => (int)$decoded['uid'],
        'username' => (string)($decoded['username'] ?? ''),
        'role' => (string)($decoded['role'] ?? 'student'),
        'email' => (string)($decoded['email'] ?? ''),
        'campus_id' => $decoded['campus_id'] ?? 'all',
    ];
}

function setPhpAuthCookie(string $token, bool $remember = true): void {
    if (headers_sent()) return;
    $isHttps = isHttpsRequest();
    $maxAge = $remember ? (14 * 24 * 60 * 60) : 0;
    $cookieParts = [
        'ryn_auth=' . rawurlencode($token),
        'Path=/',
        'HttpOnly',
        $isHttps ? 'SameSite=None' : 'SameSite=Lax',
    ];
    if ($maxAge > 0) {
        $cookieParts[] = 'Max-Age=' . $maxAge;
        $cookieParts[] = 'Expires=' . gmdate('D, d M Y H:i:s T', time() + $maxAge);
    }
    if ($isHttps) {
        $cookieParts[] = 'Secure';
        $cookieParts[] = 'Partitioned';
    }
    header('Set-Cookie: ' . implode('; ', $cookieParts), false);
}

function clearPhpAuthCookie(): void {
    if (headers_sent()) return;
    $isHttps = isHttpsRequest();
    $cookieParts = [
        'ryn_auth=',
        'Path=/',
        'HttpOnly',
        $isHttps ? 'SameSite=None' : 'SameSite=Lax',
        'Max-Age=0',
        'Expires=Thu, 01 Jan 1970 00:00:00 GMT',
    ];
    if ($isHttps) {
        $cookieParts[] = 'Secure';
        $cookieParts[] = 'Partitioned';
    }
    header('Set-Cookie: ' . implode('; ', $cookieParts), false);
}

function getBearerTokenFromRequest(): ?string {
    $headers = function_exists('getallheaders') ? @getallheaders() : [];
    if (!is_array($headers)) $headers = [];

    // Custom header fallback for Nginx / FastCGI / proxies that strip Authorization
    $customToken = $headers['X-Ryn-Token']
        ?? $headers['x-ryn-token']
        ?? $headers['X-Educore-Token']
        ?? $headers['x-educore-token']
        ?? $headers['X-Auth-Token']
        ?? $headers['x-auth-token']
        ?? ($_SERVER['HTTP_X_RYN_TOKEN'] ?? ($_SERVER['HTTP_X_EDUCORE_TOKEN'] ?? ($_SERVER['HTTP_X_AUTH_TOKEN'] ?? '')));
    if (!empty($customToken)) {
        return trim((string)$customToken);
    }

    $authHeader = $headers['Authorization']
        ?? $headers['authorization']
        ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if (!empty($authHeader) && preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        return trim($matches[1]);
    }

    if (!empty($_COOKIE['ryn_auth'])) {
        return trim((string)$_COOKIE['ryn_auth']);
    }
    if (!empty($_COOKIE['educore_auth'])) {
        return trim((string)$_COOKIE['educore_auth']);
    }
    return null;
}

function getAuthenticatedUser(): ?array {
    startSecureSession();

    // Check explicit bearer / custom header / cookie token first so fresh tokens always take precedence
    $bearer = getBearerTokenFromRequest();
    if ($bearer) {
        $tokenUser = verifyPhpAuthToken($bearer);
        if ($tokenUser) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['user'] = $tokenUser;
            }
            return $tokenUser;
        }
    }

    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        return $_SESSION['user'];
    }

    $rawCookieToken = $_COOKIE['ryn_auth'] ?? ($_COOKIE['educore_auth'] ?? '');
    if (!empty($rawCookieToken)) {
        $cookieUser = verifyPhpAuthToken((string)$rawCookieToken);
        if ($cookieUser) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['user'] = $cookieUser;
            }
            return $cookieUser;
        }
    }

    return null;
}

function isValidConfiguredAdminKey(): bool {
    $configuredKey = trim((string)(getenv('ADMIN_API_KEY') ?: ''));
    if ($configuredKey === '') {
        return false;
    }

    $headers = function_exists('getallheaders') ? @getallheaders() : [];
    if (!is_array($headers)) $headers = [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $adminKey = $headers['X-Admin-Key'] ?? $headers['x-admin-key'] ?? ($_SERVER['HTTP_X_ADMIN_KEY'] ?? '');

    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        if (hash_equals($configuredKey, trim($matches[1]))) {
            return true;
        }
    }

    if (!empty($adminKey) && hash_equals($configuredKey, trim($adminKey))) {
        return true;
    }

    return false;
}

function requireAuthenticatedUser(): array {
    $user = getAuthenticatedUser();
    if ($user) {
        return $user;
    }
    if (isValidConfiguredAdminKey()) {
        return ['id' => 0, 'username' => 'system_api_key', 'role' => 'superadmin'];
    }
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please sign in first.']);
    exit;
}

function requireRoles(array $allowedRoles): array {
    $user = requireAuthenticatedUser();
    if (!in_array($user['role'] ?? '', $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission for this operation.']);
        exit;
    }
    return $user;
}
