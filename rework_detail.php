<?php
require 'config.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT r.*, m.mold_code, m.id as mold_id FROM reworks r JOIN molds m ON m.id = r.mold_id WHERE r.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$rw = $stmt->get_result()->fetch_assoc();

if (!$rw) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rework детайли - <?= h($rw['mold_code']) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header>
    <h1>Rework запис</h1>
    <button onclick="window.print()" class="btn secondary">🖨️ Принтирай / PDF</button>
</header>

<main>
    <a href="mold.php?id=<?= $rw['mold_id'] ?>" class="back-link">← Назад към <?= h($rw['mold_code']) ?></a>

    <div class="card">
        <span class="badge <?= h($rw['post_rework_status']) ?>"><?= h($POST_REWORK_LABELS[$rw['post_rework_status']]) ?></span>

        <table style="margin-top:14px;">
            <tr><td data-label="Форма"><strong>Форма</strong></td><td data-label=""><?= h($rw['mold_code']) ?></td></tr>
            <tr><td data-label="Докладвано от"><strong>Докладвано от</strong></td><td data-label=""><?= h($rw['reported_by'] ?: '—') ?></td></tr>
            <tr><td data-label="Дата"><strong>Дата на докладване</strong></td><td data-label=""><?= h($rw['reported_date']) ?></td></tr>
            <tr><td data-label="Причина"><strong>Причина</strong></td><td data-label=""><?= h($REASON_LABELS[$rw['reason']] ?? $rw['reason']) ?></td></tr>
            <tr><td data-label="Бележки"><strong>Бележки към причината</strong></td><td data-label=""><?= nl2br(h($rw['reason_notes'] ?: '—')) ?></td></tr>
            <?php if (!empty($rw['photo'])): ?>
            <tr>
                <td data-label="Снимка"><strong>Снимка на дефекта</strong></td>
                <td data-label="">
                    <a href="uploads/<?= h($rw['photo']) ?>" target="_blank">
                        <img src="uploads/<?= h($rw['photo']) ?>" alt="Дефект" style="max-width:250px; border-radius:8px; border:1px solid var(--border);">
                    </a>
                </td>
            </tr>
            <?php endif; ?>
            <tr><td data-label="Техник"><strong>Техник</strong></td><td data-label=""><?= h($rw['technician'] ?: '—') ?></td></tr>
            <tr><td data-label="Начало"><strong>Начало на ремонта</strong></td><td data-label=""><?= h($rw['rework_start_date'] ?: '—') ?></td></tr>
            <tr><td data-label="Край"><strong>Край на ремонта</strong></td><td data-label=""><?= h($rw['rework_end_date'] ?: '—') ?></td></tr>
            <tr><td data-label="Действия"><strong>Извършени действия</strong></td><td data-label=""><?= nl2br(h($rw['actions_taken'] ?: '—')) ?></td></tr>
            <tr><td data-label="QC"><strong>Проверено от</strong></td><td data-label=""><?= h($rw['inspected_by'] ?: '—') ?></td></tr>
            <tr><td data-label="Дата проверка"><strong>Дата на проверка</strong></td><td data-label=""><?= h($rw['inspection_date'] ?: '—') ?></td></tr>
        </table>
    </div>
</main>

</body>
</html>