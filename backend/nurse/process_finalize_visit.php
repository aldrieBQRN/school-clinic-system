<?php

/**
 * Finalize a visit, create the health record, and update dispensed medicine stock.
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect the visit outcome details and any dispensed medicines.
    $visit_id = $_POST['visit_id'] ?? '';
    $student_id = $_POST['student_id'] ?? '';
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $medicine_ids = $_POST['medicine_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if (empty($visit_id) || empty($student_id) || empty($diagnosis) || empty($treatment)) {
        die("Error: Missing required fields.");
    }

    try {
        // Update all related tables in a single transaction.
        $conn->beginTransaction();

        // Insert the permanent health record.
        $stmtRecord = $conn->prepare("INSERT INTO health_records (visit_id, student_id, diagnosis, treatment) VALUES (?, ?, ?, ?)");
        $stmtRecord->execute([$visit_id, $student_id, $diagnosis, $treatment]);

        // Mark the visit as completed in the queue.
        $stmtVisit = $conn->prepare("UPDATE visits SET status = 'Completed' WHERE visit_id = ?");
        $stmtVisit->execute([$visit_id]);

        // Deduct any dispensed medicine and lock rows to avoid race conditions.
        if (!empty($medicine_ids)) {
            for ($i = 0; $i < count($medicine_ids); $i++) {
                $med_id = $medicine_ids[$i];
                $qty = (int)$quantities[$i];

                if (empty($med_id) || $qty <= 0) {
                    continue;
                }

                // Lock the stock row before checking availability.
                $stmtLock = $conn->prepare("SELECT quantity FROM medicines WHERE med_id = ? FOR UPDATE");
                $stmtLock->execute([$med_id]);
                $currentQty = $stmtLock->fetchColumn();

                if ($currentQty === false) {
                    throw new Exception("Medicine ID: $med_id not found.");
                }
                if ($currentQty < $qty) {
                    throw new Exception("Insufficient stock for Medicine ID: $med_id. Available: $currentQty, Requested: $qty");
                }

                // Apply the stock deduction.
                $stmtUpdateStock = $conn->prepare("UPDATE medicines SET quantity = quantity - ? WHERE med_id = ?");
                $stmtUpdateStock->execute([$qty, $med_id]);
            }
        }

        $conn->commit();
        header("Location: ../../nurse/visits.php?success=consultation_completed");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        die("Database Error: " . $e->getMessage());
    }
} else {
    header("Location: ../../nurse/visits.php");
    exit();
}
