document.addEventListener('DOMContentLoaded', function() {
    // Toggle dropdown menus
    const menuHeaders = document.querySelectorAll('.menu-group-header');
    menuHeaders.forEach(header => {
        header.addEventListener('click', function() {
            // Toggle active class on the header
            this.classList.toggle('active');
            
            // Toggle submenu visibility
            const submenu = this.nextElementSibling;
            submenu.classList.toggle('show');
        });
        
        // Auto-expand menu if it contains active item
        const submenu = header.nextElementSibling;
        const hasActiveItem = submenu.querySelector('.nav-item.active');
        if (hasActiveItem) {
            header.classList.add('active');
            submenu.classList.add('show');
        }
    });
    
    // Mobile menu toggle
    const mobileNavToggle = document.getElementById('mobileNavToggle');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('backdrop');
    const menuIcon = document.getElementById('menuIcon');
    
    function toggleMenu() {
        sidebar.classList.toggle('active');
        backdrop.classList.toggle('active');
        
        if (sidebar.classList.contains('active')) {
            menuIcon.classList.remove('fa-bars');
            menuIcon.classList.add('fa-times');
        } else {
            menuIcon.classList.remove('fa-times');
            menuIcon.classList.add('fa-bars');
        }
    }
    
    if (mobileNavToggle) {
        mobileNavToggle.addEventListener('click', toggleMenu);
    }
    
    if (backdrop) {
        backdrop.addEventListener('click', toggleMenu);
    }
    
    // Close mobile menu when clicking a nav item (on small screens)
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 991 && sidebar.classList.contains('active')) {
                toggleMenu();
            }
        });
    });
});