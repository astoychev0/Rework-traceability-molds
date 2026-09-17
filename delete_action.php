<?php
require 'config.php';

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($type === 'mold' && $id > 0) {
    $getMold = $conn->prepare("SELECT mold_code, serial_number FROM molds WHERE id = ?");
    $getMold->bind_param('i', $id);
    $getMold->execute();
    $moldData = $getMold->get_result()->fetch_assoc();

    if ($moldData) {
        $log = $conn->prepare("INSERT INTO audit_log (action_type, mold_code, serial_number, details) VALUES ('DELETE', ?, ?, 'Deleted Mold and its history')");
        $log->bind_param('ss', $moldData['mold_code'], $moldData['serial_number']);
        $log->execute();
    }

    $delReworks = $conn->prepare("DELETE FROM reworks WHERE mold_id = ?");
    $delReworks->bind_param('i', $id);
    $delReworks->execute();

    $delMold = $conn->prepare("DELETE FROM molds WHERE id = ?");
    $delMold->bind_param('i', $id);
    $delMold->execute();

    header('Location: index.php?msg=' . urlencode('Mold deleted successfully.'));
    exit;
}

header('Location: index.php');
exit;