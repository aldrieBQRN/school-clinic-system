<?php
require_once '../config/db_connect.php';
require_once '../config/auth_check.php';

// Pagination, search, and filters
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course_filter = isset($_GET['course']) ? trim($_GET['course']) : '';
$from_date = isset($_GET['from']) ? $_GET['from'] : '';
$to_date = isset($_GET['to']) ? $_GET['to'] : '';

// Build the visit log query filters
$where_clauses = ["v.status = 'Completed'"];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR v.complaint LIKE :search OR s.student_id LIKE :search OR v.visit_id LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($course_filter)) {
    $where_clauses[] = "s.course = :course";
    $params[':course'] = $course_filter;
}
if (!empty($from_date)) {
    $where_clauses[] = "DATE(v.date_logged) >= :from_date";
    $params[':from_date'] = $from_date;
}
if (!empty($to_date)) {
    $where_clauses[] = "DATE(v.date_logged) <= :to_date";
    $params[':to_date'] = $to_date;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

try {
    // Count matching visit log records
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

    // Fetch the current page of visit logs
    $query = "
        SELECT
            v.visit_id, v.complaint, v.temperature, v.height, v.weight, v.nurse_notes, v.time_in, v.date_logged, v.status,
            s.student_id as real_student_id, s.first_name, s.last_name, s.course, s.year_level
        FROM visits v
        JOIN students s ON v.student_id = s.student_id
        $where_sql
        ORDER BY v.date_logged DESC, v.time_in DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $visits = $stmt->fetchAll();

    // Load course options for the filter dropdown
    $course_stmt = $conn->query("SELECT DISTINCT course FROM students WHERE course != '' AND course IS NOT NULL ORDER BY course ASC");
    $courses = $course_stmt->fetchAll(PDO::FETCH_COLUMN);

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
    <title>Triage Visit Log | KCCF Clinic (Nurse)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .data-table {
            width: 100%;
            min-width: 1000px;
        }

        .data-table tr {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
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

        /* Modal close button */
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

        /* Autocomplete Styles */
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
            max-height: 200px;
            overflow-y: auto;
            display: none;
            margin-top: 5px;
        }

        .suggestion-item {
            padding: 12px 16px;
            font-size: 14px;
            color: var(--text-heading);
            cursor: pointer;
            border-bottom: 1px solid var(--border);
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

        /* Filter Controls UI */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-card);
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .filter-group {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select,
        .date-input {
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-family: inherit;
            font-size: 13px;
            background: var(--bg-base);
            color: var(--text-heading);
            outline: none;
        }

        .date-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        /* Pagination & Actions */
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

        /* Fixed Button UI */
        .action-btn {
            background: none;
            border: none;
            border-radius: 8px;
            padding: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }

        .action-btn:hover {
            background: var(--bg-base);
            color: var(--brand-primary);
        }

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

        /* Loading UI */
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

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .data-table {
                min-width: 100%;
            }

            .data-table thead {
                display: none;
            }

            .data-table tbody tr {
                display: block;
                background: var(--bg-card);
                margin: 15px;
                border-radius: 12px;
                padding: 15px 15px 5px 15px;
                border: 1px solid var(--border);
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            }

            .data-table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border: none;
                border-bottom: 1px solid var(--border);
                text-align: right;
            }

            .data-table tbody td:last-child {
                border-bottom: none;
                padding-top: 12px;
            }

            .data-table tbody td::before {
                content: attr(data-label);
                font-weight: 700;
                color: var(--text-muted);
                text-transform: uppercase;
                font-size: 11px;
                text-align: left;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
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
                        <p class="page-eyebrow">Archive</p>
                        <h1 class="page-title">Triage Visit Log</h1>
                        <p class="page-subtitle">Historical archive of initial patient complaints, vitals, and nurse observations.</p>
                    </div>
                </div>

                <div class="panel" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">

                    <div class="filter-bar print-hide">
                        <form id="searchForm" action="" method="GET" class="header-search">
                            <span class="search-icon"><i class="ph ph-magnifying-glass"></i></span>
                            <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, ID, complaint..." class="search-input" autocomplete="off">
                        </form>

                        <div class="filter-group">
                            <span class="date-label">From:</span>
                            <input type="date" id="fromDate" class="date-input" value="<?php echo htmlspecialchars($from_date); ?>">
                            <span class="date-label">To:</span>
                            <input type="date" id="toDate" class="date-input" value="<?php echo htmlspecialchars($to_date); ?>">

                            <select id="courseFilter" class="filter-select">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" <?php if ($course_filter == $c) echo 'selected'; ?>><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="table-wrapper" id="contentWrapper">
                        <div class="loader-overlay">
                            <i class="ph-bold ph-spinner-gap spinner-icon"></i>
                            <span class="spinner-text">Fetching visits...</span>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">Visit ID</th>
                                        <th>Patient Details</th>
                                        <th>Chief Complaint</th>
                                        <th>Vitals</th>
                                        <th>Nurse Notes</th>
                                        <th style="width: 130px;">Date & Time</th>
                                        <th class="text-right print-hide" style="width: 140px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($total_records > 0): ?>
                                        <?php foreach ($visits as $row):
                                            $formatted_visit_id = "VST-" . str_pad($row['visit_id'], 4, "0", STR_PAD_LEFT);
                                            $formatted_student_id = "KCCF-" . str_pad($row['real_student_id'], 4, "0", STR_PAD_LEFT);
                                            $full_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);

                                            // Smart Temperature Badge Logic
                                            $temp = floatval($row['temperature']);
                                            $badge_style = 'badge-green'; // Normal
                                            $custom_style = '';
                                            if ($temp >= 37.8) {
                                                $badge_style = 'badge-red'; // High Fever
                                            } elseif ($temp >= 37.3) {
                                                $badge_style = 'badge-warn'; // Low-grade fever
                                                $custom_style = 'background:#FEF3C7; color:#D97706;';
                                            }

                                            $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                        ?>
                                            <tr>
                                                <td data-label="Visit ID" style="font-family: monospace; font-weight: 600; color: var(--text-muted);">
                                                    <?php echo $formatted_visit_id; ?>
                                                </td>
                                                <td data-label="Patient Details">
                                                    <div class="med-name" style="font-weight: 500; color: var(--text-heading);"><?php echo $full_name; ?></div>
                                                    <div class="med-category" style="font-size: 12px; color: var(--text-muted);">ID: <?php echo $formatted_student_id; ?></div>
                                                </td>
                                                <td data-label="Complaint" style="font-weight: 600; color: var(--text-heading);"><?php echo htmlspecialchars($row['complaint']); ?></td>
                                                <td data-label="Vitals">
                                                    <span class="badge <?php echo $badge_style; ?>" style="font-size: 13px; margin-bottom: 6px; display: inline-block; <?php echo $custom_style; ?>"><?php echo htmlspecialchars($row['temperature']); ?> °C</span>
                                                    <!-- UPDATED: 2 Lines for Height and Weight -->
                                                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; line-height: 1.5;">
                                                        <div>H: <?php echo htmlspecialchars($row['height'] ?? '--'); ?> cm</div>
                                                        <div>W: <?php echo htmlspecialchars($row['weight'] ?? '--'); ?> kg</div>
                                                    </div>
                                                </td>
                                                <td data-label="Nurse Notes">
                                                    <div style="color: var(--text-body); font-size: 13px; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($row['nurse_notes']); ?>">
                                                        <?php echo htmlspecialchars($row['nurse_notes'] ?: 'No notes.'); ?>
                                                    </div>
                                                </td>
                                                <td data-label="Date & Time">
                                                    <div style="font-weight: 500; color: var(--text-heading);"><?php echo date("M d, Y", strtotime($row['date_logged'])); ?></div>
                                                    <div style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 4px;"><?php echo date("h:i A", strtotime($row['time_in'])); ?></div>
                                                </td>
                                                <td data-label="Actions" class="text-right print-hide" style="white-space: nowrap;">
                                                    <button class="action-btn" title="View Details" onclick='viewVisit(<?php echo $json_data; ?>, "<?php echo $formatted_visit_id; ?>", "<?php echo $formatted_student_id; ?>")'>
                                                        <i class="ph ph-eye" style="font-size: 18px;"></i>
                                                    </button>
                                                    <button class="action-btn edit-btn" title="Edit Triage Data" onclick='openEditModal(<?php echo $json_data; ?>, "<?php echo $formatted_visit_id; ?>")'>
                                                        <i class="ph ph-pencil-simple" style="font-size: 18px;"></i>
                                                    </button>
                                                    <button class="action-btn delete-btn" title="Delete Visit Log" onclick='confirmDelete(<?php echo $row["visit_id"]; ?>, "<?php echo $formatted_visit_id; ?>", "<?php echo addslashes($full_name); ?>")'>
                                                        <i class="ph ph-trash" style="font-size: 18px;"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">No visit logs found matching your filters.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div id="paginationContainer">
                            <?php if ($total_pages > 0): ?>
                                <div class="pagination-container">
                                    <div class="page-info">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> visits</div>
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

    <!-- MODAL: VIEW VISIT -->
    <div id="viewVisitModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-stethoscope" style="color: var(--brand-primary);"></i> Triage Details</h3>
                <button type="button" class="modal-close" onclick="closeModal('viewVisitModal')" title="Close"><i class="ph ph-x"></i></button>
            </div>
            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: var(--bg-card); padding: 12px 16px; border-radius: var(--r-sm); border: 1px solid var(--border);">
                    <div><span class="date-label">Visit ID</span>
                        <div id="v-vis-id" style="font-weight: 700; color: var(--text-heading); font-family: monospace;"></div>
                    </div>
                    <div style="text-align: right;"><span class="date-label">Date & Time In</span>
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
                <div class="detail-grid">
                    <div class="detail-item"><label>Chief Complaint</label>
                        <p id="v-complaint" style="color: var(--brand-primary); font-weight: 700;"></p>
                    </div>
                    <div class="detail-item"><label>Temperature</label>
                        <p id="v-temp"></p>
                    </div>
                    <div class="detail-item"><label>Height (cm)</label>
                        <p id="v-height"></p>
                    </div>
                    <div class="detail-item"><label>Weight (kg)</label>
                        <p id="v-weight"></p>
                    </div>
                </div>

                <div class="detail-item" style="margin-bottom: 15px;">
                    <label>Nurse Notes</label>
                    <p id="v-nurse-notes" style="font-style: italic; background: var(--bg-base); padding: 10px; border-radius: var(--r-sm); border: 1px dashed var(--border);"></p>
                </div>

                <!-- ADDED: Status item for the archive log -->
                <div class="detail-item">
                    <label>Status</label>
                    <p id="v-status"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('viewVisitModal')" style="border-radius: 8px;">Close Window</button>
            </div>
        </div>
    </div>

    <!-- MODAL: EDIT VISIT WITH AUTOCOMPLETE -->
    <div id="editVisitModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-pencil-simple" style="color: var(--brand-primary);"></i> Edit Triage</h3>
                <button type="button" class="modal-close" onclick="closeModal('editVisitModal')"><i class="ph ph-x"></i></button>
            </div>
            <form action="../backend/nurse/process_edit_visit.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="visit_id" id="edit_visit_id">

                    <div style="margin-bottom: 20px;"><span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Editing Visit</span>
                        <div id="edit_display_id" style="font-weight: 700; color: var(--text-heading); font-family: monospace; font-size: 16px;"></div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:8px;">CHIEF COMPLAINT</label>
                        <div class="autocomplete-wrapper">
                            <div style="position: relative;">
                                <i class="ph ph-clock-counter-clockwise" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 18px;"></i>
                                <input type="text" name="complaint" id="edit_complaint" placeholder="Search recent complaints..." required autocomplete="off" style="width:100%; padding: 12px 16px 12px 40px; background: var(--bg-card); border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 14px;">
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
                        <textarea name="nurse_notes" id="edit_notes" rows="3" style="width:100%; padding:12px; border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; resize: vertical;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('editVisitModal')" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelector('a[href="visit_log.php"]').classList.add('active');

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function viewVisit(data, visId, stuId) {
            document.getElementById('v-vis-id').innerText = visId;

            // Format date and time cleanly
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

            document.getElementById('v-name').innerText = data.first_name + ' ' + data.last_name;
            document.getElementById('v-stu-id').innerText = stuId;
            document.getElementById('v-course').innerText = data.course + ' - ' + data.year_level + 'th Year';
            document.getElementById('v-complaint').innerText = data.complaint;
            document.getElementById('v-temp').innerText = data.temperature + ' °C';
            document.getElementById('v-height').innerText = data.height ? data.height + ' cm' : '--';
            document.getElementById('v-weight').innerText = data.weight ? data.weight + ' kg' : '--';
            document.getElementById('v-nurse-notes').innerText = data.nurse_notes ? '"' + data.nurse_notes + '"' : 'No notes provided.';

            // Render Completed Badge
            document.getElementById('v-status').innerHTML = '<span class="badge badge-green">Completed</span>';

            document.getElementById('viewVisitModal').classList.add('active');
        }

        function openEditModal(data, formattedVisId) {
            document.getElementById('edit_visit_id').value = data.visit_id;
            document.getElementById('edit_display_id').innerText = formattedVisId + " - " + data.first_name + " " + data.last_name;
            document.getElementById('edit_complaint').value = data.complaint;
            document.getElementById('edit_temp').value = data.temperature;
            document.getElementById('edit_height').value = data.height;
            document.getElementById('edit_weight').value = data.weight;
            document.getElementById('edit_notes').value = data.nurse_notes;

            document.getElementById('editVisitModal').classList.add('active');
        }

        function confirmDelete(id, formattedId, name) {
            Swal.fire({
                title: 'Delete Visit Log?',
                text: `You are about to delete ${formattedId} for ${name}. This cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Yes, delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading alert
                    Swal.fire({
                        title: 'Deleting...',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: (modal) => {
                            Swal.showLoading();
                        }
                    });

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '../backend/nurse/process_delete_visit.php';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'visit_id';
                    input.value = id;
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active');
        }

        // AJAX Filtering Logic
        const searchInput = document.getElementById('searchInput');
        const courseFilter = document.getElementById('courseFilter');
        const fromDate = document.getElementById('fromDate');
        const toDate = document.getElementById('toDate');
        const contentWrapper = document.getElementById('contentWrapper');
        let debounceTimer;

        function triggerAjax(page = 1) {
            contentWrapper.classList.add('is-loading');
            const url = new URL(window.location.href);
            url.searchParams.delete('success');
            url.searchParams.delete('error');
            url.searchParams.set('search', searchInput.value);
            url.searchParams.set('course', courseFilter.value);
            url.searchParams.set('from', fromDate.value);
            url.searchParams.set('to', toDate.value);
            url.searchParams.set('page', page);

            fetch(url).then(r => r.text()).then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                document.querySelector('.data-table tbody').innerHTML = doc.querySelector('.data-table tbody').innerHTML;
                const paginationNode = doc.getElementById('paginationContainer');
                document.getElementById('paginationContainer').innerHTML = paginationNode ? paginationNode.innerHTML : '';
                contentWrapper.classList.remove('is-loading');
                window.history.pushState({}, '', url);
            }).catch(() => contentWrapper.classList.remove('is-loading'));
        }

        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => triggerAjax(1), 400);
        });
        [courseFilter, fromDate, toDate].forEach(el => el.addEventListener('change', () => triggerAjax(1)));
        document.getElementById('searchForm').addEventListener('submit', e => e.preventDefault());

        document.getElementById('contentWrapper').addEventListener('click', e => {
            if (e.target.classList.contains('page-btn') && !e.target.classList.contains('disabled')) {
                e.preventDefault();
                const url = new URL(e.target.href);
                triggerAjax(url.searchParams.get('page'));
            }
        });

        // AUTOCOMPLETE LOGIC
        const complaintsData = <?php echo json_encode($recent_complaints); ?>;

        function setupAutocomplete(inputId, suggestionsId, dataArray, iconClass) {
            const input = document.getElementById(inputId);
            const suggestions = document.getElementById(suggestionsId);

            function showSuggestions(val) {
                suggestions.innerHTML = '';
                let filtered = val ? dataArray.filter(item => item.toLowerCase().includes(val.toLowerCase())) : dataArray;
                if (filtered.length > 0) {
                    filtered.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        let displayText = item;
                        if (val) {
                            const regex = new RegExp(`(${val})`, "gi");
                            displayText = item.replace(regex, "<strong>$1</strong>");
                        }
                        div.innerHTML = `<i class="${iconClass}" style="opacity: 0.6; font-size: 16px;"></i> <span>${displayText}</span>`;
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
                if (!input.contains(e.target) && !suggestions.contains(e.target)) suggestions.style.display = 'none';
            });
        }
        setupAutocomplete('edit_complaint', 'editComplaintSuggestions', complaintsData, 'ph ph-clock-counter-clockwise');

        // SPINNER LOGIC
        document.querySelectorAll('form:not(#searchForm)').forEach(form => {
            form.addEventListener('submit', function() {
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    btn.classList.add('btn-loading');
                    btn.innerHTML = '<i class="ph-bold ph-spinner-gap spinner-icon" style="font-size:16px;"></i> Processing...';
                }
            });
        });

        // SWEETALERT NOTIFICATIONS
        document.addEventListener("DOMContentLoaded", function() {
            const params = new URLSearchParams(window.location.search);
            const success = params.get('success');
            const error = params.get('error');

            if (success || error) {
                let title = '',
                    text = '',
                    icon = '';
                if (success) {
                    icon = 'success';
                    title = 'Success!';
                    if (success === 'updated') text = "Triage details updated successfully.";
                    if (success === 'deleted') text = "Visit log removed successfully.";
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
                    confirmButtonColor: '#2563EB'
                }).then(() => {
                    const newUrl = window.location.pathname + window.location.search.replace(/[?&](success|error)=[^&]+/, '').replace(/^&/, '?');
                    window.history.replaceState({}, document.title, newUrl);
                });
            }
        });
    </script>
    <script src="../assets/js/script.js"></script>
</body>

</html>