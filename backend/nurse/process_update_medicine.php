<?php

/**
 * Update medicine details and refresh stock status.
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';
require_once '../../config/medicine_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect the edited medicine details.
    $med_id = trim($_POST['med_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $expiration = trim($_POST['expiration'] ?? '');

    if (empty($med_id) || empty($name) || empty($category) || empty($expiration)) {
        die("Error: Missing required fields for update.");
    }

    // Recompute stock status after the quantity changes.
    $status = getMedicineStatus($quantity);

    try {
        // Save the updated medicine record.
        $query = "UPDATE medicines SET name = ?, category = ?, quantity = ?, status = ?, expiration = ? WHERE med_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$name, $category, $quantity, $status, $expiration, $med_id]);

        header("Location: ../../nurse/inventory.php?success=medicine_updated");
        exit();
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
} else {
    header("Location: ../../nurse/inventory.php");
    exit();
}
