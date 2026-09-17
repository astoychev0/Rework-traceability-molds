<?php
require 'config.php';

if (isset($_GET['id'])) {
    $reworkId = intval($_GET['id']);

    // Първо може да изтриете свързаните файлове от паметта и базата (по желание)
    $stmtFiles = $conn->prepare("SELECT file_name FROM rework_attachments WHERE rework_id = ?");
    $stmtFiles->bind_param("i", $reworkId);
    $stmtFiles->execute();
    $resultFiles = $stmtFiles->get_result();
    while ($file = $resultFiles->fetch_assoc()) {
        $filePath = 'uploads/reworks/' . $file['file_name'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    $stmtFiles->close();

    // Изтриване на записите за файловете от базата
    $stmtDelAtt = $conn->prepare("DELETE FROM rework_attachments WHERE rework_id = ?");
    $stmtDelAtt->bind_param("i", $reworkId);
    $stmtDelAtt->execute();
    $stmtDelAtt->close();

    // Изтриване на самия rework запис
    $stmt = $conn->prepare("DELETE FROM reworks WHERE id = ?");
    $stmt->bind_param("i", $reworkId);
    $stmt->execute();
    $stmt->close();
}

// Връщане обратно към списъка с успешно съобщение
header('Location: all_reworks.php?msg=' . urlencode('Rework deleted successfully!'));
exit;