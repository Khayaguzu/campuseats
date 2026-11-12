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
        if (!preg_match('/^[a-f0-9]{64}$/D', $ticket) ||
            !preg_match('/^[A-Za-z0-9_-]{43}$/D', $verifier))
        {
            throw new RuntimeException('Invalid sign-in handoff. Start Google sign-in again.');
        }
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
        establishSession((int)$row['user_id'], 'google');
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
    if (isset($_GET['error']))
    {
        throw new RuntimeException('Google sign-in was cancelled. You can return and try again.');
    }
    if (empty($_GET['code']) || !is_string($_GET['code']))
    {
        throw new RuntimeException('Google did not return an authorization code. Please try again.');
    }
    $tokens = oauthRequest('https://oauth2.googleapis.com/token', [
        'client_id' => $config['google_client_id'], 'client_secret' => $config['google_client_secret'],
        'redirect_uri' => $redirect, 'grant_type' => 'authorization_code', 'code' => $_GET['code'] ?? ''
    ]);
    // Obtain identity directly from Google's TLS-protected userinfo endpoint;
    // do not decode and trust an unverified ID token from the browser.
    if (empty($tokens['access_token']) || !is_string($tokens['access_token']))
    {
        throw new RuntimeException('Google did not return an access token. Please try again.');
    }
    $profile = oauthRequest('https://openidconnect.googleapis.com/v1/userinfo', null, $tokens['access_token']);
    if (($profile['email_verified'] ?? false) !== true || empty($profile['sub']) ||
        !is_string($profile['sub']) || !filter_var($profile['email'] ?? '', FILTER_VALIDATE_EMAIL))
    {
        throw new RuntimeException('A verified Google email address is required.');
    }
    $user = query('SELECT * FROM users WHERE google_sub=?', [$profile['sub']])->fetch();
    if (!$user)
    {
        $existing = query('SELECT id,google_sub FROM users WHERE email=?', [strtolower($profile['email'])])->fetch();
        if ($existing)
        {
            // Linking requires an authenticated session for this exact local account.
            if ((int)($_SESSION['user_id'] ?? 0) !== (int)$existing['id'])
            {
                throw new RuntimeException('This email already has an account. Sign in with its password in this browser, then select Link Google in Settings.');
            }
            if ($existing['google_sub'] !== null && $existing['google_sub'] !== $profile['sub'])
            {
                throw new RuntimeException('This account is already linked to another Google identity.');
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
    establishSession((int)$user['id'], 'google');
    if ($challenge)
    {
        $ticket = bin2hex(random_bytes(32));
        query('DELETE FROM mobile_tickets WHERE expires_at<UTC_TIMESTAMP()');
        query('INSERT INTO mobile_tickets(ticket_hash,user_id,challenge,expires_at) VALUES (?,?,?,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 MINUTE))',
            [hash('sha256', $ticket), $user['id'], $challenge]);
        // An explicit user action reliably opens the app from browsers that block
        // automatic custom-scheme redirects. No provider token leaves this server.
        $appLink = 'campuseats://auth?ticket=' . $ticket;
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Return to CampusEats</title><link rel="stylesheet" href="assets/style.css"><main class="shell"><section class="panel"><h1>Google sign-in successful</h1><p>Return to the Android app within two minutes to finish signing in.</p><a class="button" href="' . htmlspecialchars($appLink, ENT_QUOTES, 'UTF-8') . '">Return to CampusEats app</a><p>Your website session is also signed in.</p></section></main></html>';
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
