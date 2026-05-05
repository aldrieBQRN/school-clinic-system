<?php

/**
 * backend/auth_login.php
 *
 * Authenticate a user and create a session, then redirect by role.
 * Keeps responses generic on failure to avoid leaking details.
 *
 * @package KCCF Clinic System
 * @author Dev
 * @since 2024-01-01
 */

session_start();

require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect the submitted login credentials.
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        header("Location: ../index.php?error=empty");
        exit();
    }

    try {
        // Load the user record for authentication.
        $stmt = $conn->prepare("SELECT user_id, name, role, password, status FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            // Block inactive accounts from logging in.
            if ($user['status'] === 'Inactive') {
                header("Location: ../index.php?error=inactive");
                exit();
            }

            // Verify the password before creating the session.
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                // Route users to the dashboard for their role.
                if ($user['role'] === 'Admin') {
                    header("Location: ../admin/dashboard.php");
                } elseif ($user['role'] === 'Nurse') {
                    header("Location: ../nurse/dashboard.php");
                } else {
                    header("Location: ../index.php?error=invalid");
                }
                exit();
            } else {
                header("Location: ../index.php?error=invalid&last_user=" . urlencode($username));
                exit();
            }
        } else {
            // Keep the response generic when the username is not found.
            header("Location: ../index.php?error=invalid&last_user=" . urlencode($username));
            exit();
        }
    } catch (PDOException $e) {
        // Avoid exposing database details in the browser.
        die("System Error: " . $e->getMessage());
    }
} else {
    header("Location: ../index.php");
    exit();
}
