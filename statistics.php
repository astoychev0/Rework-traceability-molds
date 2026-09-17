<?php
require 'config.php';

// ================= ОБРАБОТКА НА КАЧВАНЕТО НА МНОЖЕСТВО ФАЙЛОВЕ =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rework_id'])) {
    $reworkId = intval($_POST['rework_id']);

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

    // Запазване на филтрите при редирект след качване
    $queryStr = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: statistics.php' . $queryStr);
    exit;
}

if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// 1. Извличане на параметрите от филтъра
$fromDate = isset($_GET['from_date']) && $_GET['from_date'] !== '' ? $_GET['from_date'] : null;
$toDate = isset($_GET['to_date']) && $_GET['to_date'] !== '' ? $_GET['to_date'] : null;
$urgentFilter = isset($_GET['urgent_only']) ? $_GET['urgent_only'] : null;

// 2. Построяване на SQL условие за филтриране
$whereConditions = ["1=1"];

if ($fromDate) {
    $cleanFrom = $conn->real_escape_string($fromDate);
    $whereConditions[] = "(r.reported_date >= '{$cleanFrom}' OR r.created_at >= '{$cleanFrom}')";
}
if ($toDate) {
    $cleanTo = $conn->real_escape_string($toDate);
    $whereConditions[] = "(r.reported_date <= '{$cleanTo}' OR r.rework_end_date <= '{$cleanTo}')";
}
if ($urgentFilter === '1') {
    $whereConditions[] = "r.priority = 'high'";
}

$whereClause = implode(" AND ", $whereConditions);

// 3. Общ брой регистрирани матрици
$totalMolds = $conn->query("SELECT COUNT(*) AS total FROM molds")->fetch_assoc()['total'] ?? 0;

// 4. Филтриран брой ремонти (Общо и с висок приоритет)
$reworksQuery = $conn->query("SELECT COUNT(*) AS total FROM reworks r WHERE {$whereClause}");
$totalReworks = $reworksQuery ? ($reworksQuery->fetch_assoc()['total'] ?? 0) : 0;

// Брой ремонти с висок приоритет спрямо текущия филтър
$urgentReworksQuery = $conn->query("SELECT COUNT(*) AS total FROM reworks r WHERE {$whereClause} AND r.priority = 'high'");
$totalUrgentReworks = $urgentReworksQuery ? ($urgentReworksQuery->fetch_assoc()['total'] ?? 0) : 0;

