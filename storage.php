<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . " GB";
    elseif ($bytes >= 1048576) return round($bytes / 1048576, 2) . " MB";
    elseif ($bytes >= 1024) return round($bytes / 1024, 2) . " KB";
    else return "$bytes bytes";
}

// Get ACCURATE storage statistics
$totalSize = $conn->query("SELECT COALESCE(SUM(filesize), 0) as total FROM input WHERE is_deleted = 0")->fetch_assoc()['total'];
$activeSize = $conn->query("SELECT COALESCE(SUM(filesize), 0) as total FROM input WHERE is_deleted = 0 AND is_archived = 0")->fetch_assoc()['total'];
$archivedSize = $conn->query("SELECT COALESCE(SUM(filesize), 0) as total FROM input WHERE is_archived = 1 AND is_deleted = 0")->fetch_assoc()['total'];
$deletedSize = $conn->query("SELECT COALESCE(SUM(filesize), 0) as total FROM input WHERE is_deleted = 1")->fetch_assoc()['total'];

// File counts
$totalFiles = $conn->query("SELECT COUNT(*) as count FROM input WHERE is_deleted = 0")->fetch_assoc()['count'];
$activeFiles = $conn->query("SELECT COUNT(*) as count FROM input WHERE is_deleted = 0 AND is_archived = 0")->fetch_assoc()['count'];
$archivedFiles = $conn->query("SELECT COUNT(*) as count FROM input WHERE is_archived = 1 AND is_deleted = 0")->fetch_assoc()['count'];
$deletedFiles = $conn->query("SELECT COUNT(*) as count FROM input WHERE is_deleted = 1")->fetch_assoc()['count'];

// Storage by category (ACCURATE)
$categories = $conn->query("
    SELECT 
        category, 
        COALESCE(SUM(filesize), 0) as size, 
        COUNT(*) as count 
    FROM input 
    WHERE is_deleted = 0 
    GROUP BY category 
    ORDER BY size DESC
");

// Storage by folder (ACCURATE)
$folders = $conn->query("
    SELECT 
        COALESCE(folder, 'General') as folder, 
        COALESCE(SUM(filesize), 0) as size, 
        COUNT(*) as count 
    FROM input 
    WHERE is_deleted = 0 
    GROUP BY folder 
    ORDER BY size DESC
");

// Storage by user (ACCURATE)
$users = $conn->query("
    SELECT 
        COALESCE(uploaded_by, 'Unknown') as uploaded_by, 
        COALESCE(SUM(filesize), 0) as size, 
        COUNT(*) as count 
    FROM input 
    WHERE is_deleted = 0 
    GROUP BY uploaded_by 
    ORDER BY size DESC
");

// Storage limit (2TB for demo)
$storageLimit = 2 * 1024 * 1024 * 1024 * 1024; // 2TB in bytes
$usagePercent = $totalSize > 0 ? min(($totalSize / $storageLimit) * 100, 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Storage - CIT File Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root {
    --primary: #2563eb;
    --primary-dark: #1e40af;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --dark: #1e293b;
    --light: #f8fafc;
    --border: #e2e8f0;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    color: var(--dark);
}

.modern-header {
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.header-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
}

.logo-section {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.logo-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow: 0 4px 6px rgba(139, 92, 246, 0.3);
}

.logo-text h1 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
}

.logo-text p {
    font-size: 0.75rem;
    color: var(--secondary);
    margin: 0;
}

.back-btn {
    background: var(--light);
    color: var(--dark);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.back-btn:hover {
    background: var(--border);
    color: var(--dark);
}

.main-container {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.storage-overview {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.storage-overview h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.storage-bar {
    background: var(--light);
    height: 40px;
    border-radius: 12px;
    overflow: hidden;
    margin: 1.5rem 0;
    position: relative;
}

.storage-fill {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
    transition: width 0.8s ease, background 0.3s ease;
    position: relative;
    min-width: 60px;
}

.storage-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.storage-fill.low {
    background: linear-gradient(90deg, #10b981, #059669);
}

.storage-fill.medium {
    background: linear-gradient(90deg, #3b82f6, #2563eb);
}

.storage-fill.high {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

.storage-fill.critical {
    background: linear-gradient(90deg, #ef4444, #dc2626);
}

.storage-info {
    text-align: center;
    color: var(--secondary);
    font-size: 0.95rem;
}

.storage-info strong {
    color: var(--dark);
    font-size: 1.1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-icon.primary {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(37, 99, 235, 0.2));
    color: var(--primary);
}

.stat-icon.success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
    color: var(--success);
}

.stat-icon.warning {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));
    color: var(--warning);
}

.stat-icon.danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.2));
    color: var(--danger);
}

.stat-content .label {
    font-size: 0.875rem;
    color: var(--secondary);
    margin-bottom: 0.25rem;
}

.stat-content .value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
}

.breakdown-section {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.breakdown-section h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.breakdown-table {
    width: 100%;
    border-collapse: collapse;
}

.breakdown-table thead {
    background: var(--light);
}

.breakdown-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.breakdown-table td {
    padding: 1rem;
    border-top: 1px solid var(--border);
}

.breakdown-table tbody tr {
    transition: background 0.2s;
}

.breakdown-table tbody tr:hover {
    background: var(--light);
}

.breakdown-table .name-cell {
    font-weight: 600;
    color: var(--dark);
}

.progress-bar-cell {
    width: 200px;
}

.mini-progress {
    background: var(--light);
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}

.mini-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-dark));
    border-radius: 4px;
    transition: width 0.5s ease;
}

.percentage-cell {
    color: var(--secondary);
    font-weight: 500;
}

@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .progress-bar-cell {
        display: none;
    }
}
</style>
</head>
<body>

