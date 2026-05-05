<?php

/**
 * Add a new medicine to inventory.
 */

session_start();

require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';
require_once '../../config/medicine_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect the submitted medicine details.
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $expiration = trim($_POST['expiration'] ?? '');

    if (empty($name) || empty($category) || empty($expiration)) {
        die("Error: Please fill in all required fields.");
    }

    // Derive stock status from the submitted quantity.
    $status = getMedicineStatus($quantity);

    try {
        $query = "INSERT INTO medicines (name, category, quantity, status, expiration) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->execute([$name, $category, $quantity, $status, $expiration]);

        header("Location: ../../nurse/inventory.php?success=medicine_added");
        exit();
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
} else {
    header("Location: ../../nurse/inventory.php");
    exit();
}
