<?php
require 'config.php'; 

// ================= ОБРАБОТКА НА КАЧВАНЕТО НА ФАЙЛОВЕ ОТ МОДАЛА =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rework_id'])) {
    $reworkId = intval($_POST['rework_id']);
    $mold_id = intval($_POST['mold_id'] ?? 0);

    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        $uploadDir = 'uploads/reworks/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $totalFiles = count($_FILES['attachments']['name']);

        for ($i = 0; $i < $totalFiles; $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['attachments']['tmp_name'][$i];
                $originalName = $_FILES['attachments']['name'][$i];
                $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);
                
                $newFileName = 'rework_' . $reworkId . '_' . time() . '_' . $i . '.' . $fileExtension;
                $targetPath = $uploadDir . $newFileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $stmt = $conn->prepare("INSERT INTO rework_attachments (rework_id, file_name, original_name) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $reworkId, $newFileName, $originalName);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    header('Location: mold_history.php?id=' . $mold_id . '&msg=' . urlencode('Files uploaded successfully!'));
    exit;
}

$mold_id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM molds WHERE id = ?");
$stmt->bind_param('i', $mold_id);
$stmt->execute();
$mold = $stmt->get_result()->fetch_assoc();

if (!$mold) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mold - <?= h($mold['mold_code']) ?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    .modal-content {
        background: #ffffff;
        padding: 24px;
        border-radius: 8px;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        max-height: 85vh;
        overflow-y: auto;
    }
    .action-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 10px;
        border-radius: 4px;
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
        border-radius: 6px;
        margin-bottom: 8px;
    }
</style>
</head>
<body style="background-color: #f8fafc; color: #1e293b; font-family: system-ui, -apple-system, sans-serif; margin: 0; padding-bottom: 50px;">

<header class="header-container" style="background: #ffffff; padding: 15px 30px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div class="header-brand-container" style="display: flex; align-items: center; gap: 15px;">
        <a href="index.php" class="logo-link" title="Go to Main Dashboard">
            <img src="logo-ottobock 3.png" alt="Ottobock Logo" class="logo-img" style="height: 35px;">
        </a>
        <h1 class="header-title" style="font-size: 1.25rem; font-weight: 700; margin: 0; color: #0f172a;">🔍 Mold Details: <span style="color: #2563eb;"><?= h($mold['mold_code']) ?></span></h1>
    </div>
    
    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="index.php" class="btn secondary" style="background: #e2e8f0; color: #334155; font-size: 0.85rem; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600;">⬅️ Dashboard</a>
        <a href="add_rework.php?mold_id=<?= $mold_id ?>" class="btn" style="background: #10b981; color: #fff; font-size: 0.85rem; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600;">🔧 Add Rework</a>
        <a href="delete_action.php?type=mold&id=<?= $mold_id ?>" class="btn danger" style="background: #ef4444; color: #fff; font-size: 0.85rem; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600;" onclick="return confirm('Are you sure you want to delete this mold and all its history?');">❌ Delete Mold</a>
    </div>
</header>

