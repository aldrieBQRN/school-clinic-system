<?php

/**
 * Update a student profile.
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect the edited student details.
    $student_id = $_POST['student_id'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $course     = trim($_POST['course'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $guardian   = trim($_POST['guardian_name'] ?? '');
    $relation   = trim($_POST['relationship'] ?? '');
    $contact    = trim($_POST['guardian_contact'] ?? '');

    if (empty($student_id) || empty($first_name) || empty($last_name)) {
        header("Location: ../../nurse/student_records.php?error=empty_fields");
        exit();
    }

    try {
        // Persist the updated student profile.
        $query = "UPDATE students SET
                    first_name = :first_name,
                    last_name = :last_name,
                    course = :course,
                    year_level = :year_level,
                    guardian_name = :guardian,
                    relationship = :relation,
                    guardian_contact = :contact
                  WHERE student_id = :student_id";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':course'     => $course,
            ':year_level' => $year_level,
            ':guardian'   => $guardian,
            ':relation'   => $relation,
            ':contact'    => $contact,
            ':student_id' => $student_id
        ]);

        header("Location: ../../nurse/student_records.php?success=updated");
        exit();
    } catch (PDOException $e) {
        // Log the failure and return a generic database error.
        error_log("Update Error: " . $e->getMessage());
        header("Location: ../../nurse/student_records.php?error=db_error");
        exit();
    }
} else {
    header("Location: ../../nurse/student_records.php");
    exit();
}
