<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userCount = $conn->query("SELECT COUNT(*) as count FROM user")->fetch_assoc()['count'];
$maxUsers = 3;

// ADD NEW USER
if (isset($_POST['signup'])) {
    if ($userCount >= $maxUsers) {
        $error = "Maximum user limit reached! Only $maxUsers users are allowed.";
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (empty($username) || empty($email) || empty($password)) {
            $error = "All fields are required.";
        } else {
            $check = $conn->prepare("SELECT * FROM user WHERE Email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            
            if ($check->get_result()->num_rows > 0) {
                $error = "Email already exists.";
            } else {
                $hashedPassword = hashPassword($password);
                $stmt = $conn->prepare("INSERT INTO user (Username, Email, Password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $email, $hashedPassword);
                
                if ($stmt->execute()) {
                    $success = "User added successfully!";
                    $userCount++;
                    logActivity($conn, 'create_user', null, null, "Created user: $username");
                } else {
                    $error = "Error adding user.";
                }
                $stmt->close();
            }
            $check->close();
        }
    }
}

// EDIT USER
if (isset($_POST['edit_user'])) {
    $userId = intval($_POST['user_id']);
    $username = trim($_POST['edit_username']);
    $email = trim($_POST['edit_email']);
    $newPassword = trim($_POST['edit_password']);

    if (empty($username) || empty($email)) {
        $error = "Username and email are required.";
    } else {
        if (!empty($newPassword)) {
            $hashedPassword = hashPassword($newPassword);
            $stmt = $conn->prepare("UPDATE user SET Username = ?, Email = ?, Password = ? WHERE User_ID = ?");
            $stmt->bind_param("sssi", $username, $email, $hashedPassword, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE user SET Username = ?, Email = ? WHERE User_ID = ?");
            $stmt->bind_param("ssi", $username, $email, $userId);
        }
        
        if ($stmt->execute()) {
            $success = "User updated successfully!";
            logActivity($conn, 'edit_user', $userId, null, "Updated user: $username");
        } else {
            $error = "Error updating user.";
        }
        $stmt->close();
    }
}

// DELETE USER
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    if ($id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account!";
    } else {
        $conn->query("DELETE FROM user WHERE User_ID = $id");
        $success = "User deleted successfully!";
        $userCount--;
        logActivity($conn, 'delete_user', $id, null, "Deleted user account");
    }
}

$users = $conn->query("SELECT * FROM user ORDER BY User_ID DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management - CIT File Management</title>
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
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
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

.user-limit-banner {
    background: white;
    padding: 1.25rem 1.5rem;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.user-limit-banner.warning {
    border-left: 4px solid var(--warning);
}

.user-limit-banner.danger {
    border-left: 4px solid var(--danger);
}

.user-count {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
}

.action-bar {
    background: white;
    padding: 1.25rem 1.5rem;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.btn-add-user {
    background: linear-gradient(135deg, var(--success), #059669);
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

.btn-add-user:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

.btn-add-user:disabled {
    background: var(--secondary);
    cursor: not-allowed;
}

.users-table-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table {
    margin: 0;
}

.table thead {
    background: var(--light);
}

.table th {
    padding: 1rem 1.5rem;
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
}

.table td {
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--border);
    vertical-align: middle;
}

.table tbody tr {
    transition: background 0.2s;
}

.table tbody tr:hover {
    background: var(--light);
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-details .name {
    font-weight: 600;
    color: var(--dark);
}

.user-details .email {
    font-size: 0.875rem;
    color: var(--secondary);
}

.badge {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
}

.badge-primary {
    background: rgba(37, 99, 235, 0.1);
    color: var(--primary);
}

.action-btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-edit {
    background: var(--warning);
    color: white;
}

.btn-edit:hover {
    background: #d97706;
    color: white;
}

.btn-delete {
    background: var(--danger);
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
    color: white;
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

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 1rem;
    }
    
    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
</head>
<body>

<header class="modern-header">
    <div class="header-content">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-users-cog"></i>
            </div>
            <div class="logo-text">
                <h1>User Management</h1>
                <p>Manage system users and permissions</p>
            </div>
        </div>
        <a href="home_page.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
    </div>
</header>

<div class="main-container">
    
    <div class="user-limit-banner <?php echo $userCount >= $maxUsers ? 'danger' : ($userCount >= $maxUsers - 1 ? 'warning' : ''); ?>">
        <div>
            <i class="fas fa-info-circle me-2"></i>
            <strong>User Limit:</strong> This system supports a maximum of <?php echo $maxUsers; ?> users.
        </div>
        <div class="user-count">
            <?php echo $userCount; ?> / <?php echo $maxUsers; ?>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <div class="action-bar">
        <h4 style="margin: 0; color: var(--dark);">
            <i class="fas fa-users"></i> Registered Users
        </h4>
        <button type="button" class="btn-add-user" data-bs-toggle="modal" data-bs-target="#addUserModal" <?php echo $userCount >= $maxUsers ? 'disabled' : ''; ?>>
            <i class="fas fa-user-plus"></i> 
            <?php echo $userCount >= $maxUsers ? 'User Limit Reached' : 'Add New User'; ?>
        </button>
    </div>

    <div class="users-table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($users->num_rows > 0): ?>
                <?php while ($row = $users->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($row['Username'], 0, 1)); ?>
                                </div>
                                <div class="user-details">
                                    <div class="name"><?php echo htmlspecialchars($row['Username']); ?></div>
                                    <div class="email">ID: <?php echo $row['User_ID']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($row['Email']); ?></td>
                        <td>
                            <?php if ($row['User_ID'] == $_SESSION['user_id']): ?>
                                <span class="badge badge-primary">
                                    <i class="fas fa-user-check"></i> Current User
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="action-btn btn-edit" onclick="openEditModal(<?php echo $row['User_ID']; ?>, '<?php echo addslashes($row['Username']); ?>', '<?php echo addslashes($row['Email']); ?>')">
                                <i class="fas fa-edit"></i> Edit
                            </button>

                            <?php if ($row['User_ID'] != $_SESSION['user_id']): ?>
                                <a href="users.php?delete=<?php echo $row['User_ID']; ?>" class="action-btn btn-delete" onclick="return confirm('Are you sure you want to delete this user?');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center" style="padding: 3rem;">
                        <i class="fas fa-users-slash" style="font-size: 4rem; color: var(--secondary); opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                        <h4 style="color: var(--dark);">No users found</h4>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">
                <i class="fas fa-user-plus me-2"></i>Add New User
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-user me-2"></i>Username
                </label>
                <input type="text" name="username" class="form-control" placeholder="Enter username" required>
            </div>
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-envelope me-2"></i>Email Address
                </label>
                <input type="email" name="email" class="form-control" placeholder="Enter email address" required>
            </div>
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-lock me-2"></i>Password
                </label>
                <input type="password" name="password" class="form-control" placeholder="Enter password (min 6 characters)" minlength="6" required>
                <small class="text-muted">Password must be at least 6 characters long</small>
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" name="signup" class="btn btn-success">
                <i class="fas fa-save"></i> Create User
            </button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </form>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">
                <i class="fas fa-user-edit me-2"></i>Edit User
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-user me-2"></i>Username
                </label>
                <input type="text" name="edit_username" id="edit_username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-envelope me-2"></i>Email Address
                </label>
                <input type="email" name="edit_email" id="edit_email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-lock me-2"></i>New Password
                </label>
                <input type="password" name="edit_password" id="edit_password" class="form-control" minlength="6">
                <small class="text-muted">Leave blank to keep current password</small>
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" name="edit_user" class="btn btn-warning">
                <i class="fas fa-save"></i> Update User
            </button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEditModal(userId, username, email) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_password').value = '';
    
    const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    editModal.show();
}
</script>
</body>
</html>