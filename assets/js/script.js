/**
 * Mobile-First Responsive Design
 * Handles sidebar toggle, overlay, and mobile menu interactions
 */

(function() {
    'use strict';

    // DOM Elements
    const sidebar = document.querySelector('.sidebar');
    const header = document.querySelector('.main-header');
    const body = document.body;
    let sidebarOverlay = null;
    let mobileMenuBtn = null;

    /**
     * Initialize mobile menu button if needed
     */
    function initializeMobileMenu() {
        // Only add menu button on mobile
        if (window.innerWidth < 768) {
            // Check if button already exists
            if (document.querySelector('.mobile-menu-btn')) {
                return;
            }

            // Create mobile menu button
            mobileMenuBtn = document.createElement('button');
            mobileMenuBtn.className = 'mobile-menu-btn';
            mobileMenuBtn.setAttribute('aria-label', 'Toggle navigation menu');
            mobileMenuBtn.setAttribute('type', 'button');
            mobileMenuBtn.innerHTML = '<i class="ph ph-list"></i>';

            // Insert at the beginning of header
            if (header) {
                header.insertBefore(mobileMenuBtn, header.firstChild);
                mobileMenuBtn.addEventListener('click', toggleSidebar);
            }
        } else {
            // Remove mobile button on desktop
            const existingBtn = document.querySelector('.mobile-menu-btn');
            if (existingBtn) {
                existingBtn.removeEventListener('click', toggleSidebar);
                existingBtn.remove();
            }
            // Ensure sidebar is visible on desktop
            if (sidebar) {
                sidebar.classList.remove('active');
            }
        }
    }

    /**
     * Create sidebar overlay
     */
    function createOverlay() {
        if (!sidebarOverlay && window.innerWidth < 768) {
            sidebarOverlay = document.createElement('div');
            sidebarOverlay.className = 'sidebar-overlay';
            body.appendChild(sidebarOverlay);
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
    }

    /**
     * Toggle sidebar visibility
     */
    function toggleSidebar() {
        if (!sidebar) return;

        const isActive = sidebar.classList.toggle('active');

        if (isActive) {
            createOverlay();
            if (sidebarOverlay) {
                sidebarOverlay.classList.add('active');
            }
            // Prevent body scroll
            body.style.overflow = 'hidden';
        } else {
            closeSidebar();
        }
    }

    /**
     * Close sidebar
     */
    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.remove('active');
        }
        if (sidebarOverlay) {
            sidebarOverlay.classList.remove('active');
        }
        // Re-enable body scroll
        body.style.overflow = '';
    }

    /**
     * Close sidebar when clicking on a nav link
     */
    function initializeNavLinks() {
        const navLinks = document.querySelectorAll('.nav-link, .logout-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                // Close sidebar on mobile after clicking a link
                if (window.innerWidth < 768) {
                    closeSidebar();
                }
            });
        });
    }

    /**
     * Handle window resize
     */
    function handleResize() {
        const width = window.innerWidth;

        if (width >= 768) {
            // Desktop: ensure sidebar is not hidden
            if (sidebar) {
                sidebar.classList.remove('active');
            }
            // Remove overlay on desktop
            if (sidebarOverlay) {
                sidebarOverlay.remove();
                sidebarOverlay = null;
            }
            body.style.overflow = '';
        }

        // Reinitialize menu button based on screen size
        initializeMobileMenu();
    }

    /**
     * Handle escape key to close sidebar
     */
    function handleEscapeKey(event) {
        if (event.key === 'Escape' && sidebar && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    }

    /**
     * Initialize mobile responsive features
     */
    function init() {
        if (!sidebar) return;

        // Initial setup
        initializeMobileMenu();
        initializeNavLinks();

        // Event listeners
        window.addEventListener('resize', handleResize);
        document.addEventListener('keydown', handleEscapeKey);

        // Handle sidebar links - close on click
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    closeSidebar();
                }
            });
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose functions globally if needed
    window.toggleSidebar = toggleSidebar;
    window.closeSidebar = closeSidebar;
})();
