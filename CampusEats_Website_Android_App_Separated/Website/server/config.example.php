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
];