<main style="max-width: 1100px; margin: 30px auto; padding: 0 20px;">

    <?php if (isset($_GET['msg'])): ?>
        <div class="flash" style="background: #d1fae5; color: #065f46; padding: 12px 18px; border-radius: 8px; margin-bottom: 25px; font-weight: 500; border: 1px solid #a7f3d0;"><?= h($_GET['msg']) ?></div>
    <?php endif; ?>

    <!-- General Information Card -->
    <div class="card" style="background: #ffffff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 30px; border: 1px solid #e2e8f0;">
        <h3 style="margin-top: 0; margin-bottom: 18px; font-size: 1.15rem; font-weight: 700; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">General Information</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
            <p style="margin: 0;"><strong>Mold Code:</strong> <span style="color: #475569;"><?= h($mold['mold_code']) ?></span></p>
            <p style="margin: 0;"><strong>Serial Number:</strong> <span style="color: #475569;"><?= h($mold['serial_number'] ?: 'N/A') ?></span></p>
            <p style="margin: 0;"><strong>Created At:</strong> <span style="color: #475569;"><?= date('d.m.Y H:i:s', strtotime($mold['created_at'])) ?></span></p>
        </div>
    </div>

    <!-- Rework History Card -->
    <div class="card" style="background: #ffffff; padding: 0; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e2e8f0;">
        <h3 style="padding: 20px 25px; margin: 0; border-bottom: 1px solid #e2e8f0; font-size: 1.15rem; font-weight: 700; color: #0f172a; background: #f8fafc;">Rework History</h3>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="background: #f1f5f9; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 14px 20px;">Entry Date</th>
                        <th style="padding: 14px 20px;">Reported Date</th>
                        <th style="padding: 14px 20px;">Completion Date</th>
                        <th style="padding: 14px 20px;">Technician</th>
                        <th style="padding: 14px 20px;">Short Note & Attachments</th>
                    </tr>
                </thead>
                <tbody style="font-size: 0.95rem; color: #334155;">
                <?php
                $reworksSql = "SELECT r.*, 
                               (SELECT COUNT(*) FROM rework_attachments WHERE rework_id = r.id) as file_count 
                               FROM reworks r 
                               WHERE r.mold_id = ? 
                               ORDER BY r.created_at DESC";
                $reworksStmt = $conn->prepare($reworksSql);
                $reworksStmt->bind_param('i', $mold_id);
                $reworksStmt->execute();
                $reworksRes = $reworksStmt->get_result();

                if ($reworksRes && $reworksRes->num_rows > 0):
                    while ($r = $reworksRes->fetch_assoc()):
                        $shortNote = $r['short_note'] ?? ($r['reason_notes'] ?? '');
                        $fileCount = intval($r['file_count']);
                ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                        <td style="padding: 16px 20px;"><span class="timestamp" style="color: #64748b;">⏱️ <?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></span></td>
                        <td style="padding: 16px 20px;"><?= $r['reported_date'] ? date('d.m.Y', strtotime($r['reported_date'])) : '—' ?></td>
                        <td style="padding: 16px 20px;"><?= !empty($r['rework_end_date']) ? date('d.m.Y', strtotime($r['rework_end_date'])) : '—' ?></td>
                        <td style="padding: 16px 20px; font-weight: 500;"><?= h($r['technician'] ?: '—') ?></td>
                        <td style="padding: 16px 20px;">
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <span class="short-note-text"><?= h($shortNote ?: '—') ?></span>
                                
                                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <button type="button" onclick="openFilesModal(<?= $r['id'] ?>, '<?= h($mold['mold_code']) ?>')" class="action-badge badge-view">
                                        📂 View Files (<?= $fileCount ?>)
                                    </button>
                                    <button type="button" onclick="openUploadModal(<?= $r['id'] ?>)" class="action-badge badge-upload">
                                        ➕ Add Files
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php 
                    endwhile;
                else:
                ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #94a3b8; padding: 40px; font-style: italic;">No reworks logged yet for this mold.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
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
        <div id="modalFilesList" style="margin-bottom: 20px; max-height: 300px; overflow-y: auto;">
            <p style="color: #64748b; text-align: center;">Loading...</p>
        </div>
        <div style="text-align: right;">
            <button type="button" class="btn secondary" onclick="closeViewModal()" style="padding: 6px 14px; background: #e2e8f0; border: none; border-radius: 6px; cursor: pointer;">Close</button>
        </div>
    </div>
</div>

<!-- МОДАЛЕН ПРОЗОРЕЦ ЗА КАЧВАНЕ НА НОВИ ФАЙЛОВЕ -->
<div id="uploadModal" class="modal-overlay">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;">📎 Upload Files / Photos</h3>
            <button type="button" onclick="closeUploadModal()" style="background:none; border:none; font-size: 1.2rem; cursor:pointer;">✕</button>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="rework_id" id="uploadReworkId">
            <input type="hidden" name="mold_id" value="<?= $mold_id ?>">

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem;">Select one or multiple files:</label>
                <input type="file" name="attachments[]" multiple required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn secondary" onclick="closeUploadModal()" style="padding: 6px 14px; background: #e2e8f0; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn" style="padding: 6px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer;">Upload Files</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUploadModal(reworkId) {
    document.getElementById('uploadReworkId').value = reworkId;
    document.getElementById('uploadModal').style.display = 'flex';
}

function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}

function openFilesModal(reworkId, moldCode) {
    document.getElementById('modalFilesTitle').innerText = '📁 Files for Mold: ' + moldCode;
    let listContainer = document.getElementById('modalFilesList');
    listContainer.innerHTML = '<p style="color: #64748b; text-align: center;">Loading...</p>';
    document.getElementById('viewFilesModal').style.display = 'flex';

    fetch('get_files.php?rework_id=' + reworkId)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                listContainer.innerHTML = '<p style="color: #64748b; text-align: center; padding: 15px;">No files attached yet.</p>';
                return;
            }

            let html = '';
            data.forEach(file => {
                let displayName = file.original_name ? file.original_name : file.file_name;
                html += `
                    <div class="file-item">
                        <span style="font-size: 0.9rem; word-break: break-all; max-width: 260px;">📄 ${displayName}</span>
                        <div style="display: flex; gap: 6px;">
                            <a href="uploads/reworks/${file.file_name}" target="_blank" class="action-badge badge-view" style="padding: 4px 8px; text-decoration:none;">View</a>
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

window.onclick = function(event) {
    let modalUpload = document.getElementById('uploadModal');
    let modalView = document.getElementById('viewFilesModal');
    if (event.target === modalUpload) closeUploadModal();
    if (event.target === modalView) closeViewModal();
}
</script>

</body>
</html>