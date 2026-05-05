<?php

/**
 * Delete a health record while keeping at least one record per visit.
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the health record selected for deletion.
    $record_id = trim($_POST['record_id'] ?? '');

    if (empty($record_id)) {
        die("Error: No Record ID provided for deletion.");
    }

    try {
        // Find the parent visit so we can avoid orphaning it.
        $checkVisit = $conn->prepare("SELECT visit_id FROM health_records WHERE record_id = ?");
        $checkVisit->execute([$record_id]);
        $visit_id = $checkVisit->fetchColumn();

        if (!$visit_id) {
            header("Location: ../../nurse/health_records.php?error=record_not_found");
            exit();
        }

        // Keep at least one health record linked to the visit.
        $countRecords = $conn->prepare("SELECT COUNT(*) FROM health_records WHERE visit_id = ?");
        $countRecords->execute([$visit_id]);
        $recordCount = $countRecords->fetchColumn();

        if ($recordCount <= 1) {
            header("Location: ../../nurse/health_records.php?error=last_record_orphan&record_id=" . urlencode($record_id));
            exit();
        }

        // Delete the selected record once the visit linkage is safe.
        $query = "DELETE FROM health_records WHERE record_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$record_id]);

        header("Location: ../../nurse/health_records.php?success=record_deleted");
        exit();
    } catch (PDOException $e) {
        die("Database Error: Cannot delete this record. " . $e->getMessage());
    }
} else {
    header("Location: ../../nurse/health_records.php");
    exit();
}
