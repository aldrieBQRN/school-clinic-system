<?php
// Make the sidebar smart: adjust paths if loaded from a sub-folder (like forms/)
$nav_prefix = isset($is_subfolder) && $is_subfolder ? '../' : '';
$asset_prefix = isset($is_subfolder) && $is_subfolder ? '../../' : '../';

// Fetch dynamic notification counts if the database connection exists
$active_queue_count = 0;
$critical_stock_count = 0;

if (isset($conn)) {
    try {
        // Count active walk-ins waiting in the queue
        $stmtQueue = $conn->query("SELECT COUNT(*) FROM visits WHERE status = 'Active'");
        $active_queue_count = $stmtQueue->fetchColumn();

        // Count medicines that are critically low (5 or less)
        $stmtStock = $conn->query("SELECT COUNT(*) FROM medicines WHERE quantity <= 5");
        $critical_stock_count = $stmtStock->fetchColumn();
    } catch (PDOException $e) {
        // Silently handle errors so it doesn't break the sidebar
    }
}
?>

<script src="https://unpkg.com/@phosphor-icons/web"></script>

<style>
    /* Notification badge styles */
    .sidebar-badge {
        background-color: #ef4444;
        /* Urgent alert color */
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 20px;
        margin-left: auto;
        /* Align badge to the right edge */
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
    }

    .sidebar-badge.warning {
        background-color: #f59e0b;
        /* Warning color */
    }
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo-wrap">
            <img src="<?php echo $asset_prefix; ?>assets/images/logo.jpg" alt="KCCF Logo" class="sidebar-logo">
        </div>
        <div class="sidebar-info">
            <span class="system-name">KCCF Clinic</span>
            <span class="system-role">Nurse Portal</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <span class="nav-section-label">Overview</span>
            <li class="nav-item">
                <a href="<?php echo $nav_prefix; ?>dashboard.php" class="nav-link">
                    <span class="nav-icon-wrap"><i class="ph ph-squares-four"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>

            <span class="nav-section-label" style="display: block; margin-top: 15px;">Clinic Operations</span>
            <li class="nav-item">
                <a href="<?php echo $nav_prefix; ?>visits.php" class="nav-link">
                    <span class="nav-icon-wrap"><i class="ph ph-stethoscope"></i></span>
                    <span class="nav-text">Consultation</span>
                    <?php if ($active_queue_count > 0): ?>
                        <span class="sidebar-badge"><?php echo $active_queue_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <span class="nav-section-label" style="display: block; margin-top: 15px;">Data Management</span>
            <li class="nav-item">
                <a href="<?php echo $nav_prefix; ?>visit_log.php" class="nav-link">
                    <span class="nav-icon-wrap"><i class="ph ph-clock-counter-clockwise"></i></span>
                    <span class="nav-text">Visit Log</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $nav_prefix; ?>health_records.php" class="nav-link">
                    <span class="nav-icon-wrap"><i class="ph ph-clipboard-text"></i></span>
                    <span class="nav-text">Health Records</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $nav_prefix; ?>student_records.php" class="nav-link">
                    <span class="nav-icon-wrap"><i class="ph ph-identification-card"></i></span>
                    <span class="nav-text">Student Profiles</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $nav_prefix; ?>inventory.php" class="nav-link">
                    <span class="nav-icon-wrap"><i class="ph ph-pill"></i></span>
                    <span class="nav-text">Medicine Inventory</span>
                    <?php if ($critical_stock_count > 0): ?>
                        <span class="sidebar-badge warning"><?php echo $critical_stock_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="<?php echo $asset_prefix; ?>logout.php" class="logout-link">
            <span class="nav-icon-wrap"><i class="ph ph-sign-out"></i></span>
            <span class="nav-text">Sign Out</span>
        </a>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let currentPage = window.location.pathname.split("/").pop();

            // Keep the parent menu active while on subfolder form pages.
            const formPageAliases = {
                "register_student.php": "student_records.php",
                "finalize_visit.php": "visits.php"
            };

            if (formPageAliases[currentPage]) {
                currentPage = formPageAliases[currentPage];
            }

            const navLinks = document.querySelectorAll(".nav-link");

            navLinks.forEach(link => {
                link.classList.remove("active");

                // Match by filename only.
                const linkFile = link.getAttribute("href").split("/").pop();

                if (linkFile === currentPage) {
                    link.classList.add("active");
                }
            });
        });
    </script>
</aside>