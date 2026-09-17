<?php
session_start();
require 'config.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($id > 0) {
    $newStatus = ($action === 'archive') ? 'archived' : 'active';
    $stmt = $conn->prepare("UPDATE reworks SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $newStatus, $id);
    $stmt->execute();

    $msg = ($action === 'archive') ? 'Записът е преместен в архива.' : 'Записът е възстановен в дневника.';
    header('Location: ' . ($action === 'archive' ? 'index.php' : 'archive.php') . '?msg=' . urlencode($msg));
    exit;
}

header('Location: index.php');