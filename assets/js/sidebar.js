document.addEventListener('DOMContentLoaded', function() {
    console.log("Sidebar.js loading...");
    
    // Fix 1: Better handling of dropdown menus with direct event listeners
    const menuHeaders = document.querySelectorAll('.menu-group-header');
    menuHeaders.forEach(header => {
        header.addEventListener('click', function(e) {
            // Prevent default action and stop propagation
            e.preventDefault();
            e.stopPropagation();
            
            // Toggle active class on the header
            this.classList.toggle('active');
            
            // Toggle submenu visibility
            const submenu = this.nextElementSibling;
            if(submenu && submenu.classList.contains('submenu')) {
                submenu.classList.toggle('show');
            }
        });
        
        // Auto-expand menu if it contains active item
        const submenu = header.nextElementSibling;
        if (submenu && submenu.querySelector('.nav-item.active')) {
            header.classList.add('active');
            submenu.classList.add('show');
        }
    });
    
    // Fix 2: Improved mobile menu toggle that works across pages
    const mobileNavToggle = document.getElementById('mobileNavToggle');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('backdrop');
    
    if (mobileNavToggle && sidebar && backdrop) {
        function toggleMenu(event) {
            if (event) {
                event.preventDefault();
            }
            
            sidebar.classList.toggle('active');
            backdrop.classList.toggle('active');
            
            const menuIcon = document.getElementById('menuIcon');
            if (menuIcon) {
                if (sidebar.classList.contains('active')) {
                    menuIcon.classList.remove('fa-bars');
                    menuIcon.classList.add('fa-times');
                } else {
                    menuIcon.classList.remove('fa-times');
                    menuIcon.classList.add('fa-bars');
                }
            }
        }
        
        // Fix 3: Direct event listeners without cloning
        mobileNavToggle.addEventListener('click', toggleMenu);
        backdrop.addEventListener('click', toggleMenu);
    }
    
    // Fix 4: Ensure mobile menu closes after navigation on small screens
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        // Skip links inside dropdown menus - they should navigate normally
        if (item.closest('.submenu')) {
            return;
        }
        
        // For other nav items, add click handler to close mobile menu when clicked
        item.addEventListener('click', function(e) {
            // Only close the menu on mobile
            if (window.innerWidth <= 991 && sidebar && sidebar.classList.contains('active')) {
                // Let the link navigate first, then close the menu
                setTimeout(() => {
                    sidebar.classList.remove('active');
                    backdrop.classList.remove('active');
                    
                    const menuIcon = document.getElementById('menuIcon');
                    if (menuIcon) {
                        menuIcon.classList.remove('fa-times');
                        menuIcon.classList.add('fa-bars');
                    }
                }, 100);
            }
        });
    });
});