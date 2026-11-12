(function () {
    'use strict';

    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var toggle = document.getElementById('sidebarToggle');

    function setOpen(open) {
        if (!sidebar) { return; }
        sidebar.classList.toggle('show', open);
        backdrop.classList.toggle('show', open);
    }

    if (toggle) {
        toggle.addEventListener('click', function () { setOpen(!sidebar.classList.contains('show')); });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () { setOpen(false); });
    }

    // Prevent double submits and show a busy state on forms.
    document.querySelectorAll('form[data-busy]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Please wait...';
            }
        });
    });

    // Auto-dismiss flash alerts.
    document.querySelectorAll('.alert[data-autodismiss]').forEach(function (el) {
        setTimeout(function () { el.classList.remove('show'); el.remove(); }, 5000);
    });
})();
