<?php
require_once '../config/db_connect.php';
require_once '../config/auth_check.php';

try {
    // Dashboard summary counts
    $stmtStudents = $conn->query("SELECT COUNT(*) FROM students");
    $total_students = $stmtStudents->fetchColumn();

    // Today's finalized consultations
    $stmtToday = $conn->query("SELECT COUNT(*) FROM health_records WHERE DATE(date) = CURDATE()");
    $todays_consultations = $stmtToday->fetchColumn();

    // Active queue count
    $stmtQueue = $conn->query("SELECT COUNT(*) FROM visits WHERE status = 'Active'");
    $active_queue = $stmtQueue->fetchColumn();

    // Critical stock count
    $stmtStock = $conn->query("SELECT COUNT(*) FROM medicines WHERE quantity <= 5");
    $critical_stock = $stmtStock->fetchColumn();

    // Recent clinical records
    $queryRecent = "
        SELECT hr.diagnosis, hr.treatment, hr.date as record_date, s.first_name, s.last_name, s.course, s.year_level
        FROM health_records hr
        JOIN students s ON hr.student_id = s.student_id
        ORDER BY hr.date DESC
        LIMIT 4
    ";
    $stmtRecent = $conn->query($queryRecent);
    $recent_records = $stmtRecent->fetchAll();

    // Top complaints
    $queryComplaints = "
        SELECT complaint, COUNT(*) as complaint_count
        FROM visits
        GROUP BY complaint
        ORDER BY complaint_count DESC
        LIMIT 4
    ";
    $stmtComplaints = $conn->query($queryComplaints);
    $top_complaints = $stmtComplaints->fetchAll();

    // Total visits for complaint percentages
    $stmtTotalVisits = $conn->query("SELECT COUNT(*) FROM visits");
    $total_visits = $stmtTotalVisits->fetchColumn();
    $total_visits = $total_visits > 0 ? $total_visits : 1; // Avoid division by zero

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Format timestamps as relative time
function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . "m ago";
    if ($diff < 86400) return floor($diff / 3600) . "h ago";
    return floor($diff / 86400) . "d ago";
}

