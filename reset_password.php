<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['reset_email']);
    $newPassword = trim($_POST['new_password']);
    $confirmPassword = trim($_POST['confirm_new_password']);

    // Validation
    if (empty($email) || empty($newPassword) || empty($confirmPassword)) {
        $_SESSION['reset_error'] = "All fields are required!";
        header("Location: login.php");
        exit();
    }

    if ($newPassword !== $confirmPassword) {
        $_SESSION['reset_error'] = "Passwords do not match!";
        header("Location: login.php");
        exit();
    }

    if (strlen($newPassword) < 6) {
        $_SESSION['reset_error'] = "Password must be at least 6 characters long!";
        header("Location: login.php");
        exit();
    }

    // Check if user exists in the database
    $stmt = $conn->prepare("SELECT User_ID, Username, Email FROM user WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['reset_error'] = "User not found!";
        header("Location: login.php");
        exit();
    }

    $user = $result->fetch_assoc();
    $userId = $user['User_ID'];
    $username = $user['Username'];

    // Hash the new password using bcrypt for security
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

    // Update password in the user table
    $updateStmt = $conn->prepare("UPDATE user SET Password = ? WHERE User_ID = ?");
    $updateStmt->bind_param("si", $hashedPassword, $userId);

    if ($updateStmt->execute()) {
        // Automatically log the user in after password reset
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['user_id'] = $userId;
        $_SESSION['password_reset_success'] = "Your password has been successfully changed!";
        
        // Redirect to home page
        header("Location: home_page.php");
        exit();
    } else {
        $_SESSION['reset_error'] = "Failed to reset password. Please try again.";
        header("Location: login.php");
        exit();
    }

  if (isset($stmt)) {
    $stmt->close();
}
if (isset($updateStmt)) {
    $updateStmt->close();
}
$conn->close();
}