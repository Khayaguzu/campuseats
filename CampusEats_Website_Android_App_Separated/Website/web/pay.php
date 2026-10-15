<?php
/** Short-lived payment hand-off; usable from Android's system browser. */
require __DIR__ . '/../server/bootstrap.php';
require __DIR__ . '/../server/payments.php';
$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
if (!preg_match('/^[a-f0-9]{64}$/D', $token))
{
    http_response_code(404);
    exit('Payment link not found. Open My orders and select Pay online again.');
}
$options = paymentOptions($config);
if (!$options['payfast'])
{
    http_response_code(503);
    exit('Online payments are not configured. Please contact the administrator.');
}
$payment = query('SELECT p.*,o.total_cents,o.status AS order_status,u.name,u.email FROM order_payments p
    JOIN orders o ON o.id=p.order_id JOIN users u ON u.id=o.user_id
    WHERE p.payment_token_hash=? AND p.payment_token_expires_at > UTC_TIMESTAMP()', [hash('sha256', $token)])->fetch();
if (!$payment || $payment['state'] !== 'pending' || $payment['order_status'] === 'cancelled' || $payment['method'] !== 'payfast')
{
    http_response_code(404);
    exit('This payment link has expired or the order is no longer payable.');
}
$settings = $config['payments']['payfast'];
$prefix = rtrim((string)$config['base_url'], '/');
$firstName = explode(' ', trim($payment['name']))[0];
$fields = [
    'merchant_id' => (string)$settings['merchant_id'],
    'merchant_key' => (string)$settings['merchant_key'],
    'return_url' => $prefix . '/index.php?payment=returned#orders',
    'cancel_url' => $prefix . '/index.php?payment=cancelled#orders',
    'notify_url' => $prefix . '/payfast-itn.php',
    'name_first' => $firstName,
    'email_address' => (string)$payment['email'],
    'm_payment_id' => (string)$payment['order_id'],
    'amount' => number_format((int)$payment['total_cents'] / 100, 2, '.', ''),
    'item_name' => 'CampusEats order ' . $payment['order_id']
];
$fields['signature'] = payfastSignature($fields, (string)$settings['passphrase']);
$endpoint = 'https://' . payfastHost($config) . '/eng/process';
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action https://www.payfast.co.za https://sandbox.payfast.co.za; base-uri 'none'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
function fieldHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Secure CampusEats payment</title></head>
<body style="font-family:system-ui,sans-serif;max-width:440px;margin:8vh auto;padding:24px;line-height:1.5">
<h1>Complete your payment</h1>
<p>Pay <strong>R<?= fieldHtml($fields['amount']) ?></strong> securely through PayFast.</p>
<p>You will only receive a valid collection number after the payment has been verified by CampusEats.</p>
<form method="post" action="<?= fieldHtml($endpoint) ?>">
<?php foreach ($fields as $name => $value): ?>
<input type="hidden" name="<?= fieldHtml($name) ?>" value="<?= fieldHtml((string)$value) ?>">
<?php endforeach; ?>
<button type="submit" style="padding:14px 22px;background:#173d32;color:white;border:0;border-radius:8px;font-size:16px">Continue to PayFast</button>
</form>
</body></html>
