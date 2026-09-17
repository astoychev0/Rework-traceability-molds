<?php
require 'config.php'; 

// Стартираме сесия за CSRF защита
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Генериране на CSRF токен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Предпазна дефиниция на h() за защита от XSS
if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// ================= ФУНКЦИЯ ЗА ОРАЗМЕРЯВАНЕ И КОМПРЕСИРАНЕ С PHP GD =================
function compressAndResizeImage($sourcePath, $targetPath, $maxWidth = 1920, $maxHeight = 1920, $quality = 82) {
    $info = @getimagesize($sourcePath);
    if (!$info) {
        return false; // Не е валидно изображение
    }

    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];

    // Създаване на GD ресурс спрямо типа
    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }

    if (!$image) {
        return false;
    }

    // Автоматично коригиране на ориентацията от EXIF (ако е снимка от телефон)
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($sourcePath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $image = imagerotate($image, 180, 0);
                    break;
                case 6:
                    $image = imagerotate($image, -90, 0);
                    $temp = $width; $width = $height; $height = $temp;
                    break;
                case 8:
                    $image = imagerotate($image, 90, 0);
                    $temp = $width; $width = $height; $height = $temp;
                    break;
            }
        }
    }

    // Изчисление на новите размери с запазване на пропорциите
    $newWidth = $width;
    $newHeight = $height;

    if ($width > $maxWidth || $height > $maxHeight) {
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);
    }

    // Създаване на новото оразмерено платно
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Запазване на прозрачността за PNG и WEBP
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Запазване като конвертиран JPEG/PNG/WEBP с компресия
    $success = false;
    if ($mime === 'image/png') {
        $pngQuality = (int)round((100 - $quality) / 10);
        $success = imagepng($newImage, $targetPath, $pngQuality);
    } elseif ($mime === 'image/webp') {
        $success = imagewebp($newImage, $targetPath, $quality);
    } else {
        $success = imagejpeg($newImage, $targetPath, $quality);
    }

    imagedestroy($image);
    imagedestroy($newImage);

    return $success;
}

