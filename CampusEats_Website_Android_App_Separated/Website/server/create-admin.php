<?php
/** Run only from a terminal: php server/create-admin.php email name password. */
if (PHP_SAPI !== 'cli')
{
    http_response_code(404);
    exit;
}
$config = require __DIR__ . '/config.php';
[$script, $email, $name, $password] = array_pad($argv, 4, '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$name || strlen($password) < 12 || strlen($password) > 72)
{
    exit("Usage: php server/create-admin.php admin@example.com 'Admin Name' 'unique-12-character-password'\n");
}
$pdo = new PDO("mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4", $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stmt = $pdo->prepare("INSERT INTO users(name,email,password_hash,role,requested_role,verified) VALUES (?,?,?,'admin','admin',1)");
$stmt->execute([$name, strtolower($email), password_hash($password, PASSWORD_DEFAULT)]);
echo "Administrator created. Sign in with the credentials you supplied.\n";
