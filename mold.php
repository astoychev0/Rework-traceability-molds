<?php
require 'config.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM molds WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$mold = $stmt->get_result()->fetch_assoc();

if (!$mold) {
    header('Location: index.php');
    exit;
}

$reworks = $conn->prepare("SELECT * FROM reworks WHERE mold_id = ? ORDER BY reported_date DESC, id DESC");
$reworks->bind_param('i', $id);
$reworks->execute();
$reworkResult = $reworks->get_result();

// Взимаме историята на статусите с точен час
$historyQuery = $conn->prepare("SELECT * FROM status_history WHERE mold_id = ? ORDER BY changed_at DESC");
$historyQuery->bind_param('i', $id);
$historyQuery->execute();
$historyResult = $historyQuery->get_result();
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($mold['mold_code']) ?> - Rework History</title>
<link rel="stylesheet" href="assets/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>

<header>
    <h1><?= h($mold['mold_code']) ?></h1>
    <a href="add_rework.php?mold_id=<?= $id ?>" class="btn">+ Нов rework</a>
</header>

<main>
    <a href="index.php" class="back-link">← Назад към списъка</a>

    <?php if (isset($_GET['msg'])): ?>
        <div class="flash"><?= h($_GET['msg']) ?></div>
    <?php endif; ?>

    <div class="card" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <span class="badge <?= h($mold['status']) ?>"><?= h($STATUS_LABELS[$mold['status']]) ?></span>
            <p style="margin:10px 0 4px 0; color:var(--muted);">📍 Локация: <?= h($mold['location'] ?: '—') ?></p>
            <p style="margin:0 0 4px 0; color:var(--muted);">🔧 Общо ремонти: <?= (int)$mold['rework_count'] ?></p>
            <?php if ($mold['notes']): ?>
                <p style="margin:8px 0 0 0;"><?= nl2br(h($mold['notes'])) ?></p>
            <?php endif; ?>

            <div class="form-actions" style="margin-top:14px;">
                <a href="edit_mold_status.php?id=<?= $id ?>" class="btn small secondary">Смени статус</a>
            </div>
        </div>

        <div style="background:#fff; padding:10px; border-radius:8px; text-align:center;">
            <div id="qrcode"></div>
            <span style="font-size:0.75rem; color:#0f172a; font-weight:bold; display:block; margin-top:4px;">Сканирай за бърз достъп</span>
        </div>
    </div>

    <!-- История на промените на статусите -->
    <?php if ($historyResult->num_rows > 0): ?>
    <h3 style="margin-top:26px;">🕒 История на статусите (Дата и час)</h3>
    <table style="margin-bottom: 24px;">
        <thead>
            <tr>
                <th>Дата и час</th>
                <th>Предишен статус</th>
                <th>Нов статус</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($h = $historyResult->fetch_assoc()): ?>
            <tr>
                <td data-label="Дата и час"><strong><?= date('d.m.Y H:i:s', strtotime($h['changed_at'])) ?></strong></td>
                <td data-label="Предишен"><?= h($STATUS_LABELS[$h['old_status']] ?? $h['old_status']) ?></td>
                <td data-label="Нов"><span class="badge <?= h($h['new_status']) ?>"><?= h($STATUS_LABELS[$h['new_status']]) ?></span></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <h3>История на ремонтите</h3>

    <?php if ($reworkResult->num_rows === 0): ?>
        <div class="empty-state">Няма записани ремонти за тази форма.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Причина</th>
                    <th>Техник</th>
                    <th>Период</th>
                    <th>Резултат</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php while ($rw = $reworkResult->fetch_assoc()): ?>
                <tr>
                    <td data-label="Дата"><?= h($rw['reported_date']) ?></td>
                    <td data-label="Причина"><?= h($REASON_LABELS[$rw['reason']] ?? $rw['reason']) ?></td>
                    <td data-label="Техник"><?= h($rw['technician'] ?: '—') ?></td>
                    <td data-label="Период">
                        <?= h($rw['rework_start_date'] ?: '—') ?> → <?= h($rw['rework_end_date'] ?: '—') ?>
                    </td>
                    <td data-label="Резултат">
                        <span class="badge <?= h($rw['post_rework_status']) ?>"><?= h($POST_REWORK_LABELS[$rw['post_rework_status']]) ?></span>
                    </td>
                    <td data-label="Действие">
                        <a href="rework_detail.php?id=<?= $rw['id'] ?>" class="btn small secondary">Виж</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>

</main>

<script>
new QRCode(document.getElementById("qrcode"), {
    text: window.location.href,
    width: 100,
    height: 100
});
</script>

</body>
</html>