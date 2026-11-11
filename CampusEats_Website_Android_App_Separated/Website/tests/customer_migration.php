<?php
/** Run only in CI: verifies an upgrade without changing the application database. */
$config = require __DIR__ . '/../server/config.php';
$pdo = new PDO("mysql:host={$config['db_host']};port={$config['db_port']};charset=utf8mb4",
    $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'campuseats_migration_' . bin2hex(random_bytes(4));
$pdo->exec("CREATE DATABASE `$database`");
try
{
    $pdo->exec("USE `$database`");
    $pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, role ENUM('student','lecturer','admin') NOT NULL DEFAULT 'lecturer', requested_role VARCHAR(30), password_hash VARCHAR(100), google_sub VARCHAR(100))");
    $pdo->exec("INSERT INTO users VALUES (1,'student','student','password1','google1'),(2,'lecturer','admin','password2',NULL),(3,'admin','admin','password3','google3')");
    $pdo->exec('CREATE TABLE orders (id INT PRIMARY KEY,user_id INT,total_cents INT,discount_cents INT,FOREIGN KEY(user_id) REFERENCES users(id))');
    $pdo->exec('INSERT INTO orders VALUES (7,1,8100,900)');
    $migration = file_get_contents(__DIR__ . '/../database/general_customers_migration.sql');
    // Repeat to prove an accidental second import is safe.
    $pdo->exec($migration);
    $pdo->exec($migration);
    $roles = $pdo->query('SELECT role FROM users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    if ($roles !== ['customer', 'customer', 'admin'])
    {
        throw new RuntimeException('Migration must preserve admins and never promote pending requests.');
    }
    $row = $pdo->query('SELECT password_hash,google_sub FROM users WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    $order = $pdo->query('SELECT total_cents,discount_cents FROM orders WHERE id=7')->fetch(PDO::FETCH_ASSOC);
    if ($row !== ['password_hash' => 'password1', 'google_sub' => 'google1'] ||
        (int)$order['total_cents'] !== 8100 || (int)$order['discount_cents'] !== 900)
    {
        throw new RuntimeException('Existing identity and order history must be preserved.');
    }
    $pdo->exec('INSERT INTO users(id) VALUES (4)');
    if ($pdo->query('SELECT role FROM users WHERE id=4')->fetchColumn() !== 'customer')
    {
        throw new RuntimeException('New accounts must default to customer.');
    }
    echo "PASS: migration preserves identities, administrators and historical prices; rerun is safe\n";
}
finally
{
    $pdo->exec("DROP DATABASE `$database`");
}
