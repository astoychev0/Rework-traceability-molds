<?php
require 'config.php'; 

// Помощна функция за екраниране срещу XSS
if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Функция за автоматично оразмеряване и компресиране на изображения (GD Library)
function compressAndResizeImage($tmpName, $targetPath, $fileExtension, $maxWidth = 1920, $quality = 82) {
    if (!extension_loaded('gd')) {
        return move_uploaded_file($tmpName, $targetPath);
    }

    list($width, $height) = @getimagesize($tmpName);
    if (!$width || !$height) {
        return move_uploaded_file($tmpName, $targetPath);
    }

    $img = null;
    switch ($fileExtension) {
        case 'jpg':
        case 'jpeg':
            $img = @imagecreatefromjpeg($tmpName);
            break;
        case 'png':
            $img = @imagecreatefrompng($tmpName);
            break;
        case 'webp':
            $img = @imagecreatefromwebp($tmpName);
            break;
        default:
            return move_uploaded_file($tmpName, $targetPath);
    }

    if (!$img) {
        return move_uploaded_file($tmpName, $targetPath);
    }

    // Преоразмеряване, ако ширината надвишава $maxWidth
    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = (int)($height * ($maxWidth / $width));
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Запазване на прозрачността за PNG и WEBP
        if (in_array($fileExtension, ['png', 'webp'])) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($img);
        $img = $resized;
    }

    // Запазване с компресия
    $success = false;
    if (in_array($fileExtension, ['jpg', 'jpeg'])) {
        $success = imagejpeg($img, $targetPath, $quality);
    } elseif ($fileExtension === 'png') {
        $pngQuality = (int)((100 - $quality) / 10); // Скала 0-9 за PNG
        $success = imagepng($img, $targetPath, $pngQuality);
    } elseif ($fileExtension === 'webp') {
        $success = imagewebp($img, $targetPath, $quality);
    } else {
        $success = move_uploaded_file($tmpName, $targetPath);
    }

    imagedestroy($img);
    return $success;
}

// ================= ОБРАБОТКА НА РЕДАКЦИЯТА (EDIT REWORK) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_rework') {
    $reworkId = intval($_POST['rework_id']);
    $reportedDate = $_POST['reported_date'] ?? '';
    $completionDate = $_POST['completion_date'] ?? '';
    $shortNote = trim($_POST['short_note'] ?? '');
    $technician = trim($_POST['technician'] ?? 'Георги Терзиев');
    $priority = $_POST['priority'] ?? 'normal';

    if ($reportedDate && $completionDate && strtotime($completionDate) < strtotime($reportedDate)) {
        $queryStr = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
        header('Location: all_reworks.php' . $queryStr . (empty($queryStr) ? '?' : '&') . 'err=' . urlencode('Крайната дата не може да бъде преди датата на докладване!'));
        exit;
    }

    $stmt = $conn->prepare("UPDATE reworks SET reported_date = ?, rework_end_date = ?, short_note = ?, technician = ?, priority = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $reportedDate, $completionDate, $shortNote, $technician, $priority, $reworkId);
    $stmt->execute();
    $stmt->close();

    $queryStr = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: all_reworks.php' . $queryStr . (empty($queryStr) ? '?' : '&') . 'msg=' . urlencode('Записът беше обновен успешно!'));
    exit;
}

