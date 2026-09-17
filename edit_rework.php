<?php
require 'config.php';

$reworkId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$mold_id = (int)($_GET['mold_id'] ?? $_POST['mold_id'] ?? 0);

if ($reworkId > 0) {
    $stmt = $conn->prepare("SELECT r.*, m.mold_code, m.serial_number, m.id as mold_id 
                            FROM reworks r 
                            JOIN molds m ON m.id = r.mold_id 
                            WHERE r.id = ?");
    $stmt->bind_param('i', $reworkId);
    $stmt->execute();
    $rework = $stmt->get_result()->fetch_assoc();
} else if ($mold_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM molds WHERE id = ?");
    $stmt->bind_param('i', $mold_id);
    $stmt->execute();
    $mold = $stmt->get_result()->fetch_assoc();
    $rework = [
        'id' => 0,
        'mold_id' => $mold['id'],
        'mold_code' => $mold['mold_code'],
        'serial_number' => $mold['serial_number'],
        'reported_date' => date('Y-m-d'),
        'rework_end_date' => date('Y-m-d'),
        'technician' => '',
        'reason_notes' => '',
        'created_at' => null
    ];
} else {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serial_number = trim($_POST['serial_number'] ?? '');
    $mold_code = trim($_POST['mold_code'] ?? '');
    $reported_date = $_POST['reported_date'] ?: null;
    $rework_end_date = $_POST['rework_end_date'] ?: null;
    $technician = trim($_POST['technician'] ?? '');
    $reason_notes = trim($_POST['reason_notes'] ?? '');

    if ($mold_code === '') {
        $error = 'Mold Code is required.';
    } else {
        $updateMold = $conn->prepare("UPDATE molds SET serial_number = ?, mold_code = ? WHERE id = ?");
        $updateMold->bind_param('ssi', $serial_number, $mold_code, $rework['mold_id']);
        $updateMold->execute();

        if ($reworkId > 0) {
            $updateRework = $conn->prepare("UPDATE reworks 
                SET reported_date = ?, rework_end_date = ?, technician = ?, reason_notes = ? 
                WHERE id = ?");
            $updateRework->bind_param('ssssi', $reported_date, $rework_end_date, $technician, $reason_notes, $reworkId);
            $updateRework->execute();
        } else {
            $insertRework = $conn->prepare("INSERT INTO reworks (mold_id, reported_date, rework_end_date, technician, reason_notes, status) VALUES (?, ?, ?, ?, ?, 'completed')");
            $insertRework->bind_param('issss', $rework['mold_id'], $reported_date, $rework_end_date, $technician, $reason_notes);
            $insertRework->execute();
        }

        $log = $conn->prepare("INSERT INTO audit_log (action_type, mold_code, serial_number, technician, details) VALUES ('UPDATE', ?, ?, ?, 'Updated Record')");
        $log->bind_param('sss', $mold_code, $serial_number, $technician);
        $log->execute();

        header('Location: index.php?msg=' . urlencode('Record updated successfully.'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Record</title>
<link rel="stylesheet" href="assets/style.css">
<style>
    .header-container { display: flex; align-items: center; justify-content: space-between; padding: 14px 24px; }
    .header-brand { display: flex; align-items: center; gap: 20px; }
    .logo-img { height: 44px; width: auto; object-fit: contain; display: block; }
    .header-title { font-size: 1.35rem; font-weight: 700; color: #0f172a; margin: 0; }
</style>
</head>
<body>

<header class="header-container">
    <div class="header-brand">
        <img src="logo-ottobock 3.png" alt="Ottobock Logo" class="logo-img">
        <h1 class="header-title">✏️ Edit Record: <?= h($rework['mold_code']) ?></h1>
    </div>
</header>

<main>
    <a href="index.php" class="back-link">← Back to Main Log</a>

    <?php if ($error): ?>
        <div class="flash" style="background:#fee2e2; color:#dc2626; border-color:#fca5a5;"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 600px; margin: 20px auto;">
        <form method="post">
            <input type="hidden" name="id" value="<?= $reworkId ?>">
            <input type="hidden" name="mold_id" value="<?= $rework['mold_id'] ?>">

            <label>Mold Code *</label>
            <input type="text" name="mold_code" value="<?= h($rework['mold_code']) ?>" required>

            <label>Serial Number</label>
            <input type="text" name="serial_number" value="<?= h($rework['serial_number'] ?? '') ?>">

            <label>Reported Date</label>
            <input type="date" name="reported_date" value="<?= h($rework['reported_date']) ?>">

            <label>Completion Date</label>
            <input type="date" name="rework_end_date" value="<?= h($rework['rework_end_date']) ?>">

            <label>Technician</label>
            <input type="text" name="technician" value="<?= h($rework['technician']) ?>">

            <label>Notes / Remarks</label>
            <textarea name="reason_notes" rows="4"><?= h($rework['reason_notes']) ?></textarea>

            <div class="form-actions" style="display:flex; gap:10px; margin-top:16px;">
                <button type="submit" class="btn" style="font-size: 0.85rem; padding: 7px 14px;">Save Changes</button>
                <a href="index.php" class="btn secondary" style="font-size: 0.85rem; padding: 7px 14px;">Cancel</a>
            </div>
        </form>
    </div>
</main>

</body>
</html>