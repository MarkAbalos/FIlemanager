<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// RESTORE FILE
if (isset($_GET['restore'])) {
    $id = intval($_GET['restore']);
    $stmt = $conn->prepare("UPDATE input SET is_deleted = 0, deleted_at = NULL WHERE id_n = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        logActivity($conn, 'restore', $id, null, 'File restored from trash');
        $success = "File restored successfully!";
    }
    $stmt->close();
}

// PERMANENT DELETE
if (isset($_GET['permanent_delete'])) {
    $id = intval($_GET['permanent_delete']);
    $stmt = $conn->prepare("SELECT filepath, filename FROM input WHERE id_n = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (file_exists($row['filepath'])) {
            unlink($row['filepath']);
        }
        $deleteStmt = $conn->prepare("DELETE FROM input WHERE id_n = ?");
        $deleteStmt->bind_param("i", $id);
        $deleteStmt->execute();
        logActivity($conn, 'permanent_delete', $id, $row['filename'], 'File permanently deleted');
        $success = "File permanently deleted!";
        $deleteStmt->close();
    }
    $stmt->close();
}

// EMPTY TRASH
if (isset($_POST['empty_trash'])) {
    $result = $conn->query("SELECT id_n, filepath, filename FROM input WHERE is_deleted = 1");
    $deletedCount = 0;
    while ($row = $result->fetch_assoc()) {
        if (file_exists($row['filepath'])) {
            unlink($row['filepath']);
        }
        $deletedCount++;
    }
    $conn->query("DELETE FROM input WHERE is_deleted = 1");
    logActivity($conn, 'empty_trash', null, null, "Emptied trash ($deletedCount files)");
    $success = "Trash emptied successfully! ($deletedCount files deleted)";
}

// AUTO DELETE after 30 days
$autoDeleted = $conn->query("SELECT COUNT(*) as count FROM input WHERE is_deleted = 1 AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'];
if ($autoDeleted > 0) {
    $conn->query("DELETE FROM input WHERE is_deleted = 1 AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    logActivity($conn, 'auto_delete', null, null, "Auto-deleted $autoDeleted expired files");
}

// FETCH DELETED FILES
$result = $conn->query("SELECT *, DATEDIFF(DATE_ADD(deleted_at, INTERVAL 30 DAY), NOW()) as days_left FROM input WHERE is_deleted = 1 ORDER BY deleted_at DESC");

// Get statistics
$totalDeleted = $conn->query("SELECT COUNT(*) as count FROM input WHERE is_deleted = 1")->fetch_assoc()['count'];
$deletedSize = $conn->query("SELECT SUM(filesize) as size FROM input WHERE is_deleted = 1")->fetch_assoc()['size'] ?? 0;

function formatSize($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . " GB";
    elseif ($bytes >= 1048576) return round($bytes / 1048576, 2) . " MB";
    elseif ($bytes >= 1024) return round($bytes / 1024, 2) . " KB";
    else return "$bytes bytes";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recently Deleted - CIT File Management</title>
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
    background: linear-gradient(135deg, var(--danger), #dc2626);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow: 0 4px 6px rgba(239, 68, 68, 0.3);
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

.warning-banner {
    background: white;
    padding: 1.5rem;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-left: 4px solid var(--danger);
}

.warning-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.2));
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--danger);
}

.warning-content h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.warning-content p {
    font-size: 0.875rem;
    color: var(--secondary);
    margin: 0;
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
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--dark);
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.action-bar {
    background: white;
    padding: 1.25rem 1.5rem;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}

