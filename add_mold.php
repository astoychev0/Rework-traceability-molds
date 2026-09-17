<?php
require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mold_code = trim($_POST['mold_code'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($mold_code === '') {
        $error = 'Mold Code is required!';
    } else {
        // Проверка дали има колона description в таблицата ви, или ползвайте само основните
        $stmt = $conn->prepare("INSERT INTO molds (mold_code, serial_number) VALUES (?, ?)");
        $stmt->bind_param('ss', $mold_code, $serial_number);

        if ($stmt->execute()) {
            header('Location: index.php?msg=' . urlencode('Mold added successfully!'));
            exit;
        } else {
            $error = 'Error saving mold: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add New Mold - Mold Tracking</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="header-container">
    <div class="header-brand-container">
        <a href="index.php" class="logo-link" title="Go to Main Dashboard">
            <img src="logo-ottobock 3.png" alt="Ottobock Logo" class="logo-img">
        </a>
        <h1 class="header-title">➕ Add New Mold</h1>
    </div>
    
    <div>
        <a href="index.php" class="btn secondary" style="font-size: 0.85rem; padding: 7px 14px;">← Back to Dashboard</a>
    </div>
</header>

<main>
    <div class="card" style="max-width: 550px; margin: 20px auto; padding: 30px;">
        
        <div style="margin-bottom: 24px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
            <h3 style="margin: 0; font-size: 1.15rem; color: #0f172a;">🛠️ Mold Registration Details</h3>
            <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: #64748b;">Enter the unique code and optional serial number to register a new mold in the system.</p>
        </div>

        <?php if ($error): ?>
            <div class="flash danger" style="margin-bottom: 20px; padding: 12px; font-size: 0.9rem; background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5; border-radius: 6px;">
                ⚠️ <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="mold_code" style="display: block; font-weight: 700; margin-bottom: 6px; color: #1e293b;">Mold Code *</label>
                <input type="text" id="mold_code" name="mold_code" required autofocus placeholder="e.g. M-102" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem;">
                <small style="color: #64748b; display: block; margin-top: 4px;">Unique identifier code used in the production line.</small>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="serial_number" style="display: block; font-weight: 700; margin-bottom: 6px; color: #1e293b;">Serial Number <span style="font-weight: normal; color: #64748b;">(Optional)</span></label>
                <input type="text" id="serial_number" name="serial_number" placeholder="e.g. SN-99482" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem;">
                <small style="color: #64748b; display: block; margin-top: 4px;">Manufacturer serial number or hardware tracking tag.</small>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                <a href="index.php" class="btn secondary" style="padding: 10px 18px; font-size: 0.9rem;">Cancel</a>
                <button type="submit" class="btn" style="padding: 10px 24px; font-size: 0.9rem; font-weight: 600;">Save Mold</button>
            </div>
        </form>
    </div>
</main>

</body>
</html>