// Visual mapping for recent record icons
$activity_styles = [
    ['bg' => '#E5F0FF', 'icon' => 'ph-file-text'],
    ['bg' => '#EAF4EE', 'icon' => 'ph-first-aid'],
    ['bg' => '#FDF2D0', 'icon' => 'ph-bandaids'],
    ['bg' => '#FEECEC', 'icon' => 'ph-pill']
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duty Dashboard | KCCF Clinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /* Dashboard styles */

        /* Stat cards */
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

        /* Accent bar */
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

        /* Main content grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
            animation: fadeUp 0.5s var(--ease-out) 0.5s both;
        }

        /* Stack on smaller screens */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Panel */
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

        /* Activity list */
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

        /* Item divider */
        .activity-item+.activity-item {
            border-top: 1px solid var(--border);
        }

        /* Stock panel */
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

        /* Progress indicator */
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

        /* Quick actions */
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

        /* Top complaints */
        .complaint-list {
            padding: 8px 0;
        }

        .complaint-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            transition: background var(--t-fast) var(--ease-out);
        }

        .complaint-item:hover {
            background: var(--bg-base);
        }

        .complaint-item:not(:last-child) {
            border-bottom: 1px solid var(--border);
        }

        .complaint-meta {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .complaint-name {
            font-size: 14.5px;
            font-weight: 700;
            color: var(--text-heading);
        }

        .complaint-desc {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 400;
        }

        .complaint-value {
            font-size: 13px;
            font-weight: 800;
            color: var(--brand-primary);
            background: var(--bg-base);
            padding: 6px 14px;
            border-radius: var(--r-pill);
            border: 1px solid var(--border);
            flex-shrink: 0;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include '../includes/nurse_sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/nurse_header.php'; ?>

            <main class="content-body">

                <div class="page-header">
                    <div class="page-header-text">
                        <p class="page-eyebrow">Clinic Operations Overview</p>
                        <h1 class="page-title">Duty Dashboard</h1>
                        <p class="page-subtitle">Welcome back, Nurse. Here is the real-time status of the KCCF Clinic for <?php echo date('F d, Y'); ?>.</p>
                    </div>
                </div>

                <div class="dashboard-cards">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon-wrap" style="color: var(--brand-primary);"><i class="ph ph-users-three"></i></div>
                        </div>
                        <p class="stat-label">Total Students</p>
                        <div class="stat-value"><?php echo number_format($total_students); ?></div>
                        <p class="stat-desc">Enrolled student profiles</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon-wrap" style="color: #10b981;"><i class="ph ph-stethoscope"></i></div>
                        </div>
                        <p class="stat-label">Today's Consultations</p>
                        <div class="stat-value"><?php echo str_pad($todays_consultations, 2, '0', STR_PAD_LEFT); ?></div>
                        <p class="stat-desc">Completed clinical sessions</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon-wrap" style="color: #f59e0b;"><i class="ph ph-user-list"></i></div>
                        </div>
                        <p class="stat-label">Active Queue</p>
                        <div class="stat-value"><?php echo str_pad($active_queue, 2, '0', STR_PAD_LEFT); ?></div>
                        <p class="stat-desc">Students waiting for triage</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon-wrap" style="color: #ef4444;"><i class="ph ph-warning-circle"></i></div>
                        </div>
                        <p class="stat-label">Critical Stock</p>
                        <div class="stat-value" <?php if ($critical_stock > 0) echo 'style="color: #ef4444;"'; ?>><?php echo str_pad($critical_stock, 2, '0', STR_PAD_LEFT); ?></div>
                        <p class="stat-desc">Items requiring reorder</p>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="panel">
                        <div class="panel-header">
                            <h2 class="panel-title">Recent Clinical Records</h2>
                            <a href="health_records.php" class="panel-action">View All <i class="ph ph-arrow-right"></i></a>
                        </div>
                        <div class="activity-list">
                            <?php if (count($recent_records) > 0): ?>
                                <?php foreach ($recent_records as $index => $record):
                                    $style = $activity_styles[$index % count($activity_styles)];
                                ?>
                                    <div class="activity-item">
                                        <div class="activity-icon" style="--icon-bg: <?php echo $style['bg']; ?>;"><i class="ph <?php echo $style['icon']; ?>"></i></div>
                                        <div class="activity-info">
                                            <p class="activity-title"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?> (<?php echo htmlspecialchars($record['course'] . ' - ' . $record['year_level'] . 'Yr'); ?>)</p>
                                            <p class="activity-sub">Diagnosis: <strong><?php echo htmlspecialchars($record['diagnosis']); ?></strong></p>
                                        </div>
                                        <span class="activity-time"><?php echo timeAgo($record['record_date']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 14px;">
                                    No completed records yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-header">
                            <h2 class="panel-title">Top Complaints (Overall)</h2>
                        </div>
                        <div class="complaint-list">
                            <?php if (count($top_complaints) > 0): ?>
                                <?php foreach ($top_complaints as $complaint):
                                    $percentage = round(($complaint['complaint_count'] / $total_visits) * 100);
                                ?>
                                    <div class="complaint-item">
                                        <div class="complaint-meta">
                                            <span class="complaint-name"><?php echo htmlspecialchars($complaint['complaint']); ?></span>
                                            <span class="complaint-desc">Logged <?php echo $complaint['complaint_count']; ?> time(s)</span>
                                        </div>
                                        <span class="complaint-value"><?php echo $percentage; ?>%</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 14px;">
                                    Not enough data available.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // Automatic Active Link Highlight logic
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelector('a[href="dashboard.php"]').classList.add('active');
    </script>
</body>

</html>