<?php

/**
 * Register a new student in the clinic system.
 */

session_start();

require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect the submitted student details.
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $course = trim($_POST['course']);
    $year_level = trim($_POST['year_level']);
    $gender = trim($_POST['gender']);
    $birthdate = trim($_POST['birthdate']);
    $guardian_name = trim($_POST['guardian_name']);
    $relationship = trim($_POST['relationship']);
    $guardian_contact = trim($_POST['guardian_contact']);

    if (empty($first_name) || empty($last_name) || empty($course) || empty($year_level) || empty($birthdate)) {
        die("Error: Please fill in all required personal information.");
    }

    try {
        // Store the student profile in the database.
        $query = "INSERT INTO students (
                    first_name,
                    last_name,
                    course,
                    year_level,
                    gender,
                    birthdate,
                    guardian_name,
                    relationship,
                    guardian_contact
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);

        $stmt->execute([
            $first_name,
            $last_name,
            $course,
            $year_level,
            $gender,
            $birthdate,
            $guardian_name,
            $relationship,
            $guardian_contact
        ]);

        header("Location: ../../nurse/student_records.php?success=student_registered");
        exit();
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
} else {
    header("Location: ../../nurse/student_records.php");
    exit();
}
