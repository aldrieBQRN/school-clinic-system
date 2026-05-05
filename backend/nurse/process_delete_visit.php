<?php

/**
 * Delete a visit from the active queue or visit archive.
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the visit selected for deletion.
    $visit_id = $_POST['visit_id'] ?? '';

    // Return to the list the action came from.
    $source = $_POST['source'] ?? 'visit_log';
    $redirect_page = ($source === 'visits') ? 'visits.php' : 'visit_log.php';

    if (empty($visit_id)) {
        header("Location: ../../nurse/$redirect_page?error=invalid_id");
        exit();
    }

    try {
        // Remove the selected visit.
        $query = "DELETE FROM visits WHERE visit_id = :visit_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':visit_id', $visit_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            header("Location: ../../nurse/$redirect_page?success=deleted");
            exit();
        } else {
            header("Location: ../../nurse/$redirect_page?error=delete_failed");
            exit();
        }
    } catch (PDOException $e) {
        // Map foreign key errors to a friendlier message.
        if ($e->getCode() == '23000') {
            header("Location: ../../nurse/$redirect_page?error=has_health_record");
        } else {
            header("Location: ../../nurse/$redirect_page?error=db_error");
        }
        exit();
    }
} else {
    header("Location: ../../nurse/visit_log.php");
    exit();
}
