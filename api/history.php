<?php
// api/history.php
require_once '../includes/db.php';

header('Content-Type: application/json');

session_start();
// If no session, can't show history specific to user easily without login system. 
// We rely on session_id set during upload.
$sessionId = session_id();

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT * FROM conversions WHERE session_id = ? ORDER BY upload_time DESC LIMIT ? OFFSET ?");
$stmt->bind_param("sii", $sessionId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

// Count total for pagination
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM conversions WHERE session_id = ?");
$countStmt->bind_param("s", $sessionId);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

echo json_encode([
    'data' => $history,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $totalRows
    ]
]);

$conn->close();
?>
