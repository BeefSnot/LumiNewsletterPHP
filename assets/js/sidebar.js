document.addEventListener('DOMContentLoaded', function() {
    console.log("Sidebar.js loading...");
    
    // Toggle dropdown menus
    const menuHeaders = document.querySelectorAll('.menu-group-header');
    menuHeaders.forEach(header => {
        header.addEventListener('click', function() {
            // Toggle active class on the header
            this.classList.toggle('active');
            
            // Toggle submenu visibility
            const submenu = this.nextElementSibling;
            if(submenu) submenu.classList.toggle('show');
        });
        
        // Auto-expand menu if it contains active item
        const submenu = header.nextElementSibling;
        if (submenu && submenu.querySelector('.nav-item.active')) {
            header.classList.add('active');
            submenu.classList.add('show');
        }
    });
    
    // Mobile menu toggle - centralized implementation
    const mobileNavToggle = document.getElementById('mobileNavToggle');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('backdrop');
    const menuIcon = document.getElementById('menuIcon');
    
    if (mobileNavToggle && sidebar && backdrop && menuIcon) {
        console.log("Mobile menu elements found");
        
        function toggleMenu(e) {
            if(e) e.stopPropagation();
            console.log("Toggle menu clicked");
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
        
        // Remove any existing click event listeners first
        mobileNavToggle.removeEventListener('click', toggleMenu);
        backdrop.removeEventListener('click', toggleMenu);
        
        // Add new event listeners with debug
        mobileNavToggle.addEventListener('click', function(e) {
            console.log("Mobile toggle clicked");
            toggleMenu(e);
        });
        
        backdrop.addEventListener('click', function(e) {
            console.log("Backdrop clicked");
            toggleMenu(e);
        });
        
        // Close mobile menu when clicking a nav item (on small screens)
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.removeEventListener('click', toggleMenu);
            item.addEventListener('click', function() {
                if (window.innerWidth <= 991 && sidebar.classList.contains('active')) {
                    toggleMenu();
                }
            });
        });
    } else {
        console.error('Mobile menu elements not found:', { 
            mobileNavToggle: mobileNavToggle ? true : false, 
            sidebar: sidebar ? true : false,
            backdrop: backdrop ? true : false,
            menuIcon: menuIcon ? true : false
        });
    }
});