<?php

/**
 * Delete a student only when no visit history exists.
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the student identifier selected for deletion.
    $student_id = $_POST['student_id'] ?? '';

    if (empty($student_id)) {
        header("Location: ../../nurse/student_records.php?error=invalid_id");
        exit();
    }

    try {
        // Preserve clinical history by blocking deletion when visits already exist.
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM visits WHERE student_id = ?");
        $checkStmt->execute([$student_id]);
        $visitCount = $checkStmt->fetchColumn();

        if ($visitCount > 0) {
            header("Location: ../../nurse/student_records.php?error=has_medical_history&record_id=" . urlencode($student_id));
            exit();
        }

        // Remove the student only when there is no linked visit history.
        $query = "DELETE FROM students WHERE student_id = :student_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            header("Location: ../../nurse/student_records.php?success=deleted");
            exit();
        } else {
            header("Location: ../../nurse/student_records.php?error=delete_failed");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ../../nurse/student_records.php?error=db_error");
        exit();
    }
} else {
    header("Location: ../../nurse/student_records.php");
    exit();
}
