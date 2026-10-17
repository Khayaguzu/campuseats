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
        // Coursework order flow on the configured HTTPS site. No real funds.
        // Off by default. Enable allow_registered_users only for a no-charge
        // school project where every signed-in customer may place an order.
        // Keep it disabled for a real-money shop.
        'school_checkout' => [
            'enabled' => false,
            'allow_registered_users' => false,
            'allowed_user_ids' => [] // Used when allow_registered_users is false.
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
