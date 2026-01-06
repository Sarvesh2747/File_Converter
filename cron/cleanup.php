<?php
// cron/cleanup.php
// Ideally run this via a real cron job: 0 * * * * php /path/to/cron/cleanup.php
require_once dirname(__FILE__) . '/../includes/db.php';

echo "Starting cleanup...\n";

// Threshold: 24 hours ago
$threshold = date('Y-m-d H:i:s', strtotime('-24 hours'));

// Select files to delete
$stmt = $conn->prepare("SELECT id, original_path, converted_path FROM conversions WHERE upload_time < ?");
$stmt->bind_param("s", $threshold);
$stmt->execute();
$result = $stmt->get_result();

$deletedCount = 0;

while ($row = $result->fetch_assoc()) {
    // Delete physical files
    $original = dirname(__FILE__) . '/../' . $row['original_path'];
    $converted = dirname(__FILE__) . '/../' . $row['converted_path'];
    
    if (file_exists($original)) {
        unlink($original);
    }
    
    if ($row['converted_path'] && file_exists($converted)) {
        unlink($converted);
    }

    // Delete from DB
    $delStmt = $conn->prepare("DELETE FROM conversions WHERE id = ?");
    $delStmt->bind_param("i", $row['id']);
    $delStmt->execute();
    
    $deletedCount++;
}

echo "Cleanup completed. Removed $deletedCount records.\n";

$conn->close();
?>
