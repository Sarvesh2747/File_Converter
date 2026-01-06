<?php
// download.php
require_once 'includes/db.php';

if (!isset($_GET['id'])) {
    die('Invalid request');
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT converted_path, converted_filename FROM conversions WHERE id = ? AND status = 'completed'");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('File not found or expired.');
}

$row = $result->fetch_assoc();
$filepath = $row['converted_path'];
$filename = $row['converted_filename'];

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
