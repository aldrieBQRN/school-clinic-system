<?php

/**
 * Update an existing admin or nurse account.
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

// Collect the submitted account details.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    $new_password = $_POST['new_password'];

    if (empty($user_id) || empty($name) || empty($username) || empty($role) || empty($status)) {
        header("Location: ../../admin/manage_users.php?error=empty_fields");
        exit();
    }

    // Prevent duplicate usernames when editing a different account.
    try {
        // Prevent duplicate usernames when editing an existing account.
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
        $checkStmt->execute([$username, $user_id]);
        if ($checkStmt->fetchColumn() > 0) {
            header("Location: ../../admin/manage_users.php?error=username_taken");
            exit();
        }

        $params = [$name, $username, $role, $status];
        $sql = "UPDATE users SET name = ?, username = ?, role = ?, status = ?";
        // Only update the password when a new one is provided.
        if (!empty($new_password)) {
            // Only update the password when a new one is provided.
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $params[] = $hashed_password;
        }

        $sql .= " WHERE user_id = ?";
        $params[] = $user_id;

        $updateStmt = $conn->prepare($sql);
        $updateStmt->execute($params);

        header("Location: ../../admin/manage_users.php?success=user_updated");
        // Keep the response generic to avoid exposing database details.
        exit();
    } catch (PDOException $e) {
        header("Location: ../../admin/manage_users.php?error=db_fail");
        exit();
    }
} else {
    header("Location: ../../admin/manage_users.php");
    exit();
}
