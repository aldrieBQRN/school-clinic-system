<?php

/**
 * Log a new patient visit into the active queue.
 */

session_start();

require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect the patient triage details.
    $student_id = trim($_POST['student_id'] ?? '');
    $complaint = trim($_POST['complaint'] ?? '');
    $temperature = trim($_POST['temperature'] ?? '');

    $height = !empty($_POST['height']) ? trim($_POST['height']) : null;
    $weight = !empty($_POST['weight']) ? trim($_POST['weight']) : null;
    $nurse_notes = trim($_POST['nurse_notes'] ?? '');

    // Use Manila time for consistent visit timestamps.
    date_default_timezone_set('Asia/Manila');
    $time_in = date('H:i:s');
    $date_logged = date('Y-m-d');

    if (empty($student_id) || empty($complaint) || empty($temperature)) {
        die("Error: Please select a patient and provide the chief complaint and temperature.");
    }

    try {
        // Insert the visit as an active queue item.
        $query = "INSERT INTO visits (
                    student_id,
                    complaint,
                    temperature,
                    height,
                    weight,
                    nurse_notes,
                    time_in,
                    date_logged,
                    status
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')";

        $stmt = $conn->prepare($query);

        $stmt->execute([
            $student_id,
            $complaint,
            $temperature,
            $height,
            $weight,
            $nurse_notes,
            $time_in,
            $date_logged
        ]);

        header("Location: ../../nurse/visits.php?success=visit_logged");
        exit();
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
} else {
    header("Location: ../../nurse/visits.php");
    exit();
}
