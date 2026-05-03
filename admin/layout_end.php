    </div><!-- #admin-content -->
</div><!-- #admin-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mobile sidebar toggle
const sidebar = document.getElementById('admin-sidebar');
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    sidebar.classList.toggle('open');
});
// Close sidebar on outside click (mobile)
document.addEventListener('click', e => {
    if (window.innerWidth < 768 && !sidebar.contains(e.target) && !e.target.closest('#sidebarToggle')) {
        sidebar.classList.remove('open');
    }
});
</script>
</body>
</html>
