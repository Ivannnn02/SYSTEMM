<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const SMARTENROLL_LOGIN_MAX_ATTEMPTS = 5;
const SMARTENROLL_LOGIN_WINDOW_SECONDS = 900;
const SMARTENROLL_LOGIN_LOCK_SECONDS = 600;

function smartenroll_auth_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $secure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function smartenroll_auth_db(): mysqli
{
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartenroll');
    $conn->set_charset('utf8mb4');

    return $conn;
}

function smartenroll_ensure_users_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS users (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(150) NOT NULL,
            employee_id VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_users_employee_id (employee_id),
            UNIQUE KEY uniq_users_email (email),
            UNIQUE KEY uniq_users_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $employeeIdColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'employee_id'");
    if ($employeeIdColumn->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN employee_id VARCHAR(100) NOT NULL DEFAULT '' AFTER full_name");
        $conn->query("ALTER TABLE users ADD UNIQUE KEY uniq_users_employee_id (employee_id)");
    }
}

function smartenroll_find_user_by_email(mysqli $conn, string $email): ?array
{
    $stmt = $conn->prepare('SELECT id, full_name, employee_id, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function smartenroll_normalize_role(string $role): string
{
    $normalized = strtolower(trim($role));
    if (in_array($normalized, ['admin', 'registrar', 'finance'], true)) {
        return 'finance';
    }

    return $normalized;
}

function smartenroll_find_user_by_role(mysqli $conn, string $role): ?array
{
    $normalizedRole = smartenroll_normalize_role($role);

    if ($normalizedRole === 'finance') {
        $stmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE LOWER(TRIM(role)) IN ('finance', 'admin', 'registrar') LIMIT 1");
    } else {
        $stmt = $conn->prepare('SELECT id, full_name, email, role FROM users WHERE role = ? LIMIT 1');
        $stmt->bind_param('s', $role);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function smartenroll_find_user_by_employee_id(mysqli $conn, string $employeeId): ?array
{
    $stmt = $conn->prepare('SELECT id, full_name, employee_id, email, role FROM users WHERE employee_id = ? LIMIT 1');
    $stmt->bind_param('s', $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function smartenroll_login_user(array $user): void
{
    smartenroll_auth_start_session();
    session_regenerate_id(true);
    $normalizedRole = smartenroll_normalize_role((string)($user['role'] ?? ''));
    $_SESSION['auth_user'] = [
        'id' => (int) $user['id'],
        'full_name' => $user['full_name'],
        'employee_id' => $user['employee_id'] ?? '',
        'email' => $user['email'],
        'role' => $normalizedRole,
        'raw_role' => $user['role'] ?? $normalizedRole,
    ];
}

function smartenroll_current_user(): ?array
{
    smartenroll_auth_start_session();

    return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])
        ? $_SESSION['auth_user']
        : null;
}

function smartenroll_require_login(): array
{
    $user = smartenroll_current_user();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function smartenroll_require_role(...$roles): array
{
    $user = smartenroll_require_login();
    $role = smartenroll_normalize_role((string)($user['role'] ?? ''));

    if (count($roles) === 1 && is_array($roles[0])) {
        $roles = $roles[0];
    }

    $normalizedAllowed = array_map(
        static fn($item): string => smartenroll_normalize_role((string) $item),
        $roles
    );

    if (!in_array($role, $normalizedAllowed, true)) {
        http_response_code(403);
        exit('Forbidden');
    }

    return $user;
}

function smartenroll_csrf_token(string $formKey = 'default'): string
{
    smartenroll_auth_start_session();
    if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    if (!isset($_SESSION['csrf_tokens'][$formKey]) || !is_string($_SESSION['csrf_tokens'][$formKey]) || $_SESSION['csrf_tokens'][$formKey] === '') {
        $_SESSION['csrf_tokens'][$formKey] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_tokens'][$formKey];
}

function smartenroll_verify_csrf(string $token, string $formKey = 'default'): bool
{
    smartenroll_auth_start_session();
    $sessionToken = (string)($_SESSION['csrf_tokens'][$formKey] ?? '');
    if ($sessionToken === '' || $token === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function smartenroll_issue_one_time_token(string $scope): string
{
    smartenroll_auth_start_session();
    if (!isset($_SESSION['one_time_tokens']) || !is_array($_SESSION['one_time_tokens'])) {
        $_SESSION['one_time_tokens'] = [];
    }

    $token = bin2hex(random_bytes(16));
    $_SESSION['one_time_tokens'][$scope][$token] = time();
    return $token;
}

function smartenroll_consume_one_time_token(string $scope, string $token): bool
{
    smartenroll_auth_start_session();
    if ($token === '') {
        return false;
    }

    $exists = isset($_SESSION['one_time_tokens'][$scope][$token]);
    if ($exists) {
        unset($_SESSION['one_time_tokens'][$scope][$token]);
    }

    return $exists;
}

function smartenroll_login_attempt_key(string $email): string
{
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    return strtolower(trim($email)) . '|' . $ip;
}

function smartenroll_login_is_allowed(string $attemptKey, ?int &$retryAfterSeconds = null): bool
{
    smartenroll_auth_start_session();
    $retryAfterSeconds = null;

    $record = $_SESSION['login_attempts'][$attemptKey] ?? null;
    if (!is_array($record)) {
        return true;
    }

    $lockedUntil = (int)($record['locked_until'] ?? 0);
    $now = time();
    if ($lockedUntil > $now) {
        $retryAfterSeconds = $lockedUntil - $now;
        return false;
    }

    return true;
}

function smartenroll_record_login_failure(string $attemptKey): void
{
    smartenroll_auth_start_session();
    if (!isset($_SESSION['login_attempts']) || !is_array($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }

    $now = time();
    $record = $_SESSION['login_attempts'][$attemptKey] ?? [
        'count' => 0,
        'window_start' => $now,
        'locked_until' => 0,
    ];

    $windowStart = (int)($record['window_start'] ?? $now);
    if ($now - $windowStart > SMARTENROLL_LOGIN_WINDOW_SECONDS) {
        $record['count'] = 0;
        $record['window_start'] = $now;
        $record['locked_until'] = 0;
    }

    $record['count'] = (int)($record['count'] ?? 0) + 1;
    if ($record['count'] >= SMARTENROLL_LOGIN_MAX_ATTEMPTS) {
        $record['locked_until'] = $now + SMARTENROLL_LOGIN_LOCK_SECONDS;
    }

    $_SESSION['login_attempts'][$attemptKey] = $record;
}

function smartenroll_clear_login_failures(string $attemptKey): void
{
    smartenroll_auth_start_session();
    if (isset($_SESSION['login_attempts'][$attemptKey])) {
        unset($_SESSION['login_attempts'][$attemptKey]);
    }
}
function smartenroll_logout_user(): void
{
    smartenroll_auth_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
