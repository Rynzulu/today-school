<?php
/**
 * RYN School Management System
 * Database Configuration using PHP PDO (PHP Data Objects)
 * Provides resilient MySQL connection with .env/db_config.php support,
 * localhost/127.0.0.1 TCP fallback, and safe diagnostic hints.
 */

class DatabaseConnectionException extends RuntimeException {
    private string $hint;
    private string $dbName;
    private string $dbHost;
    private int $dbPort;

    public function __construct(string $message, string $hint = '', string $dbName = '', string $dbHost = '', int $dbPort = 3306, ?Throwable $previous = null) {
        parent::__construct($message, 0, $previous);
        $this->hint = $hint;
        $this->dbName = $dbName;
        $this->dbHost = $dbHost;
        $this->dbPort = $dbPort;
    }

    public function getHint(): string {
        return $this->hint;
    }

    public function getDbName(): string {
        return $this->dbName;
    }

    public function getDbHost(): string {
        return $this->dbHost;
    }

    public function getDbPort(): int {
        return $this->dbPort;
    }
}

class Database {
    // cPanel / XAMPP settings: update these or set DB_HOST / DB_NAME / DB_USER / DB_PASS in .env or db_config.php
    private string $host = 'localhost';
    private string $db_name = 'school_management_db';
    private string $username = 'root';
    private string $password = '';
    private int $port = 3306;
    private ?PDO $conn = null;
    private ?string $lastError = null;
    private ?string $lastHint = null;

    public function __construct() {
        $this->loadDotEnv();
        $this->loadPhpConfigOverride();

        $envHost = getenv('DB_HOST');
        $envName = getenv('DB_NAME');
        $envUser = getenv('DB_USER');
        $envPass = getenv('DB_PASS');
        $envPort = getenv('DB_PORT');

        if ($envHost !== false && trim($envHost) !== '') $this->host = trim($envHost);
        if ($envName !== false && trim($envName) !== '') $this->db_name = trim($envName);
        if ($envUser !== false && trim($envUser) !== '') $this->username = trim($envUser);
        if ($envPass !== false) $this->password = $envPass;
        if ($envPort !== false && (int)$envPort > 0) $this->port = (int)$envPort;
    }

