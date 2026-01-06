<?php
// includes/db.php

// Load .env manually to avoid dependencies
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? 'file_converter';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? ''; // Default empty

// Security: Disable error display in production
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

try {
    $conn = new mysqli($host, $username, $password, $db_name);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // Log error internally but do not show to user
    error_log("Database Connection Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Service temporarily unavailable due to a database error.']);
    exit;
}
