<?php
require_once '../config/db_connect.php';
require_once '../config/auth_check.php'; // Restrict access to authenticated admins

try {
    // Dashboard summary counts
    $stmt = $conn->query("SELECT COUNT(*) FROM students");
    $total_students = $stmt->fetchColumn();

    // Today's visit total
    $stmt = $conn->query("SELECT COUNT(*) FROM visits WHERE date_logged = CURRENT_DATE");
    $todays_visits = $stmt->fetchColumn();

    // Total medicine entries
    $stmt = $conn->query("SELECT COUNT(*) FROM medicines");
    $total_medicines = $stmt->fetchColumn();

    // Low-stock medicine count
    $stmt = $conn->query("SELECT COUNT(*) FROM medicines WHERE quantity <= 10");
    $critical_count = $stmt->fetchColumn();

    // Recent consultations
    $stmt = $conn->query("
        SELECT
            v.time_in, v.complaint, v.date_logged,
            s.first_name, s.last_name,
            hr.treatment
        FROM visits v
        JOIN students s ON v.student_id = s.student_id
        LEFT JOIN health_records hr ON v.visit_id = hr.visit_id
        ORDER BY v.date_logged DESC, v.time_in DESC
            LIMIT 8
    ");
    $recent_activities = $stmt->fetchAll();

    // Low-stock items for the sidebar
    $stmt = $conn->query("SELECT name, category, quantity FROM medicines WHERE quantity <= 10 ORDER BY quantity ASC LIMIT 4");
    $critical_items = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Dashboard Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | KCCF Clinic Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /* ======== DASHBOARD STYLES ======== */

        /* STAT CARDS (Mobile-First) */
        .dashboard-cards {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }

        @media (min-width: 480px) {
            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
        }

        @media (min-width: 768px) {
            .dashboard-cards {
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 20px;
                margin-bottom: 32px;
            }
        }

        .stat-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: var(--r-lg);
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: all var(--t-std) var(--ease-out);
            cursor: default;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--border-dark);
            transform: translateY(-2px);
        }

        /* Animated gradient top bar */
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--bar-c1, var(--brand-primary)), var(--bar-c2, var(--brand-accent)));
            border-radius: var(--r-lg) var(--r-lg) 0 0;
        }

        .stat-card:nth-child(1) {
            --bar-c1: #167B46;
            --bar-c2: #2BB673;
            animation: fadeUp 0.5s var(--ease-out) 0.2s both;
        }

        .stat-card:nth-child(2) {
            --bar-c1: #1A6FBF;
            --bar-c2: #5BA4E0;
            animation: fadeUp 0.5s var(--ease-out) 0.28s both;
        }

        .stat-card:nth-child(3) {
            --bar-c1: #D8A331;
            --bar-c2: #F4CC6A;
            animation: fadeUp 0.5s var(--ease-out) 0.36s both;
        }

        .stat-card:nth-child(4) {
            --bar-c1: #C0392B;
            --bar-c2: #E74C3C;
            animation: fadeUp 0.5s var(--ease-out) 0.44s both;
        }

        .stat-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .stat-icon-wrap {
            width: 46px;
            height: 46px;
            border-radius: var(--r-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            background: var(--icon-bg, #EAF4EE);
        }

        .stat-card:nth-child(1) .stat-icon-wrap {
            --icon-bg: #EAF4EE;
        }

        .stat-card:nth-child(2) .stat-icon-wrap {
            --icon-bg: #E5F0FF;
        }

        .stat-card:nth-child(3) .stat-icon-wrap {
            --icon-bg: #FDF6E4;
        }

        .stat-card:nth-child(4) .stat-icon-wrap {
            --icon-bg: #FEECEC;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: var(--r-pill);
        }

        .trend-up {
            color: #16A34A;
            background: #DCFCE7;
        }

        .trend-down {
            color: #DC2626;
            background: #FEE2E2;
        }

        .trend-warn {
            color: #D97706;
            background: #FEF3C7;
        }

        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 6px;
        }

        .stat-value {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 38px;
            font-weight: 400;
            color: var(--text-heading);
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-desc {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 400;
        }

        /* ======== BOTTOM GRID ======== */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
            animation: fadeUp 0.5s var(--ease-out) 0.5s both;
        }

        /* Dashboard grid responsive - stack vertically on tablet and mobile */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Section Panel */
        .panel {
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: var(--r-lg);
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }

        .panel-title {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 18px;
            color: var(--text-heading);
            font-weight: 400;
        }

        .panel-action {
            font-size: 13px;
            font-weight: 600;
            color: var(--brand-primary);
            cursor: pointer;
            transition: color var(--t-fast);
            border: none;
            background: none;
            font-family: 'Outfit', sans-serif;
        }

        .panel-action:hover {
            color: var(--brand-deep);
        }

        /* ======== ACTIVITY LIST ======== */
        .activity-list {
            padding: 8px 0;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 24px;
            transition: background var(--t-fast);
            cursor: default;
        }

        .activity-item:hover {
            background: var(--bg-base);
        }

        .activity-dot-wrap {
            position: relative;
            flex-shrink: 0;
        }

        .activity-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--dot-c, var(--brand-primary));
        }

        .activity-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            background: var(--icon-bg, #EAF4EE);
            flex-shrink: 0;
        }

        .activity-info {
            flex: 1;
            min-width: 0;
        }

        .activity-title {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-heading);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .activity-sub {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 400;
        }

        .activity-time {
            font-size: 11.5px;
            color: var(--text-xmuted);
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* Divider line between activity items */
        .activity-item+.activity-item {
            border-top: 1px solid var(--border);
        }

        /* ======== STOCK PANEL ======== */
        .stock-list {
            padding: 8px 0;
        }

        .stock-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 13px 24px;
            transition: background var(--t-fast);
        }

        .stock-item:hover {
            background: var(--bg-base);
        }

        .stock-item+.stock-item {
            border-top: 1px solid var(--border);
        }

        .stock-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--r-sm);
            background: #FDF6E4;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .stock-info {
            flex: 1;
            min-width: 0;
        }

        .stock-name {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-heading);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stock-sub {
            font-size: 11.5px;
            color: var(--text-muted);
        }

        /* Progress bar */
        .stock-bar-wrap {
            width: 64px;
            flex-shrink: 0;
            text-align: right;
        }

        .stock-qty {
            font-size: 12px;
            font-weight: 700;
            color: var(--qty-c, #DC2626);
            margin-bottom: 4px;
        }

        .stock-bar-bg {
            height: 5px;
            background: #F0F4F1;
            border-radius: var(--r-pill);
            overflow: hidden;
        }

        .stock-bar-fill {
            height: 100%;
            border-radius: var(--r-pill);
            background: var(--bar-fill-c, #ef4444);
            width: var(--fill-pct, 10%);
            transition: width 0.8s var(--ease-out) 0.6s;
        }

        /* ======== QUICK ACTIONS ======== */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 16px 20px 20px;
        }

        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px 12px;
            border-radius: var(--r-md);
            border: 1.5px solid var(--border);
            background: var(--bg-base);
            cursor: pointer;
            transition: all var(--t-std) var(--ease-out);
            text-align: center;
            font-family: 'Outfit', sans-serif;
        }

        .quick-action-btn:hover {
            background: var(--brand-primary);
            border-color: var(--brand-primary);
            color: white;
            box-shadow: var(--shadow-sm);
            transform: translateY(-1px);
        }

        .qa-icon {
            font-size: 22px;
        }

        .qa-label {
            font-size: 12px;
            font-weight: 600;
            color: inherit;
        }
    </style>
