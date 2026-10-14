    <div class="footer">
        <i class="fas fa-plug"></i> KLASNET Billing System by AtmoPro &copy; <?= date('Y') ?> - All Rights Reserved
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script>
    function toggleUserDropdown() {
        var dropdown = document.getElementById('userDropdownContent');
        dropdown.classList.toggle('show');
    }
    
    window.onclick = function(event) {
        if (!event.target.matches('.user-dropdown-btn') && !event.target.closest('.user-dropdown-btn')) {
            var dropdowns = document.getElementsByClassName('user-dropdown-content');
            for (var i = 0; i < dropdowns.length; i++) {
                if (dropdowns[i].classList.contains('show')) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }
    }
    
    document.getElementById('menuToggle')?.addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('show');
    });
    
    document.querySelectorAll('.nav-link').forEach(link => {
        if (link.getAttribute('href') === window.location.pathname) {
            link.classList.add('active');
        }
    });
</script>
</body>
</html>
