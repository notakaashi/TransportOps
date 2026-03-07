<script>
/* ── Admin Mobile Dropdown Menu ─────────────────────────── */
(function () {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const dropdown = document.getElementById('mobileDropdown');
    const closeBtn = document.getElementById('dropdownClose');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (!hamburgerBtn || !dropdown || !closeBtn || !overlay) return;

    function openDropdown() {
        dropdown.classList.add('show');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    function closeDropdown() {
        dropdown.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    hamburgerBtn.addEventListener('click', openDropdown);
    closeBtn.addEventListener('click', closeDropdown);
    overlay.addEventListener('click', closeDropdown);

    // Close dropdown when clicking on a navigation link
    const navLinks = dropdown.querySelectorAll('.mobile-nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            setTimeout(closeDropdown, 150); // Small delay to allow navigation
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDropdown();
    });
})();
</script>
