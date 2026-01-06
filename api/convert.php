<?php
// api/convert.php
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !isset($input['format_to'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing conversion parameters']);
    exit;
}

$conversionId = intval($input['id']);
$formatTo = strtolower($input['format_to']);

// Fetch conversion record
$stmt = $conn->prepare("SELECT * FROM conversions WHERE id = ?");
$stmt->bind_param("i", $conversionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

$row = $result->fetch_assoc();
$originalPath = '../' . $row['original_path']; // Prepend ../ because path in DB is relative to root, script is in api/
$formatFrom = $row['format_from'];

if (!file_exists($originalPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Source file not found on server']);
    exit;
}

// Update status to processing
$updateStmt = $conn->prepare("UPDATE conversions SET status = 'processing', format_to = ? WHERE id = ?");
$updateStmt->bind_param("si", $formatTo, $conversionId);
$updateStmt->execute();

// Supported Formats Configuration
$supportedImages = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$supportedOffice = ['doc', 'docx', 'ppt', 'pptx'];

function convertImage($source, $destination, $formatFrom, $formatTo) {
    // ... (Keep existing image-to-image logic, verified working)
    // For brevity, using the previous implementation's core logic here
    $image = null;
    switch ($formatFrom) {
        case 'jpg': case 'jpeg': $image = imagecreatefromjpeg($source); break;
        case 'png': $image = imagecreatefrompng($source); break;
        case 'gif': $image = imagecreatefromgif($source); break;
        case 'webp': $image = imagecreatefromwebp($source); break;
    }
    if (!$image) return false;

    if ($formatTo == 'png' || $formatTo == 'webp') {
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);
    }

    $success = false;
    switch ($formatTo) {
        case 'jpg': case 'jpeg':
            $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
            imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            $success = imagejpeg($bg, $destination, 90);
            imagedestroy($bg);
            break;
        case 'png': $success = imagepng($image, $destination, 6); break;
        case 'gif': $success = imagegif($image, $destination); break;
        case 'webp': $success = imagewebp($image, $destination, 85); break;
    }
    imagedestroy($image);
    return $success;
}

function convertImageToPdf($source, $destination) {
    require_once '../includes/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();
    // Get original image size to fit page or center
    list($width, $height) = getimagesize($source);
    // Convert px to mm (approx 1px = 0.264583 mm at 96dpi)
    $mmW = $width * 0.264583;
    $mmH = $height * 0.264583;
    
    // Fit to A4 (210x297) with 10mm margin
    $maxWidth = 190;
    $maxHeight = 277;
    
    // Simple scaling logic
    if ($mmW > $maxWidth || $mmH > $maxHeight) {
        $ratio = min($maxWidth / $mmW, $maxHeight / $mmH);
        $mmW *= $ratio;
        $mmH *= $ratio;
    }

    $pdf->Image($source, 10, 10, $mmW, $mmH);
    $pdf->Output('F', $destination);
    return file_exists($destination);
}

function convertOfficeToPdf($source, $destinationDir, $outputFilename) {
    // Requires LibreOffice in PATH
    // Command: soffice --headless --convert-to pdf --outdir <dir> <source>
    // Note: LibreOffice output filename is same as input basename, so we need to handle renaming if needed
    
    $cmd = "soffice --headless --convert-to pdf --outdir " . escapeshellarg($destinationDir) . " " . escapeshellarg($source);
    exec($cmd, $output, $returnVar);
    
    // LibreOffice creates [filename].pdf in dest dir
    $expectedOutput = $destinationDir . pathinfo($source, PATHINFO_FILENAME) . '.pdf';
    
    if ($returnVar === 0 && file_exists($expectedOutput)) {
        return $expectedOutput; // Return the actual path
    }
    return false;
}

function convertPdfToImage($source, $destination, $formatTo) {
    // Requires Ghostscript or Imagick
    // Try Ghostscript first (common on servers)
    // gswin64c -dNOPAUSE -dBATCH -sDEVICE=jpeg -r150 -sOutputFile=output.jpg input.pdf
    
    $device = ($formatTo == 'png') ? 'png16m' : 'jpeg';
    $cmd = "gswin64c -dNOPAUSE -dBATCH -sDEVICE={$device} -r150 -sOutputFile=" . escapeshellarg($destination) . " " . escapeshellarg($source);
    exec($cmd, $output, $returnVar);

    return File_exists($destination);
}


// --- Main Execution ---

// 1. Image -> Image
if (in_array($formatFrom, $supportedImages) && in_array($formatTo, $supportedImages)) {
    // ... (Same setup as before)
    $newFilename = pathinfo($row['original_filename'], PATHINFO_FILENAME) . '.' . $formatTo;
    $uniqueNewName = uniqid() . '_' . time() . '.' . $formatTo;
    $convertedPathDir = '../converted/';
    if (!is_dir($convertedPathDir)) mkdir($convertedPathDir, 0755, true);
    $convertedPath = $convertedPathDir . $uniqueNewName;

    if (convertImage($originalPath, $convertedPath, $formatFrom, $formatTo)) {
        // DB Update Success
        // ... (Update DB code duplicated below for cleaner final block, or shared)
        $success = true;
    } else {
        $success = false;
        $errorMsg = 'Image conversion failed';
    }

} 
// 2. Image -> PDF
elseif (in_array($formatFrom, $supportedImages) && $formatTo == 'pdf') {
    $newFilename = pathinfo($row['original_filename'], PATHINFO_FILENAME) . '.pdf';
    $uniqueNewName = uniqid() . '_' . time() . '.pdf';
    $convertedPathDir = '../converted/';
    if (!is_dir($convertedPathDir)) mkdir($convertedPathDir, 0755, true);
    $convertedPath = $convertedPathDir . $uniqueNewName;

    if (convertImageToPdf($originalPath, $convertedPath)) {
        $success = true;
    } else {
        $success = false;
        $errorMsg = 'Failed to convert image to PDF';
    }
}
// 3. Office -> PDF
elseif (in_array($formatFrom, $supportedOffice) && $formatTo == 'pdf') {
     $convertedPathDir = '../converted/';
     $absSource = realpath($originalPath);
     $targetPath = $convertedPathDir . uniqid() . '_' . time() . '.pdf';
     $absDest = realpath($convertedPathDir) . DIRECTORY_SEPARATOR . basename($targetPath);
     
     // Call PowerShell script
     $psScript = realpath('../scripts/convert_office.ps1');
     $cmd = "powershell -ExecutionPolicy Bypass -File " . escapeshellarg($psScript) . " -SourceFile " . escapeshellarg($absSource) . " -DestFile " . escapeshellarg($absDest) . " -Format " . escapeshellarg($formatTo);
     
     $output = shell_exec($cmd);
     
     if (file_exists($absDest)) {
         $success = true;
         $convertedPath = $targetPath; // Relative for DB
         $newFilename = pathinfo($row['original_filename'], PATHINFO_FILENAME) . '.pdf';
     } else {
         $success = false;
         // Log the error output for debugging if needed
         $errorMsg = 'Office conversion failed. Ensure MS Office is installed.';
     }
}
// 4. PDF -> Image (Requires Ghostscript)
elseif ($formatFrom == 'pdf' && in_array($formatTo, $supportedImages)) {
    // Verified Path from user installation
    $gsPath = 'C:\Program Files\gs\gs10.04.0\bin\gswin64c.exe';
    
    if (!file_exists($gsPath)) {
         // Fallback or error
         // Ideally check PATH but we know it's here
         $success = false;
         $errorMsg = 'Ghostscript not found at expected path.';
    } else {
        $convertedPathDir = '../converted/';
        $newFilename = pathinfo($row['original_filename'], PATHINFO_FILENAME) . '.' . $formatTo;
        $uniqueNewName = uniqid() . '_' . time() . '.' . $formatTo;
        $convertedPath = $convertedPathDir . $uniqueNewName;
        $absDest = realpath($convertedPathDir) . DIRECTORY_SEPARATOR . $uniqueNewName;
        
        // Ghostscript Command
        // -dNOPAUSE -dBATCH -sDEVICE=jpeg/png16m -r150 -sOutputFile=...
        $device = ($formatTo == 'png') ? 'png16m' : 'jpeg';
        // -dFirstPage=1 -dLastPage=1 to only get the first page for now (simplification)
        $cmd = "\"$gsPath\" -dNOPAUSE -dBATCH -dFirstPage=1 -dLastPage=1 -sDEVICE={$device} -r150 -sOutputFile=" . escapeshellarg($absDest) . " " . escapeshellarg($originalPath);
        
        exec($cmd, $output, $returnVar);
        
        if (file_exists($absDest)) {
            $success = true;
        } else {
             $success = false;
             $errorMsg = 'PDF to Image conversion failed.';
        }
    }
}
// 5. PDF -> Word (PDF to Word)
elseif ($formatFrom == 'pdf' && ($formatTo == 'doc' || $formatTo == 'docx')) {
    $convertedPathDir = '../converted/';
    $absSource = realpath($originalPath);
    // Force docx for better results with automation, or respect request
    $targetExt = 'docx'; 
    $targetPath = $convertedPathDir . uniqid() . '_' . time() . '.' . $targetExt;
    $absDest = realpath($convertedPathDir) . DIRECTORY_SEPARATOR . basename($targetPath);
    
    $psScript = realpath('../scripts/convert_office.ps1');
    $cmd = "powershell -ExecutionPolicy Bypass -File " . escapeshellarg($psScript) . " -SourceFile " . escapeshellarg($absSource) . " -DestFile " . escapeshellarg($absDest) . " -Format " . escapeshellarg($targetExt);
    
    $output = shell_exec($cmd);

    if (file_exists($absDest)) {
         $success = true;
         $convertedPath = $targetPath;
         $newFilename = pathinfo($row['original_filename'], PATHINFO_FILENAME) . '.' . $targetExt;
    } else {
        $success = false;
        $errorMsg = 'PDF to Word conversion failed.';
    }
}
// 6. PDF -> PPT (Using Python)
elseif ($formatFrom == 'pdf' && ($formatTo == 'ppt' || $formatTo == 'pptx')) {
    $convertedPathDir = '../converted/';
    $absSource = realpath($originalPath);
    $targetExt = 'pptx';
    $targetPath = $convertedPathDir . uniqid() . '_' . time() . '.' . $targetExt;
    $absDest = realpath($convertedPathDir) . DIRECTORY_SEPARATOR . basename($targetPath);
    
    $pyScript = realpath('../scripts/pdf_to_ppt.py');
    // Assumes 'python' is in PATH. If issues, use full path to python.exe
    $cmd = "python " . escapeshellarg($pyScript) . " " . escapeshellarg($absSource) . " " . escapeshellarg($absDest);
    
    // Debug output capture if needed: 2>&1
    $output = shell_exec($cmd . " 2>&1");
    // file_put_contents('debug_py.txt', $output); // Uncomment to debug

    if (file_exists($absDest)) {
         $success = true;
         $convertedPath = $targetPath;
         $newFilename = pathinfo($row['original_filename'], PATHINFO_FILENAME) . '.' . $targetExt;
    } else {
        $success = false;
        $errorMsg = 'PDF to PPT conversion failed. Check server logs.';
    }
}
else {
    $success = false;
    $errorMsg = 'Unsupported conversion format pair';
}

// Final processing
if ($success) {
    if (!file_exists($convertedPath)) {
         // Double check
         $failStmt = $conn->prepare("UPDATE conversions SET status = 'failed' WHERE id = ?");
         $failStmt->bind_param("i", $conversionId);
         $failStmt->execute();
         echo json_encode(['error' => 'Conversion file creation failed unexpectedly']);
         exit;
    }

    $convertedSize = filesize($convertedPath);
    $dbConvertedPath = 'converted/' . basename($convertedPath);
    
    $finalStmt = $conn->prepare("UPDATE conversions SET status = 'completed', converted_filename = ?, converted_path = ?, converted_size = ? WHERE id = ?");
    $finalStmt->bind_param("ssii", $newFilename, $dbConvertedPath, $convertedSize, $conversionId);
    
    if ($finalStmt->execute()) {
         echo json_encode([
            'success' => true, 
            'message' => 'Conversion successful',
            'converted_filename' => $newFilename,
            'converted_size' => $convertedSize
        ]);
    } else {
         echo json_encode(['error' => 'Failed to update database after conversion']);
    }
} else {
    $failStmt = $conn->prepare("UPDATE conversions SET status = 'failed' WHERE id = ?");
    $failStmt->bind_param("i", $conversionId);
    $failStmt->execute();
    http_response_code(500);
    echo json_encode(['error' => $errorMsg]);
}

$conn->close();
?>
