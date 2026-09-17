<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

$files = [];

if (isset($_GET['rework_id'])) {
    $reworkId = intval($_GET['rework_id']);

    if ($reworkId > 0) {
        $stmt = $conn->prepare("SELECT id, file_name, original_name FROM rework_attachments WHERE rework_id = ? ORDER BY id DESC");
        
        if ($stmt) {
            $stmt->bind_param("i", $reworkId);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                // Път до файла спрямо корена
                $filePath = 'uploads/reworks/' . $row['file_name'];
                
                // Добавяме и флаг дали файлът наистина съществува физически на диска
                $row['file_exists'] = file_exists($filePath);
                $row['file_url'] = $filePath;
                
                $files[] = $row;
            }
            
            $stmt->close();
        }
    }
}

echo json_encode($files, JSON_UNESCAPED_UNICODE);
exit;