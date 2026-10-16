<?php
/** Copy to config.php. Keep credentials outside the public web directory. */
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'campuseats',
    'db_user' => 'root',
    'db_password' => '',
    'base_url' => 'http://localhost/campuseats/web',
    'secure_cookies' => false, // Set true when serving the production site over HTTPS.
    'google_client_id' => '',
    'google_client_secret' => '',
    // Automatic payment uses PayFast with a public HTTPS callback. Manual EFT is disabled.
    // Demo mode simulates a payment and can only run over localhost (never on public hosts).
    'payments' => [
        'demo' => [
            'enabled' => false // Change to true ONLY to test locally, NO REAL PAYMENT.
        ],
        // Coursework order flow on a hosted HTTPS site. ONLY listed accounts
        // can confirm no-charge orders. Disabled unless explicitly configured.
        // This does not process cards, EFTs or funds.
        'school_checkout' => [
            'enabled' => false,
            'allowed_user_ids' => [] // e.g. [1, 2] for approved accounts only.
        ],
        'eft' => [
            'enabled' => false,
            'bank_name' => '',
            'account_name' => '',
            'account_number' => '',
            'branch_code' => ''
        ],
        'payfast' => [
            'enabled' => false,
            'mode' => 'sandbox', // Change to live only after successful sandbox tests.
            'merchant_id' => '',
            'merchant_key' => '',
            'passphrase' => ''
        ]
    ],
];
