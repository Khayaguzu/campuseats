<?php
/** PayFast payment notification. Never accept a browser redirect as proof of payment. */
require __DIR__ . '/../server/bootstrap.php';
require __DIR__ . '/../server/payments.php';
header('Content-Type: text/plain; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !paymentOptions($config)['payfast'])
{
    http_response_code(405);
    exit('Not available');
}
$data = $_POST;
$settings = $config['payments']['payfast'];
$signature = isset($data['signature']) ? (string)$data['signature'] : '';
$remoteIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
// The signature, sender IP and PayFast's independent validation must ALL pass.
if (!preg_match('/^[a-f0-9]{32}$/D', $signature)
    || !hash_equals(payfastSignature($data, (string)$settings['passphrase']), $signature)
    || !payfastSourceVerified($remoteIp)
    || !payfastServerVerified($data, $config))
{
    error_log('Rejected PayFast notification: source or signature not verified.');
    http_response_code(403);
    exit('Invalid notification');
}
$id = filter_var($data['m_payment_id'] ?? null, FILTER_VALIDATE_INT);
$reference = (string)($data['pf_payment_id'] ?? '');
$amount = paymentAmountCents($data['amount_gross'] ?? null);
if (!$id || $id < 1 || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $reference)
    || !hash_equals((string)$settings['merchant_id'], (string)($data['merchant_id'] ?? ''))
    || $amount === null)
{
    http_response_code(422);
    exit('Invalid payment details');
}
if (($data['payment_status'] ?? '') !== 'COMPLETE')
{
    // A cancelled or failed gateway attempt does not grant collection privileges.
    http_response_code(200);
    exit('No completed payment');
}
try
{
    $pdo->beginTransaction();
    $order = query('SELECT * FROM orders WHERE id=? FOR UPDATE', [$id])->fetch();
    $payment = query('SELECT * FROM order_payments WHERE order_id=? FOR UPDATE', [$id])->fetch();
    if (!$order || !$payment || $payment['method'] !== 'payfast' || $amount !== (int)$order['total_cents'])
    {
        $pdo->rollBack();
        error_log('PayFast payment review required: unknown order or amount mismatch, ID ' . $id);
        http_response_code(422);
        exit('Order or amount mismatch');
    }
    if ($payment['state'] === 'paid' && hash_equals((string)$payment['gateway_payment_id'], $reference))
    {
        $pdo->commit();
        exit('OK');
    }
    if ($payment['state'] !== 'pending' || $order['status'] === 'cancelled')
    {
        $pdo->rollBack();
        error_log('PayFast refund review: payment arrived after order closed, ID ' . $id);
        http_response_code(409);
        exit('Order requires manual review');
    }
    // Unique gateway_payment_id prevents a provider transaction crediting two orders.
    query('UPDATE order_payments SET state=?,gateway_payment_id=?,confirmed_at=UTC_TIMESTAMP(),payment_token_hash=NULL,payment_token_expires_at=NULL WHERE order_id=?', ['paid', $reference, $id]);
    query('UPDATE orders SET payment_status=? WHERE id=?', ['paid', $id]);
    $pdo->commit();
    exit('OK');
}
catch (Throwable $error)
{
    if ($pdo->inTransaction())
    {
        $pdo->rollBack();
    }
    error_log((string)$error);
    http_response_code(503);
    exit('Retry notification');
}
