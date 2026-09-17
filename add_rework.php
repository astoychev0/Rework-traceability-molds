<?php
require 'config.php';

$mold = '';
$serial_number = '';
$mold_id = 0;

if (isset($_GET['mold_id'])) {
    $mold_id = (int)$_GET['mold_id'];
    $stmt = $conn->prepare("SELECT mold_code, serial_number FROM molds WHERE id = ?");
    $stmt->bind_param('i', $mold_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $mold = $res['mold_code'];
        $serial_number = $res['serial_number'];
    }
    $stmt->close();
} elseif (isset($_GET['mold'])) {
    $mold = trim($_GET['mold']);
    $stmt = $conn->prepare("SELECT id, serial_number FROM molds WHERE mold_code = ?");
    $stmt->bind_param('s', $mold);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $mold_id = $res['id'];
        $serial_number = $res['serial_number'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Нов Rework | Mold Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        .top-header-bar {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
        }
        .logo-link {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: opacity 0.2s;
        }
        .logo-link:hover { opacity: 0.85; }
        .logo-img { height: 32px; width: auto; display: block; }
        .gear-icon { font-size: 18px; opacity: 0.8; }
        .system-title-link {
            color: #002554;
            font-size: 1.15rem;
            font-weight: 700;
            text-decoration: none;
        }
        .system-title-link:hover { text-decoration: underline; }
        .rework-page-title { color: #0284c7; font-size: 1rem; font-weight: 600; }
        .plus-icon { font-weight: 700; }
        .header-blue-line { height: 3px; background-color: #0066cc; width: 100%; }
        .card-custom {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            background-color: #ffffff;
        }
        .btn-add-files {
            background-color: #ffffff;
            color: #4b38b3;
            border: 1.5px solid #4b38b3;
            border-radius: 6px;
            padding: 6px 14px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .btn-add-files:hover { background-color: #4b38b3; color: #ffffff; }
        .upload-option-card {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background-color: #f8fafc;
        }
        .upload-option-card:hover {
            border-color: #4b38b3;
            background-color: #f0edff;
        }
        
        /* Drag & Drop Зона */
        .drag-drop-zone {
            border: 2px dashed #4b38b3;
            border-radius: 10px;
            background-color: #f8fafc;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s;
        }
        .drag-drop-zone.dragover {
            background-color: #e0e7ff;
            border-color: #312e81;
        }

        .file-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 6px;
        }
        .file-thumb {
            width: 36px;
            height: 36px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ced4da;
            cursor: pointer;
            transition: transform 0.15s;
        }
        .file-thumb:hover { transform: scale(1.1); }

        .toggle-chip {
            background-color: #f1f5f9;
            border: 1.5px solid #cbd5e1;
            color: #334155;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }
        .toggle-chip:hover { background-color: #e2e8f0; border-color: #94a3b8; }
        .toggle-chip.active { background-color: #4b38b3; border-color: #4b38b3; color: #ffffff; }
        .toggle-chip.priority-high.active {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: #ffffff !important;
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);
        }
    </style>
</head>
<body>

<div class="top-header-bar mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between py-2">
            <div class="d-flex align-items-center gap-3">
                <a href="index.php" class="logo-link" title="Main Dashboard">
                    <img src="logo-ottobock 3.png" alt="Ottobock Logo" class="logo-img">
                </a>
                <span class="gear-icon">⚙️</span>
                <div class="d-flex align-items-center gap-2">
                    <a href="index.php" class="system-title-link">Mold Tracking System</a>
                    <span class="text-muted fw-bold">/</span>
                    <span class="rework-page-title">
                        <span class="plus-icon">+</span> Нов Rework 
                        <?php if(!empty($mold)): ?> <span class="text-secondary">(<?= htmlspecialchars($mold) ?><?php if(!empty($serial_number)) echo ' - SN: ' . htmlspecialchars($serial_number); ?>)</span> <?php endif; ?>
                    </span>
                </div>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-2">← Назад към списъка</a>
            </div>
        </div>
    </div>
    <div class="header-blue-line"></div>
</div>

<div class="container py-2" style="max-width: 850px;">
    <div class="card card-custom p-4">
        <h5 class="fw-bold mb-4" style="color: #1e293b;">Детайли за ремонта</h5>
        
        <form action="save_rework.php" method="POST" enctype="multipart/form-data" id="reworkForm">
            <input type="hidden" name="mold_id" value="<?php echo (int)$mold_id; ?>">
            <input type="hidden" name="mold_code" value="<?php echo htmlspecialchars($mold); ?>">

            <div class="mb-3">
                <label class="form-label fw-semibold">Техник (Technician):</label>
                <input type="text" name="technician" class="form-control bg-light text-dark fw-semibold" value="Георги Терзиев" readonly>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Приоритет (Priority):</label>
                <div class="d-flex flex-wrap gap-2 mb-1">
                    <button type="button" class="toggle-chip active" id="chipPriorityNormal" onclick="setPriority('Нормален')">Нормален</button>
                    <button type="button" class="toggle-chip priority-high" id="chipPriorityHigh" onclick="setPriority('Priority')">🔴 Висок (Priority)</button>
                </div>
                <input type="hidden" name="priority" id="priorityInput" value="Нормален">
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Дата на докладване:</label>
                    <input type="date" name="reported_date" id="reportedDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Крайна дата на ремонта:</label>
                    <input type="date" name="completion_date" id="completionDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Кратка бележка / Описание:</label>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <button type="button" class="toggle-chip" id="chipTop" onclick="setNoteText('Горен капак')">Горен капак</button>
                    <button type="button" class="toggle-chip" id="chipBottom" onclick="setNoteText('Долен капак')">Долен капак</button>
                    <button type="button" class="toggle-chip" id="chipBoth" onclick="setNoteText('Долен и горен капак')">Долен и горен капак</button>
                </div>
                <textarea name="short_note" id="shortNoteTextarea" class="form-control" rows="3" placeholder="Изберете капак от бутоните по-горе или въведете описание ръчно..."></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold d-block">Прикачване на снимки / файлове:</label>
                
                <button type="button" class="btn-add-files" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add Files
                </button>

                <input type="file" id="inputCamera" capture="environment" accept="image/*" style="display: none;" onchange="handleFilesSelected(this.files)">
                <input type="file" id="inputGallery" accept="image/*" multiple style="display: none;" onchange="handleFilesSelected(this.files)">
                <input type="file" id="inputFile" multiple style="display: none;" onchange="handleFilesSelected(this.files)">

                <div class="mt-3 p-3 bg-light rounded border" id="uploadedFilesContainer" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="text-dark" style="font-size: 14px;">📁 Прикачени файлове (<span id="fileCount">0</span>):</strong>
                    </div>
                    <div id="fileList"></div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="index.php" class="btn btn-light px-4">Отказ</a>
                <button type="submit" id="submitBtn" class="btn btn-primary px-4" style="background-color: #4b38b3; border-color: #4b38b3;">
                    <span id="submitText">Запази Rework</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Модал за избор на файл + Drag & Drop Зона -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="uploadModalLabel">Изберете или пуснете файлове</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Drag & Drop Зона -->
                <div class="drag-drop-zone mb-3" id="dropZone">
                    <div style="font-size: 32px;">📥</div>
                    <div class="fw-bold mt-1" style="font-size: 14px;">Плъзнете и пуснете файлове тук</div>
                    <small class="text-muted">или използвайте опциите по-долу</small>
                </div>

                <div class="row g-3">
                    <div class="col-4">
                        <div class="upload-option-card" onclick="triggerInput('inputCamera')">
                            <div style="font-size: 28px;">📸</div>
                            <div class="fw-bold mt-2" style="font-size: 13px;">Снимай</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="upload-option-card" onclick="triggerInput('inputGallery')">
                            <div style="font-size: 28px;">🖼️</div>
                            <div class="fw-bold mt-2" style="font-size: 13px;">Галерия</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="upload-option-card" onclick="triggerInput('inputFile')">
                            <div style="font-size: 28px;">📁</div>
                            <div class="fw-bold mt-2" style="font-size: 13px;">Файл</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox Модал за преглед на снимки в пълен размер -->
<div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body text-center position-relative p-0">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" style="z-index: 1056;"></button>
                <img id="lightboxImage" src="" class="img-fluid rounded shadow" style="max-height: 85vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedFiles = [];

function setPriority(level) {
    document.getElementById('priorityInput').value = level;
    document.getElementById('chipPriorityNormal').classList.remove('active');
    document.getElementById('chipPriorityHigh').classList.remove('active');
    if (level === 'Нормален') {
        document.getElementById('chipPriorityNormal').classList.add('active');
    } else if (level === 'Priority') {
        document.getElementById('chipPriorityHigh').classList.add('active');
    }
}

function setNoteText(textValue) {
    const textarea = document.getElementById('shortNoteTextarea');
    textarea.value = textValue;
    document.getElementById('chipTop').classList.remove('active');
    document.getElementById('chipBottom').classList.remove('active');
    document.getElementById('chipBoth').classList.remove('active');
    if (textValue === 'Горен капак') {
        document.getElementById('chipTop').classList.add('active');
    } else if (textValue === 'Долен капак') {
        document.getElementById('chipBottom').classList.add('active');
    } else if (textValue === 'Долен и горен капак') {
        document.getElementById('chipBoth').classList.add('active');
    }
}

function triggerInput(inputId) {
    document.getElementById(inputId).click();
    closeUploadModal();
}

function closeUploadModal() {
    const modalEl = document.getElementById('uploadModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

function handleFilesSelected(files) {
    for (let file of files) {
        selectedFiles.push(file);
    }
    renderFileList();
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    renderFileList();
}

function openLightbox(imageSrc) {
    document.getElementById('lightboxImage').src = imageSrc;
    const lightboxModal = new bootstrap.Modal(document.getElementById('lightboxModal'));
    lightboxModal.show();
}

function renderFileList() {
    const container = document.getElementById('uploadedFilesContainer');
    const fileList = document.getElementById('fileList');
    const fileCount = document.getElementById('fileCount');

    fileList.innerHTML = '';
    fileCount.innerText = selectedFiles.length;

    if (selectedFiles.length === 0) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'block';

    selectedFiles.forEach((file, index) => {
        const row = document.createElement('div');
        row.className = 'file-item-row';
        
        let filePreviewHtml = `<span class="me-2 fs-5">📄</span>`;
        if (file.type.startsWith('image/')) {
            const objectUrl = URL.createObjectURL(file);
            filePreviewHtml = `<img src="${objectUrl}" class="file-thumb me-2" alt="Preview" onclick="openLightbox('${objectUrl}')">`;
        }

        row.innerHTML = `
            <div class="d-flex align-items-center">
                ${filePreviewHtml}
                <div>
                    <span class="fw-semibold text-truncate d-block" style="max-width: 230px; font-size: 13px;">${file.name}</span>
                    <small class="text-muted">(${(file.size / 1024).toFixed(1)} KB)</small>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="removeFile(${index})">✕</button>
        `;
        fileList.appendChild(row);
    });
}

// Drag & Drop функционалност
const dropZone = document.getElementById('dropZone');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
});

dropZone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    handleFilesSelected(files);
    closeUploadModal();
});

document.getElementById('reworkForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Обработка и запазване...
    `;

    try {
        const formData = new FormData(this);
        for (let i = 0; i < selectedFiles.length; i++) {
            formData.append('attachments[]', selectedFiles[i], selectedFiles[i].name);
        }

        const response = await fetch('save_rework.php', {
            method: 'POST',
            body: formData
        });

        if (response.redirected) {
            window.location.href = response.url;
        } else if (response.ok) {
            window.location.href = 'index.php?success=1';
        } else {
            alert('Грешка при запазването!');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span id="submitText">Запази Rework</span>';
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Грешка при връзката със сървъра.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span id="submitText">Запази Rework</span>';
    }
});
</script>

</body>
</html>