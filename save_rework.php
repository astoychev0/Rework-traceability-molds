<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'config.php';

function processAndCompressImage($tmpPath, $destinationPath, $maxWidth = 1920, $quality = 82) {
    if (!extension_loaded('gd') || !function_exists('imagecreatefromjpeg')) {
        return move_uploaded_file($tmpPath, $destinationPath);
    }

    $info = @getimagesize($tmpPath);
    if (!$info) {
        return move_uploaded_file($tmpPath, $destinationPath);
    }

    $mime = $info['mime'];
    $image = null;

    switch ($mime) {
        case 'image/jpeg':
            if (function_exists('imagecreatefromjpeg')) $image = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            if (function_exists('imagecreatefrompng')) $image = @imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) $image = @imagecreatefromwebp($tmpPath);
            break;
    }

    if (!$image) {
        return move_uploaded_file($tmpPath, $destinationPath);
    }

    $width = imagesx($image);
    $height = imagesy($image);

    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = (int)round(($height * $maxWidth) / $width);
        $image = imagescale($image, $newWidth, $newHeight);
    }

    $success = imagejpeg($image, $destinationPath, $quality);
    imagedestroy($image);
    
    return $success;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mold_code       = trim($_POST['mold_code'] ?? '');
    $technician      = trim($_POST['technician'] ?? '');
    $reported_date   = $_POST['reported_date'] ?? date('Y-m-d');
    $completion_date = $_POST['completion_date'] ?? date('Y-m-d');
    $short_note      = trim($_POST['short_note'] ?? '');
    $priority        = trim($_POST['priority'] ?? 'Нормален');

    $mold_id = 0;
    if (!empty($mold_code)) {
        $stmt_m = $conn->prepare("SELECT id FROM molds WHERE mold_code = ?");
        $stmt_m->bind_param('s', $mold_code);
        $stmt_m->execute();
        $res_m = $stmt_m->get_result()->fetch_assoc();
        if ($res_m) {
            $mold_id = $res_m['id'];
        }
        $stmt_m->close();
    }

    if ($mold_id === 0) {
        $mold_id = 1; 
    }

    $reason = 'other';
    $post_rework_status = 'pending_inspection';

    $sql = "INSERT INTO reworks (mold_id, mold_code, technician, reported_date, rework_end_date, short_note, reason, post_rework_status, priority) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issssssss', $mold_id, $mold_code, $technician, $reported_date, $completion_date, $short_note, $reason, $post_rework_status, $priority);

    if ($stmt->execute()) {
        $reworkId = $stmt->insert_id;
        $stmt->close();

        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $targetDir = __DIR__ . "/uploads/reworks/";
            
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $totalFiles = count($_FILES['attachments']['name']);

            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName       = $_FILES['attachments']['tmp_name'][$i];
                    $originalName  = $_FILES['attachments']['name'][$i];
                    $ext           = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    
                    // Реална проверка на файла по съдържание, а не само по разширение
                    $imgInfo       = @getimagesize($tmpName);
                    $isImage       = ($imgInfo !== false);
                    
                    // Ако е снимка, винаги записваме като .jpg, иначе ползваме оригиналното разширение
                    $finalExt      = $isImage ? 'jpg' : (!empty($ext) ? $ext : 'file');

                    $newFileName   = 'rework_' . $reworkId . '_' . time() . '_' . $i . '.' . $finalExt;
                    $fullServerPath = $targetDir . $newFileName;
                    $dbRelativePath = 'uploads/reworks/' . $newFileName;

                    $uploaded = false;
                    if ($isImage) {
                        $uploaded = processAndCompressImage($tmpName, $fullServerPath, 1920, 82);
                    } else {
                        $uploaded = move_uploaded_file($tmpName, $fullServerPath);
                    }

                    if ($uploaded) {
                        $fileStmt = $conn->prepare("INSERT INTO rework_attachments (rework_id, file_name, original_name) VALUES (?, ?, ?)");
                        $fileStmt->bind_param("iss", $reworkId, $dbRelativePath, $originalName);
                        $fileStmt->execute();
                        $fileStmt->close();
                    }
                }
            }
        }

        header("Location: index.php?success=1");
        exit();
    } else {
        echo "Грешка при запис: " . $stmt->error;
        $stmt->close();
    }
}
?>