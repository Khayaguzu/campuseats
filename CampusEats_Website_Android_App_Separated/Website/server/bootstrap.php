<?php
/** Shared infrastructure: PDO, secure cookie sessions, JSON responses and validation. */
declare(strict_types=1);
$configPath = __DIR__ . '/config.php';
if (!is_file($configPath))
{
    http_response_code(503);
    if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'api.php')
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Server setup required. Copy server/config.example.php to server/config.php, import the database and configure your WampServer credentials.']);
        exit;
    }
    exit('Setup required: copy server/config.example.php to server/config.php and follow README.md.');
}
$config = require $configPath;
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $error): void
{
    error_log((string)$error);
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Service unavailable. Check the server configuration and database connection.']);
});
ini_set('session.use_strict_mode', '1');
session_name('campuseats');
// PHP 7.3 introduced the options-array form with native SameSite support.
// On PHP 7.2 the path suffix is the compatibility technique for SameSite=Lax.
// Both branches retain the original session lifetime, HttpOnly and Secure settings.
if (PHP_VERSION_ID >= 70300)
{
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => (bool)$config['secure_cookies'],
        'samesite' => 'Lax'
    ]);
}
else
{
    session_set_cookie_params(0, '/; SameSite=Lax', '', (bool)$config['secure_cookies'], true);
}
session_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'], $config['db_password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES => false]
);
$pdo->exec("SET time_zone = '+00:00'");
// Assign the anti-CSRF token only once per session. The explicit check works on PHP 7.2.
if (!isset($_SESSION['csrf']))
{
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function query(string $sql, array $args = []): PDOStatement
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt;
}

/** Send the same JSON response as before; terminate execution after responding.
 * PHP 7.2 does not support the PHP 8.1 "never" return type or JSON_THROW_ON_ERROR.
 */
function respond(array $data, int $status = 200): void
{
    $body = json_encode($data);
    if ($body === false)
    {
        error_log('CampusEats JSON encoding error: ' . json_last_error_msg());
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"error":"The server could not create a response."}';
        exit;
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo $body;
    exit;
}

function fail(string $message, int $status = 422): void
{
    respond(['error' => $message], $status);
}

function currentUser(): ?array
{
    if (!isset($_SESSION['user_id']))
    {
        return null;
    }
    $user = query('SELECT id,name,email,role,requested_role,student_number,verified,phone,theme,order_updates FROM users WHERE id = ?', [$_SESSION['user_id']])->fetch();
    return $user ?: null;
}

function requireUser(bool $admin = false): array
{
    $user = currentUser();
    if (!$user)
    {
        fail('Please sign in first.', 401);
    }
    if ($admin && $user['role'] !== 'admin')
    {
        fail('Administrator access required.', 403);
    }
    return $user;
}

function textField(array $data, string $key, int $max = 200, bool $required = true): string
{
    $value = trim((string)($data[$key] ?? ''));
    if (($required && $value === '') || strlen($value) > $max)
    {
        fail("Please provide a valid {$key} (maximum {$max} characters).");
    }
    return $value;
}

function integerField(array $data, string $key, int $min, int $max): int
{
    $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value === null || $value < $min || $value > $max)
    {
        fail("Invalid {$key}.");
    }
    return $value;
}

/** Database-backed sliding window; IP comes only from the direct connection. */
function rateLimit(string $action, int $limit = 20): void
{
    $key = hash('sha256', $action . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
    query('DELETE FROM rate_limits WHERE occurred_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)');
    if ((int)query('SELECT COUNT(*) FROM rate_limits WHERE bucket = ?', [$key])->fetchColumn() >= $limit)
    {
        fail('Too many attempts. Please wait 15 minutes.', 429);
    }
    query('INSERT INTO rate_limits(bucket) VALUES (?)', [$key]);
}
