<footer class="app-footer">
    <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
</footer>

<script src="assets/js/sidebar.js"></script>
<script>
// Ensure mobile toggle works on every page
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileNavToggle = document.getElementById('mobileNavToggle');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('backdrop');
    const menuIcon = document.getElementById('menuIcon');
    
    if (mobileNavToggle && sidebar && backdrop && menuIcon) {
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
        
        mobileNavToggle.addEventListener('click', toggleMenu);
        backdrop.addEventListener('click', toggleMenu);
        
        // Close mobile menu when clicking a nav item (on small screens)
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 991 && sidebar.classList.contains('active')) {
                    toggleMenu();
                }
            });
        });
    }
});
</script>