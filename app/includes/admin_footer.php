<?php
// app/includes/admin_footer.php
?>
    </div>
</main>
</div> <!-- /admin-container -->

<!-- Admin JS -->
<script>
    // Sidebar toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.querySelector('.admin-sidebar-overlay');
        
        function toggleSidebar() {
            if (sidebar && overlay) {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            }
        }
        
        function closeSidebar() {
            if (sidebar && overlay) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        }
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                e.preventDefault();
                closeSidebar();
            });
        }
        
        // Close sidebar on window resize if it's desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 993) {
                closeSidebar();
            }
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 993) {
                if (sidebar && sidebar.classList.contains('show')) {
                    // Check if click is outside sidebar and not on toggle button
                    if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                        closeSidebar();
                    }
                }
            }
        });
    });
</script>

</body>
</html>

