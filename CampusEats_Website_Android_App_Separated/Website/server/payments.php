<?php
/** Payment configuration and PayFast helpers. Compatible with PHP 7.2+. */

declare(strict_types=1);

/**
 * A simulated payment is permitted exclusively on the local computer.
 * It MUST NOT be enabled on a public server because no money changes hands.
 * Even if misconfigured in production, remote network requests cannot use it.
 */
function localDemoPaymentsAllowed(array $config): bool
{
    $host = strtolower((string)parse_url((string)($config['base_url'] ?? ''), PHP_URL_HOST));
    $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $requestHost = explode(':', $requestHost)[0];
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $serverAddress = (string)($_SERVER['SERVER_ADDR'] ?? '');
    $loopback = ['127.0.0.1', '::1'];
    return !empty($config['payments']['demo']['enabled'])
        && in_array($host, ['localhost', '127.0.0.1'], true)
        && in_array($requestHost, ['localhost', '127.0.0.1', '10.0.2.2'], true)
        && in_array($remote, $loopback, true)
        && in_array($serverAddress, $loopback, true);
}

/**
 * Confirm the current CampusEats account's credentials before initiating a
 * payment. Never accept another account's email for the authenticated session.
 * This is NOT a banking login. The submitted password is not stored or logged.
 * Google-only users may create a password in My account before using this flow.
 */
function paymentLoginMatches(array $account, string $email, string $password): bool
{
    $savedEmail = strtolower((string)($account['email'] ?? ''));
    $providedEmail = strtolower(trim($email));
    $hash = (string)($account['password_hash'] ?? '');

    return $savedEmail !== '' && $hash !== ''
        && hash_equals($savedEmail, $providedEmail)
        && password_verify($password, $hash);
}

/** Reauthentication is checked on the server, not only in JavaScript. */
function requirePaymentLogin(array $currentUser, array $input): void
{
    // Throttle attempted passwords using the existing database-backed limiter.
    rateLimit('payment_login', 10);
    $account = query('SELECT email,password_hash FROM users WHERE id=?', [(int)$currentUser['id']])->fetch();
    if (!$account || empty($account['password_hash']))
    {
        fail('Set a CampusEats password in My account before continuing to payment.', 403);
    }

    if (!paymentLoginMatches($account, (string)($input['email'] ?? ''), (string)($input['password'] ?? '')))
    {
        fail('The CampusEats email or password is incorrect.', 401);
    }
}

/** Only advertise automatic payment methods that are genuinely available. */
function paymentOptions(array $config): array
{
    $settings = isset($config['payments']) && is_array($config['payments']) ? $config['payments'] : [];
    $gateway = isset($settings['payfast']) && is_array($settings['payfast']) ? $settings['payfast'] : [];
    // Real money: no EFT/manual approval. The provider confirms payment via signed ITN.
    $online = !empty($gateway['enabled']) && !empty($gateway['merchant_id']) && !empty($gateway['merchant_key'])
        && !empty($gateway['passphrase']) && extension_loaded('curl')
        && strpos((string)$config['base_url'], 'https://') === 0;
    return [
        'eft' => null, // For compatibility with the previous client response only.
        'payfast' => $online,
        'demo' => localDemoPaymentsAllowed($config)
    ];
}

/** Explain missing MySQL migration instead of returning an ambiguous 500 error. */
function requirePaymentSchema(PDO $pdo): void
{
    $statement = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_payments'");
    if ((int)$statement->fetchColumn() !== 1)
    {
        fail('Payment database setup is incomplete. Back up your campuseats database, then import database/payment_migration.sql in phpMyAdmin.', 503);
    }
}

/** PayFast custom integration signs the values in their insertion order. */
function payfastSignature(array $values, string $passphrase): string
{
    $parts = [];
    foreach ($values as $key => $value)
    {
        if ($key !== 'signature' && (string)$value !== '')
        {
            $parts[] = $key . '=' . urlencode(trim((string)$value));
        }
    }
    $parts[] = 'passphrase=' . urlencode(trim($passphrase));
    return md5(implode('&', $parts));
}

/** Convert a provider money string to cents, rejecting floats and malformed values. */
function paymentAmountCents($value): ?int
{
    $value = (string)$value;
    if (!preg_match('/^([0-9]{1,8})(?:\.([0-9]{1,2}))?$/', $value, $parts))
    {
        return null;
    }
    return ((int)$parts[1] * 100) + (int)str_pad(isset($parts[2]) ? $parts[2] : '', 2, '0');
}

function payfastHost(array $config): string
{
    return (($config['payments']['payfast']['mode'] ?? 'sandbox') === 'live')
        ? 'www.payfast.co.za' : 'sandbox.payfast.co.za';
}

/** Fail closed when the notification is not from a recognised PayFast server. */
function payfastSourceVerified(string $remoteIp): bool
{
    if (!filter_var($remoteIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
    {
        return false;
    }
    foreach (['www.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za', 'sandbox.payfast.co.za'] as $hostname)
    {
        $addresses = gethostbynamel($hostname);
        if ($addresses !== false && in_array($remoteIp, $addresses, true))
        {
            return true;
        }
    }
    return false;
}

/** Independently validate the exact callback fields with PayFast over TLS. */
function payfastServerVerified(array $data, array $config): bool
{
    $parts = [];
    foreach ($data as $key => $value)
    {
        if ($key !== 'signature' && (string)$value !== '')
        {
            $parts[] = $key . '=' . urlencode(trim((string)$value));
        }
    }
    $client = curl_init('https://' . payfastHost($config) . '/eng/query/validate');
    curl_setopt_array($client, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => implode('&', $parts),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15
    ]);
    $answer = curl_exec($client);
    $code = (int)curl_getinfo($client, CURLINFO_HTTP_CODE);
    curl_close($client);
    return $answer === 'VALID' && $code === 200;
}
