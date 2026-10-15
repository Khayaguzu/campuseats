<?php
/** Google authorization-code sign-in. Android uses the system browser and a
 * single-use, short-lived ticket bound to a verifier held by the application. */
require __DIR__ . '/../server/bootstrap.php';
$mode = $_GET['mode'] ?? 'start';

function oauthRequest(string $url, ?array $body = null, ?string $token = null): array
{
    $curl = curl_init($url);
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false];
    if ($body !== null)
    {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($body);
    }
    if ($token !== null)
    {
        $options[CURLOPT_HTTPHEADER] = ['Authorization: Bearer ' . $token];
    }
    curl_setopt_array($curl, $options);
    $raw = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $data = json_decode($raw ?: '', true);
    if ($status !== 200 || !is_array($data))
    {
        throw new RuntimeException('Identity provider did not complete the request.');
    }
    return $data;
}

try
{
    if ($mode === 'redeem')
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            throw new RuntimeException('POST required.');
        }
        $ticket = (string)($_POST['ticket'] ?? '');
        $verifier = (string)($_POST['verifier'] ?? '');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $pdo->beginTransaction();
        $row = query('SELECT * FROM mobile_tickets WHERE ticket_hash=? AND expires_at>UTC_TIMESTAMP() FOR UPDATE', [hash('sha256', $ticket)])->fetch();
        if (!$row || !hash_equals($row['challenge'], $challenge))
        {
            $pdo->rollBack();
            throw new RuntimeException('Sign-in link expired. Please try again.');
        }
        query('DELETE FROM mobile_tickets WHERE ticket_hash=?', [hash('sha256', $ticket)]);
        $pdo->commit();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $row['user_id'];
        header('Location: index.php', true, 303);
        exit;
    }
    if (!$config['google_client_id'] || !$config['google_client_secret'])
    {
        throw new RuntimeException('Google sign-in has not been configured. Use your email and password.');
    }
    $redirect = rtrim($config['base_url'], '/') . '/sso.php?mode=callback';
    if ($mode === 'start')
    {
        $_SESSION['oauth_state'] = bin2hex(random_bytes(32));
        $_SESSION['oauth_started'] = time();
        $_SESSION['oauth_challenge'] = null;
        if (isset($_GET['challenge']))
        {
            if (!preg_match('/^[A-Za-z0-9_-]{43}$/', (string)$_GET['challenge']))
            {
                throw new RuntimeException('Invalid application challenge.');
            }
            $_SESSION['oauth_challenge'] = $_GET['challenge'];
        }
        $parameters = ['client_id' => $config['google_client_id'], 'redirect_uri' => $redirect,
            'response_type' => 'code', 'scope' => 'openid email profile',
            'state' => $_SESSION['oauth_state'], 'prompt' => 'select_account'];
        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($parameters));
        exit;
    }
    if ($mode !== 'callback' || !isset($_SESSION['oauth_state']) ||
        !hash_equals($_SESSION['oauth_state'], (string)($_GET['state'] ?? '')) ||
        time() - ($_SESSION['oauth_started'] ?? 0) > 600)
    {
        throw new RuntimeException('Sign-in request expired or could not be verified.');
    }
    $challenge = $_SESSION['oauth_challenge'] ?? null;
    unset($_SESSION['oauth_state'], $_SESSION['oauth_started'], $_SESSION['oauth_challenge']);
    $tokens = oauthRequest('https://oauth2.googleapis.com/token', [
        'client_id' => $config['google_client_id'], 'client_secret' => $config['google_client_secret'],
        'redirect_uri' => $redirect, 'grant_type' => 'authorization_code', 'code' => $_GET['code'] ?? ''
    ]);
    // Obtain identity directly from Google's TLS-protected userinfo endpoint;
    // do not decode and trust an unverified ID token from the browser.
    $profile = oauthRequest('https://openidconnect.googleapis.com/v1/userinfo', null, $tokens['access_token']);
    if (empty($profile['email_verified']) || empty($profile['sub']) || empty($profile['email']))
    {
        throw new RuntimeException('A verified Google email address is required.');
    }
    $user = query('SELECT * FROM users WHERE google_sub=?', [$profile['sub']])->fetch();
    if (!$user)
    {
        $existing = query('SELECT id FROM users WHERE email=?', [strtolower($profile['email'])])->fetch();
        if ($existing)
        {
            // Linking requires an authenticated session for this exact local account.
            if ((int)($_SESSION['user_id'] ?? 0) !== (int)$existing['id'])
            {
                throw new RuntimeException('This email already has an account. Sign in with its password in this browser, then select Link Google in Settings.');
            }
            query('UPDATE users SET google_sub=? WHERE id=?', [$profile['sub'], $existing['id']]);
            $user = $existing;
        }
        else
        {
            query('INSERT INTO users(name,email,google_sub) VALUES (?,?,?)',
                [substr($profile['name'] ?? 'Campus user', 0, 100), strtolower($profile['email']), $profile['sub']]);
            $user = ['id' => $pdo->lastInsertId()];
        }
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    if ($challenge)
    {
        $ticket = bin2hex(random_bytes(32));
        query('DELETE FROM mobile_tickets WHERE expires_at<UTC_TIMESTAMP()');
        query('INSERT INTO mobile_tickets(ticket_hash,user_id,challenge,expires_at) VALUES (?,?,?,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 MINUTE))',
            [hash('sha256', $ticket), $user['id'], $challenge]);
        header('Location: campuseats://auth?ticket=' . $ticket);
        exit;
    }
    header('Location: index.php#settings');
}
catch (Throwable $error)
{
    if ($pdo->inTransaction())
    {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign-in</title><link rel="stylesheet" href="assets/style.css"><main class="shell"><section class="panel"><h1>Sign-in could not finish</h1><p>' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8') . '</p><a class="button" href="index.php">Back to CampusEats</a></section></main></html>';
}
