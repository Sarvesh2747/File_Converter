<?php
// api/upload.php
require_once '../includes/db.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['session_id'])) {
    $_SESSION['session_id'] = session_id();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];
$fileName = $file['name'];
$fileSize = $file['size'];
$fileTmpPath = $file['tmp_name'];

// Validate File Size (Max 5MB)
$maxSize = 5 * 1024 * 1024;
if ($fileSize > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File size exceeds 5MB limit']);
    exit;
}

// Validate File Type
$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf',
    // Office Formats
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    'application/vnd.ms-powerpoint', // .ppt
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' // .pptx
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($fileTmpPath);

// Loose check for some systems where mimetype might be generic 'application/zip' for docx/pptx
// We can double check extension if generic zip is detected, but standard mime is safer.
// Let's rely on these standard mimes first.

if (!in_array($mimeType, $allowedMimeTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Allowed: Images, PDF, Word, PowerPoint']);
    exit;
}

// Format Detection
$extension = pathinfo($fileName, PATHINFO_EXTENSION);
$formatFrom = strtolower($extension);
if ($formatFrom == 'jpeg') $formatFrom = 'jpg';


// Generate Unique Filename
$uniqueName = uniqid() . '_' . time() . '.' . $extension;
$uploadDir = '../uploads/';
$destination = $uploadDir . $uniqueName;

if (move_uploaded_file($fileTmpPath, $destination)) {
    // Insert into Database
    $sessionId = $_SESSION['session_id'];
    $userIp = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO conversions (original_filename, original_path, format_from, original_size, status, user_ip, session_id) VALUES (?, ?, ?, ?, 'pending', ?, ?)");
    
    // Store relative path for DB to avoid full path exposure handy for internal logic
    $dbPath = 'uploads/' . $uniqueName; 
    
    $stmt->bind_param("sssiss", $fileName, $dbPath, $formatFrom, $fileSize, $userIp, $sessionId);
    
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        echo json_encode([
            'success' => true,
            'id' => $id,
            'filename' => $fileName,
            'format' => $formatFrom,
            'size' => $fileSize,
            'message' => 'File uploaded successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to move uploaded file']);
}

$conn->close();
?>
