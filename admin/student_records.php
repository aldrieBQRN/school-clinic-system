<?php
require_once '../config/db_connect.php';
require_once '../config/auth_check.php';

// Pagination and search filters
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course_filter = isset($_GET['course']) ? $_GET['course'] : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';

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
        SELECT s.* FROM students s
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
} catch (PDOException $e) {
    die("Error fetching student records: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records | KCCF Clinic (Admin)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* Table layout */
        .data-table {
            width: 100%;
            min-width: 800px;
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
        }

        /* Modal close button */
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

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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

        /* Filter controls */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
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
            gap: 10px;
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

        /* Mobile App Card Layout (phones only; tablet keeps table) */
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
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/admin_header.php'; ?>

            <main class="content-body">
                <div class="page-header print-hide">
                    <div class="page-header-text">
                        <p class="page-eyebrow">Patient Database</p>
                        <h1 class="page-title">Student Records</h1>
                        <p class="page-subtitle">Centralized database for student medical profiles.</p>
                    </div>
                </div>

                <div class="panel" id="tablePanel" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">

                    <div class="filter-bar">
                        <form id="searchForm" action="" method="GET" class="header-search">
                            <span class="search-icon"><i class="ph ph-magnifying-glass"></i></span>
                            <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by ID or name..." class="search-input" autocomplete="off">
                        </form>

                        <div class="filter-group">
                            <select id="courseFilter" class="filter-select">
                                <option value="">All Courses</option>
                                <option value="BSED" <?php if ($course_filter == 'BSED') echo 'selected'; ?>>BSED</option>
                                <option value="BSIT" <?php if ($course_filter == 'BSIT') echo 'selected'; ?>>BSIT</option>
                                <option value="BSCRIM" <?php if ($course_filter == 'BSCRIM') echo 'selected'; ?>>BSCRIM</option>
                                <option value="BSBA" <?php if ($course_filter == 'BSBA') echo 'selected'; ?>>BSBA</option>
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
                                            <th class="text-right action-col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($students): ?>
                                            <?php foreach ($students as $row):
                                                $birthdate = new DateTime($row['birthdate']);
                                                $age = $birthdate->diff(new DateTime('today'))->y;
                                                $formatted_id = "KCCF-" . str_pad($row['student_id'], 4, "0", STR_PAD_LEFT);

                                                // Date and Time formatting based on created_at
                                                $created_at = strtotime($row['created_at']);
                                                $reg_date = date("M d, Y", $created_at);
                                                $reg_time = date("h:i A", $created_at);
                                            ?>
                                                <tr>
                                                    <td data-label="Student ID" style="font-weight: 600; color: var(--brand-primary);"><?php echo $formatted_id; ?></td>
                                                    <td data-label="Patient Name" style="font-weight: 500; color: var(--text-heading);"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                    <td data-label="Age"><?php echo $age; ?> yrs</td>
                                                    <td data-label="Gender"><?php echo htmlspecialchars($row['gender']); ?></td>
                                                    <td data-label="Course & Year"><?php echo htmlspecialchars($row['course'] . " - " . $row['year_level'] . "th Yr"); ?></td>
                                                    <td data-label="Date Registered">
                                                        <div style="font-weight: 500; color: var(--text-heading);"><?php echo $reg_date; ?></div>
                                                        <div style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 4px;"><?php echo $reg_time; ?></div>
                                                    </td>
                                                    <td data-label="Actions" class="text-right action-col">
                                                        <button class="action-btn" title="View Profile" onclick='viewStudent(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>, "<?php echo $formatted_id; ?>", <?php echo $age; ?>)'>
                                                            <i class="ph ph-eye" style="font-size: 18px;"></i>
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

    <div id="studentModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title">Full Medical Profile</h3>
                <button class="modal-close" onclick="closeModal()" title="Close">&times;</button>
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

                <div class="section-tag">Academic & Personal Info</div>
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
            <div style="padding: 16px 24px; background: var(--bg-card); border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeModal()" style="border-radius: 8px;">Close Profile</button>
            </div>
        </div>
    </div>

    <script>
        // Set Active Nav
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelector('a[href="student_records.php"]').classList.add('active');

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
            document.getElementById('m-course').innerText = data.course + ' - ' + data.year_level + 'th Year';
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

        function closeModal() {
            document.getElementById('studentModal').classList.remove('active');
        }
        window.onclick = function(e) {
            if (e.target == document.getElementById('studentModal')) closeModal();
        }

        // ==========================================
        // AJAX SEARCH, FILTERS & PAGINATION WITH LOADER
        // ==========================================
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
                    document.getElementById('paginationContainer').innerHTML = doc.getElementById('paginationContainer').innerHTML;

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

        document.getElementById('paginationContainer').addEventListener('click', function(e) {
            if (e.target.tagName === 'A' && e.target.classList.contains('page-btn') && !e.target.classList.contains('disabled')) {
                e.preventDefault();
                const url = new URL(e.target.href);
                triggerAjax(url.searchParams.get('page'));
            }
        });

        // ==========================================
        // EXPORT TO PDF (INVISIBLE CLONE METHOD WITH PAGE BREAK FIX)
        // ==========================================
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