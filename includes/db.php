<?php
// includes/db.php

$host = 'localhost';
$db_name = 'file_converter';
$username = 'root'; // Default XAMPP/WAMP username, change if needed
$password = ''; // Default XAMPP/WAMP password, change if needed

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $db_name);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // If database doesn't exist, try to connect without db name and create it? 
    // For now, let's assume the user runs the schema.sql or we might handle it gracefully.
    // However, best practice is to fail if DB setup is wrong.
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
?>