// Ремонти за последните 30 дни
$reworks30Days = $conn->query("SELECT COUNT(*) AS total FROM reworks WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['total'] ?? 0;

// 5. Данни за графиката по месеци (Общо и High Priority)
$monthlyData = array_fill(1, 12, 0);
$monthlyUrgentData = array_fill(1, 12, 0);

$monthlySql = "
    SELECT MONTH(COALESCE(r.reported_date, r.created_at)) as month_num, 
           COUNT(*) as count,
           SUM(CASE WHEN r.priority = 'high' THEN 1 ELSE 0 END) as urgent_count
    FROM reworks r
    WHERE {$whereClause}
    GROUP BY month_num
";
$monthlyQuery = $conn->query($monthlySql);

if ($monthlyQuery) {
    while ($row = $monthlyQuery->fetch_assoc()) {
        $mNum = (int)$row['month_num'];
        if ($mNum >= 1 && $mNum <= 12) {
            $monthlyData[$mNum] = (int)$row['count'];
            $monthlyUrgentData[$mNum] = (int)$row['urgent_count'];
        }
    }
}

$monthsLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$chartCounts = array_values($monthlyData);
$chartUrgentCounts = array_values($monthlyUrgentData);

// 6. Топ 5 най-често ремонтирани матрици според филтъра
$topMoldsSql = "
    SELECT m.mold_code, m.serial_number, COUNT(r.id) as rework_count,
           SUM(CASE WHEN r.priority = 'high' THEN 1 ELSE 0 END) as urgent_count
    FROM reworks r
    JOIN molds m ON m.id = r.mold_id
    WHERE {$whereClause}
    GROUP BY r.mold_id
    ORDER BY rework_count DESC
    LIMIT 5
";
$topMoldsQuery = $conn->query($topMoldsSql);

// 7. Активност по техници според филтъра
$techSql = "
    SELECT technician, COUNT(*) as total_reworks,
           SUM(CASE WHEN r.priority = 'high' THEN 1 ELSE 0 END) as urgent_reworks
    FROM reworks r
    WHERE technician IS NOT NULL AND technician != '' AND {$whereClause}
    GROUP BY technician
    ORDER BY total_reworks DESC
    LIMIT 5
";
$techQuery = $conn->query($techSql);

// 8. Последните 10 ремонта според филтъра
$last10Sql = "
    SELECT r.*, m.mold_code, m.serial_number,
    (SELECT COUNT(*) FROM rework_attachments WHERE rework_id = r.id) as file_count
    FROM reworks r
    JOIN molds m ON m.id = r.mold_id
    WHERE {$whereClause}
    ORDER BY r.created_at DESC
    LIMIT 10
";
$last10ReworksQuery = $conn->query($last10Sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statistics & Analytics - Mold Tracking</title>
<link rel="stylesheet" href="assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        border-radius: 12px;
        width: 100%;
        max-width: 520px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
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

    .urgent-badge {
        background: #fee2e2;
        color: #dc2626;
        border: 1px solid #fca5a5;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }

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

    .upload-options-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin: 20px 0;
    }
    .upload-option-card {
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        padding: 15px 10px;
        text-align: center;
        cursor: pointer;
        background: #fafafa;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .upload-option-card:hover {
        border-color: #3b82f6;
        background: #f0fdf4;
    }
    .upload-option-card span {
        font-size: 0.85rem;
        font-weight: 600;
        color: #334155;
    }

    .stats-two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }
    @media (max-width: 900px) {
        .stats-two-col {
            grid-template-columns: 1fr;
        }
    }
    .kpi-value { font-size: 1.75rem; color: #0f172a; }
    .kpi-sub { font-size: 0.85rem; color: #64748b; }
    .section-title { font-size: 1.1rem; color: #1e293b; margin-top: 0; margin-bottom: 15px; }
    .timestamp { font-size: 0.85rem; color: #475569; }
    .short-note-text { font-size: 0.9rem; color: #334155; }
    .short-note-badge { padding: 4px 8px; border-radius: 4px; font-weight: 600; display: inline-block; }
</style>
</head>
<body>

<header class="header-container" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: #fff; border-bottom: 1px solid #e2e8f0; margin-bottom: 24px;">
    <div class="header-brand-container" style="display: flex; align-items: center; gap: 15px;">
        <a href="index.php" class="logo-link" title="Go to Main Dashboard">
            <img src="logo-ottobock 3.png" alt="Ottobock Logo" class="logo-img" style="height: 35px;">
        </a>
        <h1 class="header-title" style="font-size: 1.25rem; margin: 0; color: #0f172a;">📊 Analytics & Insights</h1>
    </div>
   
    <div>
        <a href="index.php" class="btn secondary" style="font-size: 0.85rem; padding: 7px 14px; text-decoration: none; background: #f1f5f9; color: #334155; border-radius: 6px;">← Back to Dashboard</a>
    </div>
</header>

<main style="max-width: 1400px; margin: 0 auto; padding: 0 20px;">

    <!-- DATE & PRIORITY FILTER SECTION -->
    <div class="card filter-card" style="margin-bottom: 24px; background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0;">
        <form method="GET" action="statistics.php" class="filter-form" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div class="filter-group">
                <span style="font-weight:700; color:#0f172a; font-size:0.9rem; display:block; margin-bottom:4px;">📅 Filters:</span>
            </div>
           
            <div class="filter-group" style="display: flex; flex-direction: column; gap: 4px;">
                <label for="from_date" style="font-size: 0.8rem; font-weight: 600; color: #475569;">From:</label>
                <input type="date" id="from_date" name="from_date" value="<?= h($fromDate) ?>" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
            </div>

            <div class="filter-group" style="display: flex; flex-direction: column; gap: 4px;">
                <label for="to_date" style="font-size: 0.8rem; font-weight: 600; color: #475569;">To:</label>
                <input type="date" id="to_date" name="to_date" value="<?= h($toDate) ?>" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
            </div>

            <div class="filter-group" style="display: flex; align-items: center; gap: 6px; align-self: flex-end; padding-bottom: 6px;">
                <input type="checkbox" id="urgent_only" name="urgent_only" value="1" <?= $urgentFilter === '1' ? 'checked' : '' ?> style="width: 16px; height: 16px; cursor: pointer;">
                <label for="urgent_only" style="cursor: pointer; font-weight: 600; color: #dc2626; margin-bottom: 0; font-size: 0.9rem;">⚡ High Priority Only</label>
            </div>

            <div class="filter-group" style="align-self: flex-end; padding-bottom: 2px;">
                <button type="submit" class="btn" style="padding: 7px 14px; font-size: 0.85rem; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer;">Apply Filter</button>
            </div>

            <?php if ($fromDate || $toDate || $urgentFilter): ?>
                <div class="filter-group" style="align-self: flex-end; padding-bottom: 2px;">
                    <a href="statistics.php" class="btn secondary" style="padding: 7px 14px; font-size: 0.85rem; text-decoration: none; background: #e2e8f0; color: #334155; border-radius: 6px;">Reset</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- KPI SUMMARY CARDS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px;">
        <div class="card" style="background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; border-top: 3px solid #2563eb;">
            <div class="kpi-title" style="font-weight: 800; font-size: 0.95rem;">Total Molds</div>
            <div style="white-space: nowrap; margin-top: 6px;">
                <span class="kpi-value" style="display: inline-block; font-weight: 800;"><?= number_format($totalMolds) ?></span>
                <span class="kpi-sub" style="display: inline-block; margin-left: 8px; font-weight: 600;">Registered</span>
            </div>
        </div>
        <div class="card" style="background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; border-top: 3px solid #0284c7;">
            <div class="kpi-title" style="font-weight: 800; font-size: 0.95rem;">Filtered Reworks</div>
            <div style="white-space: nowrap; margin-top: 6px;">
                <span class="kpi-value" style="display: inline-block; color: #0284c7; font-weight: 800;"><?= number_format($totalReworks) ?></span>
                <span class="kpi-sub" style="display: inline-block; margin-left: 8px; font-weight: 600;">Logged total</span>
            </div>
        </div>
        <div class="card" style="background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; border-top: 3px solid #dc2626;">
            <div class="kpi-title" style="font-weight: 800; font-size: 0.95rem; color: #dc2626;">⚡ HIGH PRIORITY</div>
            <div style="white-space: nowrap; margin-top: 6px;">
                <span class="kpi-value" style="display: inline-block; color: #dc2626; font-weight: 800;"><?= number_format($totalUrgentReworks) ?></span>
                <span class="kpi-sub" style="display: inline-block; margin-left: 8px; font-weight: 600;">Priority repairs</span>
            </div>
        </div>
        <div class="card" style="background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; border-top: 3px solid #10b981;">
            <div class="kpi-title" style="font-weight: 800; font-size: 0.95rem;">Last 30 Days</div>
            <div style="white-space: nowrap; margin-top: 6px;">
                <span class="kpi-value" style="display: inline-block; color: #10b981; font-weight: 800;"><?= number_format($reworks30Days) ?></span>
                <span class="kpi-sub" style="display: inline-block; margin-left: 8px; font-weight: 600;">Recent activity</span>
            </div>
        </div>
    </div>

    <!-- CHARTS SECTION -->
    <div class="card" style="background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 24px;">
        <h3 class="section-title">📈 Rework Trend Overview (Total vs. High Priority)</h3>
        <div style="height: 280px; position: relative;">
            <canvas id="reworksChart"></canvas>
        </div>
    </div>

    <!-- TABLES SECTION -->
    <div class="stats-two-col">
        
        <!-- TOP MOLDS TABLE -->
        <div class="card" style="background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; padding: 0; overflow: hidden; margin-bottom: 0;">
            <h3 style="padding: 16px; margin: 0; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 1rem;">🔥 Most Serviced Molds</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f1f5f9; text-align: left;">
                        <th style="padding: 10px 15px;">Mold Code</th>
                        <th style="padding: 10px 15px;">Serial No.</th>
                        <th style="padding: 10px 15px; text-align: right;">Reworks (Total / Priority)</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($topMoldsQuery && $topMoldsQuery->num_rows > 0): ?>
                    <?php while ($m = $topMoldsQuery->fetch_assoc()): ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 10px 15px;"><strong><?= h($m['mold_code']) ?></strong></td>
                            <td style="padding: 10px 15px;"><?= h($m['serial_number'] ?: '—') ?></td>
                            <td style="padding: 10px 15px; text-align: right;">
                                <span class="short-note-badge" style="background: #ef4444; color: #fff; font-size: 0.8rem;">
                                    <?= $m['rework_count'] ?> total
                                </span>
                                <?php if ($m['urgent_count'] > 0): ?>
                                    <span class="urgent-badge" style="margin-left: 4px;">⚡ <?= $m['urgent_count'] ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="text-align:center; color: #64748b; padding: 20px;">No records found for selected dates.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- TECHNICIANS ACTIVITY TABLE -->
        <div class="card" style="background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; padding: 0; overflow: hidden; margin-bottom: 0;">
            <h3 style="padding: 16px; margin: 0; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 1rem;">👨‍🔧 Technician Activity</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f1f5f9; text-align: left;">
                        <th style="padding: 10px 15px;">Technician Name</th>
                        <th style="padding: 10px 15px; text-align: right;">Completed (Total / Priority)</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($techQuery && $techQuery->num_rows > 0): ?>
                    <?php while ($t = $techQuery->fetch_assoc()): ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 10px 15px;"><strong><?= h($t['technician']) ?></strong></td>
                            <td style="padding: 10px 15px; text-align: right;">
                                <span class="short-note-badge" style="background: #0284c7; color: #fff; font-size: 0.8rem;">
                                    <?= $t['total_reworks'] ?> entries
                                </span>
                                <?php if ($t['urgent_reworks'] > 0): ?>
                                    <span class="urgent-badge" style="margin-left: 4px;">⚡ <?= $t['urgent_reworks'] ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="2" style="text-align:center; color: #64748b; padding: 20px;">No technician records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- LAST 10 REWORKS TABLE WITH ATTACHMENTS -->
    <div class="card" style="background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; padding: 0; overflow: hidden; margin-top: 24px; margin-bottom: 40px;">
        <h3 style="padding: 16px; margin: 0; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 1rem;">📋 Last 10 Rework Entries</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f1f5f9; text-align: left;">
                        <th style="padding: 12px 15px;">Date & Time</th>
                        <th style="padding: 12px 15px;">Mold Code</th>
                        <th style="padding: 12px 15px;">Serial Number</th>
                        <th style="padding: 12px 15px;">Reported Date</th>
                        <th style="padding: 12px 15px;">Completion Date</th>
                        <th style="padding: 12px 15px;">Technician</th>
                        <th style="padding: 12px 15px;">Short Note & Attachments</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($last10ReworksQuery && $last10ReworksQuery->num_rows > 0): ?>
                    <?php while ($r = $last10ReworksQuery->fetch_assoc()):
                        $moldCode = $r['mold_code'] ?? '';
                        $technician = $r['technician'] ?? '';
                        $shortNote = $r['short_note'] ?? ($r['reason_notes'] ?? '');
                        $fileCount = intval($r['file_count']);
                        $isUrgent = isset($r['priority']) && $r['priority'] === 'high';
                    ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 12px 15px;"><span class="timestamp">⏱️ <?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></span></td>
                            <td style="padding: 12px 15px;">
                                <a href="mold_history.php?id=<?= $r['mold_id'] ?>" style="color: #2563eb; font-weight: 700; text-decoration: none;">
                                    <?= h($moldCode) ?>
                                </a>
                                <?php if ($isUrgent): ?>
                                    <div style="margin-top: 4px;"><span class="urgent-badge">⚡ High Priority</span></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 15px;"><?= h($r['serial_number'] ?: '—') ?></td>
                            <td style="padding: 12px 15px;"><?= $r['reported_date'] ? date('d.m.Y', strtotime($r['reported_date'])) : '—' ?></td>
                            <td style="padding: 12px 15px;"><?= $r['rework_end_date'] ? date('d.m.Y', strtotime($r['rework_end_date'])) : '—' ?></td>
                            <td style="padding: 12px 15px;"><?= h($technician ?: '—') ?></td>
                            <td style="padding: 12px 15px;">
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
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; color: #64748b; padding: 20px;">No entries found for selected period.</td></tr>
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
            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;">Изберете начин за прикачване</h3>
            <button type="button" onclick="closeUploadModal()" style="background:none; border:none; font-size: 1.2rem; cursor:pointer;">✕</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="uploadForm" onsubmit="prepareFormBeforeSubmit(event)">
            <input type="hidden" name="rework_id" id="uploadReworkId">

            <div class="upload-options-grid">
                <label for="fileInputCamera" class="upload-option-card">
                    <span style="font-size: 24px;">📸</span>
                    <span>Снимай</span>
                </label>
                <input type="file" id="fileInputCamera" accept="image/*" capture="environment" style="display: none;">

                <label for="fileInputGallery" class="upload-option-card">
                    <span style="font-size: 24px;">🖼️</span>
                    <span>Галерия</span>
                </label>
                <input type="file" id="fileInputGallery" accept="image/*" multiple style="display: none;">

                <label for="fileInputAny" class="upload-option-card">
                    <span style="font-size: 24px;">📁</span>
                    <span>Файл</span>
                </label>
                <input type="file" id="fileInputAny" multiple style="display: none;">
            </div>

            <input type="file" id="finalFilesInput" name="attachments[]" multiple style="display: none;">

            <div id="selectedFilesPreview" style="margin-top: 15px; font-size: 0.85rem; color: #475569; background: #f8fafc; padding: 10px; border-radius: 6px; display: none;"></div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn secondary" onclick="closeUploadModal()" style="padding: 6px 14px; background: #e2e8f0; border: none; border-radius: 6px; cursor: pointer;">Отказ</button>
                <button type="submit" class="btn" style="padding: 6px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer;">Качи файловете</button>
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

function updateFilesPreview() {
    let previewContainer = document.getElementById('selectedFilesPreview');
    if (dt.files.length > 0) {
        let html = '<b>Избрани файлове (' + dt.files.length + '):</b><ul style="margin: 5px 0 0 15px; padding:0;">';
        for (let i = 0; i < dt.files.length; i++) {
            html += `<li>📄 ${dt.files[i].name}</li>`;
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
        e.preventDefault();
        alert('Моля, изберете поне един файл за качване.');
        return false;
    }
    document.getElementById('finalFilesInput').files = dt.files;
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
                            <a href="uploads/reworks/${file.file_name}" target="_blank" class="action-badge badge-view">View</a>
                            <a href="delete_file.php?id=${file.id}" onclick="return confirm('Are you sure you want to delete this file?');" class="action-badge" style="background: #fee2e2; color: #dc2626;">Delete</a>
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

const ctx = document.getElementById('reworksChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($monthsLabels) ?>,
        datasets: <?php if ($urgentFilter === '1'): ?>
        [
            {
                label: 'High Priority Reworks',
                data: <?= json_encode($chartUrgentCounts) ?>,
                borderColor: '#dc2626',
                backgroundColor: 'rgba(220, 38, 38, 0.15)',
                fill: true,
                tension: 0.35,
                borderWidth: 3,
                pointRadius: 5,
                pointHoverRadius: 6,
                pointBackgroundColor: '#dc2626'
            }
        ]
        <?php else: ?>
        [
            {
                label: 'Total Reworks',
                data: <?= json_encode($chartCounts) ?>,
                borderColor: '#0284c7',
                backgroundColor: 'rgba(2, 132, 199, 0.08)',
                fill: true,
                tension: 0.35,
                borderWidth: 3,
                pointRadius: 4,
                pointHoverRadius: 4,
                pointBackgroundColor: '#0284c7'
            },
            {
                label: 'High Priority',
                data: <?= json_encode($chartUrgentCounts) ?>,
                borderColor: '#dc2626',
                backgroundColor: 'rgba(220, 38, 38, 0.05)',
                fill: false,
                tension: 0.35,
                borderWidth: 2.5,
                pointRadius: 4,
                pointHoverRadius: 4,
                pointBackgroundColor: '#dc2626'
            }
        ]
        <?php endif; ?>
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'top' }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});
</script>

</body>
</html>