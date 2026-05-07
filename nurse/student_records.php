<?php
require_once '../config/db_connect.php';
require_once '../config/auth_check.php';

// Pagination, search, and filters
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course_filter = isset($_GET['course']) ? trim($_GET['course']) : '';
$gender_filter = isset($_GET['gender']) ? trim($_GET['gender']) : '';

// Build the student query filters
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_id LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($course_filter)) {
    $where_clauses[] = "s.course = :course";
    $params[':course'] = $course_filter;
}

if (!empty($gender_filter)) {
    $where_clauses[] = "s.gender = :gender";
    $params[':gender'] = $gender_filter;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

try {
    // Count matching student records
    $count_query = "SELECT COUNT(*) FROM students s $where_sql";
    $count_stmt = $conn->prepare($count_query);
    foreach ($params as $key => $val) {
        $count_stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch the current page of students
    $query = "
        SELECT s.*
        FROM students s
        $where_sql
        ORDER BY s.student_id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll();

    // Load course options for the filter dropdown
    $course_query = "SELECT DISTINCT course FROM students WHERE course != '' AND course IS NOT NULL ORDER BY course ASC";
    $stmt_course = $conn->prepare($course_query);
    $stmt_course->execute();
    $courses = $stmt_course->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Error fetching student records: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records | KCCF Clinic (Nurse)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Table layout */
        .data-table {
            width: 100%;
            min-width: 900px;
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
            max-width: 650px;
            border-radius: var(--r-lg);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: transform 0.2s ease-in-out;
        }

        .modal-overlay.active .modal-box {
            transform: translateY(0);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-card);
            position: sticky;
            top: 0;
            z-index: 10;
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
            font-size: 26px;
            line-height: 1;
            color: var(--text-muted);
            cursor: pointer;
            transition: color 0.2s;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 4px;
        }

        .modal-close:hover {
            color: #DC2626;
            background: #FEECEC;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            background: var(--bg-card);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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

        /* Pagination */
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

        .filter-select {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-family: inherit;
            font-size: 13px;
            background: var(--bg-base);
            color: var(--text-heading);
            outline: none;
            cursor: pointer;
        }

        /* Loading State Overlay */
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

        /* Action Buttons */
        .action-btn {
            border-radius: 8px;
            transition: all 0.2s;
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
            line-height: 1 !important;
            color: inherit;
        }

        /* Custom SweetAlert styling */
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

        /* Mobile Layout (phones only; tablet keeps table) */
        @media (max-width: 767px) {
            .data-table {
                min-width: 100%;
            }

            .data-table thead {
                display: none;
            }

            .data-table tbody tr {
                display: block;
                background: var(--bg-card);
                margin: 0 0 16px 0;
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
                        <p class="page-eyebrow">Patient Database</p>
                        <h1 class="page-title">Student Records</h1>
                        <p class="page-subtitle">Centralized database for student medical profiles.</p>
                    </div>
                    <div class="page-actions">

                        <a href="forms/register_student.php" class="btn btn-primary" style="border-radius: 8px; text-decoration: none;"><i class="ph ph-user-plus"></i> Register Student</a>
                    </div>
                </div>

                <div class="panel" id="tablePanel" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">

                    <div class="filter-bar print-hide">
                        <form id="searchForm" action="" method="GET" class="header-search">
                            <span class="search-icon"><i class="ph ph-magnifying-glass"></i></span>
                            <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by ID or name..." class="search-input" autocomplete="off">
                        </form>

                        <div class="filter-group">
                            <select id="courseFilter" class="filter-select">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" <?php if ($course_filter == $c) echo 'selected'; ?>><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select id="genderFilter" class="filter-select">
                                <option value="">All Genders</option>
                                <option value="Male" <?php if ($gender_filter == 'Male') echo 'selected'; ?>>Male</option>
                                <option value="Female" <?php if ($gender_filter == 'Female') echo 'selected'; ?>>Female</option>
                            </select>

                            <button class="btn btn-ghost" onclick="exportToPDF()" style="border-radius: 6px; padding: 6px 12px; border: 1px solid var(--border); font-size: 13px;" title="Download PDF">
                                <i class="ph ph-file-pdf" style="color: #EF4444; font-size: 16px;"></i> Export
                            </button>
                        </div>
                    </div>

                    <div class="table-wrapper" id="contentWrapper">

                        <div class="loader-overlay">
                            <i class="ph-bold ph-spinner-gap spinner-icon"></i>
                            <span class="spinner-text">Loading records...</span>
                        </div>

                        <div id="pdfExportArea">
                            <div id="pdfHeader" style="display: none; text-align: center; margin-bottom: 25px; border-bottom: 2px solid #1e293b; padding-bottom: 15px; background: #fff; padding-top: 20px;">
                                <img src="../assets/images/logo.jpg" alt="KCCF Logo" style="width: 70px; height: 70px; margin-bottom: 10px; border-radius: 50%;">
                                <h2 style="margin: 0; color: #1e293b; font-family: 'DM Serif Display', serif;">Kurios Christian Colleges Foundation</h2>
                                <p style="margin: 2px 0; color: #64748b; font-size: 14px;">Magallanes, Cavite</p>
                                <h3 style="margin-top: 15px; text-transform: uppercase; font-size: 16px; letter-spacing: 0.5px;">Student Health Records Directory</h3>
                                <p style="font-size: 12px; color: #475569; margin-top: 5px;">Document Generated: <?php echo date('F d, Y h:i A'); ?></p>
                            </div>

                            <div class="table-responsive" id="tableContainer" style="padding: 0;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Patient Name</th>
                                            <th>Age</th>
                                            <th>Gender</th>
                                            <th>Course & Year</th>
                                            <th style="width: 140px;">Date Registered</th>
                                            <th class="text-right action-col print-hide" style="width: 140px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($students): ?>
                                            <?php foreach ($students as $row):
                                                $birthdate = new DateTime($row['birthdate']);
                                                $age = $birthdate->diff(new DateTime('today'))->y;
                                                $formatted_id = "KCCF-" . str_pad($row['student_id'], 4, "0", STR_PAD_LEFT);

                                                // Date and Time formatting based on created_at matching Admin table
                                                $created_at = strtotime($row['created_at']);
                                                $reg_date = date("M d, Y", $created_at);
                                                $reg_time = date("h:i A", $created_at);

                                                $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                            ?>
                                                <tr>
                                                    <td data-label="Student ID" style="font-weight: 600; color: var(--brand-primary);"><?php echo $formatted_id; ?></td>
                                                    <td data-label="Patient Name" style="font-weight: 500; color: var(--text-heading);"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                    <td data-label="Age"><?php echo $age; ?> yrs</td>
                                                    <td data-label="Gender"><?php echo htmlspecialchars($row['gender']); ?></td>
                                                    <td data-label="Course & Year">
                                                        <?php
                                                        $ordinal_map = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'];
                                                        $year_ordinal = $ordinal_map[$row['year_level']] ?? $row['year_level'] . 'th';
                                                        echo htmlspecialchars($row['course'] . " - " . $year_ordinal . " Yr");
                                                        ?>
                                                    </td>
                                                    <td data-label="Date Registered">
                                                        <div style="font-weight: 500; color: var(--text-heading);"><?php echo $reg_date; ?></div>
                                                        <div style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 4px;"><?php echo $reg_time; ?></div>
                                                    </td>

                                                    <!-- Standard Action Buttons (No Dropdown) -->
                                                    <td data-label="Actions" class="text-right action-col print-hide" style="white-space: nowrap;">
                                                        <button class="action-btn" title="View Profile" onclick='viewStudent(<?php echo $json_data; ?>, "<?php echo $formatted_id; ?>", <?php echo $age; ?>)'>
                                                            <i class="ph ph-eye"></i>
                                                        </button>
                                                        <button class="action-btn edit-btn" title="Edit Info" onclick='openEditModal(<?php echo $json_data; ?>)'>
                                                            <i class="ph ph-pencil-simple"></i>
                                                        </button>
                                                        <button class="action-btn delete-btn" title="Delete Record" onclick='confirmDelete(<?php echo $row["student_id"]; ?>, "<?php echo addslashes($row["first_name"] . " " . $row["last_name"]); ?>")'>
                                                            <i class="ph ph-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">No student records found matching your filters.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div id="paginationContainer">
                            <?php if ($total_pages > 0): ?>
                                <div class="pagination-container">
                                    <div class="page-info">
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries
                                    </div>
                                    <div class="page-buttons">
                                        <a href="?page=<?php echo $page - 1; ?>" class="page-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">Prev</a>

                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
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

    <!-- MODAL: VIEW STUDENT DETAILS -->
    <div id="studentModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-user" style="color: var(--brand-primary); margin-right: 8px;"></i> Medical Profile</h3>
                <button type="button" class="modal-close" onclick="closeModal('studentModal')" title="Close"><i class="ph ph-x"></i></button>
            </div>
            <div class="modal-body">

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: var(--bg-card); padding: 12px 16px; border-radius: var(--r-sm); border: 1px solid var(--border);">
                    <div>
                        <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Student ID</span>
                        <div id="m-id" style="font-weight: 700; color: var(--text-heading); font-family: monospace;"></div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Date Registered</span>
                        <div id="m-reg-date" style="font-size: 13px; font-weight: 500; color: var(--text-heading);"></div>
                    </div>
                </div>

                <div class="section-tag" style="margin-top: 0;">Academic & Personal Info</div>
                <div class="detail-grid">
                    <div class="detail-item"><label>Full Name</label>
                        <p id="m-name"></p>
                    </div>
                    <div class="detail-item"><label>Course & Year Level</label>
                        <p id="m-course"></p>
                    </div>
                    <div class="detail-item"><label>Age / Gender</label>
                        <p id="m-age-gender"></p>
                    </div>
                    <div class="detail-item"><label>Birthdate</label>
                        <p id="m-birthdate"></p>
                    </div>
                </div>

                <div class="section-tag">Emergency Contact Details</div>
                <div class="detail-grid">
                    <div class="detail-item"><label>Guardian Name</label>
                        <p id="m-guardian"></p>
                    </div>
                    <div class="detail-item"><label>Relationship</label>
                        <p id="m-relation"></p>
                    </div>
                    <div class="detail-item" style="grid-column: span 2;"><label>Contact Number</label>
                        <p id="m-contact"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('studentModal')" style="border-radius: 8px;">Close Profile</button>
            </div>
        </div>
    </div>

    <!-- MODAL: EDIT STUDENT INFO -->
    <div id="editStudentModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-pencil-simple" style="color: var(--brand-primary); margin-right: 8px;"></i> Edit Student Info</h3>
                <button type="button" onclick="closeModal('editStudentModal')" class="modal-close"><i class="ph ph-x"></i></button>
            </div>
            <form action="../backend/nurse/process_edit_student.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="student_id" id="edit_student_id">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">FIRST NAME</label>
                            <input type="text" name="first_name" id="edit_first_name" required style="width:100%; padding:12px; background: var(--bg-card); border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">LAST NAME</label>
                            <input type="text" name="last_name" id="edit_last_name" required style="width:100%; padding:12px; background: var(--bg-card); border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">COURSE</label>
                            <select name="course" id="edit_course" required style="width:100%; padding:12px; background: var(--bg-card); border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                                <option value="">Select Course...</option>
                                <option value="BSED">BSED</option>
                                <option value="BSIT">BSIT</option>
                                <option value="BSCRIM">BSCRIM</option>
                                <option value="BSBA">BSBA</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">YEAR LEVEL</label>
                            <select name="year_level" id="edit_year_level" required style="width:100%; padding:12px; background: var(--bg-card); border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                                <option value="">Select Year...</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                    </div>

                    <div class="section-tag" style="margin-top: 10px;">Emergency Contact</div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">GUARDIAN NAME</label>
                        <input type="text" name="guardian_name" id="edit_guardian_name" required style="width:100%; padding:12px; background: var(--bg-card); border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">RELATIONSHIP</label>
                            <input type="text" name="relationship" id="edit_relationship" required style="width:100%; padding:12px; background: var(--bg-card); border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">CONTACT NUMBER</label>
                            <input type="text" name="guardian_contact" id="edit_guardian_contact" required style="width:100%; padding:12px; background: var(--bg-card); border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('editStudentModal')" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Set Active Nav
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelector('a[href="student_records.php"]').classList.add('active');

        // Convert number to ordinal format (1st, 2nd, 3rd, 4th, etc.)
        function getOrdinal(num) {
            const j = num % 10,
                k = num % 100;
            if (j === 1 && k !== 11) return num + "st";
            if (j === 2 && k !== 12) return num + "nd";
            if (j === 3 && k !== 13) return num + "rd";
            return num + "th";
        }

        // Modal Logic
        function viewStudent(data, formattedId, age) {
            document.getElementById('m-id').innerText = formattedId;

            // Format the Registration Date and Time for the Modal
            let regDateObj = new Date(data.created_at);
            let formattedRegDate = regDateObj.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            }) + " • " + regDateObj.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('m-reg-date').innerText = formattedRegDate;

            document.getElementById('m-name').innerText = data.first_name + ' ' + data.last_name;
            document.getElementById('m-course').innerText = data.course + ' - ' + getOrdinal(data.year_level) + ' Year';
            document.getElementById('m-age-gender').innerText = age + ' yrs old / ' + data.gender;
            document.getElementById('m-birthdate').innerText = new Date(data.birthdate).toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
            document.getElementById('m-guardian').innerText = data.guardian_name || 'Not provided';
            document.getElementById('m-relation').innerText = data.relationship || 'Not provided';
            document.getElementById('m-contact').innerText = data.guardian_contact || 'Not provided';

            document.getElementById('studentModal').classList.add('active');
        }

        function openEditModal(data) {
            document.getElementById('edit_student_id').value = data.student_id;
            document.getElementById('edit_first_name').value = data.first_name;
            document.getElementById('edit_last_name').value = data.last_name;
            document.getElementById('edit_course').value = data.course;
            document.getElementById('edit_year_level').value = data.year_level;
            document.getElementById('edit_guardian_name').value = data.guardian_name;
            document.getElementById('edit_relationship').value = data.relationship;
            document.getElementById('edit_guardian_contact').value = data.guardian_contact;

            document.getElementById('editStudentModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active');
        }

        // DELETE CONFIRMATION
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to remove ${name} from the records. This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Yes, delete student'
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
                    form.action = '../backend/nurse/process_delete_student.php';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'student_id';
                    input.value = id;
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // SPINNER LOGIC
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

        // SWEETALERT NOTIFICATIONS
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
                    if (success === 'added') text = "New student has been registered.";
                    else if (success === 'updated') text = "Student details updated.";
                    else if (success === 'deleted') text = "Record has been permanently removed.";
                }
                if (error) {
                    icon = 'error';
                    title = 'Action Failed';
                    if (error === 'has_medical_history') text = "Cannot delete student with existing medical visit history. Archive or move records first.";
                    else if (error === 'empty_fields') text = "Please fill in all required fields.";
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

        // AJAX search, filters, and pagination
        const searchInput = document.getElementById('searchInput');
        const courseFilter = document.getElementById('courseFilter');
        const genderFilter = document.getElementById('genderFilter');
        const contentWrapper = document.getElementById('contentWrapper');
        let debounceTimer;

        function triggerAjax(page = 1) {
            const searchTerm = searchInput.value;
            const course = courseFilter.value;
            const gender = genderFilter.value;

            contentWrapper.classList.add('is-loading');

            const url = new URL(window.location.href);
            url.searchParams.delete('success');
            url.searchParams.delete('error');
            url.searchParams.set('search', searchTerm);
            url.searchParams.set('course', course);
            url.searchParams.set('gender', gender);
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

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => triggerAjax(1), 400);
        });

        courseFilter.addEventListener('change', () => triggerAjax(1));
        genderFilter.addEventListener('change', () => triggerAjax(1));
        document.getElementById('searchForm').addEventListener('submit', e => e.preventDefault());

        document.getElementById('contentWrapper').addEventListener('click', function(e) {
            if (e.target.tagName === 'A' && e.target.classList.contains('page-btn') && !e.target.classList.contains('disabled')) {
                e.preventDefault();
                const url = new URL(e.target.href);
                triggerAjax(url.searchParams.get('page'));
            }
        });

        // PDF export
        function exportToPDF() {
            const element = document.getElementById('pdfExportArea');
            const clone = element.cloneNode(true);

            clone.style.background = '#ffffff';
            clone.style.padding = '20px';
            clone.querySelector('#pdfHeader').style.display = 'block';
            clone.querySelectorAll('.action-col').forEach(el => el.remove());

            const tableContainer = clone.querySelector('#tableContainer');
            tableContainer.style.overflow = 'visible';

            const hiddenWrapper = document.createElement('div');
            hiddenWrapper.style.position = 'absolute';
            hiddenWrapper.style.left = '-9999px';
            hiddenWrapper.appendChild(clone);
            document.body.appendChild(hiddenWrapper);

            const opt = {
                margin: 0.5,
                filename: 'KCCF_Student_Directory.pdf',
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff'
                },
                jsPDF: {
                    unit: 'in',
                    format: 'letter',
                    orientation: 'landscape'
                },
                pagebreak: {
                    mode: 'css',
                    avoid: 'tr'
                }
            };

            html2pdf().set(opt).from(clone).save().then(() => {
                document.body.removeChild(hiddenWrapper);
            });
        }
    </script>
    <script src="../assets/js/script.js"></script>
</body>

</html>