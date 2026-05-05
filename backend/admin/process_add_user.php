<?php

/**
 * Create a new admin or nurse account.
 */

session_start();

require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and trim the submitted account details.
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    if (empty($name) || empty($username) || empty($password) || empty($role) || empty($status)) {
        header("Location: ../../admin/manage_users.php?error=empty_fields");
        exit();
    }

    try {
        // Keep usernames unique across the user table.
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->fetchColumn() > 0) {
            header("Location: ../../admin/manage_users.php?error=username_taken");
            exit();
        }

        // Hash the password before storing it.
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $insertQuery = "INSERT INTO users (name, username, password, role, status) VALUES (?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->execute([$name, $username, $hashed_password, $role, $status]);

        header("Location: ../../admin/manage_users.php?success=user_added");
        exit();
    } catch (PDOException $e) {
        // Keep the response generic to avoid exposing database details.
        header("Location: ../../admin/manage_users.php?error=db_fail");
        exit();
    }
} else {
    header("Location: ../../admin/manage_users.php");
    exit();
}