// ================= ОБРАБОТКА НА КАЧВАНЕТО НА ФАЙЛОВЕ ОТ МОДАЛА =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rework_id'])) {
    
    // CSRF проверка
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Грешка: Невалиден CSRF токен.');
    }

    $reworkId = intval($_POST['rework_id']);
    
    if ($reworkId > 0 && isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        
        $checkStmt = $conn->prepare("SELECT id FROM reworks WHERE id = ?");
        $checkStmt->bind_param("i", $reworkId);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result();
        
        if ($checkRes->num_rows === 0) {
            $checkStmt->close();
            header('Location: index.php?msg=' . urlencode('Грешка: Записът за обработка не съществува.'));
            exit;
        }
        $checkStmt->close();

        $uploadDir = 'uploads/reworks/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxFileSize = 20 * 1024 * 1024; // До 20MB оригинален файл
        
        $uploadedCount = 0;
        $rejectedCount = 0;

        $totalFiles = count($_FILES['attachments']['name']);
        
        for ($i = 0; $i < $totalFiles; $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $fileSize = $_FILES['attachments']['size'][$i];
                $tmpName = $_FILES['attachments']['tmp_name'][$i];
                $originalName = $_FILES['attachments']['name'][$i];
                
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                if (in_array($ext, $allowedExtensions) && $fileSize <= $maxFileSize) {
                    $newFileName = 'rework_' . $reworkId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    
                    if (in_array($ext, $imageExtensions)) {
                        $newFileName = 'rework_' . $reworkId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.jpg';
                    }

                    $targetPath = $uploadDir . $newFileName;
                    $uploadedSuccessfully = false;

                    // 1. Ако е снимка -> компресираме през GD
                    if (in_array($ext, $imageExtensions) && function_exists('imagecreatetruecolor')) {
                        $uploadedSuccessfully = compressAndResizeImage($tmpName, $targetPath, 1920, 1920, 82);
                    }
                    
                    // 2. Стандартно преместване ако не е снимка или GD откаже
                    if (!$uploadedSuccessfully) {
                        $uploadedSuccessfully = move_uploaded_file($tmpName, $targetPath);
                    }

                    if ($uploadedSuccessfully) {
                        $stmt = $conn->prepare("INSERT INTO rework_attachments (rework_id, file_name, original_name) VALUES (?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param("iss", $reworkId, $newFileName, $originalName);
                            $stmt->execute();
                            $stmt->close();
                            $uploadedCount++;
                        }
                    }
                } else {
                    $rejectedCount++;
                }
            }
        }

        if ($uploadedCount > 0 && $rejectedCount === 0) {
            $msg = 'Файловете са качени и оптимизирани успешно!';
        } elseif ($uploadedCount > 0 && $rejectedCount > 0) {
            $msg = "Успешно качен(и): $uploadedCount файла. Отхвърлени: $rejectedCount.";
        } else {
            $msg = 'Грешка: Файловете не бяха качени. Позволени формати (jpg, png, pdf, doc, xls).';
        }

        header('Location: index.php?msg=' . urlencode($msg));
        exit;
    } else {
        header('Location: index.php?msg=' . urlencode('Моля, изберете поне един файл за качване.'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mold Tracking System</title>
<link rel="stylesheet" href="assets/style.css">
<style>
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 1000;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(3px);
    }
    .modal-content {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        width: 90%;
        max-width: 540px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        max-height: 85vh;
        overflow-y: auto;
    }
    .action-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 0.8rem;
        text-decoration: none;
        font-weight: 600;
        transition: background 0.2s;
        cursor: pointer;
        border: none;
    }
    .badge-view { background: #e0f2fe; color: #0369a1; }
    .badge-view:hover { background: #bae6fd; }
    .badge-upload { background: #f1f5f9; color: #475569; }
    .badge-upload:hover { background: #e2e8f0; }

    .file-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        margin-bottom: 8px;
    }

    /* DRAG & DROP ЗОНА */
    .drop-zone {
        border: 2px dashed #003366;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        background: #f0f4f8;
        transition: all 0.2s ease;
        margin-bottom: 15px;
        cursor: pointer;
    }
    .drop-zone.drag-over {
        background: #e0eaf4;
        border-color: #2563eb;
        transform: scale(1.01);
    }

    .upload-options-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-top: 10px;
    }
    .upload-option-card {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 10px 5px;
        text-align: center;
        cursor: pointer;
        background: #ffffff;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        width: 100%;
    }
    .upload-option-card:hover {
        border-color: #003366;
        background: #f8fafc;
    }
    .upload-option-card span {
        font-size: 0.8rem;
        font-weight: 600;
        color: #334155;
    }

    .molds-title-box {
        display: inline-block;
        padding: 8px 18px;
        background: #f0f4f8;
        border: 2px solid #003366;
        border-radius: 8px;
        color: #003366;
        margin-bottom: 15px;
        font-size: 1.25rem;
        font-weight: 700;
        box-shadow: 0 2px 5px rgba(0, 51, 102, 0.1);
    }

    /* LIGHTBOX СТИЛОВЕ */
    .lightbox-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.88);
        z-index: 2000;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }
    .lightbox-img {
        max-width: 92%;
        max-height: 85vh;
        border-radius: 8px;
        box-shadow: 0 5px 30px rgba(0,0,0,0.5);
        object-fit: contain;
    }
    .lightbox-close {
        position: absolute;
        top: 20px;
        right: 25px;
        color: #ffffff;
        font-size: 35px;
        font-weight: bold;
        cursor: pointer;
        user-select: none;
    }
    .lightbox-caption {
        color: #e2e8f0;
        margin-top: 12px;
        font-size: 0.95rem;
        text-align: center;
        max-width: 80%;
    }

    /* ================= МОБИЛНА ВЕРСИЯ И ТАБЛЕТИ (UI/UX) ================= */
    @media (max-width: 768px) {
        .header-container {
            flex-direction: column;
            gap: 12px;
            align-items: stretch !important;
            text-align: center;
        }
        
        .header-brand-container {
            justify-content: center;
        }

        .action-badge {
            padding: 8px 14px !important;
            font-size: 0.88rem !important;
            min-height: 38px;
            justify-content: center;
        }

        table, thead, tbody, th, td, tr { 
            display: block; 
        }
        
        thead tr { 
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        
        tr.rework-row {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 12px;
            padding: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }
        
        td { 
            border: none !important;
            padding: 6px 0 !important;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }

        td:nth-of-type(1)::before { content: "Дата:"; font-weight: 600; color: #64748b; }
        td:nth-of-type(2)::before { content: "Код Калъп:"; font-weight: 600; color: #64748b; }
        td:nth-of-type(3)::before { content: "Заявен:"; font-weight: 600; color: #64748b; }
        td:nth-of-type(4)::before { content: "Приключен:"; font-weight: 600; color: #64748b; }
        td:nth-of-type(5)::before { content: "Техник:"; font-weight: 600; color: #64748b; }
        td:nth-of-type(6)::before { content: "Приоритет:"; font-weight: 600; color: #64748b; }
        
        td:nth-of-type(7) {
            flex-direction: column;
            align-items: stretch;
            border-top: 1px dashed #e2e8f0 !important;
            margin-top: 8px;
            padding-top: 10px !important;
        }

        .modal-content {
            width: 95% !important;
            max-height: 90vh !important;
            padding: 16px !important;
            border-radius: 14px !important;
        }

        .upload-options-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        
        .upload-option-card {
            padding: 14px 4px !important;
        }

        .lightbox-img {
            max-width: 98% !important;
            max-height: 80vh !important;
        }
    }
</style>
</head>
<body>

<header class="header-container">
    <div class="header-brand-container">
        <a href="index.php" class="logo-link" title="Go to Main Dashboard">
            <img src="logo-ottobock 3.png" alt="Ottobock Logo" class="logo-img">
        </a>
        <h1 class="header-title">⚙️ Mold Tracking System</h1>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="add_mold.php" class="btn" style="font-size: 0.85rem; padding: 7px 14px;">+ Add Mold</a>
        <a href="statistics.php" class="btn secondary" style="font-size: 0.85rem; padding: 7px 14px;">📊 Statistics</a>
    </div>
</header>

<main>

<?php if (isset($_GET['msg'])): ?>
    <div class="flash" style="padding: 10px; background: #dcfce7; color: #166534; margin-bottom: 15px; border-radius: 6px; text-align: center; font-weight: 600;">
        <?= h($_GET['msg']) ?>
    </div>
<?php endif; ?>

<!-- SECTION 1: MOLDS -->
<div class="card molds-grid-container" style="padding: 20px; margin-bottom: 20px; text-align: center;">
    <div>
        <h2 class="section-title molds-title-box">Registered Molds</h2>
    </div>
    
    <div style="margin-bottom: 15px; display: flex; justify-content: center;">
        <input type="text" id="moldSearchInput" placeholder="Type to filter molds instantly..." style="max-width: 320px; width: 100%; padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; outline: none;">
    </div>
    
    <div class="molds-grid" id="moldsGrid" style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;">
        <?php
        $moldsQuery = $conn->query("SELECT id, mold_code FROM molds ORDER BY created_at DESC");
        if ($moldsQuery && $moldsQuery->num_rows > 0):
            while ($mold = $moldsQuery->fetch_assoc()):
        ?>
            <a href="mold_history.php?id=<?= $mold['id'] ?>" class="mold-badge" data-code="<?= strtolower(h($mold['mold_code'] ?? '')) ?>" style="padding: 6px 12px; background: #f1f5f9; border-radius: 4px; text-decoration: none; color: #334155; font-weight: 600;">
                <?= h($mold['mold_code'] ?? '') ?>
            </a>
        <?php endwhile; endif; ?>
    </div>
    <div style="text-align: center; margin-top: 15px;">
        <a href="all_molds.php" class="see-all-btn">see all molds</a>
    </div>
</div>

<!-- SECTION 2: LAST 10 REWORKS -->
<div class="card" style="padding: 20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px; flex-wrap:wrap; gap:10px;">
        <h2 class="section-title" style="margin:0;">Last 10 Reworks</h2>
        <input type="text" id="reworkSearchInput" placeholder="Filter reworks instantly..." style="width: 280px; padding: 7px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; outline: none;">
    </div>
    
    <div style="overflow-x:auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8fafc; text-align: left;">
                    <th style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Date & Time</th>
                    <th style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Mold Code</th>
                    <th style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Reported Date</th>
                    <th style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Completion Date</th>
                    <th style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Technician</th>
                    <th style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Priority</th>
                    <th style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Short Note & Attachments</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $reworksSql = "SELECT r.*, 
                           (SELECT COUNT(*) FROM rework_attachments WHERE rework_id = r.id) as file_count 
                           FROM reworks r 
                           ORDER BY r.created_at DESC 
                           LIMIT 10";
            $reworksRes = $conn->query($reworksSql);

            if ($reworksRes && $reworksRes->num_rows > 0):
                while ($r = $reworksRes->fetch_assoc()):
                    $moldCode = $r['mold_code'] ?? '';
                    $technician = $r['technician'] ?? '';
                    $shortNote = $r['short_note'] ?? ($r['reason_notes'] ?? '');
                    $priority = $r['priority'] ?? 'Нормален';
                    $fileCount = intval($r['file_count']);
                    $searchData = strtolower(h($moldCode . ' ' . $technician . ' ' . $shortNote . ' ' . $priority));
            ?>
                <tr class="rework-row" data-search="<?= $searchData ?>">
                    <td style="padding: 10px; border-bottom: 1px solid #f1f5f9;"><span class="timestamp">⏱️ <?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></span></td>
                    <td style="padding: 10px; border-bottom: 1px solid #f1f5f9;">
                        <span style="color: #0369a1; font-weight: 700;"><?= h($moldCode) ?></span>
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid #f1f5f9;"><?= !empty($r['reported_date']) ? date('d.m.Y', strtotime($r['reported_date'])) : '—' ?></td>
                    <td style="padding: 10px; border-bottom: 1px solid #f1f5f9;"><?= !empty($r['rework_end_date']) ? date('d.m.Y', strtotime($r['rework_end_date'])) : '—' ?></td>
                    <td style="padding: 10px; border-bottom: 1px solid #f1f5f9;"><?= h($technician ?: '—') ?></td>
                    <td style="padding: 10px; border-bottom: 1px solid #f1f5f9;">
                        <?php if ($priority === 'Priority' || $priority === 'High'): ?>
                            <span style="background: #fee2e2; color: #dc2626; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">🔴 High</span>
                        <?php else: ?>
                            <span style="color: #64748b; font-size: 0.8rem;"><?= h($priority) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid #f1f5f9;">
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            <span class="short-note-text"><?= h($shortNote ?: '—') ?></span>
                            
                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                <button type="button" onclick="openFilesModal(<?= $r['id'] ?>, '<?= h($moldCode) ?>')" class="action-badge badge-view">
                                    📂 View Files (<?= $fileCount ?>)
                                </button>
                                <button type="button" onclick="openUploadModal(<?= $r['id'] ?>)" class="action-badge badge-upload">
                                    ➕ Add Files
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="7" style="text-align:center; color:#64748b; padding:20px;">No rework entries recorded yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div style="text-align: center; margin-top: 15px;">
        <a href="all_reworks.php" class="see-all-btn">see all reworks</a>
    </div>
</div>

</main>

<!-- МОДАЛЕН ПРОЗОРЕЦ ЗА ПРЕГЛЕД НА ФАЙЛОВЕТЕ -->
<div id="viewFilesModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;" id="modalFilesTitle">📁 Attached Files</h3>
            <button type="button" onclick="closeViewModal()" style="background:none; border:none; font-size: 1.2rem; cursor:pointer;">✕</button>
        </div>
        <div id="modalFilesList" style="margin-bottom: 20px; max-height: 320px; overflow-y: auto;">
            <p style="color: #64748b; text-align: center;">Loading...</p>
        </div>
        <div style="text-align: right;">
            <button type="button" class="btn secondary" onclick="closeViewModal()" style="padding: 6px 14px;">Close</button>
        </div>
    </div>
</div>

<!-- МОДАЛЕН ПРОЗОРЕЦ ЗА КАЧВАНЕ НА ФАЙЛОВЕ (С DRAG & DROP) -->
<div id="uploadModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;">Прикачване на файлове</h3>
            <button type="button" onclick="closeUploadModal()" style="background:none; border:none; font-size: 1.2rem; cursor:pointer;">✕</button>
        </div>

        <form method="POST" action="index.php" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="rework_id" id="uploadReworkId">
            <input type="file" id="realFileInput" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx" style="display: none;" onchange="updateFilesPreview()">

            <!-- DRAG & DROP ЗОНА -->
            <div id="dropZone" class="drop-zone" onclick="triggerFileSelect('file')">
                <span style="font-size: 32px; display: block; margin-bottom: 6px;">📥</span>
                <strong style="color: #003366;">Плъзнете и пуснете файлове тук</strong>
                <span style="display: block; font-size: 0.8rem; color: #64748b; margin-top: 4px;">или кликнете за избор от компютъра</span>
            </div>

            <!-- БУТОНИ ЗА МОБИЛНИ И БЪРЗ ИЗБОР -->
            <div class="upload-options-grid">
                <button type="button" class="upload-option-card" onclick="triggerFileSelect('camera')">
                    <span style="font-size: 20px;">📸</span>
                    <span>Снимай</span>
                </button>

                <button type="button" class="upload-option-card" onclick="triggerFileSelect('gallery')">
                    <span style="font-size: 20px;">🖼️</span>
                    <span>Галерия</span>
                </button>

                <button type="button" class="upload-option-card" onclick="triggerFileSelect('file')">
                    <span style="font-size: 20px;">📁</span>
                    <span>Файл</span>
                </button>
            </div>

            <div id="selectedFilesPreview" style="margin-top: 15px; font-size: 0.85rem; color: #475569; background: #f8fafc; padding: 10px; border-radius: 6px; display: none;"></div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn secondary" onclick="closeUploadModal()" style="padding: 6px 14px;">Отказ</button>
                <button type="submit" class="btn" style="padding: 6px 16px;">Качи файловете</button>
            </div>
        </form>
    </div>
</div>

<!-- LIGHTBOX МОДАЛ ЗА ПРЕГЛЕД НА СНИМКИ В ПЪЛЕН РАЗМЕР -->
<div id="lightboxModal" class="lightbox-overlay" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <img id="lightboxImage" class="lightbox-img" src="" alt="Full view" onclick="event.stopPropagation()">
    <div id="lightboxCaption" class="lightbox-caption"></div>
</div>

<script>
const fileInput = document.getElementById('realFileInput');
const dropZone = document.getElementById('dropZone');

// --- DRAG & DROP ЛОГИКА ---
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
    document.body.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-over'), false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-over'), false);
});

dropZone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    fileInput.files = files;
    updateFilesPreview();
});

// --- ИЗБОР НА ФАЙЛОВЕ ---
function triggerFileSelect(type) {
    if (type === 'camera') {
        fileInput.removeAttribute('multiple');
        fileInput.setAttribute('accept', 'image/*');
        fileInput.setAttribute('capture', 'environment');
    } else if (type === 'gallery') {
        fileInput.setAttribute('multiple', 'multiple');
        fileInput.setAttribute('accept', 'image/*');
        fileInput.removeAttribute('capture');
    } else {
        fileInput.setAttribute('multiple', 'multiple');
        fileInput.setAttribute('accept', '.jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx');
        fileInput.removeAttribute('capture');
    }
    fileInput.click();
}

function updateFilesPreview() {
    const previewContainer = document.getElementById('selectedFilesPreview');
    if (fileInput.files.length > 0) {
        let html = '<b>Избрани файлове (' + fileInput.files.length + '):</b><ul style="margin: 5px 0 0 15px; padding:0;">';
        for (let i = 0; i < fileInput.files.length; i++) {
            const safeName = document.createElement('div');
            safeName.textContent = fileInput.files[i].name;
            html += `<li>${safeName.innerHTML}</li>`;
        }
        html += '</ul>';
        previewContainer.innerHTML = html;
        previewContainer.style.display = 'block';
    } else {
        previewContainer.innerHTML = '';
        previewContainer.style.display = 'none';
    }
}

function openUploadModal(reworkId) {
    document.getElementById('uploadReworkId').value = reworkId;
    fileInput.value = '';
    updateFilesPreview();
    document.getElementById('uploadModal').style.display = 'flex';
}

function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}

// --- LIGHTBOX ЛОГИКА ---
function openLightbox(url, title) {
    document.getElementById('lightboxImage').src = url;
    document.getElementById('lightboxCaption').innerText = title;
    document.getElementById('lightboxModal').style.display = 'flex';
}

function closeLightbox() {
    document.getElementById('lightboxModal').style.display = 'none';
    document.getElementById('lightboxImage').src = '';
}

// --- ПРЕГЛЕД НА ФАЙЛОВЕТЕ С ТУМБНЕЙЛ И ВРЪЗКА С LIGHTBOX ---
function openFilesModal(reworkId, moldCode) {
    document.getElementById('modalFilesTitle').innerText = '📁 Files for Mold: ' + moldCode;
    const listContainer = document.getElementById('modalFilesList');
    listContainer.innerHTML = '<p style="color: #64748b; text-align: center;">Loading...</p>';
    document.getElementById('viewFilesModal').style.display = 'flex';

    fetch('get_files.php?rework_id=' + reworkId)
        .then(response => response.json())
        .then(data => {
            if (!Array.isArray(data) || data.length === 0) {
                listContainer.innerHTML = '<p style="color: #64748b; text-align: center; padding: 15px;">No files attached yet.</p>';
                return;
            }

            listContainer.innerHTML = '';
            data.forEach(file => {
                const displayName = file.original_name ? file.original_name : file.file_name;
                const ext = displayName.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);

                const itemDiv = document.createElement('div');
                itemDiv.className = 'file-item';

                // Лява част: Снимка/Иконка + Име
                const leftContainer = document.createElement('div');
                leftContainer.style.display = 'flex';
                leftContainer.style.alignItems = 'center';
                leftContainer.style.gap = '10px';

                const fileTargetUrl = file.file_url ? file.file_url : 'uploads/reworks/' + encodeURIComponent(file.file_name);

                if (isImage && file.file_exists !== false) {
                    const imgThumb = document.createElement('img');
                    imgThumb.src = fileTargetUrl;
                    imgThumb.alt = displayName;
                    imgThumb.style.width = '42px';
                    imgThumb.style.height = '42px';
                    imgThumb.style.objectFit = 'cover';
                    imgThumb.style.borderRadius = '6px';
                    imgThumb.style.border = '1px solid #cbd5e1';
                    imgThumb.style.cursor = 'pointer';
                    imgThumb.onclick = function() {
                        openLightbox(fileTargetUrl, displayName);
                    };
                    leftContainer.appendChild(imgThumb);
                } else {
                    const iconSpan = document.createElement('span');
                    iconSpan.style.fontSize = '1.4rem';
                    iconSpan.innerText = '📄';
                    leftContainer.appendChild(iconSpan);
                }

                const nameSpan = document.createElement('span');
                nameSpan.style.cssText = 'font-size: 0.88rem; word-break: break-all; max-width: 220px; color: #334155; line-height: 1.2;';
                
                if (file.file_exists === false) {
                    nameSpan.innerHTML = displayName + ' <br><span style="color:#dc2626; font-size:0.75rem; font-weight:bold;">(Файлът липсва)</span>';
                } else {
                    nameSpan.innerText = displayName;
                }
                leftContainer.appendChild(nameSpan);

                // Дясна част: Бутони View и Delete
                const actionsDiv = document.createElement('div');
                actionsDiv.style.display = 'flex';
                actionsDiv.style.gap = '6px';

                const viewBtn = document.createElement('a');
                viewBtn.className = 'action-badge badge-view';
                viewBtn.style.padding = '5px 10px';
                viewBtn.style.textDecoration = 'none';
                viewBtn.innerText = 'View';

                if (isImage) {
                    viewBtn.href = '#';
                    viewBtn.onclick = function(e) {
                        e.preventDefault();
                        openLightbox(fileTargetUrl, displayName);
                    };
                } else {
                    viewBtn.href = fileTargetUrl;
                    viewBtn.target = '_blank';
                }

                if (file.file_exists === false) {
                    viewBtn.style.opacity = '0.5';
                    viewBtn.style.pointerEvents = 'none';
                }

                const deleteBtn = document.createElement('a');
                deleteBtn.href = `delete_file.php?id=${file.id}`;
                deleteBtn.className = 'action-badge';
                deleteBtn.style.cssText = 'background: #fee2e2; color: #dc2626; padding: 5px 10px; text-decoration:none;';
                deleteBtn.innerText = 'Delete';
                deleteBtn.onclick = function() {
                    return confirm('Are you sure you want to delete this file?');
                };

                actionsDiv.appendChild(viewBtn);
                actionsDiv.appendChild(deleteBtn);

                itemDiv.appendChild(leftContainer);
                itemDiv.appendChild(actionsDiv);

                listContainer.appendChild(itemDiv);
            });
        })
        .catch(error => {
            listContainer.innerHTML = '<p style="color: #ef4444; text-align: center;">Error loading files.</p>';
        });
}

function closeViewModal() {
    document.getElementById('viewFilesModal').style.display = 'none';
}

window.onclick = function(event) {
    const modalUpload = document.getElementById('uploadModal');
    const modalView = document.getElementById('viewFilesModal');
    if (event.target === modalUpload) closeUploadModal();
    if (event.target === modalView) closeViewModal();
};

window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeUploadModal();
        closeViewModal();
        closeLightbox();
    }
});

document.getElementById('moldSearchInput').addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    document.querySelectorAll('#moldsGrid .mold-badge').forEach(badge => {
        const code = badge.getAttribute('data-code') || '';
        badge.style.display = code.includes(query) ? 'inline-block' : 'none';
    });
});

document.getElementById('reworkSearchInput').addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    document.querySelectorAll('.rework-row').forEach(row => {
        const text = row.getAttribute('data-search') || '';
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>

</body>
</html>