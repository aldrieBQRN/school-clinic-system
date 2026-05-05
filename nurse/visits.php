<?php
// Restrict page access and initialize the database connection
require_once '../config/db_connect.php';
require_once '../config/auth_check.php';

// Pagination and search
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the active-queue query filters
$where_clauses = ["v.status = 'Active'"];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_id LIKE :search OR v.visit_id LIKE :search OR v.complaint LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

try {
    // Count active queue records
    $count_query = "
        SELECT COUNT(*)
        FROM visits v
        JOIN students s ON v.student_id = s.student_id
        $where_sql
    ";
    $count_stmt = $conn->prepare($count_query);
    foreach ($params as $key => $val) {
        $count_stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch the current page of active visits
    $active_visits_query = "
        SELECT v.*, s.first_name, s.last_name, s.student_id as real_student_id, s.course, s.year_level
        FROM visits v
        JOIN students s ON v.student_id = s.student_id
        $where_sql
        ORDER BY v.date_logged ASC, v.time_in ASC
        LIMIT :limit OFFSET :offset
    ";
    $stmtVisits = $conn->prepare($active_visits_query);
    foreach ($params as $key => $val) {
        $stmtVisits->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmtVisits->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtVisits->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtVisits->execute();
    $active_visits = $stmtVisits->fetchAll();

    // Load students for the visit modal
    $students_query = "SELECT student_id, first_name, last_name FROM students ORDER BY last_name ASC";
    $stmtStudents = $conn->prepare($students_query);
    $stmtStudents->execute();
    $students = $stmtStudents->fetchAll();

    // Load recent complaint suggestions
    $comp_query = "SELECT complaint FROM visits WHERE complaint != '' AND complaint IS NOT NULL GROUP BY complaint ORDER BY MAX(visit_id) DESC LIMIT 50";
    $stmt_comp = $conn->prepare($comp_query);
    $stmt_comp->execute();
    $recent_complaints = $stmt_comp->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Visits & Triage | KCCF Clinic (Nurse)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .data-table {
            width: 100%;
        }

        @media (min-width: 1025px) {
            .data-table {
                min-width: 950px;
            }
        }

        .table-responsive {
            padding-bottom: 80px;
            overflow-x: auto;
        }

        @media (max-width: 1024px) {
            .table-responsive {
                overflow-x: visible;
                padding-bottom: 20px;
            }
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal-box {
            background: var(--bg-base);
            width: 100%;
            max-width: 600px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transform: translateY(20px);
            transition: transform 0.2s ease-in-out;
            overflow: visible;
            max-height: 90vh;
        }

        .modal-overlay.active .modal-box {
            transform: translateY(0);
        }

        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-card);
            position: sticky;
            top: 0;
            z-index: 10;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .modal-title {
            font-family: 'DM Serif Display', serif;
            font-size: 20px;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 22px;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
        }

        .modal-close:hover {
            background: #FEE2E2;
            color: #DC2626;
        }

        .modal-body {
            padding: 24px;
            max-height: 65vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            background: var(--bg-card);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        /* Three-dot action menu */
        .dropdown-wrapper {
            position: relative;
            display: inline-block;
            text-align: left;
        }

        .dropdown-toggle {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dropdown-toggle:hover,
        .dropdown-toggle.active {
            background: var(--bg-base);
            color: var(--text-heading);
        }

        .dropdown-menu {
            position: fixed;
            background: var(--bg-card);
            border: 1px solid var(--border);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            z-index: 9999;
            padding: 8px 0;
            display: none;
            min-width: 160px;
        }

        .dropdown-menu button,
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-heading);
            background: none;
            border: none;
            text-decoration: none;
            cursor: pointer;
            text-align: left;
            font-family: inherit;
            transition: background 0.2s;
        }

        .dropdown-menu button:hover,
        .dropdown-menu a:hover {
            background: var(--bg-base);
            color: var(--brand-primary);
        }

        .dropdown-menu .text-danger {
            color: #DC2626;
        }

        .dropdown-menu .text-danger:hover {
            background: #FEE2E2;
            color: #DC2626;
        }

        /* Custom Searchable Dropdown CSS (For Patient Search) */
        .custom-select-wrapper {
            position: relative;
            width: 100%;
        }

        .custom-select-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: var(--r-md);
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            color: var(--text-heading);
            cursor: text;
        }

        .custom-select-input:focus {
            outline: none;
            border-color: var(--brand-primary);
        }

        .custom-select-list {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--r-md);
            margin-top: 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .custom-select-list.active {
            display: block;
        }

        .custom-select-item {
            padding: 10px 16px;
            font-size: 14px;
            color: var(--text-heading);
            cursor: pointer;
            border-bottom: 1px solid var(--bg-base);
        }

        .custom-select-item:hover {
            background: var(--bg-base);
            color: var(--brand-primary);
        }

        .custom-select-item:last-child {
            border-bottom: none;
        }

        .custom-select-empty {
            padding: 10px 16px;
            font-size: 13px;
            color: var(--text-muted);
            text-align: center;
        }

        /* Autocomplete Styles (For Chief Complaint) */
        .autocomplete-wrapper {
            position: relative;
            width: 100%;
        }

        .suggestions-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            max-height: 250px;
            overflow-y: auto;
            display: none;
            margin-top: 5px;
        }

        .suggestion-item {
            padding: 12px 16px;
            font-size: 14px;
            color: var(--text-heading);
            cursor: pointer;
            border-bottom: 1px solid var(--bg-base);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-item:hover {
            background: var(--bg-base);
            color: var(--brand-primary);
        }

        /* Details */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .detail-item p {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-heading);
            margin: 0;
        }

        .section-tag {
            margin-top: 24px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
            font-weight: 700;
            color: var(--brand-primary);
            font-size: 13px;
            text-transform: uppercase;
        }

        /* Loading & Pagination */
        .table-wrapper {
            position: relative;
            min-height: 300px;
        }

        .loader-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 5;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .is-loading .loader-overlay {
            opacity: 1;
            pointer-events: all;
        }

        .spinner-icon {
            font-size: 32px;
            color: var(--brand-primary);
            animation: spin 1s linear infinite;
            margin-bottom: 8px;
        }

        .spinner-text {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
        }

        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            background: var(--bg-card);
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .page-info {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .page-buttons {
            display: flex;
            gap: 6px;
        }

        .page-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
            border: 1px solid var(--border);
            color: var(--text-heading);
            background: var(--bg-base);
        }

        .page-btn:hover,
        .page-btn.active {
            background: var(--brand-primary);
            color: #fff;
            border-color: var(--brand-primary);
        }

        .page-btn.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* FIXED Button Loading UI */
        .btn-loading {
            pointer-events: none;
            opacity: 0.8;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-loading .spinner-icon {
            font-size: 18px !important;
            margin: 0 !important;
            color: inherit !important;
            display: inline-block;
        }

        /* SweetAlert */
        .swal2-popup {
            border-radius: 12px !important;
            font-family: 'Outfit', sans-serif !important;
        }

        .swal2-title {
            font-family: 'DM Serif Display', serif !important;
            color: var(--text-heading) !important;
        }

        .swal2-confirm {
            border-radius: 8px !important;
            padding: 10px 24px !important;
            font-weight: 600 !important;
            background-color: var(--brand-primary) !important;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include '../includes/nurse_sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/nurse_header.php'; ?>

            <main class="content-body">

                <div class="page-header print-hide">
                    <div class="page-header-text">
                        <p class="page-eyebrow">Triage & Queue</p>
                        <h1 class="page-title">Active Visits</h1>
                        <p class="page-subtitle">Log initial complaints, vitals, and triage notes for walk-in students.</p>
                    </div>
                    <div class="page-actions">
                        <button type="button" class="btn btn-primary" style="border-radius: 8px;" onclick="openVisitModal()">
                            <i class="ph ph-plus"></i> Log New Visit
                        </button>
                    </div>
                </div>

                <div class="panel" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                    <div class="panel-header print-hide" style="border-top-left-radius: 12px; border-top-right-radius: 12px; background: var(--bg-card); padding: 12px 20px; border-bottom: 1px solid var(--border);">
                        <form id="searchForm" action="" method="GET" class="header-search" style="width: 280px; padding: 6px 14px; margin: 0;">
                            <span class="search-icon"><i class="ph ph-magnifying-glass"></i></span>
                            <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, ID, or complaint..." class="search-input" autocomplete="off" style="border: none; background: transparent; outline: none; width: 100%;">
                        </form>
                    </div>

                    <div class="table-wrapper" id="contentWrapper">
                        <div class="loader-overlay">
                            <i class="ph-bold ph-spinner-gap spinner-icon"></i>
                            <span class="spinner-text">Updating queue...</span>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table" id="queueTable">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">Visit ID</th>
                                        <th>Patient Details</th>
                                        <th>Chief Complaint</th>
                                        <th>Vitals</th>
                                        <th>Nurse Notes</th>
                                        <th style="width: 130px;">Date & Time</th>
                                        <th class="text-right print-hide" style="width: 160px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($total_records > 0): ?>
                                        <?php foreach ($active_visits as $visit):
                                            $formatted_visit_id = "VST-" . str_pad($visit['visit_id'], 4, "0", STR_PAD_LEFT);
                                            $formatted_student_id = "KCCF-" . str_pad($visit['real_student_id'], 4, "0", STR_PAD_LEFT);
                                            $full_name = htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']);

                                            // Smart Temperature Badge Logic
                                            $temp = floatval($visit['temperature']);
                                            $badge_style = 'badge-green';
                                            if ($temp >= 37.8) $badge_style = 'badge-red';
                                            elseif ($temp >= 37.3) $badge_style = 'badge-warn" style="background:#FEF3C7; color:#D97706;';

                                            // Safely encode for Javascript parameters
                                            $json_data = htmlspecialchars(json_encode($visit), ENT_QUOTES, 'UTF-8');
                                        ?>
                                            <tr>
                                                <td data-label="Visit ID" style="font-family: monospace; font-weight: 600; color: var(--text-muted);"><?php echo $formatted_visit_id; ?></td>
                                                <td data-label="Patient Details">
                                                    <div class="med-name" style="font-weight: 500; color: var(--text-heading);"><?php echo $full_name; ?></div>
                                                    <div class="med-category" style="font-size: 12px; color: var(--text-muted);">ID: <?php echo $formatted_student_id; ?></div>
                                                </td>
                                                <td data-label="Chief Complaint" style="font-weight: 600; color: var(--text-heading);"><?php echo htmlspecialchars($visit['complaint']); ?></td>
                                                <td data-label="Vitals">
                                                    <span class="badge <?php echo $badge_style; ?>" style="font-size: 13px; margin-bottom: 6px; display: inline-block;"><?php echo htmlspecialchars($visit['temperature']); ?> °C</span>
                                                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; line-height: 1.5;">
                                                        <div>H: <?php echo htmlspecialchars($visit['height'] ?? '--'); ?> cm</div>
                                                        <div>W: <?php echo htmlspecialchars($visit['weight'] ?? '--'); ?> kg</div>
                                                    </div>
                                                </td>
                                                <td data-label="Nurse Notes" style="color: var(--text-body); font-size: 13px; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($visit['nurse_notes']); ?>">
                                                    <?php echo htmlspecialchars($visit['nurse_notes'] ?: 'No notes provided.'); ?>
                                                </td>
                                                <td data-label="Date & Time">
                                                    <div style="font-weight: 500; color: var(--text-heading);"><?php echo date("M d, Y", strtotime($visit['date_logged'])); ?></div>
                                                    <div style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 4px;"><?php echo date("h:i A", strtotime($visit['time_in'])); ?></div>
                                                </td>

                                                <td data-label="Actions" class="text-right print-hide" style="white-space: nowrap;">
                                                    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 8px;">

                                                        <div class="dropdown-wrapper">
                                                            <button class="dropdown-toggle" onclick="toggleDropdown(this, event)">
                                                                <i class="ph ph-dots-three-vertical"></i>
                                                            </button>
                                                            <!-- Dropdown Menu -->
                                                            <div class="dropdown-menu">
                                                                <button type="button" onclick='viewVisit(<?php echo $json_data; ?>, "<?php echo $formatted_visit_id; ?>", "<?php echo $formatted_student_id; ?>")'>
                                                                    <i class="ph ph-eye"></i> View Details
                                                                </button>
                                                                <button type="button" onclick='openEditModal(<?php echo $json_data; ?>, "<?php echo $formatted_visit_id; ?>")'>
                                                                    <i class="ph ph-pencil-simple"></i> Edit Triage
                                                                </button>
                                                                <button type="button" class="text-danger" onclick='confirmDelete(<?php echo $visit["visit_id"]; ?>, "<?php echo $formatted_visit_id; ?>", "<?php echo addslashes($full_name); ?>")'>
                                                                    <i class="ph ph-trash"></i> Remove Queue
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <!-- Finalize Button -->
                                                        <a href="forms/finalize_visit.php?visit_id=<?php echo $visit['visit_id']; ?>" class="btn btn-primary" style="border-radius: 8px; padding: 6px 16px; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                                                            Finalize
                                                        </a>

                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                                No active visits in the queue right now. Walk-in patients will appear here.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div id="paginationContainer">
                            <?php if ($total_pages > 0): ?>
                                <div class="pagination-container">
                                    <div class="page-info">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> active visits</div>
                                    <div class="page-buttons">
                                        <a href="?page=<?php echo $page - 1; ?>" class="page-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">Prev</a>
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <a href="?page=<?php echo $i; ?>" class="page-btn <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                        <?php endfor; ?>
                                        <a href="?page=<?php echo $page + 1; ?>" class="page-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">Next</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- VIEW VISIT MODAL -->
    <div id="viewVisitModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-stethoscope" style="color: var(--brand-primary);"></i> Triage Details</h3>
                <button type="button" class="modal-close" onclick="closeModal('viewVisitModal')" title="Close"><i class="ph ph-x"></i></button>
            </div>
            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: var(--bg-card); padding: 12px 16px; border-radius: var(--r-sm); border: 1px solid var(--border);">
                    <div><span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Visit ID</span>
                        <div id="v-vis-id" style="font-weight: 700; color: var(--text-heading); font-family: monospace;"></div>
                    </div>
                    <div style="text-align: right;"><span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Date & Time In</span>
                        <div id="v-date" style="font-size: 13px; font-weight: 500; color: var(--text-heading);"></div>
                    </div>
                </div>

                <div class="section-tag" style="margin-top: 0;">Patient Information</div>
                <div class="detail-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                    <div class="detail-item"><label>Student Name</label>
                        <p id="v-name"></p>
                    </div>
                    <div class="detail-item"><label>Student ID</label>
                        <p id="v-stu-id"></p>
                    </div>
                    <div class="detail-item"><label>Course & Year</label>
                        <p id="v-course"></p>
                    </div>
                </div>

                <div class="section-tag">Triage & Vitals</div>
                <div class="detail-grid" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="detail-item" style="grid-column: span 2;"><label>Chief Complaint</label>
                        <p id="v-complaint" style="color: var(--brand-primary); font-weight: 700;"></p>
                    </div>
                    <div class="detail-item"><label>Temperature</label>
                        <p id="v-temp"></p>
                    </div>
                    <div class="detail-item"><label>Height (cm) / Weight (kg)</label>
                        <p><span id="v-height"></span> / <span id="v-weight"></span></p>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 15px;">
                    <label>Initial Nurse Notes</label>
                    <p id="v-nurse-notes" style="font-style: italic; background: var(--bg-base); padding: 10px; border-radius: var(--r-sm); border: 1px dashed var(--border);"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('viewVisitModal')" style="border-radius: 8px;">Close Window</button>
            </div>
        </div>
    </div>

    <!-- LOG VISIT MODAL -->
    <div id="logVisitModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-stethoscope" style="color: var(--brand-primary);"></i> Log Walk-in Visit</h3>
                <button type="button" class="modal-close" onclick="closeVisitModal()"><i class="ph ph-x"></i></button>
            </div>
            <form action="../backend/nurse/process_log_visit.php" method="POST" id="logVisitForm">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Search / Select Patient</label>
                        <div class="custom-select-wrapper">
                            <input type="text" id="patientSearchInput" class="custom-select-input" placeholder="Type name or student ID to search..." autocomplete="off" required>
                            <input type="hidden" name="student_id" id="hiddenStudentId" required>
                            <div class="custom-select-list" id="patientDropdownList">
                                <?php foreach ($students as $s):
                                    $formatted_s_id = "KCCF-" . str_pad($s['student_id'], 4, "0", STR_PAD_LEFT);
                                    $full_name = htmlspecialchars($s['first_name'] . ' ' . $s['last_name']);
                                ?>
                                    <div class="custom-select-item" data-value="<?php echo $s['student_id']; ?>">
                                        <?php echo $full_name . ' (' . $formatted_s_id . ')'; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">If not in list, <a href="forms/register_student.php" style="color: var(--brand-primary); font-weight: 600;">register student first</a>.</p>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Chief Complaint</label>
                        <div class="autocomplete-wrapper">
                            <div style="position: relative;">
                                <i class="ph ph-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 18px;"></i>
                                <input type="text" id="complaintInput" name="complaint" placeholder="Search recent records or type complaint..." required autocomplete="off" style="width: 100%; padding: 12px 16px 12px 40px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 14px;">
                            </div>
                            <div id="complaintSuggestions" class="suggestions-list"></div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Temp (°C)</label>
                            <input type="number" name="temperature" step="0.1" placeholder="36.5" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 14px;">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Height (cm)</label>
                            <input type="number" name="height" step="0.1" placeholder="e.g. 165" style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 14px;">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Weight (kg)</label>
                            <input type="number" name="weight" step="0.1" placeholder="e.g. 55" style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 14px;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Initial Nurse Notes</label>
                        <textarea name="nurse_notes" placeholder="Any immediate observations..." rows="2" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 14px; resize: vertical;"></textarea>
                    </div>
                    <input type="hidden" name="time_in" value="<?php echo date('H:i'); ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeVisitModal()" style="border-radius: 8px; padding: 10px 20px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px; padding: 10px 20px;">Add to Queue</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT VISIT MODAL -->
    <div id="editVisitModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-pencil-simple" style="color: var(--brand-primary);"></i> Edit Triage Details</h3>
                <button type="button" class="modal-close" onclick="closeEditModal()"><i class="ph ph-x"></i></button>
            </div>
            <form action="../backend/nurse/process_edit_visit.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="visit_id" id="edit_visit_id">

                    <!-- Dynamic Redirect Source -->
                    <input type="hidden" name="source" value="visits">

                    <div style="margin-bottom: 20px;">
                        <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Editing Active Queue Visit</span>
                        <div id="edit_display_id" style="font-weight: 700; color: var(--text-heading); font-family: monospace; font-size: 16px;"></div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:8px;">CHIEF COMPLAINT</label>
                        <div class="autocomplete-wrapper">
                            <div style="position: relative;">
                                <i class="ph ph-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 18px;"></i>
                                <input type="text" name="complaint" id="edit_complaint" required autocomplete="off" style="width:100%; padding: 12px 16px 12px 40px; background: var(--bg-card); border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                            </div>
                            <div id="editComplaintSuggestions" class="suggestions-list"></div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Temp (°C)</label>
                            <input type="number" name="temperature" id="edit_temp" step="0.1" required style="width: 100%; padding: 12px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 14px;">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Height (cm)</label>
                            <input type="number" name="height" id="edit_height" step="0.1" style="width: 100%; padding: 12px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 14px;">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Weight (kg)</label>
                            <input type="number" name="weight" id="edit_weight" step="0.1" style="width: 100%; padding: 12px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 14px;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">NURSE NOTES</label>
                        <textarea name="nurse_notes" id="edit_notes" rows="3" required style="width:100%; padding:12px; background: var(--bg-card); border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; resize: vertical;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeEditModal()" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;">Update Queue</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelector('a[href="visits.php"]').classList.add('active');

        // Three-dot dropdown logic
        function toggleDropdown(btn, event) {
            event.stopPropagation();
            const menu = btn.nextElementSibling;
            const isActive = menu.classList.contains('active');

            // Close all open menus first
            document.querySelectorAll('.dropdown-menu').forEach(m => {
                m.classList.remove('active');
                m.style.display = 'none';
            });

            if (!isActive) {
                // Get the button's exact position on screen BEFORE showing the menu
                const rect = btn.getBoundingClientRect();

                // Show it to populate layout
                menu.style.display = 'block';
                menu.classList.add('active');

                // Use fixed positioning so it ignores table overflow:auto
                menu.style.top = (rect.bottom + 5) + 'px';
                menu.style.left = (rect.right - menu.offsetWidth) + 'px';
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-wrapper')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('active');
                    menu.style.display = 'none';
                });
            }
        });

        // Close dropdown immediately if the table or page is scrolled
        document.addEventListener('scroll', function(e) {
            document.querySelectorAll('.dropdown-menu.active').forEach(menu => {
                menu.classList.remove('active');
                menu.style.display = 'none';
            });
        }, true);

        // Modal logic
        const logModal = document.getElementById('logVisitModal');
        const editModal = document.getElementById('editVisitModal');
        const viewModal = document.getElementById('viewVisitModal');

        function openVisitModal() {
            logModal.classList.add('active');
        }

        function closeVisitModal() {
            logModal.classList.remove('active');
            document.getElementById('logVisitForm').reset();
            document.getElementById('hiddenStudentId').value = '';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function viewVisit(data, formattedVisId, stuId) {
            document.getElementById('v-vis-id').innerText = formattedVisId;

            // Format date and time
            let timeObj = new Date('1970-01-01T' + data.time_in + 'Z');
            let formattedTime = timeObj.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                timeZone: 'UTC'
            });
            document.getElementById('v-date').innerText = new Date(data.date_logged).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            }) + " • " + formattedTime;

            // Convert number to ordinal format
            function getOrdinal(num) {
                const j = num % 10,
                    k = num % 100;
                if (j === 1 && k !== 11) return num + "st";
                if (j === 2 && k !== 12) return num + "nd";
                if (j === 3 && k !== 13) return num + "rd";
                return num + "th";
            }

            document.getElementById('v-name').innerText = data.first_name + ' ' + data.last_name;
            document.getElementById('v-stu-id').innerText = stuId;
            document.getElementById('v-course').innerText = data.course + ' - ' + getOrdinal(data.year_level) + ' Year';
            document.getElementById('v-complaint').innerText = data.complaint;
            document.getElementById('v-temp').innerText = data.temperature + ' °C';
            document.getElementById('v-height').innerText = data.height ? data.height + ' cm' : '--';
            document.getElementById('v-weight').innerText = data.weight ? data.weight + ' kg' : '--';
            document.getElementById('v-nurse-notes').innerText = data.nurse_notes ? '"' + data.nurse_notes + '"' : 'No initial notes.';

            viewModal.classList.add('active');
        }

        function openEditModal(data, formattedVisId) {
            document.getElementById('edit_visit_id').value = data.visit_id || '';
            document.getElementById('edit_display_id').innerText = formattedVisId + " - " + data.first_name + " " + data.last_name;
            document.getElementById('edit_complaint').value = data.complaint || '';
            document.getElementById('edit_temp').value = data.temperature || '';
            document.getElementById('edit_height').value = data.height || '';
            document.getElementById('edit_weight').value = data.weight || '';
            document.getElementById('edit_notes').value = data.nurse_notes || '';

            editModal.classList.add('active');
        }

        function closeEditModal() {
            editModal.classList.remove('active');
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('active');
            }
        }

        // ==========================================
        // SWEETALERT CONFIRM & NOTIFICATIONS
        // ==========================================
        function confirmDelete(id, formattedId, name) {
            Swal.fire({
                title: 'Remove from Queue?',
                text: `You are about to delete ${formattedId} for ${name}. This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Yes, remove visit'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '../backend/nurse/process_delete_visit.php';

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'visit_id';
                    input.value = id;
                    form.appendChild(input);

                    // Dynamic Redirect Source
                    const sourceInput = document.createElement('input');
                    sourceInput.type = 'hidden';
                    sourceInput.name = 'source';
                    sourceInput.value = 'visits';
                    form.appendChild(sourceInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');

            if (success || error) {
                let title = '',
                    text = '',
                    icon = '';
                if (success) {
                    icon = 'success';
                    title = 'Success!';
                    if (success === 'logged') text = "Patient has been successfully added to the queue.";
                    else if (success === 'updated') text = "Queue triage details have been updated.";
                    else if (success === 'deleted') text = "Visit was successfully removed from the queue.";
                    else if (success === 'finalized') text = "Visit has been finalized and archived.";
                }
                if (error) {
                    icon = 'error';
                    title = 'Action Failed';
                    if (error === 'has_health_record') text = "Cannot delete this visit because it is linked to a finalized health record.";
                    else text = "A database error occurred. Please try again.";
                }
                Swal.fire({
                    title,
                    text,
                    icon,
                    confirmButtonText: 'Got it',
                    confirmButtonColor: '#2563EB'
                }).then(() => {
                    const newUrl = window.location.pathname + window.location.search.replace(/[?&](success|error)=[^&]+/, '').replace(/^&/, '?');
                    window.history.replaceState({}, document.title, newUrl);
                });
            }
        });

        // ==========================================
        // AJAX SEARCH & PAGINATION
        // ==========================================
        const searchInputBox = document.getElementById('searchInput');
        const contentWrapper = document.getElementById('contentWrapper');
        let debounceTimer;

        function triggerAjax(page = 1) {
            contentWrapper.classList.add('is-loading');
            const url = new URL(window.location.href);
            url.searchParams.delete('success');
            url.searchParams.delete('error');
            url.searchParams.set('search', searchInputBox.value);
            url.searchParams.set('page', page);

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    document.querySelector('.data-table tbody').innerHTML = doc.querySelector('.data-table tbody').innerHTML;
                    const newPagination = doc.getElementById('paginationContainer');
                    document.getElementById('paginationContainer').innerHTML = newPagination ? newPagination.innerHTML : '';
                    contentWrapper.classList.remove('is-loading');
                    window.history.pushState({}, '', url);
                })
                .catch(() => contentWrapper.classList.remove('is-loading'));
        }

        searchInputBox.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => triggerAjax(1), 400);
        });

        document.getElementById('searchForm').addEventListener('submit', e => e.preventDefault());

        document.getElementById('contentWrapper').addEventListener('click', function(e) {
            if (e.target.tagName === 'A' && e.target.classList.contains('page-btn') && !e.target.classList.contains('disabled')) {
                e.preventDefault();
                triggerAjax(new URL(e.target.href).searchParams.get('page'));
            }
        });

        // ==========================================
        // CUSTOM SEARCHABLE DROPDOWN (PATIENT SELECT)
        // ==========================================
        const pSearchInput = document.getElementById('patientSearchInput');
        const pDropdownList = document.getElementById('patientDropdownList');
        const pHiddenInput = document.getElementById('hiddenStudentId');
        const pItems = pDropdownList.getElementsByClassName('custom-select-item');

        pSearchInput.addEventListener('focus', function() {
            pDropdownList.classList.add('active');
            filterPatientList();
        });

        pSearchInput.addEventListener('input', filterPatientList);

        function filterPatientList() {
            const filter = pSearchInput.value.toLowerCase();
            let hasVisibleItems = false;
            const emptyMsg = pDropdownList.querySelector('.custom-select-empty');
            if (emptyMsg) emptyMsg.remove();

            for (let i = 0; i < pItems.length; i++) {
                const text = pItems[i].textContent || pItems[i].innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    pItems[i].style.display = "";
                    hasVisibleItems = true;
                } else {
                    pItems[i].style.display = "none";
                }
            }

            if (!hasVisibleItems && filter !== "") {
                const noData = document.createElement('div');
                noData.className = 'custom-select-empty';
                noData.textContent = 'No student found. Please register them first.';
                pDropdownList.appendChild(noData);
            }
        }

        for (let i = 0; i < pItems.length; i++) {
            pItems[i].addEventListener('click', function() {
                pSearchInput.value = this.textContent.trim();
                pHiddenInput.value = this.getAttribute('data-value');
                pDropdownList.classList.remove('active');
            });
        }

        document.addEventListener('click', function(e) {
            if (!pSearchInput.contains(e.target) && !pDropdownList.contains(e.target)) {
                pDropdownList.classList.remove('active');
                if (pHiddenInput.value === '') pSearchInput.value = '';
            }
        });

        // ==========================================
        // CHIEF COMPLAINT AUTOCOMPLETE
        // ==========================================
        const complaintsData = <?php echo json_encode($recent_complaints); ?>;

        function setupAutocomplete(inputId, suggestionsId, dataArray, iconClass) {
            const input = document.getElementById(inputId);
            const suggestions = document.getElementById(suggestionsId);

            function showSuggestions(val) {
                suggestions.innerHTML = '';
                let filtered = dataArray;
                if (val) {
                    filtered = dataArray.filter(item => item.toLowerCase().includes(val.toLowerCase()));
                }

                if (filtered.length > 0) {
                    filtered.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        let displayText = item;
                        if (val) {
                            const regex = new RegExp(`(${val})`, "gi");
                            displayText = item.replace(regex, "<strong style='color: inherit; text-decoration: underline;'>$1</strong>");
                        }
                        div.innerHTML = `<i class="${iconClass}" style="opacity: 0.6; font-size: 16px;"></i> <span style="font-weight: 500;">${displayText}</span>`;
                        div.onclick = function() {
                            input.value = item;
                            suggestions.style.display = 'none';
                        };
                        suggestions.appendChild(div);
                    });
                    suggestions.style.display = 'block';
                } else {
                    suggestions.style.display = 'none';
                }
            }

            input.addEventListener('focus', function() {
                this.select();
                showSuggestions('');
            });
            input.addEventListener('input', function() {
                showSuggestions(this.value.trim());
            });

            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                    suggestions.style.display = 'none';
                }
            });
        }

        setupAutocomplete('complaintInput', 'complaintSuggestions', complaintsData, 'ph ph-magnifying-glass');
        setupAutocomplete('edit_complaint', 'editComplaintSuggestions', complaintsData, 'ph ph-magnifying-glass');

        // ==========================================
        // SMART BUTTON SPINNER (PREVENTS DOUBLE CLICKS)
        // ==========================================
        document.querySelectorAll('form:not(#searchForm)').forEach(form => {
            form.addEventListener('submit', function() {
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    if (btn.classList.contains('btn-loading')) return false;
                    btn.classList.add('btn-loading');
                    btn.innerHTML = '<i class="ph-bold ph-spinner-gap spinner-icon"></i> Please wait...';
                }
            });
        });
    </script>
    <script src="../assets/js/script.js"></script>
</body>

</html>