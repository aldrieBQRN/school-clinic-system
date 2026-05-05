<?php

/**
 * Delete a medicine when it is no longer referenced in treatment history.
 */

session_start();

require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the selected medicine identifier.
    $med_id = trim($_POST['med_id'] ?? '');

    if (empty($med_id)) {
        die("Error: No Medicine ID provided for deletion.");
    }

    try {
        // Look up the medicine name so we can check treatment references.
        $getMedicine = $conn->prepare("SELECT name FROM medicines WHERE med_id = ?");
        $getMedicine->execute([$med_id]);
        $medicine_name = $getMedicine->fetchColumn();

        if (!$medicine_name) {
            header("Location: ../../nurse/inventory.php?error=medicine_not_found");
            exit();
        }

        // Block deletion if the medicine already appears in recorded treatments.
        $checkUsage = $conn->prepare("SELECT COUNT(*) FROM health_records WHERE treatment LIKE ?");
        $checkUsage->execute(['%' . $medicine_name . '%']);
        $usageCount = $checkUsage->fetchColumn();

        if ($usageCount > 0) {
            header("Location: ../../nurse/inventory.php?error=medicine_in_use&med_id=" . urlencode($med_id) . "&usage_count=" . urlencode($usageCount));
            exit();
        }

        // Remove the medicine only after confirming it is not referenced.
        $query = "DELETE FROM medicines WHERE med_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$med_id]);

        header("Location: ../../nurse/inventory.php?success=medicine_deleted");
        exit();
    } catch (PDOException $e) {
        die("Database Error: Cannot delete this item. Error details: " . $e->getMessage());
    }
} else {
    header("Location: ../../nurse/inventory.php");
    exit();
}
