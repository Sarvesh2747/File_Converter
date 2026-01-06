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

if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || !isset($_SESSION['csrf_token']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF Token']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];
$fileName = basename($file['name']); // Sanitize input name slightly
$fileSize = $file['size'];
$fileTmpPath = $file['tmp_name'];

// Rate Limiting (Max 100 uploads per hour per IP)
$userIp = $_SERVER['REMOTE_ADDR'];
$limitTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
$stmtLimit = $conn->prepare("SELECT COUNT(*) FROM conversions WHERE user_ip = ? AND upload_time > ?");
$stmtLimit->bind_param("ss", $userIp, $limitTime);
$stmtLimit->execute();
$stmtLimit->bind_result($reqCount);
$stmtLimit->fetch();
$stmtLimit->close();

if ($reqCount >= 100) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded (100 uploads/hour).']);
    exit;
}

// Validate File Size (Max 5MB)
$maxSize = 5 * 1024 * 1024;
if ($fileSize > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File size exceeds 5MB limit']);
    exit;
}

// Strict MIME Type Validation
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($fileTmpPath);

// Map MIME types to known safe extensions
$mimeMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
    // Office - Standard
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-powerpoint' => 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    // Office - Common Variations/Fallbacks
    'application/zip' => 'zip', 
    'application/x-zip-compressed' => 'zip',
    'application/octet-stream' => 'bin' 
];

// Special handling for Office files that appear as ZIP/Octet
$isOffice = false;
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (in_array($mimeType, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'])) {
    if (in_array($ext, ['docx', 'pptx', 'xlsx', 'doc', 'ppt'])) {
        $isOffice = true;
        $extension = $ext;
    }
}

if (!array_key_exists($mimeType, $mimeMap) && !$isOffice) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Detected: ' . $mimeType . ' (' . $ext . ')']);
    exit;
}

if (!$isOffice) {
    // Force extension based on MIME type for standard files
    $extension = $mimeMap[$mimeType];
    // Map zip/bin back to original extension if it was legitimately one of those (but we only really allow office zips)
    // The previous block handles office zips. 
    // If we are here, it's an image or PDF.
}

// Double check: if it mapped to 'zip' or 'bin' but wasn't flagged as office, we should probably reject it 
// unless we want to allow generic zips (which we don't).
if (($extension === 'zip' || $extension === 'bin') && !$isOffice) {
     http_response_code(400);
     echo json_encode(['error' => 'Generic ZIP/Binary files not allowed. Upload Office files or Images.']);
     exit; 
}

// Determine "format_from"
$formatFrom = ($ext === 'jpeg') ? 'jpg' : $ext; // Use original extension logic for format_from to be consistent e.g. docx

// Generate Unique Filename (Randomized, not based on input)
$uniqueName = uniqid('up_', true) . '_' . time() . '.' . $extension;
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
