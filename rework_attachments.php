<?php
require 'config.php';

$rework_id = intval($_GET['rework_id'] ?? 0);

// Взимаме информация за rework-а и съответния калъп
$stmt = $conn->prepare("SELECT r.*, m.id as mold_id, m.mold_code FROM reworks r JOIN molds m ON r.mold_id = m.id WHERE r.id = ?");
$stmt->bind_param('i', $rework_id);
$stmt->execute();
$rework = $stmt->get_result()->fetch_assoc();

if (!$rework) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// 1. Логика за добавяне на нов файл в движение
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['new_attachment'])) {
    if ($_FILES['new_attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/reworks/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $tmpName = $_FILES['new_attachment']['tmp_name'];
        $originalName = $_FILES['new_attachment']['name'];
        $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);
        
        $newFileName = 'rework_' . $rework_id . '_' . time() . '.' . $fileExtension;
        $targetPath = $uploadDir . $newFileName;

        if (move_uploaded_file($tmpName, $targetPath)) {
            $stmtFile = $conn->prepare("INSERT INTO rework_attachments (rework_id, file_name, original_name) VALUES (?, ?, ?)");
            $stmtFile->bind_param("iss", $rework_id, $newFileName, $originalName);
            if ($stmtFile->execute()) {
                $success = 'Файлът е прикачен успешно!';
            } else {
                $error = 'Грешка при записа в базата данни.';
            }
        } else {
            $error = 'Грешка при качването на файла на сървъра.';
        }
    } else {
        $error = 'Моля, изберете валиден файл.';
    }
}

// 2. Логика за изтриване на файл
if (isset($_GET['delete_file'])) {
    $file_id = intval($_GET['delete_file']);
    
    // Намираме файла, за да го изтрием физически от паметта
    $fileQuery = $conn->prepare("SELECT * FROM rework_attachments WHERE id = ? AND rework_id = ?");
    $fileQuery->bind_param('ii', $file_id, $rework_id);
    $fileQuery->execute();
    $fileData = $fileQuery->get_result()->fetch_assoc();

    if ($fileData) {
        $filePath = 'uploads/reworks/' . $fileData['file_name'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        
        $del = $conn->prepare("DELETE FROM rework_attachments WHERE id = ?");
        $del->bind_param('i', $file_id);
        $del->execute();
        
        header("Location: rework_attachments.php?rework_id=" . $rework_id);
        exit;
    }
}

// Взимаме актуалния списък с файлове към този rework
$filesStmt = $conn->prepare("SELECT * FROM rework_attachments WHERE rework_id = ? ORDER BY id DESC");
$filesStmt->bind_param('i', $rework_id);
$filesStmt->execute();
$attachments = $filesStmt->get_result();
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Файлове за Rework - Калъп <?= h($rework['mold_code']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="header-container">
    <div class="header-brand-container">
        <a href="index.php" class="logo-link">
            <img src="logo-ottobock 3.png" alt="Ottobock Logo" class="logo-img">
        </a>
        <h1 class="header-title">📎 Прикачени файлове за Rework (#<?= $rework_id ?> - <span style="color: #2563eb;"><?= h($rework['mold_code']) ?></span>)</h1>
    </div>
    <div>
        <a href="mold_history.php?id=<?= $rework['mold_id'] ?>" class="btn secondary">← Назад към историята на калъпа</a>
    </div>
</header>

<main style="padding: 30px 20px; max-width: 800px; margin: 0 auto;">
    
    <?php if (!empty($success)): ?>
        <div style="background-color: #dcfce7; color: #166534; padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-weight: 500;">
            ✅ <?= h($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div style="background-color: #fee2e2; color: #991b1b; padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-weight: 500;">
            ⚠️ <?= h($error) ?>
        </div>
    <?php endif; ?>

    <!-- Форма за добавяне на нов файл в движение по всяко време -->
    <div class="card" style="background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 25px;">
        <h3 style="margin-top: 0; color: #1e293b; margin-bottom: 15px;">Добави нов файл (сега или по-късно)</h3>
        <form method="POST" action="rework_attachments.php?rework_id=<?= $rework_id ?>" enctype="multipart/form-data" style="display: flex; gap: 15px; align-items: center;">
            <input type="file" name="new_attachment" required style="flex: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff;">
            <button type="submit" class="btn" style="padding: 9px 20px; white-space: nowrap;">📤 Качи файл</button>
        </form>
    </div>

    <!-- Списък с вече качените файлове -->
    <div class="card" style="background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; color: #1e293b; margin-bottom: 15px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">Списък с файлове</h3>
        
        <?php if ($attachments->num_rows > 0): ?>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php while($file = $attachments->fetch_assoc()): ?>
                    <li style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #f1f5f9;">
                        <span style="font-weight: 500; color: #334155;">📄 <?= h($file['original_name']) ?></span>
                        <div style="display: flex; gap: 10px;">
                            <!-- Преглед в нов прозорец/таб -->
                            <a href="uploads/reworks/<?= h($file['file_name']) ?>" target="_blank" class="btn secondary" style="padding: 6px 12px; font-size: 0.85rem; text-decoration: none;">👁️ Виж в нов прозорец</a>
                            <!-- Бутон за изтриване -->
                            <a href="rework_attachments.php?rework_id=<?= $rework_id ?>&delete_file=<?= $file['id'] ?>" onclick="return confirm('Сигурни ли сте, че искате да изтриете този файл?');" class="btn" style="background-color: #ef4444; padding: 6px 12px; font-size: 0.85rem; text-decoration: none;">🗑️ Изтрий</a>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p style="color: #64748b; font-style: italic; margin: 0;">Все още няма прикачени файлове към този rework.</p>
        <?php endif; ?>
    </div>

</main>

</body>
</html>