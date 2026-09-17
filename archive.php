<?php 
session_start();
require 'config.php'; 
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Документален Архив / История</title>
<link rel="stylesheet" href="assets/style.css">
<style>
    .badge-action {
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: bold;
    }
    .badge-CREATE { background: #dcfce7; color: #15803d; }
    .badge-UPDATE { background: #fef3c7; color: #b45309; }
    .badge-DELETE { background: #fee2e2; color: #b91c1c; }
    .timestamp { font-family: monospace; font-weight: bold; color: var(--accent); }
</style>
</head>
<body>

<header>
    <h1>📦 Документален Архив (История на действията)</h1>
    <a href="index.php" class="btn secondary">← Назад към главния дневник</a>
</header>

<main>

<div class="card" style="padding: 12px;">
    <input type="text" id="searchInput" placeholder="🔍 Търсене в архива по дата, точен час, матрица..." onkeyup="filterTable()" autofocus style="margin:0;">
</div>

<div class="card" style="padding:0; overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Точен час на събитието</th>
                <th>Действие</th>
                <th>Матрица №</th>
                <th>Сериен №</th>
                <th>Извършил</th>
                <th>Детайли / Бележки</th>
            </tr>
        </thead>
        <tbody id="archiveBody">
        <?php
        $sql = "SELECT * FROM audit_log ORDER BY action_time DESC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                $actionBadge = 'badge-' . $row['action_type'];
                $actionName = [
                    'CREATE' => '➕ Създадена',
                    'UPDATE' => '✏️ Редактирана',
                    'DELETE' => '❌ Изтрита'
                ][$row['action_type']] ?? $row['action_type'];
        ?>
            <tr>
                <td><span class="timestamp">⏱️ <?= date('d.m.Y H:i:s', strtotime($row['action_time'])) ?></span></td>
                <td><span class="badge-action <?= $actionBadge ?>"><?= $actionName ?></span></td>
                <td><strong><?= h($row['mold_code']) ?></strong></td>
                <td><?= h($row['serial_number'] ?: '—') ?></td>
                <td><?= h($row['technician'] ?: '—') ?></td>
                <td><?= h($row['details'] ?: '—') ?></td>
            </tr>
        <?php 
            endwhile;
        else:
        ?>
            <tr>
                <td colspan="6" style="text-align:center; color:var(--muted); padding:24px;">Няма записана история в архива.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</main>

<script>
function filterTable() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#archiveBody tr');

    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
    });
}
</script>

</body>
</html>