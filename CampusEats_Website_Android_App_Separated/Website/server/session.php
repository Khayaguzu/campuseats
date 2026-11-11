<?php
/** Apply identical authentication lifetimes to browser and Android sessions. */
function sessionExpired(array $session, int $now, int $idle, int $absolute): bool
{
    if (!isset($session['user_id']))
    {
        return false;
    }
    // Pre-upgrade sessions must authenticate again to establish trusted timestamps.
    return !isset($session['authenticated_at'], $session['last_activity']) ||
        $now - $session['last_activity'] >= $idle ||
        $now - $session['authenticated_at'] >= $absolute;
}

/** Rotate the session identifier after authentication, retaining the API CSRF token. */
function establishSession(int $userId, string $method): void
{
    session_regenerate_id(true);
    $csrf = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));
    $_SESSION = ['user_id' => $userId, 'csrf' => $csrf,
        'authenticated_at' => time(), 'last_activity' => time(), 'auth_method' => $method];
}
