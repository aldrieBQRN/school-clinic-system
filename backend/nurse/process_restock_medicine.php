<?php

/**
 * Increase medicine stock and refresh its availability status.
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';
require_once '../../config/medicine_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the medicine identifier and restock amount.
    $med_id = trim($_POST['med_id'] ?? '');
    $add_quantity = (int)($_POST['add_quantity'] ?? 0);

    if (empty($med_id) || $add_quantity <= 0) {
        die("Error: Please provide a valid quantity to restock.");
    }

    try {
        // Load the current stock before applying the restock amount.
        $stmtCheck = $conn->prepare("SELECT quantity FROM medicines WHERE med_id = ?");
        $stmtCheck->execute([$med_id]);
        $current_qty = $stmtCheck->fetchColumn();

        // Recalculate the final quantity and status after restocking.
        $new_qty = $current_qty + $add_quantity;
        $status = getMedicineStatus($new_qty);

        // Persist the updated inventory totals.
        $stmtUpdate = $conn->prepare("UPDATE medicines SET quantity = ?, status = ? WHERE med_id = ?");
        $stmtUpdate->execute([$new_qty, $status, $med_id]);

        header("Location: ../../nurse/inventory.php?success=restocked");
        exit();
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
} else {
    header("Location: ../../nurse/inventory.php");
    exit();
}
