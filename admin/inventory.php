<?php
require_once '../config/db_connect.php';
require_once '../config/auth_check.php';

// Pagination and search filters
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Load category options for the filter dropdown
try {
    $cat_stmt = $conn->query("SELECT DISTINCT category FROM medicines WHERE category != '' ORDER BY category ASC");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

// Build the inventory query filters
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(name LIKE :search OR category LIKE :search OR med_id LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($category_filter)) {
    $where_clauses[] = "category = :category";
    $params[':category'] = $category_filter;
}
if (!empty($status_filter)) {
    // Match the selected status to the stored database value
    $where_clauses[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

try {
    // Count matching inventory records
    $count_query = "SELECT COUNT(*) FROM medicines $where_sql";
    $count_stmt = $conn->prepare($count_query);
    foreach ($params as $key => $val) {
        $count_stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch the current page of medicines
    $query = "
        SELECT * FROM medicines
        $where_sql
        ORDER BY name ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $medicines = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching inventory: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Inventory | KCCF Clinic (Admin)</title>
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
            max-width: 500px;
            border-radius: var(--r-lg);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
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
        }

        .modal-title {
            font-family: 'DM Serif Display', serif;
            font-size: 20px;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            background: var(--bg-card);
            display: flex;
            justify-content: center;
            gap: 12px;
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

        /* Badge Colors */
        .badge-gold {
            background: #FEF3C7;
            color: #D97706;
        }

        .badge-red {
            background: #FEECEC;
            color: #DC2626;
        }

        .badge-green {
            background: #EAF4EE;
            color: #10B981;
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
                        <p class="page-eyebrow">Data Management</p>
                        <h1 class="page-title">Medicine Inventory</h1>
                        <p class="page-subtitle">Real-time overview of clinic medical supplies and stock levels.</p>
                    </div>
                </div>

                <div class="panel" id="tablePanel" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">

                    <div class="filter-bar">

                        <form id="searchForm" action="" method="GET" class="header-search">
                            <span class="search-icon"><i class="ph ph-magnifying-glass"></i></span>
                            <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by ID or name..." class="search-input" autocomplete="off">
                        </form>

                        <div class="filter-group">
                            <select id="categoryFilter" class="filter-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php if ($category_filter == $cat) echo 'selected'; ?>><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select id="statusFilter" class="filter-select">
                                <option value="">All Statuses</option>
                                <option value="In Stock" <?php if ($status_filter == 'In Stock') echo 'selected'; ?>>In Stock</option>
                                <option value="Low Stock" <?php if ($status_filter == 'Low Stock') echo 'selected'; ?>>Low Stock</option>
                                <option value="Critical" <?php if ($status_filter == 'Critical') echo 'selected'; ?>>Critical</option>
                                <option value="Out of Stock" <?php if ($status_filter == 'Out of Stock') echo 'selected'; ?>>Out of Stock</option>
                            </select>

                            <button class="btn btn-ghost" onclick="exportToPDF()" style="border-radius: 6px; padding: 6px 12px; border: 1px solid var(--border); font-size: 13px;" title="Download PDF">
                                <i class="ph ph-file-pdf" style="color: #EF4444; font-size: 16px;"></i> Export
                            </button>
                        </div>
                    </div>

                    <div class="table-wrapper" id="contentWrapper">

                        <div class="loader-overlay">
                            <i class="ph-bold ph-spinner-gap spinner-icon"></i>
                            <span class="spinner-text">Updating inventory...</span>
                        </div>

                        <div id="pdfExportArea">

                            <div id="pdfHeader" style="display: none; text-align: center; margin-bottom: 25px; border-bottom: 2px solid #1e293b; padding-bottom: 15px; background: #fff; padding-top: 20px;">
                                <img src="../assets/images/logo.jpg" alt="KCCF Logo" style="width: 70px; height: 70px; margin-bottom: 10px; border-radius: 50%;">
                                <h2 style="margin: 0; color: #1e293b; font-family: 'DM Serif Display', serif;">Kurios Christian Colleges Foundation</h2>
                                <p style="margin: 2px 0; color: #64748b; font-size: 14px;">Magallanes, Cavite</p>
                                <h3 style="margin-top: 15px; text-transform: uppercase; font-size: 16px; letter-spacing: 0.5px;">Clinic Inventory Report</h3>
                                <p style="font-size: 12px; color: #475569; margin-top: 5px;">Document Generated: <?php echo date('F d, Y h:i A'); ?></p>
                            </div>

                            <div class="table-responsive" id="tableContainer" style="padding: 0;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 100px;">Med ID</th>
                                            <th>Medicine Details</th>
                                            <th>Stock Level</th>
                                            <th>Status</th>
                                            <th>Expiration Date</th>
                                            <th class="text-right action-col" style="width: 80px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($medicines): ?>
                                            <?php foreach ($medicines as $med):
                                                $formatted_med_id = "MED-" . str_pad($med['med_id'], 4, "0", STR_PAD_LEFT);
                                                $qty = (int)$med['quantity'];

                                                // Dynamic UI Badges based on quantity logic
                                                if ($qty <= 0) {
                                                    $status_text = 'Out of Stock';
                                                    $badge_class = 'badge';
                                                    $badge_style = 'background:#F1F5F9; color:#64748B;';
                                                    $qty_color = '#64748B';
                                                } elseif ($qty <= 5) {
                                                    $status_text = 'Critical';
                                                    $badge_class = 'badge badge-red';
                                                    $badge_style = '';
                                                    $qty_color = '#DC2626';
                                                } elseif ($qty <= 15) {
                                                    $status_text = 'Low Stock';
                                                    $badge_class = 'badge badge-gold';
                                                    $badge_style = '';
                                                    $qty_color = '#D97706';
                                                } else {
                                                    $status_text = 'In Stock';
                                                    $badge_class = 'badge badge-green';
                                                    $badge_style = '';
                                                    $qty_color = 'var(--text-heading)';
                                                }

                                                $exp_date = ($med['expiration'] && $med['expiration'] !== '0000-00-00')
                                                    ? date('M d, Y', strtotime($med['expiration']))
                                                    : '--';

                                                $json_data = htmlspecialchars(json_encode($med), ENT_QUOTES, 'UTF-8');
                                            ?>
                                                <tr>
                                                    <td data-label="Med ID" style="font-family: monospace; font-weight: 600; color: var(--text-muted);"><?php echo $formatted_med_id; ?></td>
                                                    <td data-label="Medicine Details">
                                                        <div class="med-name"><?php echo htmlspecialchars($med['name']); ?></div>
                                                        <div class="med-category"><?php echo htmlspecialchars($med['category']); ?></div>
                                                    </td>
                                                    <td data-label="Stock Level"><span class="stock-count" style="color: <?php echo $qty_color; ?>; font-weight: 700;"><?php echo $qty; ?></span> units</td>
                                                    <td data-label="Status"><span class="<?php echo $badge_class; ?>" style="<?php echo $badge_style; ?>"><?php echo $status_text; ?></span></td>
                                                    <td data-label="Expiration Date"><?php echo $exp_date; ?></td>
                                                    <td data-label="Actions" class="text-right action-col">
                                                        <button class="action-btn" style="border-radius: 8px;" onclick='viewMed(<?php echo $json_data; ?>, "<?php echo $formatted_med_id; ?>", "<?php echo $qty; ?>", "<?php echo $status_text; ?>", "<?php echo $badge_class; ?>", "<?php echo $badge_style; ?>")' title="View Details">
                                                            <i class="ph ph-eye" style="font-size: 18px;"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                                    No medicines found matching your filters.
                                                </td>
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
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> items
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

    <div id="viewMedModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-pill" style="color: var(--brand-primary);"></i> Medicine Details</h3>
                <button onclick="closeModal('viewMedModal')" style="background:none; border:none; font-size:24px; cursor:pointer; color: var(--text-muted);">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; background: var(--bg-card); padding: 12px 16px; border-radius: var(--r-sm); border: 1px solid var(--border);">
                    <div>
                        <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Med ID</span>
                        <div id="v-med-id" style="font-weight: 700; color: var(--text-heading); font-family: monospace;"></div>
                    </div>
                    <div style="text-align: right;" id="v-status-container"></div>
                </div>

                <div class="detail-grid">
                    <div class="detail-item" style="grid-column: span 2;">
                        <label>Medicine Name</label>
                        <p id="v-name" style="font-size: 18px; font-weight: 700; color: var(--brand-primary);"></p>
                    </div>
                    <div class="detail-item">
                        <label>Category</label>
                        <p id="v-category"></p>
                    </div>
                    <div class="detail-item">
                        <label>Expiration Date</label>
                        <p id="v-exp"></p>
                    </div>
                </div>

                <div style="text-align: center; padding: 20px; background: var(--bg-card); border-radius: 8px; border: 1px dashed var(--border);">
                    <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Current Total Stock</label>
                    <div style="font-size: 28px; font-weight: 700; color: var(--text-heading); font-family: 'Outfit', sans-serif;">
                        <span id="v-qty"></span> <span style="font-size: 16px; color: var(--text-muted); font-weight: 500;">units</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('viewMedModal')" style="border-radius: 8px; padding: 10px 24px;">Close Form</button>
            </div>
        </div>
    </div>

    <script>
        // Set Active Nav
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelector('a[href="inventory.php"]').classList.add('active');

        // Modal Logic
        function viewMed(data, formattedId, qty, statusText, badgeClass, badgeStyle) {
            document.getElementById('v-med-id').innerText = formattedId;
            document.getElementById('v-name').innerText = data.name;
            document.getElementById('v-category').innerText = data.category;

            let expDate = (data.expiration && data.expiration !== '0000-00-00') ?
                new Date(data.expiration).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                }) :
                'Not specified';
            document.getElementById('v-exp').innerText = expDate;

            document.getElementById('v-qty').innerText = qty;

            let qtyColor = 'var(--text-heading)';
            if (statusText === 'Low Stock') qtyColor = '#D97706';
            if (statusText === 'Critical' || statusText === 'Out of Stock') qtyColor = '#DC2626';
            document.getElementById('v-qty').style.color = qtyColor;

            document.getElementById('v-status-container').innerHTML = `<span class="${badgeClass}" style="${badgeStyle}">${statusText}</span>`;
            document.getElementById('viewMedModal').classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active');
        }

        // ==========================================
        // AJAX SEARCH, FILTERS & PAGINATION WITH LOADER
        // ==========================================
        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const statusFilter = document.getElementById('statusFilter');
        const contentWrapper = document.getElementById('contentWrapper');
        let debounceTimer;

        function triggerAjax(page = 1) {
            const searchTerm = searchInput.value;
            const category = categoryFilter.value;
            const status = statusFilter.value;

            contentWrapper.classList.add('is-loading');

            const url = new URL(window.location.href);
            url.searchParams.set('search', searchTerm);
            url.searchParams.set('category', category);
            url.searchParams.set('status', status);
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

        categoryFilter.addEventListener('change', () => triggerAjax(1));
        statusFilter.addEventListener('change', () => triggerAjax(1));
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
                filename: 'KCCF_Inventory_Report.pdf',
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