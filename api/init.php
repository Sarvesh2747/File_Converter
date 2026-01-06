<?php
// api/init.php
require_once '../includes/db.php';
header('Content-Type: application/json');

session_start();

// Session Fixation Protection: Regenerate on first init
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

echo json_encode([
    'csrf_token' => $_SESSION['csrf_token']
]);
