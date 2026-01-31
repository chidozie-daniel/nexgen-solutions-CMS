        </div> <!-- End of main-content -->
        
        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        
        <!-- Custom JS -->
        <script>
            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert-flash .alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
            
            // Confirm before delete
            function confirmDelete(message = 'Are you sure you want to delete this?') {
                return confirm(message);
            }
            
            // Toggle sidebar on mobile
            function toggleSidebar() {
                const sidebar = document.querySelector('.sidebar');
                const content = document.querySelector('.main-content');
                
                if (sidebar.style.width === '70px') {
                    sidebar.style.width = '250px';
                    content.style.marginLeft = '250px';
                    // Show text
                    document.querySelectorAll('.nav-text').forEach(el => {
                        el.style.display = 'inline';
                    });
                } else {
                    sidebar.style.width = '70px';
                    content.style.marginLeft = '70px';
                    // Hide text
                    document.querySelectorAll('.nav-text').forEach(el => {
                        el.style.display = 'none';
                    });
                }
            }
            
            // Initialize tooltips
            document.addEventListener('DOMContentLoaded', function() {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });
        </script>
    </body>
    </html>