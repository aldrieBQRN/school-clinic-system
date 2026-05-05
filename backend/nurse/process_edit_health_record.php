<?php

/**
 * Update diagnosis and treatment for an existing health record.
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect the edited record values.
    $record_id = trim($_POST['record_id'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');

    if (empty($record_id) || empty($diagnosis) || empty($treatment)) {
        die("Error: Missing required fields for update.");
    }

    try {
        // Save the updated clinical notes.
        $query = "UPDATE health_records SET diagnosis = ?, treatment = ? WHERE record_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$diagnosis, $treatment, $record_id]);

        header("Location: ../../nurse/health_records.php?success=record_updated");
        exit();
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
} else {
    header("Location: ../../nurse/health_records.php");
    exit();
}
