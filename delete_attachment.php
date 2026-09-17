<?php
require 'config.php';

if (isset($_GET['id'])) {
    $reworkId = intval($_GET['id']);

    // Намираме името на файла, за да го изтрием от сървъра
    $stmt = $conn->prepare("SELECT attachment FROM reworks WHERE id = ?");
    $stmt->bind_param("i", $reworkId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $fileName = $row['attachment'];
        if (!empty($fileName)) {
            $filePath = 'uploads/reworks/' . $fileName;
            if (file_exists($filePath)) {
                @unlink($filePath); // Изтрива физическия файл
            }
        }

        // Нулираме полето в базата данни
        $updateStmt = $conn->prepare("UPDATE reworks SET attachment = NULL WHERE id = ?");
        $updateStmt->bind_param("i", $reworkId);
        $updateStmt->execute();
    }
}

header('Location: index.php?msg=' . urlencode('Attachment deleted successfully!'));
exit;
?>