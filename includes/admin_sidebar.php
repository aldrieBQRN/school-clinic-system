<?php
// Make the sidebar smart: adjust paths if loaded from a sub-folder
$nav_prefix = isset($is_subfolder) && $is_subfolder ? '../' : '';
$asset_prefix = isset($is_subfolder) && $is_subfolder ? '../../' : '../';
?>

<script src="https://unpkg.com/@phosphor-icons/web"></script>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo-wrap">
            <img src="<?php echo $asset_prefix; ?>assets/images/logo.jpg" alt="KCCF Logo" class="sidebar-logo">
        </div>
        <div class="sidebar-info">
            <span class="system-name">KCCF Clinic</span>
            <span class="system-role">Administrator</span>
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
                </a>
            </li>

            <span class="nav-section-label" style="display: block; margin-top: 15px;">Reports & Analytics</span>
            <li class="nav-item">
                <a href="<?php echo $nav_prefix; ?>reports.php" class="nav-link">
                    <span class="nav-icon-wrap"><i class="ph ph-chart-bar"></i></span>
                    <span class="nav-text">Health Reports</span>
                </a>
            </li>

            <span class="nav-section-label" style="display: block; margin-top: 15px;">System Admin</span>
            <li class="nav-item">
                <a href="<?php echo $nav_prefix; ?>manage_users.php" class="nav-link">
                    <span class="nav-icon-wrap"><i class="ph ph-user-gear"></i></span>
                    <span class="nav-text">Account Management</span>
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
            const navLinks = document.querySelectorAll(".nav-link");

            navLinks.forEach(link => {
                link.classList.remove("active");

                // Compare only the filename, ignoring the PHP prefixes so highlighting works properly
                const linkFile = link.getAttribute("href").split("/").pop();

                if (linkFile === currentPage) {
                    link.classList.add("active");
                }
            });
        });
    </script>
</aside>