</head>

<body>

    <div class="wrapper">

        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="main-container">

            <?php include '../includes/admin_header.php'; ?>

            <main class="content-body">

                <div class="page-header">
                    <div class="page-header-text">
                        <p class="page-eyebrow">Admin Dashboard</p>
                        <h1 class="page-title">System Overview</h1>
                        <p class="page-subtitle">Welcome back! Here's the live status of the clinic today.</p>
                    </div>
                </div>

                <div class="dashboard-cards">

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon-wrap"><i class="ph ph-users"></i></div>
                        </div>
                        <p class="stat-label">Total Students</p>
                        <div class="stat-value"><?php echo number_format($total_students); ?></div>
                        <p class="stat-desc">Registered patients in the system</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon-wrap" style="background: #EAF4EE; color: #10B981;"><i class="ph ph-stethoscope"></i></div>
                            <?php if ($todays_visits > 0): ?>
                                <span class="stat-trend trend-up"><i class="ph ph-trend-up"></i> Active</span>
                            <?php endif; ?>
                        </div>
                        <p class="stat-label">Today's Visits</p>
                        <div class="stat-value"><?php echo $todays_visits; ?></div>
                        <p class="stat-desc">Consultations logged today</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon-wrap" style="background: #E5F0FF; color: #3B82F6;"><i class="ph ph-pill"></i></div>
                        </div>
                        <p class="stat-label">Medicines Tracked</p>
                        <div class="stat-value"><?php echo $total_medicines; ?></div>
                        <p class="stat-desc">Unique items in inventory</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon-wrap" style="background: #FEECEC; color: #EF4444;"><i class="ph ph-warning-circle"></i></div>
                            <?php if ($critical_count > 0): ?>
                                <span class="stat-trend trend-down"><i class="ph ph-trend-down"></i> Action Required</span>
                            <?php endif; ?>
                        </div>
                        <p class="stat-label">Needs Attention</p>
                        <div class="stat-value" style="color: #EF4444;"><?php echo str_pad($critical_count, 2, "0", STR_PAD_LEFT); ?></div>
                        <p class="stat-desc">Items requiring immediate restock</p>
                    </div>

                </div>

                <div class="dashboard-grid">

                    <div class="panel">
                        <div class="panel-header">
                            <h2 class="panel-title">Recent Consultations</h2>
                            <a href="visits.php" class="panel-action" style="text-decoration: none;">View all <i class="ph ph-arrow-right"></i></a>
                        </div>
                        <div class="activity-list">
                            <?php if ($recent_activities): foreach ($recent_activities as $act):
                                    $time_formatted = date('h:i A', strtotime($act['time_in']));
                                    $is_today = ($act['date_logged'] == date('Y-m-d'));
                                    $display_time = $is_today ? $time_formatted : date('M d', strtotime($act['date_logged']));
                            ?>
                                    <div class="activity-item">
                                        <div class="activity-icon" style="--icon-bg: #EAF4EE;"><i class="ph ph-user-focus"></i></div>
                                        <div class="activity-info">
                                            <p class="activity-title"><?php echo htmlspecialchars($act['first_name'] . ' ' . $act['last_name']); ?> — <?php echo htmlspecialchars($act['complaint']); ?></p>
                                            <p class="activity-sub"><?php echo htmlspecialchars($act['treatment'] ?? 'Pending treatment finalization'); ?></p>
                                        </div>
                                        <span class="activity-time"><?php echo $display_time; ?></span>
                                    </div>
                                <?php endforeach;
                            else: ?>
                                <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 14px;">No recent consultations found.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 20px;">

                        <div class="panel">
                            <div class="panel-header">
                                <h2 class="panel-title">Admin Quick Actions</h2>
                            </div>
                            <div class="quick-actions">
                                <button class="quick-action-btn" onclick="window.location.href='manage_users.php'">
                                    <span class="qa-icon"><i class="ph ph-users-three"></i></span>
                                    <span class="qa-label">Manage Staff</span>
                                </button>
                                <button class="quick-action-btn" onclick="window.location.href='reports.php'">
                                    <span class="qa-icon"><i class="ph ph-chart-line-up"></i></span>
                                    <span class="qa-label">Analytics</span>
                                </button>
                                <button class="quick-action-btn" onclick="window.location.href='inventory.php'">
                                    <span class="qa-icon"><i class="ph ph-pill"></i></span>
                                    <span class="qa-label">Inventory View</span>
                                </button>
                                <button class="quick-action-btn" onclick="window.location.href='student_records.php'">
                                    <span class="qa-icon"><i class="ph ph-folders"></i></span>
                                    <span class="qa-label">Student Records</span>
                                </button>
                            </div>
                        </div>

                        <div class="panel">
                            <div class="panel-header">
                                <h2 class="panel-title">Critical Stock</h2>
                                <a href="inventory.php" class="panel-action" style="text-decoration: none;">View all <i class="ph ph-arrow-right"></i></a>
                            </div>
                            <div class="stock-list">
                                <?php if ($critical_items): foreach ($critical_items as $item):
                                        // Calculate bar width (maxes out at 100% for 20 units to show relative scale)
                                        $pct = min(100, max(5, ($item['quantity'] / 20) * 100));
                                        $color = $item['quantity'] <= 5 ? '#ef4444' : '#f59e0b';
                                ?>
                                        <div class="stock-item">
                                            <div class="stock-icon"><i class="ph ph-warning-octagon" style="color: <?php echo $color; ?>;"></i></div>
                                            <div class="stock-info">
                                                <p class="stock-name"><?php echo htmlspecialchars($item['name']); ?></p>
                                                <p class="stock-sub"><?php echo htmlspecialchars($item['category']); ?></p>
                                            </div>
                                            <div class="stock-bar-wrap">
                                                <p class="stock-qty" style="--qty-c: <?php echo $color; ?>;"><?php echo $item['quantity']; ?> left</p>
                                                <div class="stock-bar-bg">
                                                    <div class="stock-bar-fill" style="--fill-pct: <?php echo $pct; ?>%; --bar-fill-c: <?php echo $color; ?>;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                else: ?>
                                    <div style="padding: 20px; text-align: center; color: #10B981; font-size: 14px; font-weight: 600;">
                                        <i class="ph ph-check-circle" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
                                        All inventory levels are healthy!
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelector('a[href="dashboard.php"]').classList.add('active');
    </script>
</body>

</html>