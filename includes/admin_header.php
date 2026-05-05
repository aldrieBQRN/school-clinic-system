<?php
// Adjust paths if included from a subfolder
$page_prefix = isset($is_subfolder) && $is_subfolder ? '../' : '';

$system_alerts = [];

if (isset($conn)) {
    try {
        // Critical inventory alert
        $stock_count = $conn->query("SELECT COUNT(*) FROM medicines WHERE quantity <= 5")->fetchColumn();
        if ($stock_count > 0) {
            $system_alerts[] = [
                'icon' => 'ph-warning-octagon',
                'bg' => '#FEECEC',
                'color' => '#ef4444',
                'title' => 'Critical Inventory Level',
                'desc' => "There are {$stock_count} medicine(s) running critically low. Replenishment budget approval needed.",
                'link' => $page_prefix . 'inventory.php'
            ];
        }

        // High patient volume alert
        $today_visits = $conn->query("SELECT COUNT(*) FROM visits WHERE DATE(date_logged) = CURDATE()")->fetchColumn();
        if ($today_visits >= 10) {
            $system_alerts[] = [
                'icon' => 'ph-trend-up',
                'bg' => '#FEF3C7',
                'color' => '#D97706',
                'title' => 'High Patient Volume',
                'desc' => "The clinic is unusually busy today with {$today_visits} walk-in consultations logged so far.",
                'link' => $page_prefix . 'dashboard.php'
            ];
        }

        // Active queue buildup alert
        $active_queue = $conn->query("SELECT COUNT(*) FROM visits WHERE status = 'Active'")->fetchColumn();
        if ($active_queue >= 3) {
            $system_alerts[] = [
                'icon' => 'ph-users-three',
                'bg' => '#E5F0FF',
                'color' => '#3B82F6',
                'title' => 'Queue Buildup Detected',
                'desc' => "There are currently {$active_queue} students waiting for triage. The clinic may be understaffed at this moment.",
                'link' => $page_prefix . 'dashboard.php'
            ];
        }
    } catch (PDOException $e) {
        // Silently handle database errors to prevent breaking the header UI
    }
}

$total_admin_notifs = count($system_alerts);
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
            <input type="text" placeholder="Search accounts, reports..." class="search-input">
        </div>
    </div>

    <div class="header-right">
        <div class="header-date">
            <span class="date-icon"><i class="ph ph-calendar-blank"></i></span>
            <?php echo date('F d, Y'); ?>
        </div>

        <div class="header-divider"></div>

        <div class="notif-wrapper">
            <button class="icon-btn" id="notifButton" title="System Notifications" aria-label="Notifications">
                <i class="ph ph-bell"></i>
                <?php if ($total_admin_notifs > 0): ?>
                    <span class="notif-badge"><?php echo $total_admin_notifs; ?></span>
                <?php endif; ?>
            </button>

            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span>System Alerts</span>
                    <?php if ($total_admin_notifs > 0): ?>
                        <span class="badge" style="background: #ef4444; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px;"><?php echo $total_admin_notifs; ?> Alert(s)</span>
                    <?php endif; ?>
                </div>

                <div class="notif-body">
                    <?php if ($total_admin_notifs == 0): ?>
                        <div class="notif-empty">
                            <i class="ph ph-check-circle" style="font-size: 32px; color: #10B981; margin-bottom: 8px;"></i>
                            <p>All systems operational.<br>No active alerts.</p>
                        </div>
                    <?php else: ?>

                        <?php foreach ($system_alerts as $alert): ?>
                            <a href="<?php echo $alert['link']; ?>" class="notif-item">
                                <div class="notif-icon" style="background: <?php echo $alert['bg']; ?>; color: <?php echo $alert['color']; ?>;">
                                    <i class="ph <?php echo $alert['icon']; ?>"></i>
                                </div>
                                <div class="notif-content">
                                    <div class="notif-title"><?php echo htmlspecialchars($alert['title']); ?></div>
                                    <div class="notif-desc"><?php echo htmlspecialchars($alert['desc']); ?></div>
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
            <div class="user-avatar" style="background: #1e293b; color: white;">AD</div>
            <div class="user-meta">
                <span class="user-name">Administrator</span>
                <span class="user-status">Online</span>
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
                e.stopPropagation(); // Prevent document click from firing
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