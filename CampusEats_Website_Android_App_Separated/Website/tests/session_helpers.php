<?php
require __DIR__ . '/../server/session.php';
function check(bool $condition, string $message): void
{
    if (!$condition)
    {
        throw new RuntimeException($message);
    }
}
$active = ['user_id' => 1, 'authenticated_at' => 1000, 'last_activity' => 2000];
check(!sessionExpired([], 3000, 1800, 28800), 'Anonymous session');
check(!sessionExpired($active, 3000, 1800, 28800), 'Active session');
check(sessionExpired($active, 3800, 1800, 28800), 'Idle boundary');
$active['last_activity'] = 29799;
check(sessionExpired($active, 29800, 1800, 28800), 'Absolute boundary despite activity');
check(sessionExpired(['user_id' => 1], 3000, 1800, 28800), 'Legacy session');
session_start();
$oldId = session_id();
$_SESSION = ['csrf' => 'test-csrf', 'oauth_state' => 'old'];
establishSession(42, 'google');
check(session_id() !== $oldId, 'Session fixation prevention');
check($_SESSION['user_id'] === 42 && $_SESSION['auth_method'] === 'google', 'Identity established');
check(!isset($_SESSION['oauth_state']), 'Old OAuth state cleared');
check($_SESSION['csrf'] === 'test-csrf', 'Existing API token remains usable');
echo "PASS: session lifetime, fixation prevention and authentication state\n";
