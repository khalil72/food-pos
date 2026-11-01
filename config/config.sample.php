<?php
// Copy to config.php (the web installer does this for you) and adjust.
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'food_pos',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'           => 'Saffron POS',
        'debug'          => false,
        'session_name'   => 'SAFFRONPOS',
        'session_idle'   => 7200, // seconds of inactivity before auto logout
    ],
];
