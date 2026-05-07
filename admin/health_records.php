<?php
require_once '../config/db_connect.php';
require_once '../config/auth_check.php';

// Pagination, search, and filters
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course_filter = isset($_GET['course']) ? $_GET['course'] : '';

// Date range filters
$from_date = isset($_GET['from']) ? $_GET['from'] : '';
$to_date = isset($_GET['to']) ? $_GET['to'] : '';

// Build the health record query filters
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR hr.diagnosis LIKE :search OR s.student_id LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($course_filter)) {
    $where_clauses[] = "s.course = :course";
    $params[':course'] = $course_filter;
}
if (!empty($from_date)) {
    $where_clauses[] = "DATE(hr.date) >= :from_date";
    $params[':from_date'] = $from_date;
}
if (!empty($to_date)) {
    $where_clauses[] = "DATE(hr.date) <= :to_date";
    $params[':to_date'] = $to_date;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

try {
    // Count matching health records
    $count_query = "
        SELECT COUNT(*)
        FROM health_records hr
        JOIN visits v ON hr.visit_id = v.visit_id
        JOIN students s ON hr.student_id = s.student_id
        $where_sql
    ";
    $count_stmt = $conn->prepare($count_query);
    foreach ($params as $key => $val) {
        $count_stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch the current page of records
    $query = "
        SELECT
            hr.record_id, hr.diagnosis, hr.treatment, hr.date as record_date,
            v.visit_id, v.complaint, v.temperature, v.height, v.weight, v.nurse_notes, v.time_in,
            s.student_id as real_student_id, s.first_name, s.last_name, s.course, s.year_level
        FROM health_records hr
        JOIN visits v ON hr.visit_id = v.visit_id
        JOIN students s ON hr.student_id = s.student_id
        $where_sql
        ORDER BY hr.date DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Records | KCCF Clinic (Admin)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        .data-table {
            width: 100%;
            min-width: 900px;
        }

        .data-table tr {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

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

        /* Fixed Modal Close Button */
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
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        /* Mobile: stack detail grid */
        @media (max-width: 767px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
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

        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }

        /* Mobile (phones only; tablet keeps table) */
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
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/admin_header.php'; ?>

            <main class="content-body">
                <div class="page-header print-hide">
                    <div class="page-header-text">
                        <p class="page-eyebrow">Medical Archive</p>
                        <h1 class="page-title">Health Records</h1>
                        <p class="page-subtitle">Full history of student diagnoses and treatments.</p>
                    </div>
                </div>

                <div class="panel" id="tablePanel" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">

                    <div class="filter-bar">
                        <form id="searchForm" action="" method="GET" class="header-search">
                            <span class="search-icon"><i class="ph ph-magnifying-glass"></i></span>
                            <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name or diagnosis..." class="search-input" autocomplete="off">
                        </form>

                        <div class="filter-group">
                            <span class="date-label">From:</span>
                            <input type="date" id="fromDate" class="date-input" value="<?php echo $from_date; ?>">
                            <span class="date-label">To:</span>
                            <input type="date" id="toDate" class="date-input" value="<?php echo $to_date; ?>">

                            <select id="courseFilter" class="filter-select">
                                <option value="">All Courses</option>
                                <option value="BSED" <?php if ($course_filter == 'BSED') echo 'selected'; ?>>BSED</option>
                                <option value="BSIT" <?php if ($course_filter == 'BSIT') echo 'selected'; ?>>BSIT</option>
                                <option value="BSCRIM" <?php if ($course_filter == 'BSCRIM') echo 'selected'; ?>>BSCRIM</option>
                                <option value="BSBA" <?php if ($course_filter == 'BSBA') echo 'selected'; ?>>BSBA</option>
                            </select>

                            <button class="btn btn-ghost" onclick="exportToPDF()" style="border-radius: 6px; padding: 6px 12px; border: 1px solid var(--border); font-size: 13px;" title="Download PDF">
                                <i class="ph ph-file-pdf" style="color: #EF4444; font-size: 16px;"></i> Export
                            </button>
                        </div>
                    </div>

                    <div class="table-wrapper" id="contentWrapper">
                        <div class="loader-overlay">
                            <i class="ph-bold ph-spinner-gap spinner-icon"></i>
                            <span class="spinner-text">Fetching records...</span>
                        </div>

                        <div id="pdfExportArea">
                            <div id="pdfHeader" style="display: none; text-align: center; margin-bottom: 25px; border-bottom: 2px solid #1e293b; padding-bottom: 15px; background: #fff; padding-top: 20px;">
                                <img src="../assets/images/logo.jpg" alt="KCCF Logo" style="width: 70px; height: 70px; margin-bottom: 10px; border-radius: 50%;">
                                <h2 style="margin: 0; color: #1e293b; font-family: 'DM Serif Display', serif;">Kurios Christian Colleges Foundation</h2>
                                <p style="margin: 2px 0; color: #64748b; font-size: 14px;">Magallanes, Cavite</p>
                                <h3 style="margin-top: 15px; text-transform: uppercase; font-size: 16px; letter-spacing: 0.5px;">Health Records Archive</h3>
                                <p style="font-size: 12px; color: #475569; margin-top: 5px;">Document Generated: <?php echo date('F d, Y h:i A'); ?></p>
                            </div>

                            <div class="table-responsive" id="tableContainer">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 100px;">Record ID</th>
                                            <th>Patient Details</th>
                                            <th>Diagnosis</th>
                                            <th>Treatment</th>
                                            <th style="width: 130px;">Date & Time</th>
                                            <th class="text-right action-col" style="width: 60px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($records): foreach ($records as $row):
                                                $f_rec_id = "REC-" . str_pad($row['record_id'], 4, "0", STR_PAD_LEFT);
                                                $f_stu_id = "KCCF-" . str_pad($row['real_student_id'], 4, "0", STR_PAD_LEFT);
                                        ?>
                                                <tr>
                                                    <td data-label="Record ID" style="font-family: monospace; font-weight: 600; color: var(--text-muted);"><?php echo $f_rec_id; ?></td>
                                                    <td data-label="Patient Details">
                                                        <div style="font-weight: 500; color: var(--text-heading);"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                                        <div style="font-size: 11px; color: var(--text-muted);">ID: <?php echo $f_stu_id; ?></div>
                                                    </td>
                                                    <td data-label="Diagnosis" style="font-weight: 600; color: var(--text-heading);"><?php echo htmlspecialchars($row['diagnosis']); ?></td>
                                                    <td data-label="Treatment" style="font-size: 13px; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                        <?php echo htmlspecialchars($row['treatment']); ?>
                                                    </td>
                                                    <td data-label="Date & Time">
                                                        <div style="font-weight: 500; color: var(--text-heading);"><?php echo date("M d, Y", strtotime($row['record_date'])); ?></div>
                                                        <div style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 4px;"><?php echo date("h:i A", strtotime($row['record_date'])); ?></div>
                                                    </td>
                                                    <td data-label="Actions" class="text-right action-col">
                                                        <button type="button" class="action-btn" title="View Details" style="border-radius: 8px;" onclick='viewRecord(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>, "<?php echo $f_rec_id; ?>", "VST-<?php echo str_pad($row['visit_id'], 4, "0", STR_PAD_LEFT); ?>", "<?php echo $f_stu_id; ?>")'>
                                                            <i class="ph ph-eye" style="font-size: 18px;"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach;
                                        else: ?>
                                            <tr>
                                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No records found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div id="paginationContainer">
                            <?php if ($total_pages > 0): ?>
                                <div class="pagination-container">
                                    <div class="page-info">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> records</div>
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

    <div id="recordModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-file-text" style="color: var(--brand-primary);"></i> Health Record Details</h3>
                <button class="modal-close" onclick="closeModal('recordModal')" title="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: var(--bg-card); padding: 12px 16px; border-radius: var(--r-sm); border: 1px solid var(--border); gap: 16px;">
                    <div><span class="date-label">Record ID</span>
                        <div id="m-rec-id" style="font-weight: 700; color: var(--text-heading); font-family: monospace;"></div>
                    </div>
                    <div><span class="date-label">Student ID</span>
                        <div id="m-stu-id" style="font-weight: 700; color: var(--text-heading); font-family: monospace;"></div>
                    </div>
                    <div style="text-align: right;"><span class="date-label">Date Logged</span>
                        <div id="m-date" style="font-size: 13px; font-weight: 500; color: var(--text-heading);"></div>
                    </div>
                </div>
                <div class="section-tag">Patient Info</div>
                <div class="detail-grid">
                    <div class="detail-item"><label>Name</label>
                        <p id="m-name"></p>
                    </div>
                    <div class="detail-item"><label>Course & Year</label>
                        <p id="m-course"></p>
                    </div>
                </div>
                <div class="section-tag">Triage & Assessment</div>
                <div class="detail-grid">
                    <div class="detail-item"><label>Diagnosis</label>
                        <p id="m-diagnosis" style="font-weight: 600;"></p>
                    </div>
                    <div class="detail-item"><label>Temp</label>
                        <p id="m-temp"></p>
                    </div>
                    <div class="detail-item"><label>Time In</label>
                        <p id="m-time"></p>
                    </div>
                </div>
                <div class="detail-item"><label>Treatment</label>
                    <p id="m-treatment"></p>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('recordModal')" style="border-radius: 8px;">Close Window</button></div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelector('a[href="health_records.php"]').classList.add('active');

        // Convert number to ordinal format (1st, 2nd, 3rd, 4th, etc.)
        function getOrdinal(num) {
            const j = num % 10,
                k = num % 100;
            if (j === 1 && k !== 11) return num + "st";
            if (j === 2 && k !== 12) return num + "nd";
            if (j === 3 && k !== 13) return num + "rd";
            return num + "th";
        }

        function setModalText(id, value) {
            const element = document.getElementById(id);
            if (element) element.innerText = value;
        }

        function viewRecord(data, recId, visId, stuId) {
            setModalText('m-rec-id', recId);
            setModalText('m-stu-id', stuId);

            // Format datetime cleanly for the modal
            let dtObj = new Date(data.record_date);
            let formattedDateStr = dtObj.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            }) + " • " + dtObj.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
            setModalText('m-date', formattedDateStr);
            setModalText('m-name', data.first_name + ' ' + data.last_name);
            setModalText('m-course', data.course + ' - ' + getOrdinal(data.year_level) + ' Year');
            setModalText('m-temp', data.temperature + ' °C');

            let timeInObj = new Date('1970-01-01T' + data.time_in + 'Z');
            setModalText('m-time', timeInObj.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                timeZone: 'UTC'
            }));

            setModalText('m-diagnosis', data.diagnosis);
            setModalText('m-treatment', data.treatment);

            const modal = document.getElementById('recordModal');
            if (modal) modal.classList.add('active');
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.remove('active');
        }

        // Close on outside click
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
            url.searchParams.set('search', searchInput.value);
            url.searchParams.set('course', courseFilter.value);
            url.searchParams.set('from', fromDate.value);
            url.searchParams.set('to', toDate.value);
            url.searchParams.set('page', page);

            fetch(url).then(r => r.text()).then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                document.querySelector('.data-table tbody').innerHTML = doc.querySelector('.data-table tbody').innerHTML;
                document.getElementById('paginationContainer').innerHTML = doc.getElementById('paginationContainer').innerHTML;
                contentWrapper.classList.remove('is-loading');
                window.history.pushState({}, '', url);
            });
        }

        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => triggerAjax(1), 400);
        });
        [courseFilter, fromDate, toDate].forEach(el => el.addEventListener('change', () => triggerAjax(1)));
        document.getElementById('searchForm').addEventListener('submit', e => e.preventDefault());

        document.getElementById('paginationContainer').addEventListener('click', e => {
            if (e.target.classList.contains('page-btn') && !e.target.classList.contains('disabled')) {
                e.preventDefault();
                const url = new URL(e.target.href);
                triggerAjax(url.searchParams.get('page'));
            }
        });

        function exportToPDF() {
            const element = document.getElementById('pdfExportArea');
            const clone = element.cloneNode(true);
            clone.style.background = '#ffffff';
            clone.style.padding = '20px';
            clone.querySelector('#pdfHeader').style.display = 'block';
            clone.querySelectorAll('.action-col').forEach(el => el.remove());

            const hiddenWrapper = document.createElement('div');
            hiddenWrapper.style.position = 'absolute';
            hiddenWrapper.style.left = '-9999px';
            hiddenWrapper.appendChild(clone);
            document.body.appendChild(hiddenWrapper);

            html2pdf().set({
                margin: 0.5,
                filename: 'KCCF_Health_Records.pdf',
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2,
                    useCORS: true
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
            }).from(clone).save().then(() => document.body.removeChild(hiddenWrapper));
        }
    </script>
    <script src="../assets/js/script.js"></script>
</body>

</html>