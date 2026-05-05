<?php
// Securely fetch notifications if the database connection exists
$active_queue_notifs = [];
$critical_stock_notifs = [];
$total_notifs = 0;

if (isset($conn)) {
    try {
        // Load active queue items for notifications.
        $stmtQueue = $conn->query("
            SELECT v.time_in, s.first_name, s.last_name
            FROM visits v
            JOIN students s ON v.student_id = s.student_id
            WHERE v.status = 'Active'
            ORDER BY v.time_in ASC LIMIT 3
        ");
        $active_queue_notifs = $stmtQueue->fetchAll();

        // Full queue count for the notification badge.
        $active_queue_count = $conn->query("SELECT COUNT(*) FROM visits WHERE status = 'Active'")->fetchColumn();

        // Load low-stock medicine notifications.
        $stmtStock = $conn->query("
            SELECT name, quantity
            FROM medicines
            WHERE quantity <= 5
            ORDER BY quantity ASC LIMIT 3
        ");
        $critical_stock_notifs = $stmtStock->fetchAll();

        // Full low-stock count for the notification badge.
        $critical_stock_count = $conn->query("SELECT COUNT(*) FROM medicines WHERE quantity <= 5")->fetchColumn();

        // Total count shown in the badge.
        $total_notifs = $active_queue_count + $critical_stock_count;
    } catch (PDOException $e) {
        // Silently handle to not break the header layout
    }
}

// Adjust paths if included from a subfolder (like forms/)
$asset_prefix = isset($is_subfolder) && $is_subfolder ? '../../' : '../';
$page_prefix = isset($is_subfolder) && $is_subfolder ? '../' : '';
?>

<style>
    /* Notification badge and dropdown styles */
    .notif-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .notif-badge {
        position: absolute;
        top: -2px;
        right: -2px;
        background: #ef4444;
        color: white;
        font-size: 10px;
        font-weight: 700;
        min-width: 16px;
        height: 16px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--bg-base);
    }

    .notif-dropdown {
        position: absolute;
        top: 100%;
        right: -10px;
        margin-top: 15px;
        width: 320px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        display: none;
        /* Hidden by default */
        flex-direction: column;
        z-index: 1000;
        overflow: hidden;
    }

    .notif-dropdown.active {
        display: flex;
    }

    .notif-header {
        padding: 14px 16px;
        border-bottom: 1px solid var(--border);
        background: #f8fafc;
        font-weight: 700;
        font-size: 14px;
        color: var(--text-heading);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notif-body {
        max-height: 350px;
        overflow-y: auto;
    }

    .notif-item {
        padding: 12px 16px;
        border-bottom: 1px solid var(--border);
        display: flex;
        gap: 12px;
        align-items: flex-start;
        text-decoration: none;
        transition: background 0.2s;
        text-align: left;
    }

    .notif-item:hover {
        background: #f1f5f9;
    }

    .notif-item:last-child {
        border-bottom: none;
    }

    .notif-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    .notif-icon.queue {
        background: #FEF3C7;
        color: #D97706;
    }

    .notif-icon.stock {
        background: #FEECEC;
        color: #ef4444;
    }

    .notif-content {
        flex: 1;
    }

    .notif-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-heading);
        margin-bottom: 2px;
    }

    .notif-desc {
        font-size: 12px;
        color: var(--text-muted);
        line-height: 1.4;
    }

    .notif-empty {
        padding: 30px;
        text-align: center;
        color: var(--text-muted);
        font-size: 13px;
    }
</style>

<header class="main-header">
    <div class="header-left">
        <div class="header-search">
            <span class="search-icon"><i class="ph ph-magnifying-glass"></i></span>
            <input type="text" placeholder="Search records, students..." class="search-input">
        </div>
    </div>

    <div class="header-right">
        <div class="header-date">
            <span class="date-icon"><i class="ph ph-calendar-blank"></i></span>
            <?php echo date('F d, Y'); ?>
        </div>

        <div class="header-divider"></div>

        <div class="notif-wrapper">
            <button class="icon-btn" id="notifButton" title="Notifications" aria-label="Notifications">
                <i class="ph ph-bell"></i>
                <?php if ($total_notifs > 0): ?>
                    <span class="notif-badge"><?php echo $total_notifs; ?></span>
                <?php endif; ?>
            </button>

            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span>Notifications</span>
                    <?php if ($total_notifs > 0): ?>
                        <span class="badge" style="background: #ef4444; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px;"><?php echo $total_notifs; ?> New</span>
                    <?php endif; ?>
                </div>

                <div class="notif-body">
                    <?php if ($total_notifs == 0): ?>
                        <div class="notif-empty">
                            <i class="ph ph-check-circle" style="font-size: 32px; color: #10B981; margin-bottom: 8px;"></i>
                            <p>You're all caught up!</p>
                        </div>
                    <?php else: ?>

                        <?php foreach ($active_queue_notifs as $q): ?>
                            <a href="<?php echo $page_prefix; ?>visits.php" class="notif-item">
                                <div class="notif-icon queue"><i class="ph ph-stethoscope"></i></div>
                                <div class="notif-content">
                                    <div class="notif-title">Patient Waiting</div>
                                    <div class="notif-desc"><strong><?php echo htmlspecialchars($q['first_name'] . ' ' . $q['last_name']); ?></strong> is in the queue (since <?php echo $q['time_in']; ?>).</div>
                                </div>
                            </a>
                        <?php endforeach; ?>

                        <?php foreach ($critical_stock_notifs as $s): ?>
                            <a href="<?php echo $page_prefix; ?>inventory.php" class="notif-item">
                                <div class="notif-icon stock"><i class="ph ph-warning-octagon"></i></div>
                                <div class="notif-content">
                                    <div class="notif-title">Critical Stock Alert</div>
                                    <div class="notif-desc"><strong><?php echo htmlspecialchars($s['name']); ?></strong> is running low. Only <?php echo $s['quantity']; ?> unit(s) left.</div>
                                </div>
                            </a>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>

        <button class="icon-btn" title="Settings" aria-label="Settings">
            <i class="ph ph-gear"></i>
        </button>

        <div class="header-divider"></div>

        <div class="user-pill">
            <div class="user-avatar" style="background: var(--brand-primary); color: white;">CN</div>
            <div class="user-meta">
                <span class="user-name">Clinic Nurse</span>
                <span class="user-status">On Duty</span>
            </div>
        </div>
    </div>
</header>

<script>
    // Toggle Notification Dropdown Logic
    document.addEventListener("DOMContentLoaded", function() {
        const notifBtn = document.getElementById('notifButton');
        const notifDropdown = document.getElementById('notifDropdown');

        if (notifBtn && notifDropdown) {
            notifBtn.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent document click from firing and closing it immediately
                notifDropdown.classList.toggle('active');
            });

            // Close dropdown when clicking anywhere else on the page
            document.addEventListener('click', function(e) {
                if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                    notifDropdown.classList.remove('active');
                }
            });
        }
    });
</script>