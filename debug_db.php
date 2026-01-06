<?php
require 'includes/db.php';
$res = $conn->query('SELECT id, converted_filename, converted_path FROM conversions ORDER BY id DESC LIMIT 1');
if ($res) {
    print_r($res->fetch_assoc());
} else {
    echo "Query failed";
}
?>
