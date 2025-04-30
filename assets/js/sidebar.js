document.addEventListener('DOMContentLoaded', function() {
    console.log("Sidebar.js loading...");
    
    // Toggle dropdown menus
    const menuHeaders = document.querySelectorAll('.menu-group-header');
    menuHeaders.forEach(header => {
        // Remove any existing event listeners first to prevent duplicates
        const newHeader = header.cloneNode(true);
        header.parentNode.replaceChild(newHeader, header);
        
        newHeader.addEventListener('click', function(e) {
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
        const submenu = newHeader.nextElementSibling;
        if (submenu && submenu.querySelector('.nav-item.active')) {
            newHeader.classList.add('active');
            submenu.classList.add('show');
        }
    });
    
    // Mobile menu toggle functionality
    const mobileNavToggle = document.getElementById('mobileNavToggle');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.getElementById('backdrop');
    
    if (mobileNavToggle && sidebar && backdrop) {
        function toggleMenu() {
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
        
        mobileNavToggle.addEventListener('click', toggleMenu);
        backdrop.addEventListener('click', toggleMenu);
    }
    
    // Fix clicks on nav items - don't close menu automatically on desktop
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        // If it's a link with a submenu, let it work normally
        if (item.closest('.submenu')) {
            // These are regular links, preserve their behavior
            return;
        }
        
        // Otherwise, let the link navigate normally
        // The browser will handle the navigation
    });
});