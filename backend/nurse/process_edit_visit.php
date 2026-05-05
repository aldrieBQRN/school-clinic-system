<?php

/**
 * Update an existing visit record and return to the source list.
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect the edited visit details.
    $visit_id = $_POST['visit_id'] ?? '';
    $complaint = trim($_POST['complaint'] ?? '');
    $temperature = trim($_POST['temperature'] ?? '');
    $height = trim($_POST['height'] ?? null);
    $weight = trim($_POST['weight'] ?? null);
    $nurse_notes = trim($_POST['nurse_notes'] ?? '');

    // Return to the list the user edited from.
    $source = $_POST['source'] ?? 'visit_log';
    $redirect_page = ($source === 'visits') ? 'visits.php' : 'visit_log.php';

    if (empty($visit_id) || empty($complaint) || empty($temperature)) {
        header("Location: ../../nurse/$redirect_page?error=empty_fields");
        exit();
    }

    try {
        // Save the updated visit details.
        $query = "UPDATE visits SET
                    complaint = :complaint,
                    temperature = :temperature,
                    height = :height,
                    weight = :weight,
                    nurse_notes = :nurse_notes
                  WHERE visit_id = :visit_id";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':complaint' => $complaint,
            ':temperature' => $temperature,
            ':height' => $height,
            ':weight' => $weight,
            ':nurse_notes' => $nurse_notes,
            ':visit_id' => $visit_id
        ]);

        header("Location: ../../nurse/$redirect_page?success=updated");
        exit();
    } catch (PDOException $e) {
        // Log the failure and show a generic database error.
        error_log("Update Visit Error: " . $e->getMessage());
        header("Location: ../../nurse/$redirect_page?error=db_error");
        exit();
    }
} else {
    header("Location: ../../nurse/visit_log.php");
    exit();
}