    private function loadDotEnv(): void {
        $candidates = [
            dirname(__DIR__, 2) . '/.env',
            dirname(__DIR__) . '/.env',
        ];
        foreach ($candidates as $envFile) {
            if (!is_file($envFile) || !is_readable($envFile)) continue;
            $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) continue;
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || $trimmed[0] === '#' || strpos($trimmed, '=') === false) continue;
                [$key, $val] = explode('=', $trimmed, 2);
                $key = trim($key);
                $val = trim($val);
                if (strlen($val) >= 2 && (($val[0] === '"' && substr($val, -1) === '"') || ($val[0] === "'" && substr($val, -1) === "'"))) {
                    $val = substr($val, 1, -1);
                }
                if (in_array($key, ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT', 'SESSION_SECRET', 'ALLOWED_ORIGIN', 'ADMIN_API_KEY'], true)) {
                    if (getenv($key) === false && $val !== 'MY_GEMINI_API_KEY') {
                        @putenv("{$key}={$val}");
                    }
                }
            }
            break;
        }
    }

    private function loadPhpConfigOverride(): void {
        $configFile = __DIR__ . '/db_config.php';
        if (!is_file($configFile) || !is_readable($configFile)) return;
        $cfg = @include $configFile;
        if (!is_array($cfg)) return;
        if (!empty($cfg['host'])) $this->host = trim((string)$cfg['host']);
        if (!empty($cfg['db_name'])) $this->db_name = trim((string)$cfg['db_name']);
        if (!empty($cfg['username'])) $this->username = trim((string)$cfg['username']);
        if (array_key_exists('password', $cfg)) $this->password = (string)$cfg['password'];
        if (!empty($cfg['port'])) $this->port = (int)$cfg['port'];
    }

    public function getDatabaseName(): string {
        return $this->db_name;
    }

    public function getHost(): string {
        return $this->host;
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function getLastHint(): ?string {
        return $this->lastHint;
    }

    private function diagnosePdoException(PDOException $e, string $attemptedHost): array {
        $raw = $e->getMessage();
        $lower = strtolower($raw);

        if (strpos($lower, '1049') !== false || strpos($lower, 'unknown database') !== false) {
            return [
                "MySQL database \"{$this->db_name}\" was not found on {$attemptedHost}.",
                "Create the database \"{$this->db_name}\" in phpMyAdmin / cPanel MySQL Databases and import schema.sql, or update \$db_name in backend-php/config/Database.php."
            ];
        }
        if (strpos($lower, '1045') !== false || strpos($lower, 'access denied') !== false) {
            return [
                "MySQL rejected credentials for user \"{$this->username}\" on {$attemptedHost}.",
                "Verify \$username and \$password in backend-php/config/Database.php (or DB_USER/DB_PASS environment variables) and ensure ALL PRIVILEGES are granted on \"{$this->db_name}\"."
            ];
        }
        if (strpos($lower, '2002') !== false || strpos($lower, 'no such file or directory') !== false || strpos($lower, 'connection refused') !== false || strpos($lower, 'actively refused') !== false) {
            return [
                "Unable to reach MySQL server at {$attemptedHost}:{$this->port}.",
                "Ensure the MySQL/MariaDB service is running and verify \$host ('localhost' or '127.0.0.1') and \$port ({$this->port}) in backend-php/config/Database.php."
            ];
        }
        if (strpos($lower, 'could not find driver') !== false) {
            return [
                "PHP PDO MySQL driver (pdo_mysql) is not enabled on this server.",
                "Enable the pdo_mysql and mysqli extensions in cPanel -> Select PHP Version (or php.ini)."
            ];
        }

        return [
            "MySQL connection failed ({$attemptedHost}:{$this->port} / {$this->db_name}).",
            "Verify host, port, database name, username, and password in backend-php/config/Database.php."
        ];
    }

    /**
     * Get Database Connection
     * @param bool $exitOnError When true, writes HTTP 500/503 JSON and exits (backward-compatible default for CRUD routes).
     *                          When false, throws DatabaseConnectionException for caller diagnostics.
     */
    public function connect(bool $exitOnError = true): ?PDO {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }
        $this->lastError = null;
        $this->lastHint = null;

        if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
            $msg = 'PHP PDO MySQL extension (pdo_mysql) is not enabled.';
            $hint = 'Enable pdo_mysql in cPanel -> Select PHP Version or php.ini.';
            $this->lastError = $msg;
            $this->lastHint = $hint;
            if (!$exitOnError) {
                throw new DatabaseConnectionException($msg, $hint, $this->db_name, $this->host, $this->port);
            }
            http_response_code(503);
            echo json_encode(['success' => false, 'connected' => false, 'message' => $msg, 'hint' => $hint]);
            exit;
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        // Try configured host first; if localhost socket fails, automatically retry 127.0.0.1 TCP (and vice versa)
        $hostsToTry = [$this->host];
        if (strcasecmp($this->host, 'localhost') === 0) {
            $hostsToTry[] = '127.0.0.1';
        } elseif ($this->host === '127.0.0.1') {
            $hostsToTry[] = 'localhost';
        }

        $lastException = null;
        $lastAttemptedHost = $this->host;

        foreach ($hostsToTry as $tryHost) {
            $lastAttemptedHost = $tryHost;
            $dsn = "mysql:host={$tryHost};port={$this->port};dbname={$this->db_name};charset=utf8mb4";
            try {
                $this->conn = new PDO($dsn, $this->username, $this->password, $options);
                return $this->conn;
            } catch (PDOException $e) {
                $lastException = $e;
                error_log("RYN DB Connection Error ({$tryHost}): " . $e->getMessage());
                $lower = strtolower($e->getMessage());
                // Only fall back to alternate host on socket/network errors (2002), not on auth/missing-db errors
                if (strpos($lower, '1045') !== false || strpos($lower, '1049') !== false) {
                    break;
                }
            }
        }

        [$msg, $hint] = $this->diagnosePdoException($lastException, $lastAttemptedHost);
        $this->lastError = $msg;
        $this->lastHint = $hint;

        if (!$exitOnError) {
            throw new DatabaseConnectionException($msg, $hint, $this->db_name, $lastAttemptedHost, $this->port, $lastException);
        }

        http_response_code(503);
        echo json_encode([
            'success' => false,
            'connected' => false,
            'message' => $msg,
            'hint' => $hint,
            'database_name' => $this->db_name,
        ]);
        exit;
    }
}
