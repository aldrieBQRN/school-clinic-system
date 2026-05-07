<?php

/**
 * config/auth_check.php
 *
 * Enforce authentication and simple role-based access control for pages
 * under `/admin/` and `/nurse/`. Redirects unauthenticated users.
 *
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $login_path = (isset($is_subfolder) && $is_subfolder) ? '../../index.php' : '../index.php';

    header("Location: " . $login_path . "?error=unauthorized");
    exit();
}

$current_uri = strtolower($_SERVER['REQUEST_URI']);

if (strpos($current_uri, '/admin/') !== false && $_SESSION['role'] !== 'Admin') {
    $redirect_path = (isset($is_subfolder) && $is_subfolder) ? '../../nurse/dashboard.php' : '../nurse/dashboard.php';
    header("Location: " . $redirect_path);
    exit();
}

if (strpos($current_uri, '/nurse/') !== false && $_SESSION['role'] !== 'Nurse') {
    $redirect_path = (isset($is_subfolder) && $is_subfolder) ? '../../admin/dashboard.php' : '../admin/dashboard.php';
    header("Location: " . $redirect_path);
    exit();
}