.empty-trash-btn {
    background: linear-gradient(135deg, var(--danger), #dc2626);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.empty-trash-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
}

.file-table-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.file-table {
    width: 100%;
    border-collapse: collapse;
}

.file-table thead {
    background: var(--light);
}

.file-table th {
    padding: 1rem 1.5rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.file-table td {
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--border);
}

.file-table tbody tr {
    transition: background 0.2s;
}

.file-table tbody tr:hover {
    background: var(--light);
}

.file-name {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.file-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.file-info {
    display: flex;
    flex-direction: column;
}

.file-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.file-meta {
    font-size: 0.875rem;
    color: var(--secondary);
}

.badge {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
}

.badge-secondary {
    background: rgba(100, 116, 139, 0.1);
    color: var(--secondary);
}

.days-left {
    font-weight: 600;
}

.days-left.warning {
    color: var(--warning);
}

.days-left.danger {
    color: var(--danger);
}

.action-btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #059669;
    color: white;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state i {
    font-size: 5rem;
    color: rgba(16, 185, 129, 0.3);
    margin-bottom: 1.5rem;
}

.empty-state h4 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: var(--secondary);
}

@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<header class="modern-header">
    <div class="header-content">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-trash-restore"></i>
            </div>
            <div class="logo-text">
                <h1>Recently Deleted</h1>
                <p>Files will be permanently deleted after 30 days</p>
            </div>
        </div>
        <a href="home_page.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
    </div>
</header>

<div class="main-container">
    
    <div class="warning-banner">
        <div class="warning-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="warning-content">
            <h3>Auto-Delete Warning</h3>
            <p>Files in Recently Deleted will be permanently removed after 30 days. Restore important files before they're deleted forever.</p>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-trash"></i>
            </div>
            <div class="stat-content">
                <div class="label">Deleted Files</div>
                <div class="value"><?php echo $totalDeleted; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-database"></i>
            </div>
            <div class="stat-content">
                <div class="label">Trash Size</div>
                <div class="value"><?php echo formatSize($deletedSize); ?></div>
            </div>
        </div>
    </div>

    <?php if ($result->num_rows > 0): ?>
        <div class="action-bar">
            <form method="POST" style="display: inline;">
                <button type="submit" name="empty_trash" class="empty-trash-btn" onclick="return confirm('Permanently delete all files? This cannot be undone!');">
                    <i class="fas fa-trash-alt"></i> Empty Trash
                </button>
            </form>
        </div>
    <?php endif; ?>

    <div class="file-table-container">
        <?php if ($result->num_rows > 0): ?>
            <table class="file-table">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Category</th>
                        <th>Size</th>
                        <th>Deleted On</th>
                        <th>Auto-Delete In</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): 
                        $daysLeft = max(0, $row['days_left']);
                        $daysClass = $daysLeft <= 7 ? 'danger' : ($daysLeft <= 14 ? 'warning' : '');
                    ?>
                        <tr>
                            <td>
                                <div class="file-name">
                                    <div class="file-icon">
                                        <i class="fas fa-file"></i>
                                    </div>
                                    <div class="file-info">
                                        <div class="file-title"><?php echo htmlspecialchars($row['filename']); ?></div>
                                        <div class="file-meta">Deleted by <?php echo htmlspecialchars($row['uploaded_by'] ?? 'Unknown'); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-secondary">
                                    <?php echo htmlspecialchars($row['category']); ?>
                                </span>
                            </td>
                            <td><?php echo formatSize($row['filesize']); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($row['deleted_at'])); ?></td>
                            <td>
                                <span class="days-left <?php echo $daysClass; ?>">
                                    <?php echo $daysLeft; ?> days
                                </span>
                            </td>
                            <td>
                                <a href="recently_deleted.php?restore=<?php echo $row['id_n']; ?>" class="action-btn btn-success">
                                    <i class="fas fa-undo"></i> Restore
                                </a>
                                <a href="recently_deleted.php?permanent_delete=<?php echo $row['id_n']; ?>" class="action-btn btn-danger" onclick="return confirm('Permanently delete? This cannot be undone!');">
                                    <i class="fas fa-times"></i> Delete Forever
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h4>Trash is Empty</h4>
                <p>Your Recently Deleted folder is empty. All your files are safe!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>