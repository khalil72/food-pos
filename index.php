<?php
require __DIR__ . '/app/bootstrap.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

App::run();
