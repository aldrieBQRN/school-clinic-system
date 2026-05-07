<?php
require_once '../config/db_connect.php';
require_once '../config/auth_check.php';

// Pagination, search, and filters
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build the user query filters
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(name LIKE :search OR username LIKE :search OR user_id LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($role_filter)) {
    $where_clauses[] = "role = :role";
    $params[':role'] = $role_filter;
}
if (!empty($status_filter)) {
    $where_clauses[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

try {
    // Count matching user records
    $count_query = "SELECT COUNT(*) FROM users $where_sql";
    $count_stmt = $conn->prepare($count_query);
    foreach ($params as $key => $val) {
        $count_stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_users = $count_stmt->fetchColumn();
    $total_pages = ceil($total_users / $limit);

    // Fetch the current page of users
    $query = "
        SELECT * FROM users
        $where_sql
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
    $total_users = 0;
    $total_pages = 0;
    $db_error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Accounts | KCCF Clinic (Admin)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            overflow: hidden;
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
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            background: var(--bg-card);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Form Styling inside Modals */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-body);
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--brand-primary);
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
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-family: inherit;
            font-size: 13px;
            background: var(--bg-base);
            color: var(--text-heading);
            outline: none;
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

        /* SweetAlert Styling */
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
                        <p class="page-eyebrow">System Management</p>
                        <h1 class="page-title">Staff Accounts</h1>
                        <p class="page-subtitle">Manage system access for clinic nurses and administrators.</p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-primary" style="border-radius: 8px;" onclick="openAddModal()">
                            <i class="ph ph-user-plus"></i> Add New Staff
                        </button>
                    </div>
                </div>

                <div class="panel" id="tablePanel" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">

                    <div class="filter-bar print-hide">
                        <form id="searchForm" action="" method="GET" class="header-search" style="width: 250px; padding: 6px 14px; margin: 0;">
                            <span class="search-icon"><i class="ph ph-magnifying-glass"></i></span>
                            <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name or username..." class="search-input" autocomplete="off" style="border: none; background: transparent; outline: none; width: 100%;">
                        </form>

                        <div class="filter-group">
                            <select id="roleFilter" class="filter-select">
                                <option value="">All Roles</option>
                                <option value="Admin" <?php if ($role_filter == 'Admin') echo 'selected'; ?>>Administrator</option>
                                <option value="Nurse" <?php if ($role_filter == 'Nurse') echo 'selected'; ?>>Clinic Nurse</option>
                            </select>

                            <select id="statusFilter" class="filter-select">
                                <option value="">All Statuses</option>
                                <option value="Active" <?php if ($status_filter == 'Active') echo 'selected'; ?>>Active</option>
                                <option value="Inactive" <?php if ($status_filter == 'Inactive') echo 'selected'; ?>>Inactive</option>
                            </select>

                            <button class="btn btn-ghost" onclick="exportToPDF()" style="border-radius: 6px; padding: 6px 12px; border: 1px solid var(--border); font-size: 13px;" title="Download PDF">
                                <i class="ph ph-file-pdf" style="color: #EF4444; font-size: 16px;"></i> Export
                            </button>
                        </div>
                    </div>

                    <div class="table-wrapper" id="contentWrapper">
                        <div class="loader-overlay">
                            <i class="ph-bold ph-spinner-gap spinner-icon"></i>
                            <span class="spinner-text">Loading accounts...</span>
                        </div>

                        <div id="pdfExportArea">
                            <div id="pdfHeader" style="display: none; text-align: center; margin-bottom: 25px; border-bottom: 2px solid #1e293b; padding-bottom: 15px; background: #fff; padding-top: 20px;">
                                <img src="../assets/images/logo.jpg" alt="KCCF Logo" style="width: 70px; height: 70px; margin-bottom: 10px; border-radius: 50%;">
                                <h2 style="margin: 0; color: #1e293b; font-family: 'DM Serif Display', serif;">Kurios Christian Colleges Foundation</h2>
                                <p style="margin: 2px 0; color: #64748b; font-size: 14px;">Magallanes, Cavite</p>
                                <h3 style="margin-top: 15px; text-transform: uppercase; font-size: 16px; letter-spacing: 0.5px;">Staff User Accounts</h3>
                                <p style="font-size: 12px; color: #475569; margin-top: 5px;">Document Generated: <?php echo date('F d, Y h:i A'); ?></p>
                            </div>

                            <div class="table-responsive" id="tableContainer">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 100px;">User ID</th>
                                            <th>Staff Name</th>
                                            <th>Username</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Registered Date</th>
                                            <th class="text-right action-col print-hide" style="width: 80px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($total_users > 0): ?>
                                            <?php foreach ($users as $user):
                                                $formatted_user_id = "USR-" . str_pad($user['user_id'], 3, "0", STR_PAD_LEFT);
                                                $is_admin = ($user['role'] === 'Admin');
                                                $role_badge = $is_admin ? '<span class="badge badge-blue">Admin</span>' : '<span class="badge" style="background:#F0E8FF; color:#6B21A8;">Nurse</span>';

                                                $status_badge = ($user['status'] === 'Active')
                                                    ? '<span class="badge badge-green">Active</span>'
                                                    : '<span class="badge badge-red">Inactive</span>';

                                                $reg_date = date('M d, Y', strtotime($user['created_at']));
                                                $json_data = htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8');
                                            ?>
                                                <tr>
                                                    <td data-label="User ID" style="font-family: monospace; font-weight: 600; color: var(--text-muted);"><?php echo $formatted_user_id; ?></td>
                                                    <td data-label="Staff Name">
                                                        <div class="med-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                                        <div class="med-category"><?php echo $is_admin ? 'System Administrator' : 'Clinic Nurse'; ?></div>
                                                    </td>
                                                    <td data-label="Username" style="font-family: monospace; font-weight: 700; color: var(--text-heading); font-size: 15px;"><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td data-label="Role"><?php echo $role_badge; ?></td>
                                                    <td data-label="Status"><?php echo $status_badge; ?></td>
                                                    <td data-label="Registered Date" style="font-size: 13px; font-weight: 500; color: var(--text-heading);"><?php echo $reg_date; ?></td>
                                                    <td data-label="Actions" class="text-right action-col print-hide">
                                                        <button class="action-btn" title="Edit Account" onclick='openEditModal(<?php echo $json_data; ?>)'>
                                                            <i class="ph ph-pencil-simple" style="font-size: 18px;"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">No staff accounts found matching your filters.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div id="paginationContainer">
                            <?php if ($total_pages > 0): ?>
                                <div class="pagination-container">
                                    <div class="page-info">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_users); ?> of <?php echo $total_users; ?> accounts</div>
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

    <!-- MODAL: ADD STAFF -->
    <div id="addUserModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-user-plus" style="color: var(--brand-primary);"></i> Add New Staff</h3>
                <button class="modal-close" onclick="closeModal('addUserModal')" title="Close">&times;</button>
            </div>
            <form action="../backend/admin/process_add_user.php" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Maria Reyes" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. nurse_reyes" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Create a strong password" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>System Role</label>
                            <select name="role" class="form-control" required>
                                <option value="Nurse">Clinic Nurse</option>
                                <option value="Admin">Administrator</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Account Status</label>
                            <select name="status" class="form-control" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('addUserModal')" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT STAFF -->
    <div id="editUserModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="ph ph-pencil-simple" style="color: var(--brand-primary);"></i> Edit Staff Account</h3>
                <button class="modal-close" onclick="closeModal('editUserModal')" title="Close">&times;</button>
            </div>
            <form action="../backend/admin/process_edit_user.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" id="edit_role" class="form-control" required>
                                <option value="Nurse">Clinic Nurse</option>
                                <option value="Admin">Administrator</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="edit_status" class="form-control" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 10px;">
                        <label>Reset Password (Optional)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('editUserModal')" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Active Nav
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelector('a[href="manage_users.php"]').classList.add('active');

        // Modals
        function openAddModal() {
            document.getElementById('addUserModal').classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function openEditModal(data) {
            document.getElementById('edit_user_id').value = data.user_id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_username').value = data.username;
            document.getElementById('edit_role').value = data.role;
            document.getElementById('edit_status').value = data.status;
            document.getElementById('editUserModal').classList.add('active');
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('active');
            }
        }

        // SMART BUTTON SPINNER (PREVENTS DOUBLE CLICKS)
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
                    if (success === 'user_added') text = "The new staff account has been created successfully.";
                    else if (success === 'user_updated') text = "Account details have been successfully updated.";
                }

                if (error) {
                    icon = 'error';
                    title = 'Action Failed';
                    if (error === 'username_taken') text = "That username is already in use.";
                    else if (error === 'db_fail') text = "A database error occurred. Please try again.";
                }

                Swal.fire({
                    title: title,
                    text: text,
                    icon: icon,
                    confirmButtonText: 'Got it',
                    confirmButtonColor: '#2563EB'
                }).then(() => {
                    const newUrl = window.location.pathname + window.location.search.replace(/[?&](success|error)=[^&]+/, '').replace(/^&/, '?');
                    window.history.replaceState({}, document.title, newUrl);
                });
            }
        });

        // AJAX Filtering Logic
        const searchInput = document.getElementById('searchInput');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');
        const contentWrapper = document.getElementById('contentWrapper');
        let debounceTimer;

        function triggerAjax(page = 1) {
            contentWrapper.classList.add('is-loading');
            const url = new URL(window.location.href);

            // Clean previous alerts from URL
            url.searchParams.delete('success');
            url.searchParams.delete('error');

            url.searchParams.set('search', searchInput.value);
            url.searchParams.set('role', roleFilter.value);
            url.searchParams.set('status', statusFilter.value);
            url.searchParams.set('page', page);

            fetch(url).then(r => r.text()).then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                document.querySelector('.data-table tbody').innerHTML = doc.querySelector('.data-table tbody').innerHTML;
                document.getElementById('paginationContainer').innerHTML = doc.getElementById('paginationContainer').innerHTML;
                contentWrapper.classList.remove('is-loading');
                window.history.pushState({}, '', url);
            }).catch(() => contentWrapper.classList.remove('is-loading'));
        }

        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => triggerAjax(1), 400);
        });
        [roleFilter, statusFilter].forEach(el => el.addEventListener('change', () => triggerAjax(1)));
        document.getElementById('searchForm').addEventListener('submit', e => e.preventDefault());

        document.getElementById('paginationContainer').addEventListener('click', e => {
            if (e.target.classList.contains('page-btn') && !e.target.classList.contains('disabled')) {
                e.preventDefault();
                const url = new URL(e.target.href);
                triggerAjax(url.searchParams.get('page'));
            }
        });

        // PDF Exporter
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
                filename: 'KCCF_Staff_Accounts.pdf',
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