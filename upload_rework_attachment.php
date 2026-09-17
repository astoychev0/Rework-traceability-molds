<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attachment']) && isset($_POST['rework_id'])) {
    $reworkId = intval($_POST['rework_id']);
    $file = $_FILES['attachment'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/reworks/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = 'rework_' . $reworkId . '_' . time() . '.' . $fileExtension;
        $targetPath = $uploadDir . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $stmt = $conn->prepare("UPDATE reworks SET attachment = ? WHERE id = ?");
            $stmt->bind_param("si", $newFileName, $reworkId);
            $stmt->execute();
        }
    }
}

// Пренасочване обратно към страницата с историята
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
?>