<header class="modern-header">
    <div class="header-content">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-hdd"></i>
            </div>
            <div class="logo-text">
                <h1>Storage Management</h1>
                <p>Monitor your storage usage and file distribution</p>
            </div>
        </div>
        <a href="home_page.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
    </div>
</header>

<div class="main-container">
    
    <!-- Overall Storage Usage -->
    <div class="storage-overview">
        <h3>
            <i class="fas fa-chart-pie"></i> Total Storage Usage
        </h3>
        <div class="storage-bar">
            <div class="storage-fill <?php 
                echo $usagePercent >= 90 ? 'critical' : 
                     ($usagePercent >= 70 ? 'high' : 
                     ($usagePercent >= 40 ? 'medium' : 'low')); 
            ?>" style="width: <?php echo max($usagePercent, 1); ?>%">
                <?php echo number_format($usagePercent, 2); ?>%
            </div>
        </div>
        <div class="storage-info">
            <strong><?php echo formatSize($totalSize); ?></strong> used of <strong><?php echo formatSize($storageLimit); ?></strong>
            <span style="color: var(--success); margin-left: 1rem;">
                <i class="fas fa-check-circle"></i>
                <?php echo formatSize($storageLimit - $totalSize); ?> available
            </span>
        </div>
    </div>

    <!-- Storage Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-file"></i>
            </div>
            <div class="stat-content">
                <div class="label">Active Files</div>
                <div class="value"><?php echo $activeFiles; ?></div>
                <div class="label" style="margin-top: 0.25rem;"><?php echo formatSize($activeSize); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-archive"></i>
            </div>
            <div class="stat-content">
                <div class="label">Archived Files</div>
                <div class="value"><?php echo $archivedFiles; ?></div>
                <div class="label" style="margin-top: 0.25rem;"><?php echo formatSize($archivedSize); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-trash"></i>
            </div>
            <div class="stat-content">
                <div class="label">In Trash</div>
                <div class="value"><?php echo $deletedFiles; ?></div>
                <div class="label" style="margin-top: 0.25rem;"><?php echo formatSize($deletedSize); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-database"></i>
            </div>
            <div class="stat-content">
                <div class="label">Total Files</div>
                <div class="value"><?php echo $totalFiles; ?></div>
                <div class="label" style="margin-top: 0.25rem;"><?php echo formatSize($totalSize); ?></div>
            </div>
        </div>
    </div>

    <!-- Storage by Category -->
    <div class="breakdown-section">
        <h4>
            <i class="fas fa-folder-open"></i> Storage by Category
        </h4>
        <table class="breakdown-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Files</th>
                    <th>Size</th>
                    <th class="progress-bar-cell">Usage</th>
                    <th class="percentage-cell">Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($categories->num_rows > 0): ?>
                    <?php while ($row = $categories->fetch_assoc()): 
                        $percent = $totalSize > 0 ? ($row['size'] / $totalSize) * 100 : 0;
                    ?>
                    <tr>
                        <td class="name-cell"><?php echo htmlspecialchars($row['category']); ?></td>
                        <td><?php echo $row['count']; ?> files</td>
                        <td><?php echo formatSize($row['size']); ?></td>
                        <td class="progress-bar-cell">
                            <div class="mini-progress">
                                <div class="mini-progress-fill" style="width: <?php echo $percent; ?>%"></div>
                            </div>
                        </td>
                        <td class="percentage-cell"><?php echo number_format($percent, 2); ?>%</td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem; color: var(--secondary);">
                            No files found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Storage by Folder -->
    <div class="breakdown-section">
        <h4>
            <i class="fas fa-folder"></i> Storage by Folder
        </h4>
        <table class="breakdown-table">
            <thead>
                <tr>
                    <th>Folder</th>
                    <th>Files</th>
                    <th>Size</th>
                    <th class="progress-bar-cell">Usage</th>
                    <th class="percentage-cell">Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($folders->num_rows > 0): ?>
                    <?php while ($row = $folders->fetch_assoc()): 
                        $percent = $totalSize > 0 ? ($row['size'] / $totalSize) * 100 : 0;
                    ?>
                    <tr>
                        <td class="name-cell"><?php echo htmlspecialchars($row['folder']); ?></td>
                        <td><?php echo $row['count']; ?> files</td>
                        <td><?php echo formatSize($row['size']); ?></td>
                        <td class="progress-bar-cell">
                            <div class="mini-progress">
                                <div class="mini-progress-fill" style="width: <?php echo $percent; ?>%"></div>
                            </div>
                        </td>
                        <td class="percentage-cell"><?php echo number_format($percent, 2); ?>%</td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem; color: var(--secondary);">
                            No files found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Storage by User -->
    <div class="breakdown-section">
        <h4>
            <i class="fas fa-users"></i> Storage by User
        </h4>
        <table class="breakdown-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Files</th>
                    <th>Size</th>
                    <th class="progress-bar-cell">Usage</th>
                    <th class="percentage-cell">Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users->num_rows > 0): ?>
                    <?php while ($row = $users->fetch_assoc()):
                        $percent = $totalSize > 0 ? ($row['size'] / $totalSize) * 100 : 0;
                    ?>
                    <tr>
                        <td class="name-cell"><?php echo htmlspecialchars($row['uploaded_by']); ?></td>
                        <td><?php echo $row['count']; ?> files</td>
                        <td><?php echo formatSize($row['size']); ?></td>
                        <td class="progress-bar-cell">
                            <div class="mini-progress">
                                <div class="mini-progress-fill" style="width: <?php echo $percent; ?>%"></div>
                            </div>
                        </td>
                        <td class="percentage-cell"><?php echo number_format($percent, 2); ?>%</td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem; color: var(--secondary);">
                            No files found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>