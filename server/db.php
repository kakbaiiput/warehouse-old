<?php
// Database connection + shared secret for the Apps Script sync push.
// IMPORTANT: change SYNC_SECRET to a long random value and put the same
// value in the appscript CONFIG.DB_SYNC_SECRET constant.

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'rt_warehouse');
define('DB_USER', 'rt_warehouse');
define('DB_PASS', 'rt_warehouse');
define('SYNC_SECRET', 'CHANGE_ME_TO_A_LONG_RANDOM_STRING');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function json_response($data) {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
