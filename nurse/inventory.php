<?php
require_once '../config/db_connect.php';
require_once '../config/auth_check.php';

// Pagination, search, and filters
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build the inventory query filters
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(name LIKE :search OR med_id LIKE :search OR category LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($category_filter)) {
    $where_clauses[] = "category = :category";
    $params[':category'] = $category_filter;
}

if (!empty($status_filter)) {
    if ($status_filter == 'In Stock') {
        $where_clauses[] = "quantity > 15";
    } elseif ($status_filter == 'Low Stock') {
        $where_clauses[] = "quantity > 5 AND quantity <= 15";
    } elseif ($status_filter == 'Critical') {
        $where_clauses[] = "quantity > 0 AND quantity <= 5";
    } elseif ($status_filter == 'Out of Stock') {
        $where_clauses[] = "quantity <= 0";
    }
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

try {
    // Count matching inventory records
    $count_query = "SELECT COUNT(*) FROM medicines $where_sql";
    $count_stmt = $conn->prepare($count_query);
    foreach ($params as $key => $val) {
        $count_stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch the current page of medicines
    $query = "SELECT * FROM medicines $where_sql ORDER BY med_id DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $medicines = $stmt->fetchAll();

    // Load categories for filter and autocomplete
    $cat_query = "SELECT category FROM medicines WHERE category != '' AND category IS NOT NULL GROUP BY category ORDER BY category ASC";
    $stmt_cat = $conn->prepare($cat_query);
    $stmt_cat->execute();
    $categories = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Inventory | KCCF Clinic (Nurse)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- html2pdf CDN for export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* Table layout */
        .data-table {
            width: 100%;
            min-width: 800px;
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
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal-box {
            background: var(--bg-base);
            width: 100%;
            max-width: 500px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transform: translateY(20px);
            transition: transform 0.2s ease-in-out;
            overflow: visible;
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
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            background: var(--bg-card);
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

        /* Button loading states */
        .action-btn {
            border-radius: 8px;
            transition: all 0.2s;
        }

        .btn-loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .btn-loading .spinner-icon {
            margin-right: 8px;
            font-size: 16px;
            margin-bottom: 0;
            color: inherit;
        }

        /* Filter controls */
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
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-family: inherit;
            font-size: 13px;
            background: var(--bg-base);
            color: var(--text-heading);
            outline: none;
        }

        /* Autocomplete styles */
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

        /* Pagination & Loading UI */
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

        /* Custom SweetAlert styling to match theme */
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
                        <p class="page-eyebrow">Inventory Management</p>
                        <h1 class="page-title">Medicine Stock</h1>
                        <p class="page-subtitle">Manage clinic medications and quickly restock arriving supplies.</p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-primary" style="border-radius: 8px;" onclick="openModal('addMedicineModal')">
                            <i class="ph ph-plus"></i> Add New Medicine
                        </button>
                    </div>
                </div>

                <div class="panel" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">

                    <div class="filter-bar print-hide">
                        <form id="searchForm" action="" method="GET" class="header-search">
                            <span class="search-icon"><i class="ph ph-magnifying-glass"></i></span>
                            <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search ID, name..." class="search-input" autocomplete="off">
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
                                <option value="In Stock" <?php if ($status_filter == 'In Stock') echo 'selected'; ?>>In Stock (Healthy)</option>
                                <option value="Low Stock" <?php if ($status_filter == 'Low Stock') echo 'selected'; ?>>Low Stock</option>
                                <option value="Critical" <?php if ($status_filter == 'Critical') echo 'selected'; ?>>Critical</option>
                                <option value="Out of Stock" <?php if ($status_filter == 'Out of Stock') echo 'selected'; ?>>Out of Stock</option>
                            </select>

                            <button class="btn btn-ghost" onclick="exportToPDF()" style="border-radius: 6px; padding: 8px 12px; border: 1px solid var(--border); font-size: 13px;" title="Download PDF">
                                <i class="ph ph-file-pdf" style="color: #EF4444; font-size: 16px;"></i> Export
                            </button>
                        </div>
                    </div>

                    <div class="table-wrapper" id="contentWrapper">
                        <div class="loader-overlay">
                            <i class="ph-bold ph-spinner-gap spinner-icon"></i>
                            <span class="spinner-text">Updating inventory...</span>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table" id="inventoryTable">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">Med ID</th>
                                        <th>Medicine Name</th>
                                        <th>Stock Level</th>
                                        <th>Status</th>
                                        <th>Expiration Date</th>
                                        <th class="text-right print-hide" style="width: 180px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($total_records > 0): ?>
                                        <?php foreach ($medicines as $med):
                                            $formatted_med_id = "MED-" . str_pad($med['med_id'], 4, "0", STR_PAD_LEFT);
                                            $qty = (int)$med['quantity'];

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
                                                <td style="font-family: monospace; font-weight: 600; color: var(--text-muted);"><?php echo $formatted_med_id; ?></td>
                                                <td>
                                                    <div class="med-name"><?php echo htmlspecialchars($med['name']); ?></div>
                                                    <div class="med-category"><?php echo htmlspecialchars($med['category']); ?></div>
                                                </td>
                                                <td><span class="stock-count" style="color: <?php echo $qty_color; ?>; font-weight: 700;"><?php echo $qty; ?></span> units</td>
                                                <td><span class="<?php echo $badge_class; ?>" style="<?php echo $badge_style; ?>"><?php echo $status_text; ?></span></td>
                                                <td><?php echo $exp_date; ?></td>
                                                <td class="text-right print-hide" style="white-space: nowrap;">

                                                    <button class="action-btn" onclick='openRestockModal(<?php echo $json_data; ?>)' title="Quick Restock">
                                                        <i class="ph ph-plus-circle"></i>
                                                    </button>

                                                    <button class="action-btn edit-btn" onclick='openEditModal(<?php echo $json_data; ?>)' title="Edit Details">
                                                        <i class="ph ph-pencil-simple"></i>
                                                    </button>

                                                    <button class="action-btn delete-btn" onclick='openDeleteModal(<?php echo $json_data; ?>)' title="Delete">
                                                        <i class="ph ph-trash"></i>
                                                    </button>

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                                No medicines found matching your search.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- PAGINATION UI -->
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

                <!-- Hidden export area -->
                <div id="pdfExportArea" style="display: none;">
                    <div id="pdfHeader" style="text-align: center; margin-bottom: 25px; border-bottom: 2px solid #1e293b; padding-bottom: 15px; background: #fff; padding-top: 20px;">
                        <h2 style="margin: 0 0 5px 0; font-family: 'DM Serif Display', serif; color: #0A2E1A;">KCCF Clinic Management System</h2>
                        <p style="margin: 0; font-size: 14px; color: #64748B;">Medicine Inventory Report</p>
                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #94A3B8;">Generated on <?php echo date('F d, Y \a\t h:i A'); ?></p>
                    </div>
                    <div id="tableContainer" style="overflow-x: auto;">
                        <table class="data-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f1f5f9; border-bottom: 2px solid #cbd5e1;">
                                    <th style="padding: 12px; text-align: left; font-weight: 700; color: #0A2E1A; border: 1px solid #e2e8f0;">Med ID</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 700; color: #0A2E1A; border: 1px solid #e2e8f0;">Medicine Name</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 700; color: #0A2E1A; border: 1px solid #e2e8f0;">Category</th>
                                    <th style="padding: 12px; text-align: center; font-weight: 700; color: #0A2E1A; border: 1px solid #e2e8f0;">Stock Level</th>
                                    <th style="padding: 12px; text-align: center; font-weight: 700; color: #0A2E1A; border: 1px solid #e2e8f0;">Status</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 700; color: #0A2E1A; border: 1px solid #e2e8f0;">Expiration Date</th>
                                </tr>
                            </thead>
                            <tbody id="pdfTableBody">
                                <!-- Will be populated by exportToPDF() -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- QUICK RESTOCK MODAL -->
    <div id="restockModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-package" style="color: var(--brand-primary);"></i> Quick Restock</h3>
                <button type="button" onclick="closeModal('restockModal')" style="background:none; border:none; font-size:24px; cursor:pointer; color:var(--text-muted);">&times;</button>
            </div>
            <form action="../backend/nurse/process_restock_medicine.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="med_id" id="restock_med_id">

                    <div style="text-align: center; margin-bottom: 24px;">
                        <div id="restock_med_name" style="font-size: 18px; font-weight: 700; color: var(--text-heading); margin-bottom: 4px;"></div>
                        <div style="font-size: 13px; color: var(--text-muted);">
                            Current Stock: <strong id="restock_current_qty" style="color: var(--text-heading);"></strong> units
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display:block; font-size:11px; font-weight:700; margin-bottom:8px; text-align: center; color: var(--text-muted); text-transform: uppercase;">Quantity to Add</label>
                        <input type="number" name="add_quantity" min="1" placeholder="0" required style="width:100%; padding:14px; border:1.5px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 18px; text-align: center; font-weight: 600;">
                    </div>
                </div>
                <div class="modal-footer" style="justify-content: center;">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('restockModal')" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px; padding: 10px 24px;">Update Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MEDICINE MODAL -->
    <div id="editMedicineModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-pencil-simple" style="color: var(--brand-primary);"></i> Edit Supply</h3>
                <button type="button" onclick="closeModal('editMedicineModal')" style="background:none; border:none; font-size:24px; cursor:pointer; color:var(--text-muted);">&times;</button>
            </div>
            <form action="../backend/nurse/process_update_medicine.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="med_id" id="edit_med_id">

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">MEDICINE NAME</label>
                        <input type="text" name="name" id="edit_name" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">CATEGORY</label>
                        <div class="autocomplete-wrapper">
                            <div style="position: relative;">
                                <i class="ph ph-tag" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 18px;"></i>
                                <input type="text" name="category" id="edit_category" placeholder="Type or select a category..." required autocomplete="off" style="width:100%; padding:12px 16px 12px 40px; border:1px solid var(--border); background: var(--bg-card); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                            </div>
                            <div id="editCategorySuggestions" class="suggestions-list"></div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">TOTAL STOCK (Override)</label>
                            <input type="number" name="quantity" id="edit_quantity" min="0" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">EXPIRY DATE</label>
                            <input type="date" name="expiration" id="edit_expiration" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('editMedicineModal')" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ADD MEDICINE MODAL -->
    <div id="addMedicineModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-plus-circle" style="color: var(--brand-primary);"></i> Add New Supply</h3>
                <button type="button" onclick="closeModal('addMedicineModal')" style="background:none; border:none; font-size:24px; cursor:pointer; color:var(--text-muted);">&times;</button>
            </div>
            <form action="../backend/nurse/process_add_medicine.php" method="POST">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">MEDICINE NAME</label>
                        <input type="text" name="name" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">CATEGORY</label>
                        <div class="autocomplete-wrapper">
                            <div style="position: relative;">
                                <i class="ph ph-tag" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 18px;"></i>
                                <input type="text" id="addCategoryInput" name="category" placeholder="Type or select a category..." required autocomplete="off" style="width:100%; padding:12px 16px 12px 40px; border:1px solid var(--border); background: var(--bg-card); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                            </div>
                            <div id="addCategorySuggestions" class="suggestions-list"></div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">INITIAL STOCK</label>
                            <input type="number" name="quantity" value="0" min="0" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                        </div>
                        <div class="form-group">
                            <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">EXPIRY DATE</label>
                            <input type="date" name="expiration" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('addMedicineModal')" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;">Save to Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE CONFIRMATION MODAL -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-box">
            <form action="../backend/nurse/process_delete_medicine.php" method="POST">
                <input type="hidden" name="med_id" id="delete_med_id">

                <div class="modal-body">
                    <i class="ph ph-warning-octagon" style="font-size: 64px; color: #DC2626; margin-bottom: 20px;"></i>
                    <h3 style="font-family: 'DM Serif Display', serif; font-size: 24px; margin-bottom: 10px;">Remove Supply?</h3>
                    <p style="color: var(--text-muted); font-size: 14px;">Are you sure you want to delete <strong id="delete_item_name" style="color: var(--text-heading);"></strong>?</p>
                </div>
                <div class="modal-footer" style="justify-content: center; background: none; border: none; padding-bottom: 30px;">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('deleteModal')" style="border-radius: 8px;">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitDeleteForm()" style="background: #DC2626; border-color: #DC2626; border-radius: 8px;">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelector('a[href="inventory.php"]').classList.add('active');

        // Modal Controls
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function openRestockModal(data) {
            document.getElementById('restock_med_id').value = data.med_id;
            document.getElementById('restock_med_name').textContent = data.name;
            document.getElementById('restock_current_qty').textContent = data.quantity;
            openModal('restockModal');
        }

        function openEditModal(data) {
            document.getElementById('edit_med_id').value = data.med_id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_category').value = data.category;
            document.getElementById('edit_quantity').value = data.quantity;
            document.getElementById('edit_expiration').value = data.expiration;
            openModal('editMedicineModal');
        }

        function openDeleteModal(data) {
            document.getElementById('delete_med_id').value = data.med_id;
            document.getElementById('delete_item_name').textContent = data.name;
            openModal('deleteModal');
        }

        function submitDeleteForm() {
            closeModal('deleteModal');

            // Show loading alert
            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: (modal) => {
                    Swal.showLoading();
                }
            });

            // Submit the form
            document.querySelector('#deleteModal form').submit();
        }

        // Close on outside click
        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active');
        }

        // Button loading state
        document.querySelectorAll('form:not(#searchForm)').forEach(form => {
            form.addEventListener('submit', function() {
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    if (btn.classList.contains('btn-loading')) return false; // Prevent double submit
                    btn.classList.add('btn-loading');
                    btn.innerHTML = '<i class="ph-bold ph-spinner-gap spinner-icon"></i> Please wait...';
                }
            });
        });

        // Alert notifications
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            const usageCount = urlParams.get('usage_count');

            if (success || error) {
                let title = '';
                let text = '';
                let icon = '';

                if (success) {
                    icon = 'success';
                    title = 'Success!';
                    if (success === 'added') text = "New medicine has been added to the inventory.";
                    else if (success === 'updated') text = "Medicine details have been successfully updated.";
                    else if (success === 'restocked') text = "Stock levels have been replenished.";
                    else if (success === 'deleted') text = "Medicine removed from the inventory.";
                }

                if (error) {
                    icon = 'error';
                    title = 'Action Failed';
                    if (error === 'medicine_not_found') text = "Medicine record not found.";
                    else if (error === 'medicine_in_use') text = "Cannot delete - this medicine is referenced in " + usageCount + " treatment record(s). Archive it instead.";
                    else text = "A database error occurred. Please try again.";
                }

                Swal.fire({
                    title: title,
                    text: text,
                    icon: icon,
                    confirmButtonText: 'Got it',
                    confirmButtonColor: '#2563EB'
                }).then(() => {
                    // Clean URL to prevent re-firing on refresh
                    const newUrl = window.location.pathname + window.location.search.replace(/[?&](success|error)=[^&]+/, '').replace(/^&/, '?');
                    window.history.replaceState({}, document.title, newUrl);
                });
            }
        });

        // AJAX search, filter, and pagination
        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const statusFilter = document.getElementById('statusFilter');
        const contentWrapper = document.getElementById('contentWrapper');
        let debounceTimer;

        function triggerAjax(page = 1) {
            contentWrapper.classList.add('is-loading');

            const url = new URL(window.location.href);
            // Delete alert params so they don't stick around during ajax requests
            url.searchParams.delete('success');
            url.searchParams.delete('error');

            url.searchParams.set('search', searchInput.value);
            url.searchParams.set('category', categoryFilter.value);
            url.searchParams.set('status', statusFilter.value);
            url.searchParams.set('page', page);

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    document.querySelector('.data-table tbody').innerHTML = doc.querySelector('.data-table tbody').innerHTML;

                    const newPagination = doc.getElementById('paginationContainer');
                    if (newPagination) {
                        document.getElementById('paginationContainer').innerHTML = newPagination.innerHTML;
                    } else {
                        document.getElementById('paginationContainer').innerHTML = '';
                    }

                    contentWrapper.classList.remove('is-loading');
                    window.history.pushState({}, '', url);
                })
                .catch(() => contentWrapper.classList.remove('is-loading'));
        }

        // Event Listeners for Filters
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => triggerAjax(1), 400);
        });
        categoryFilter.addEventListener('change', () => triggerAjax(1));
        statusFilter.addEventListener('change', () => triggerAjax(1));

        document.getElementById('searchForm').addEventListener('submit', e => e.preventDefault());

        // Delegate pagination clicks
        document.getElementById('contentWrapper').addEventListener('click', function(e) {
            if (e.target.tagName === 'A' && e.target.classList.contains('page-btn') && !e.target.classList.contains('disabled')) {
                e.preventDefault();
                const url = new URL(e.target.href);
                triggerAjax(url.searchParams.get('page'));
            }
        });

        // PDF export
        function exportToPDF() {
            const table = document.querySelector('.data-table');
            const clone = table.cloneNode(true);

            // Create header section
            const headerDiv = document.createElement('div');
            headerDiv.style.textAlign = 'center';
            headerDiv.style.marginBottom = '25px';
            headerDiv.style.borderBottom = '2px solid #1e293b';
            headerDiv.style.paddingBottom = '15px';
            headerDiv.style.background = '#fff';
            headerDiv.style.paddingTop = '20px';

            const logo = document.createElement('img');
            logo.src = '../assets/images/logo.jpg';
            logo.style.maxWidth = '80px';
            logo.style.height = 'auto';
            logo.style.marginBottom = '10px';
            logo.style.display = 'block';
            logo.style.margin = '0 auto 10px auto';

            const title = document.createElement('h2');
            title.textContent = 'KCCF Clinic Management System';
            title.style.margin = '0 0 5px 0';
            title.style.fontFamily = "'DM Serif Display', serif";
            title.style.color = '#0A2E1A';
            title.style.fontSize = '24px';

            const location = document.createElement('p');
            location.textContent = 'Cavite, Magallanes';
            location.style.margin = '0 0 3px 0';
            location.style.fontSize = '13px';
            location.style.color = '#64748B';
            location.style.fontWeight = '600';

            const report = document.createElement('p');
            report.textContent = 'Medicine Inventory Report';
            report.style.margin = '5px 0 0 0';
            report.style.fontSize = '12px';
            report.style.color = '#94A3B8';

            const date = document.createElement('p');
            date.textContent = 'Generated on <?php echo date('F d, Y \a\t h:i A'); ?>';
            date.style.margin = '5px 0 0 0';
            date.style.fontSize = '12px';
            date.style.color = '#94A3B8';

            headerDiv.appendChild(logo);
            headerDiv.appendChild(title);
            headerDiv.appendChild(location);
            headerDiv.appendChild(report);
            headerDiv.appendChild(date);

            // Remove action column (last th and last td in each row)
            clone.querySelectorAll('th:last-child, td:last-child').forEach(el => el.remove());

            // Create wrapper
            const wrapper = document.createElement('div');
            wrapper.style.background = '#ffffff';
            wrapper.style.padding = '20px';
            wrapper.appendChild(headerDiv);
            wrapper.appendChild(clone);

            // Hide wrapper
            const hiddenWrapper = document.createElement('div');
            hiddenWrapper.style.position = 'absolute';
            hiddenWrapper.style.left = '-9999px';
            hiddenWrapper.appendChild(wrapper);
            document.body.appendChild(hiddenWrapper);

            const opt = {
                margin: 0.5,
                filename: 'KCCF_Medicine_Inventory.pdf',
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

            html2pdf().set(opt).from(wrapper).save().then(() => {
                document.body.removeChild(hiddenWrapper);
            });
        }

        // ==========================================
        // AUTOCOMPLETE LOGIC FOR CATEGORIES
        // ==========================================
        const categoriesData = <?php echo json_encode($categories); ?>;

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

        setupAutocomplete('addCategoryInput', 'addCategorySuggestions', categoriesData, 'ph ph-tag');
        setupAutocomplete('edit_category', 'editCategorySuggestions', categoriesData, 'ph ph-tag');
    </script>
    <script src="../assets/js/script.js"></script>
</body>

</html>