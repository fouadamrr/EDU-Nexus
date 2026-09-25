/**
 * EDU Nexus Global UI Improvement Script
 * -----------------------------------------
 * Handles: 
 * 1. Menu State Persistence (localStorage)
 * 2. Scroll Position Restoration
 * 3. Smooth Transitions
 */

(function() {
    'use strict';

    // Constants
    const STORAGE_KEY_MENU = 'edu_nexus_active_menus';
    const STORAGE_KEY_SCROLL = 'edu_nexus_scroll_pos';
    const STORAGE_KEY_SIDEBAR_SCROLL = 'edu_nexus_sidebar_scroll';

    /**
     * Initialize UI Fixes
     */
    function init() {
        restoreMenuState();
        restoreScrollPositions();
        attachEventListeners();
        initTheme();
    }

    /**
     * Theme Initialization
     */
    function initTheme() {
        const savedTheme = localStorage.getItem('edu-nexus-theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    }

    window.toggleTheme = function() {
        const t = document.documentElement.getAttribute('data-theme');
        const n = t === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', n);
        localStorage.setItem('edu-nexus-theme', n);
    };

    /**
     * Sidebar Toggle (Global)
     */
    window.toggleSidebar = function() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const isHidden = sidebar.classList.contains('translate-x-full');

        if (isHidden) {
            sidebar.classList.remove('translate-x-full');
            if (backdrop) backdrop.classList.remove('hidden');
        } else {
            sidebar.classList.add('translate-x-full');
            if (backdrop) backdrop.classList.add('hidden');
        }
    };

    /**
     * Utility: Set Cookie
     */
    function setCookie(name, value, days = 7) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        let expires = "expires=" + d.toUTCString();
        document.cookie = name + "=" + (value || "") + ";" + expires + ";path=/";
    }

    /**
     * Utility: Get Cookie
     */
    function getCookie(name) {
        let nameEQ = name + "=";
        let ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    /**
     * Toggle Sidebar Accordion with Persistence
     */
    window.toggleSidebarSection = function(id) {
        const panel = document.getElementById(id);
        const chevron = document.getElementById('chevron-' + id);
        if (!panel) return;

        const isExpanded = panel.classList.contains('expanded');
        let activeMenus = {};
        try {
            activeMenus = JSON.parse(getCookie(STORAGE_KEY_MENU) || '{}');
        } catch(e) { activeMenus = {}; }

        if (!isExpanded) {
            // Expand
            panel.classList.add('expanded');
            if (chevron) chevron.classList.add('rotate-180');
            activeMenus[id] = true;
        } else {
            // Collapse
            panel.classList.remove('expanded');
            if (chevron) chevron.classList.remove('rotate-180');
            delete activeMenus[id];
        }

        setCookie(STORAGE_KEY_MENU, JSON.stringify(activeMenus));
    };

    /**
     * Restore Open Menus from Cookies (Fallback for dynamic elements)
     */
    function restoreMenuState() {
        try {
            const activeMenus = JSON.parse(getCookie(STORAGE_KEY_MENU) || '{}');
            for (const id in activeMenus) {
                const panel = document.getElementById(id);
                const chevron = document.getElementById('chevron-' + id);
                if (panel && !panel.classList.contains('expanded')) {
                    panel.classList.add('expanded');
                    if (chevron) chevron.classList.add('rotate-180');
                }
            }
        } catch (e) {
            console.error('Failed to restore menu state:', e);
        }
    }

    /**
     * Save Scroll Positions
     */
    function saveScroll() {
        const mainContent = document.querySelector('main');
        const sidebar = document.querySelector('#sidebar > .flex-1');
        
        const scrollData = {
            main: mainContent ? mainContent.scrollTop : 0,
            window: window.scrollY
        };
        
        localStorage.setItem(STORAGE_KEY_SCROLL, JSON.stringify(scrollData));
        
        if (sidebar) {
            localStorage.setItem(STORAGE_KEY_SIDEBAR_SCROLL, sidebar.scrollTop);
        }
    }

    /**
     * Restore Scroll Positions
     */
    function restoreScrollPositions() {
        try {
            // Restore Main Content / Window
            const scrollDataString = localStorage.getItem(STORAGE_KEY_SCROLL);
            if (scrollDataString) {
                const scrollData = JSON.parse(scrollDataString);
                const mainContent = document.querySelector('main');
                
                if (mainContent && scrollData.main) {
                    mainContent.scrollTop = scrollData.main;
                }
                if (scrollData.window) {
                    window.scrollTo(0, scrollData.window);
                }
            }

            // Restore Sidebar Scroll
            const sidebarScroll = localStorage.getItem(STORAGE_KEY_SIDEBAR_SCROLL);
            if (sidebarScroll) {
                const sidebar = document.querySelector('#sidebar > .flex-1');
                if (sidebar) {
                    sidebar.scrollTop = parseFloat(sidebarScroll);
                }
            }
        } catch (e) {
            console.warn('Failed to restore scroll position:', e);
        }
    }

    /**
     * Attach Listeners
     */
    function attachEventListeners() {
        // Debounced Scroll Saving
        let scrollTimeout;
        const handleScroll = () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(saveScroll, 100);
        };

        // Save scroll before unload to catch the last position
        window.addEventListener('beforeunload', saveScroll);

        const mainContent = document.querySelector('main');
        const sidebar = document.querySelector('#sidebar > .flex-1');

        if (mainContent) mainContent.addEventListener('scroll', handleScroll);
        window.addEventListener('scroll', handleScroll);
        if (sidebar) sidebar.addEventListener('scroll', handleScroll);

        // Fix all "empty" links and ensure sub-links save scroll before navigating
        document.querySelectorAll('a').forEach(link => {
            const href = link.getAttribute('href');
            if (href === '#' || href === '') {
                link.setAttribute('href', 'javascript:void(0);');
            } else if (href && !href.startsWith('javascript:')) {
                link.addEventListener('click', () => {
                    saveScroll(); // Immediate save before navigation
                });
            }
        });
    }

    // Run on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
