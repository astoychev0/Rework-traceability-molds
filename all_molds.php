<?php 
require 'config.php'; 

$search = trim($_GET['search'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Molds</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="header-container">
    <div class="header-brand-container">
        <!-- Кликаемо САМО ЛОГОТО -->
        <a href="index.php" class="logo-link" title="Go to Main Dashboard">
            <img src="logo-ottobock 3.png" alt="Ottobock Logo" class="logo-img">
        </a>
        <h1 class="header-title">🗂️ All Registered Molds</h1>
    </div>
    
    <div style="display: flex; gap: 10px;">
        <a href="add_mold.php" class="btn" style="font-size: 0.85rem; padding: 7px 14px;">+ Add Mold</a>
        <a href="index.php" class="btn secondary" style="font-size: 0.85rem; padding: 7px 14px;">← Back to Dashboard</a>
    </div>
</header>

<main>
    <div class="card molds-grid-container">
        <h2 class="section-title">All System Molds</h2>
        
        <form method="GET" style="margin-bottom: 20px; display: flex; justify-content: center; gap: 8px;">
            <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search mold code..." style="width: 280px; padding: 7px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
            <button type="submit" class="btn small">🔍 Search</button>
            <?php if ($search !== ''): ?>
                <a href="all_molds.php" class="btn small secondary">Reset</a>
            <?php endif; ?>
        </form>

        <div class="molds-grid">
            <?php
            if ($search !== '') {
                $stmt = $conn->prepare("SELECT id, mold_code FROM molds WHERE mold_code LIKE ? ORDER BY mold_code ASC");
                $like = "%$search%";
                $stmt->bind_param('s', $like);
                $stmt->execute();
                $moldsQuery = $stmt->get_result();
            } else {
                $moldsQuery = $conn->query("SELECT id, mold_code FROM molds ORDER BY mold_code ASC");
            }

            if ($moldsQuery && $moldsQuery->num_rows > 0):
                while ($mold = $moldsQuery->fetch_assoc()):
            ?>
                <a href="mold_history.php?id=<?= $mold['id'] ?>" class="mold-badge" title="Click to open details">
                    <?= h($mold['mold_code']) ?>
                </a>
            <?php 
                endwhile;
            else:
            ?>
                <p style="color: var(--muted);">No molds found matching "<?= h($search) ?>".</p>
            <?php endif; ?>
        </div>
    </div>
</main>

</body>
</html>