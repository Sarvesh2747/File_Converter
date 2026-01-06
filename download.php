<?php
// download.php
require_once 'includes/db.php';

session_start();
if (!isset($_SESSION['session_id'])) {
    die('Unauthorized access.');
}

if (!isset($_GET['id'])) {
    die('Invalid request');
}

$id = intval($_GET['id']);
$sessionId = $_SESSION['session_id'];

// Check ownership (Session ID match)
$stmt = $conn->prepare("SELECT converted_path, converted_filename FROM conversions WHERE id = ? AND session_id = ? AND status = 'completed'");
$stmt->bind_param("is", $id, $sessionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    die('File not found or access denied.');
}

$row = $result->fetch_assoc();
$filepath = $row['converted_path'];
$filename = $row['converted_filename'];

// Path Traversal Protection
$baseDir = realpath(__DIR__ . '/converted');
// Note: $filepath in DB is relative e.g., 'converted/file.pdf'. 
// Dependent on how it was saved. In api/convert.php: 'converted/' . basename($convertedPath)
// So it should be safe, but let's verify.
$realPath = realpath($filepath);

if ($realPath === false || strpos($realPath, $baseDir) !== 0) {
    // If running in root, 'converted' is in root.
    // If logic changes, this keeps it safe.
    // Also handle if file is missing (realpath returns false)
    if (!file_exists($filepath)) {
         http_response_code(404);
         die('File missing on server.');
    }
    // Deep check
    if (strpos(realpath($filepath), realpath('converted')) !== 0) {
        die('Security violation: Invalid file path.');
    }
}

if (file_exists($filepath)) {
    // Determine MIME type
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $ctype = 'application/octet-stream';
    switch ($ext) {
        case 'pdf': $ctype = 'application/pdf'; break;
        case 'jpg': case 'jpeg': $ctype = 'image/jpeg'; break;
        case 'png': $ctype = 'image/png'; break;
        case 'gif': $ctype = 'image/gif'; break;
        case 'webp': $ctype = 'image/webp'; break;
        case 'doc': case 'docx': $ctype = 'application/msword'; break;
        case 'ppt': case 'pptx': $ctype = 'application/vnd.ms-powerpoint'; break;
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $ctype);
    // Use quoted filename to handle spaces
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    
    // Clear output buffer to ensure no whitespace is sent
    ob_clean();
    flush();
    
    readfile($filepath);
    exit;
} else {
    http_response_code(404);
    die('File source missing on server.');
}
?>
