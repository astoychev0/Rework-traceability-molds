<?php
require 'config.php';

if (isset($_GET['id'])) {
    $fileId = intval($_GET['id']);

    $stmt = $conn->prepare("SELECT file_name FROM rework_attachments WHERE id = ?");
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $filePath = 'uploads/reworks/' . $row['file_name'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $delStmt = $conn->prepare("DELETE FROM rework_attachments WHERE id = ?");
        $delStmt->bind_param("i", $fileId);
        $delStmt->execute();
    }
}

header('Location: index.php?msg=' . urlencode('File deleted successfully!'));
exit;