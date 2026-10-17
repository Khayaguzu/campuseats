<?php
/** Lightweight standalone unit checks. Run: php tests/payment_helpers.php */
require __DIR__ . '/../server/payments.php';
function checkPayment(bool $ok, string $message): void
{
    if (!$ok)
    {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}
checkPayment(paymentAmountCents('81.00') === 8100, 'Rand amounts parse exactly to integer cents');
checkPayment(paymentAmountCents('0.10') === 10, 'Ten cents does not round to a float');
checkPayment(paymentAmountCents('1.234') === null, 'Reject malformed precision');
checkPayment(paymentAmountCents('-1') === null, 'Reject negative amounts');
$values = ['merchant_id' => '10000100', 'amount' => '81.00', 'item_name' => 'Lunch'];
$expected = md5('merchant_id=10000100&amount=81.00&item_name=Lunch&passphrase=testsecret');
checkPayment(payfastSignature($values, 'testsecret') === $expected, 'Payment signature uses documented ordered fields');
// Explicitly simulate requests from local Apache for a safe test of the demo gate.
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$configuration = [
    'base_url' => 'http://localhost/campuseats/web',
    'payments' => [
        'demo' => ['enabled' => true],
        'eft' => ['enabled' => true, 'bank_name' => 'Test Bank', 'account_name' => 'Test Campus', 'account_number' => '0000000', 'branch_code' => '000000'],
        'payfast' => ['enabled' => true, 'merchant_id' => 'test', 'merchant_key' => 'test', 'passphrase' => 'secret']
    ]
];
$methods = paymentOptions($configuration);
checkPayment($methods['eft'] === null, 'Manual EFT approval cannot be selected by customers');
checkPayment($methods['demo'] === true && $methods['payfast'] === false, 'Only a clearly marked localhost simulation is enabled');
$_SERVER['REMOTE_ADDR'] = '192.168.1.99';
checkPayment(paymentOptions($configuration)['demo'] === false, 'Remote devices cannot simulate confirmed payments');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$configuration['base_url'] = 'https://campuseats.example.org/web';
checkPayment(paymentOptions($configuration)['demo'] === false, 'Public HTTPS hosts cannot use local simulations');
$configuration['base_url'] = 'http://localhost/campuseats/web';
$configuration['payments']['demo']['enabled'] = false;
checkPayment(paymentOptions($configuration)['demo'] === false, 'Simulation is disabled unless explicitly configured');

 // Hosted project checkout is opt-in and restricted to approved user IDs.
$configuration['base_url'] = 'https://khayaguzu.helioho.st/';
$_SERVER['HTTP_HOST'] = 'khayaguzu.helioho.st';
$_SERVER['REMOTE_ADDR'] = '203.0.113.15';
$configuration['payments']['school_checkout'] = [
    'enabled' => true,
    'allowed_user_ids' => [1, 2]
];
checkPayment(paymentOptions($configuration)['demo'] === false, 'Anonymous hosted visitors never get checkout access');
checkPayment(paymentOptions($configuration, ['id' => 1])['demo'] === true, 'Approved student may use hosted no-charge checkout');
checkPayment(paymentOptions($configuration, ['id' => 2])['demo'] === true, 'Approved administrator may use hosted no-charge checkout');
checkPayment(paymentOptions($configuration, ['id' => 3])['demo'] === false, 'Unlisted users cannot complete a hosted no-charge order when open registration is disabled');
$configuration['payments']['school_checkout']['allow_registered_users'] = true;
checkPayment(paymentOptions($configuration)['demo'] === false, 'Anonymous visitors cannot complete hosted checkout with open registration');
checkPayment(paymentOptions($configuration, ['id' => 3, 'role' => 'student', 'verified' => 0])['demo'] === true, 'New unverified students can use no-charge checkout without the student discount');
checkPayment(paymentOptions($configuration, ['id' => 4, 'role' => 'lecturer', 'verified' => 0])['demo'] === true, 'New lecturers can use no-charge checkout');
checkPayment(paymentOptions($configuration, ['id' => 555, 'role' => 'student'])['demo'] === true, 'Newly created user IDs need no further allowlist updates');
checkPayment(paymentOptions($configuration, ['id' => 0])['demo'] === false, 'Invalid user IDs cannot use no-charge checkout');
$configuration['payments']['school_checkout']['allow_registered_users'] = false;
checkPayment(paymentOptions($configuration, ['id' => 3])['demo'] === false, 'Turning off open registration restores the account allowlist');
$configuration['payments']['school_checkout']['allow_registered_users'] = true;
$configuration['payments']['school_checkout']['enabled'] = false;
checkPayment(paymentOptions($configuration, ['id' => 1])['demo'] === false, 'Hosted checkout can be disabled centrally');
$configuration['payments']['school_checkout']['enabled'] = true;
$_SERVER['HTTP_HOST'] = 'different.example.org';
checkPayment(paymentOptions($configuration, ['id' => 1])['demo'] === false, 'Unexpected hosts cannot enable hosted checkout');
$configuration['base_url'] = 'http://khayaguzu.helioho.st/';
$_SERVER['HTTP_HOST'] = 'khayaguzu.helioho.st';
checkPayment(paymentOptions($configuration, ['id' => 1])['demo'] === false, 'Hosted school checkout requires an HTTPS base URL');

$account = [
    'email' => 'student@campus.example',
    'password_hash' => password_hash('CorrectPassphrase123!', PASSWORD_DEFAULT)
];
checkPayment(paymentLoginMatches($account, 'STUDENT@campus.example', 'CorrectPassphrase123!'), 'Reauthentication accepts current account credentials');
checkPayment(!paymentLoginMatches($account, 'someone@campus.example', 'CorrectPassphrase123!'), 'Reauthentication rejects another account');
checkPayment(!paymentLoginMatches($account, 'student@campus.example', 'WrongPassphrase123!'), 'Reauthentication rejects invalid password');
checkPayment(!paymentLoginMatches(['email' => 'sso@campus.example', 'password_hash' => null], 'sso@campus.example', 'Anything123!'), 'Account without password cannot bypass checkout verification');
echo "Payment helper tests passed.\n";