// ================= ОБРАБОТКА НА КАЧВАНЕТО НА ФАЙЛОВЕ ОТ МОДАЛА =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rework_id']) && !isset($_POST['action'])) {
    $reworkId = intval($_POST['rework_id']);

    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        $uploadDir = 'uploads/reworks/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
        $maxFileSize = 15 * 1024 * 1024; // 15MB

        $totalFiles = count($_FILES['attachments']['name']);
        $uploadedCount = 0;
        $errors = [];

        for ($i = 0; $i < $totalFiles; $i++) {
            $fileError = $_FILES['attachments']['error'][$i];
            
            if ($fileError === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['attachments']['tmp_name'][$i];
                $originalName = $_FILES['attachments']['name'][$i];
                $fileSize = $_FILES['attachments']['size'][$i];
                $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                if (!in_array($fileExtension, $allowedExtensions)) {
                    $errors[] = "Файлът " . h($originalName) . " е с непозволен формат ($fileExtension).";
                    continue;
                }

                if ($fileSize > $maxFileSize) {
                    $errors[] = "Файлът " . h($originalName) . " надвишава максималния размер от 15MB.";
                    continue;
                }

                $newFileName = 'rework_' . $reworkId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
                $targetPath = $uploadDir . $newFileName;

                // Ако е снимка, я компресираме; иначе я местим директно
                $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'webp']);
                $saved = $isImage 
                    ? compressAndResizeImage($tmpName, $targetPath, $fileExtension) 
                    : move_uploaded_file($tmpName, $targetPath);

                if ($saved) {
                    $stmt = $conn->prepare("INSERT INTO rework_attachments (rework_id, file_name, original_name) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $reworkId, $newFileName, $originalName);
                    $stmt->execute();
                    $stmt->close();
                    $uploadedCount++;
                }
            }
        }

        $queryStr = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
        if (!empty($errors)) {
            $errMessage = implode(' ', $errors);
            header('Location: all_reworks.php' . $queryStr . (empty($_SERVER['QUERY_STRING']) ? '?' : '&') . 'err=' . urlencode($errMessage));
        } else {
            header('Location: all_reworks.php' . $queryStr . (empty($_SERVER['QUERY_STRING']) ? '?' : '&') . 'msg=' . urlencode("Успешно качени файлове: $uploadedCount"));
        }
        exit;
    }
}

// Параметри за филтриране и пагинация
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$whereClauses = ["1=1"];
$params = [];
$types = "";

if ($startDate !== '') {
    $whereClauses[] = "r.reported_date >= ?";
    $params[] = $startDate;
    $types .= "s";
}

if ($endDate !== '') {
    $whereClauses[] = "r.rework_end_date <= ?";
    $params[] = $endDate;
    $types .= "s";
}

if ($priorityFilter !== '') {
    $whereClauses[] = "r.priority = ?";
    $params[] = $priorityFilter;
    $types .= "s";
}

if ($searchQuery !== '') {
    $whereClauses[] = "(m.mold_code LIKE ? OR r.technician LIKE ? OR r.short_note LIKE ? OR r.reason_notes LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ssss";
}

$whereSql = implode(" AND ", $whereClauses);

