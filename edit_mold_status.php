<?php
require 'config.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM molds WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$mold = $stmt->get_result()->fetch_assoc();

if (!$mold) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? 'active';
    $location = trim($_POST['location'] ?? '');
    $oldStatus = $mold['status'];

    if ($oldStatus !== $newStatus) {
        // Записваме промяната в историята с точния час
        $log = $conn->prepare("INSERT INTO status_history (mold_id, old_status, new_status) VALUES (?, ?, ?)");
        $log->bind_param('iss', $id, $oldStatus, $newStatus);
        $log->execute();
    }

    $update = $conn->prepare("UPDATE molds SET status = ?, location = ? WHERE id = ?");
    $update->bind_param('ssi', $newStatus, $location, $id);
    $update->execute();

    header('Location: mold.php?id=' . $id . '&msg=' . urlencode('Статусът е обновен.'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Смяна на статус - <?= h($mold['mold_code']) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header>
    <h1>Смяна на статус: <?= h($mold['mold_code']) ?></h1>
</header>

<main>
    <a href="mold.php?id=<?= $id ?>" class="back-link">← Назад</a>

    <div class="card">
        <form method="post">
            <input type="hidden" name="id" value="<?= $id ?>">

            <label>Статус</label>
            <select name="status">
                <?php foreach ($STATUS_LABELS as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $mold['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Локация</label>
            <input type="text" name="location" value="<?= h($mold['location'] ?? '') ?>">

            <div class="form-actions">
                <button type="submit" class="btn">Запази</button>
                <a href="mold.php?id=<?= $id ?>" class="btn secondary">Отказ</a>
            </div>
        </form>
    </div>
</main>

</body>
</html>