// Общ брой записи за пагинация
$countSql = "SELECT COUNT(*) as total FROM reworks r JOIN molds m ON m.id = r.mold_id WHERE {$whereSql}";
$stmtCount = $conn->prepare($countSql);
if (!empty($params)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$totalRows = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$stmtCount->close();

// Вземане на данните
$sql = "SELECT r.*, m.mold_code, m.serial_number,
        (SELECT COUNT(*) FROM rework_attachments WHERE rework_id = r.id) as file_count 
        FROM reworks r 
        JOIN molds m ON m.id = r.mold_id 
        WHERE {$whereSql}
        ORDER BY r.created_at DESC 
        LIMIT ? OFFSET ?";

$paramsWithLimit = $params;
$paramsWithLimit[] = $limit;
$paramsWithLimit[] = $offset;
$typesWithLimit = $types . "ii";

$stmt = $conn->prepare($sql);
if (!empty($paramsWithLimit)) {
    $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rework History - Mold Tracking System</title>
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
            backdrop-filter: blur(2px);
        }
        .modal-content {
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            width: 100%;
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
            border: none;
            cursor: pointer;
        }
        .badge-view { background: #e0f2fe; color: #0369a1; }
        .badge-view:hover { background: #bae6fd; }
        
        .badge-upload { background: #f1f5f9; color: #475569; }
        .badge-upload:hover { background: #e2e8f0; }

        .badge-edit { background: #fef3c7; color: #d97706; }
        .badge-edit:hover { background: #fde68a; }

        .badge-delete { background: #fee2e2; color: #dc2626; }
        .badge-delete:hover { background: #fecaca; }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-normal { background: transparent; color: #475569; font-weight: 400; }
        .priority-low { background: #f1f5f9; color: #475569; }
        .priority-urgent { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        /* DRAG & DROP ZONE */
        .drag-drop-zone {
            border: 2px dashed #94a3b8;
            border-radius: 10px;
            padding: 20px 15px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 15px;
        }
        .drag-drop-zone.dragover {
            border-color: #0284c7;
            background: #e0f2fe;
        }

        .upload-options-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
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
        }
        .upload-option-card:hover {
            border-color: #0284c7;
            background: #f0f9ff;
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding: 10px 0;
        }
        .pagination-links { display: flex; gap: 5px; }
        .pagination-links a, .pagination-links span {
            padding: 6px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            text-decoration: none;
            color: #334155;
            font-size: 0.9rem;
        }
        .pagination-links .active {
            background: var(--primary-blue, #0284c7);
            color: white;
            border-color: var(--primary-blue, #0284c7);
        }

        /* LIGHTBOX STYLES */
        .lightbox-img-preview {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid #cbd5e1;
            transition: transform 0.2s;
        }
        .lightbox-img-preview:hover {
            transform: scale(1.08);
        }
    </style>
</head>
<body>

<header class="header-container">
    <div class="header-brand-container">
        <a href="index.php" class="logo-link" title="Go to Main Dashboard">
            <img src="logo-ottobock 3.png" alt="Ottobock Logo" class="logo-img">
        </a>
        <h1 class="header-title">⚙️ Rework History</h1>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="index.php" class="btn secondary">← Back to Dashboard</a>
    </div>
</header>

<main>

<?php if (isset($_GET['msg'])): ?>
    <div class="flash" style="padding: 10px; background: #dcfce7; color: #166534; margin-bottom: 15px; border-radius: 6px;"><?= h($_GET['msg']) ?></div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
    <div class="flash" style="padding: 10px; background: #fee2e2; color: #991b1b; margin-bottom: 15px; border-radius: 6px;"><?= h($_GET['err']) ?></div>
<?php endif; ?>

<div class="card filter-card">
    <form method="GET" action="all_reworks.php" class="filter-form" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
        <div class="filter-group">
            <label for="search">Търсене:</label>
            <input type="text" id="search" name="search" placeholder="Код, техник, бележка..." value="<?= h($searchQuery) ?>" class="form-control" style="padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px;">
        </div>

        <div class="filter-group">
            <label for="start_date">From Date:</label>
            <input type="date" id="start_date" name="start_date" value="<?= h($startDate) ?>">
        </div>
        
        <div class="filter-group">
            <label for="end_date">To Date:</label>
            <input type="date" id="end_date" name="end_date" value="<?= h($endDate) ?>">
        </div>

        <div class="filter-group">
            <label for="priority">Priority:</label>
            <select id="priority" name="priority" class="form-control" style="padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px;">
                <option value="">All Priorities</option>
                <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Low</option>
                <option value="normal" <?= $priorityFilter === 'normal' ? 'selected' : '' ?>>normal</option>
                <option value="High" <?= $priorityFilter === 'High' ? 'selected' : '' ?>>High</option>
                <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn">🔍 Filter</button>
            <?php if ($startDate || $endDate || $priorityFilter || $searchQuery): ?>
                <a href="all_reworks.php" class="btn secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th class="nowrap-cell">Date & Time</th>
                    <th class="nowrap-cell">Mold Code</th>
                    <th class="nowrap-cell">Reported Date</th>
                    <th class="nowrap-cell">Completion Date</th>
                    <th>Technician</th>
                    <th class="nowrap-cell">Priority</th>
                    <th>Short Note & Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($r = $result->fetch_assoc()): 
                    $moldCode = $r['mold_code'] ?? '';
                    $technician = $r['technician'] ?? '';
                    $shortNote = $r['short_note'] ?? ($r['reason_notes'] ?? '');
                    $priority = $r['priority'] ?? 'normal';
                    $fileCount = intval($r['file_count']);

                    $pClass = 'priority-normal';
                    $pLabel = strtolower($priority);
                    if (strtolower($priority) === 'high') {
                        $pClass = 'priority-high';
                        $pLabel = 'High';
                    } elseif (strtolower($priority) === 'urgent') {
                        $pClass = 'priority-urgent';
                        $pLabel = 'Urgent';
                    } elseif (strtolower($priority) === 'low') {
                        $pClass = 'priority-low';
                        $pLabel = 'Low';
                    }
                ?>
                    <tr>
                        <td class="nowrap-cell"><span class="timestamp">⏱️ <?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></span></td>
                        <td class="nowrap-cell">
                            <a href="mold_history.php?id=<?= $r['mold_id'] ?>" style="color:var(--primary-blue); font-weight:700;">
                                <?= h($moldCode) ?>
                            </a>
                        </td>
                        <td class="nowrap-cell"><?= $r['reported_date'] ? date('d.m.Y', strtotime($r['reported_date'])) : '—' ?></td>
                        <td class="nowrap-cell"><?= $r['rework_end_date'] ? date('d.m.Y', strtotime($r['rework_end_date'])) : '—' ?></td>
                        <td><?= h($technician ?: '—') ?></td>
                        <td class="nowrap-cell">
                            <span class="priority-badge <?= $pClass ?>"><?= h($pLabel) ?></span>
                        </td>
                        <td>
                            <div class="note-attachment-container" style="display: flex; flex-direction: column; gap: 8px;">
                                <span class="short-note-text"><?= h($shortNote ?: '—') ?></span>
                                
                                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <button type="button" onclick="openFilesModal(<?= $r['id'] ?>, '<?= h($moldCode) ?>')" class="action-badge badge-view">
                                        📂 View Files (<?= $fileCount ?>)
                                    </button>
                                    <button type="button" onclick="openUploadModal(<?= $r['id'] ?>)" class="action-badge badge-upload">
                                        ➕ Add Files
                                    </button>
                                    <button type="button" onclick='openEditModal(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="action-badge badge-edit">
                                        ✏️ Edit
                                    </button>
                                    <a href="delete_rework.php?id=<?= $r['id'] ?>" onclick="return confirm('Are you sure you want to delete this entire rework entry?');" class="action-badge badge-delete">
                                        🗑️ Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center; color:var(--muted); padding:20px;">No rework entries found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Пагинация -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <span style="font-size: 0.9rem; color: #64748b;">
                Показване на <?= $offset + 1 ?>–<?= min($offset + $limit, $totalRows) ?> от общо <?= $totalRows ?> записа
            </span>
            <div class="pagination-links">
                <?php 
                $queryParams = $_GET;
                for ($p = 1; $p <= $totalPages; $p++): 
                    $queryParams['page'] = $p;
                    $link = 'all_reworks.php?' . http_build_query($queryParams);
                ?>
                    <a href="<?= $link ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

</main>

<!-- МОДАЛЕН ПРОЗОРЕЦ ЗА РЕДАКЦИЯ -->
<div id="editReworkModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;">✏️ Редакция на Rework</h3>
            <button type="button" onclick="closeEditModal()" style="background:none; border:none; font-size: 1.2rem; cursor:pointer;">✕</button>
        </div>

        <form method="POST" action="all_reworks.php<?= $_SERVER['QUERY_STRING'] ? '?' . h($_SERVER['QUERY_STRING']) : '' ?>" onsubmit="return validateEditForm()">
            <input type="hidden" name="action" value="edit_rework">
            <input type="hidden" name="rework_id" id="editReworkId">

            <div style="display: flex; gap: 10px; margin-bottom: 12px;">
                <div style="flex: 2;">
                    <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 5px;">Техник:</label>
                    <input type="text" name="technician" id="editTechnician" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 5px;">Приоритет:</label>
                    <select name="priority" id="editPriority" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                        <option value="low">Low</option>
                        <option value="normal">normal</option>
                        <option value="High">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-bottom: 12px;">
                <div style="flex: 1;">
                    <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 5px;">Дата на докладване:</label>
                    <input type="date" name="reported_date" id="editReportedDate" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 5px;">Крайна дата:</label>
                    <input type="date" name="completion_date" id="editCompletionDate" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 5px;">Описание / Бележка:</label>
                <textarea name="short_note" id="editShortNote" rows="3" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn secondary" onclick="closeEditModal()" style="padding: 6px 14px;">Отказ</button>
                <button type="submit" class="btn" style="padding: 6px 16px;">Запази промените</button>
            </div>
        </form>
    </div>
</div>

<!-- МОДАЛЕН ПРОЗОРЕЦ ЗА ПРЕГЛЕД НА ФАЙЛОВЕТЕ -->
<div id="viewFilesModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;" id="modalFilesTitle">📁 Attached Files</h3>
            <button type="button" onclick="closeViewModal()" style="background:none; border:none; font-size: 1.2rem; cursor:pointer;">✕</button>
        </div>
        <div id="modalFilesList" style="margin-bottom: 20px; max-height: 350px; overflow-y: auto;">
            <p style="color: #64748b; text-align: center;">Loading...</p>
        </div>
        <div style="text-align: right;">
            <button type="button" class="btn secondary" onclick="closeViewModal()" style="padding: 6px 14px;">Close</button>
        </div>
    </div>
</div>

<!-- МОДАЛЕН ПРОЗОРЕЦ ЗА LIGHTBOX (ПРЕГЛЕД НА СНИМКА В ГОЛЯМ РАЗМЕР) -->
<div id="lightboxModal" class="modal-overlay" onclick="closeLightbox()">
    <div style="position: relative; max-width: 90vw; max-height: 90vh;" onclick="event.stopPropagation();">
        <img id="lightboxImg" src="" style="max-width: 100%; max-height: 85vh; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: block;">
        <button type="button" onclick="closeLightbox()" style="position: absolute; top: -12px; right: -12px; background: #ffffff; color: #000; border: none; border-radius: 50%; width: 30px; height: 30px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.4);">✕</button>
    </div>
</div>

<!-- МОДАЛЕН ПРОЗОРЕЦ ЗА КАЧВАНЕ С DRAG & DROP -->
<div id="uploadModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;">Прикачване на файлове</h3>
            <button type="button" onclick="closeUploadModal()" style="background:none; border:none; font-size: 1.2rem; cursor:pointer;">✕</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="uploadForm" onsubmit="return prepareFormBeforeSubmit(event)">
            <input type="hidden" name="rework_id" id="uploadReworkId">

            <!-- DRAG AND DROP ЗОНА -->
            <div id="dragDropZone" class="drag-drop-zone">
                <span style="font-size: 32px; display: block; margin-bottom: 6px;">📥</span>
                <span style="font-weight: 600; color: #1e293b; font-size: 0.95rem;">Плъзнете файлове тук</span>
                <span style="display: block; font-size: 0.8rem; color: #64748b; margin-top: 4px;">или използвайте бутоните по-долу</span>
            </div>

            <div class="upload-options-grid">
                <label for="fileInputCamera" class="upload-option-card">
                    <span style="font-size: 20px;">📸</span>
                    <span style="font-size: 0.8rem; font-weight:600;">Снимай</span>
                </label>
                <input type="file" id="fileInputCamera" accept="image/*" capture="environment" style="display: none;">

                <label for="fileInputGallery" class="upload-option-card">
                    <span style="font-size: 20px;">🖼️</span>
                    <span style="font-size: 0.8rem; font-weight:600;">Галерия</span>
                </label>
                <input type="file" id="fileInputGallery" accept="image/*" multiple style="display: none;">

                <label for="fileInputAny" class="upload-option-card">
                    <span style="font-size: 20px;">📁</span>
                    <span style="font-size: 0.8rem; font-weight:600;">Файл</span>
                </label>
                <input type="file" id="fileInputAny" multiple style="display: none;">
            </div>

            <input type="file" id="finalFilesInput" name="attachments[]" multiple style="display: none;">

            <div id="selectedFilesPreview" style="margin-top: 15px; font-size: 0.85rem; color: #475569; background: #f8fafc; padding: 10px; border-radius: 6px; display: none;"></div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn secondary" onclick="closeUploadModal()" style="padding: 6px 14px;">Отказ</button>
                <button type="submit" class="btn" style="padding: 6px 16px;">Качи файловете</button>
            </div>
        </form>
    </div>
</div>

<script>
let dt = new DataTransfer();

function handleFilesSelection(e) {
    let files = e.target.files;
    for (let i = 0; i < files.length; i++) {
        dt.items.add(files[i]);
    }
    updateFilesPreview();
    e.target.value = '';
}

document.getElementById('fileInputCamera').addEventListener('change', handleFilesSelection);
document.getElementById('fileInputGallery').addEventListener('change', handleFilesSelection);
document.getElementById('fileInputAny').addEventListener('change', handleFilesSelection);

// DRAG AND DROP ЛОГИКА
let dropZone = document.getElementById('dragDropZone');

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.add('dragover');
    }, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('dragover');
    }, false);
});

dropZone.addEventListener('drop', (e) => {
    let files = e.dataTransfer.files;
    for (let i = 0; i < files.length; i++) {
        dt.items.add(files[i]);
    }
    updateFilesPreview();
});

dropZone.addEventListener('click', () => {
    document.getElementById('fileInputAny').click();
});

function removeSelectedFile(index) {
    let newDt = new DataTransfer();
    for (let i = 0; i < dt.files.length; i++) {
        if (i !== index) {
            newDt.items.add(dt.files[i]);
        }
    }
    dt = newDt;
    updateFilesPreview();
}

function updateFilesPreview() {
    let previewContainer = document.getElementById('selectedFilesPreview');
    if (dt.files.length > 0) {
        let html = '<b>Избрани файлове (' + dt.files.length + '):</b><ul style="margin: 5px 0 0 0; padding:0; list-style:none;">';
        for (let i = 0; i < dt.files.length; i++) {
            html += `<li style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; background:#fff; padding:4px 8px; border-radius:4px; border:1px solid #e2e8f0;">
                <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:300px;">📄 ${escapeHtml(dt.files[i].name)}</span>
                <button type="button" onclick="removeSelectedFile(${i})" style="background:none; border:none; color:#dc2626; cursor:pointer; font-weight:bold; padding:0 4px;">✕</button>
            </li>`;
        }
        html += '</ul>';
        previewContainer.innerHTML = html;
        previewContainer.style.display = 'block';
    } else {
        previewContainer.innerHTML = '';
        previewContainer.style.display = 'none';
    }
}

function prepareFormBeforeSubmit(e) {
    if (dt.files.length === 0) {
        alert('Моля, изберете или плъзнете поне един файл за качване.');
        return false;
    }
    document.getElementById('finalFilesInput').files = dt.files;
    return true;
}

function validateEditForm() {
    let reported = document.getElementById('editReportedDate').value;
    let completion = document.getElementById('editCompletionDate').value;

    if (reported && completion && completion < reported) {
        alert('Крайната дата не може да бъде преди датата на докладване!');
        return false;
    }
    return true;
}

function openEditModal(rework) {
    document.getElementById('editReworkId').value = rework.id;
    document.getElementById('editTechnician').value = rework.technician || 'Георги Терзиев';
    document.getElementById('editPriority').value = rework.priority || 'normal';
    document.getElementById('editReportedDate').value = rework.reported_date || '';
    document.getElementById('editCompletionDate').value = rework.rework_end_date || '';
    document.getElementById('editShortNote').value = rework.short_note || rework.reason_notes || '';
    document.getElementById('editReworkModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editReworkModal').style.display = 'none';
}

function openUploadModal(reworkId) {
    document.getElementById('uploadReworkId').value = reworkId;
    dt = new DataTransfer();
    updateFilesPreview();
    document.getElementById('uploadModal').style.display = 'flex';
}

function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}

// ПРЕГЛЕД НА ФАЙЛОВЕ С LIGHTBOX И ПРЕВЮ ЗА СНИМКИ
function openFilesModal(reworkId, moldCode) {
    document.getElementById('modalFilesTitle').innerText = '📁 Files for Mold: ' + moldCode;
    let listContainer = document.getElementById('modalFilesList');
    listContainer.innerHTML = '<p style="color: #64748b; text-align: center;">Loading...</p>';
    document.getElementById('viewFilesModal').style.display = 'flex';

    fetch('get_files.php?rework_id=' + reworkId)
        .then(response => response.json())
        .then(data => {
            if (!data || data.length === 0) {
                listContainer.innerHTML = '<p style="color: #64748b; text-align: center; padding: 15px;">No files attached yet.</p>';
                return;
            }

            let html = '';
            data.forEach(file => {
                let displayName = file.original_name ? file.original_name : file.file_name;
                let filePath = 'uploads/reworks/' + encodeURIComponent(file.file_name);
                let ext = file.file_name.split('.').pop().toLowerCase();
                let isImg = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);

                html += `
                    <div class="file-item">
                        <div style="display: flex; align-items: center; gap: 10px; overflow: hidden;">
                            ${isImg 
                                ? `<img src="${filePath}" class="lightbox-img-preview" onclick="openLightbox('${filePath}')" alt="Preview">` 
                                : '<span style="font-size: 1.5rem;">📄</span>'
                            }
                            <span style="font-size: 0.88rem; word-break: break-all; max-width: 220px; font-weight: 500;">${escapeHtml(displayName)}</span>
                        </div>
                        <div style="display: flex; gap: 6px; align-items: center;">
                            ${isImg 
                                ? `<button type="button" onclick="openLightbox('${filePath}')" class="action-badge badge-view" style="padding: 4px 8px;">View</button>` 
                                : `<a href="${filePath}" target="_blank" class="action-badge badge-view" style="padding: 4px 8px; text-decoration:none;">Open</a>`
                            }
                            <a href="delete_file.php?id=${file.id}" onclick="return confirm('Are you sure you want to delete this file?');" class="action-badge" style="background: #fee2e2; color: #dc2626; padding: 4px 8px; text-decoration:none;">Delete</a>
                        </div>
                    </div>
                `;
            });
            listContainer.innerHTML = html;
        })
        .catch(error => {
            listContainer.innerHTML = '<p style="color: #ef4444; text-align: center;">Error loading files.</p>';
        });
}

function closeViewModal() {
    document.getElementById('viewFilesModal').style.display = 'none';
}

function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxModal').style.display = 'flex';
}

function closeLightbox() {
    document.getElementById('lightboxModal').style.display = 'none';
}

function escapeHtml(text) {
    let map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

window.onclick = function(event) {
    let modalUpload = document.getElementById('uploadModal');
    let modalView = document.getElementById('viewFilesModal');
    let modalEdit = document.getElementById('editReworkModal');
    if (event.target === modalUpload) closeUploadModal();
    if (event.target === modalView) closeViewModal();
    if (event.target === modalEdit) closeEditModal();
}
</script>

</body>
</html>