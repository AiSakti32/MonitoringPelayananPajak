(function () {
  const shell = document.getElementById('appShell');
  const toggle = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('sidebarBackdrop');
  const loading = document.getElementById('pageLoading');

  function closeSidebar() {
    if (shell) shell.classList.remove('sidebar-open');
  }

  function toggleSidebar() {
    if (shell) shell.classList.toggle('sidebar-open');
  }

  if (toggle) {
    toggle.addEventListener('click', toggleSidebar);
  }
  if (backdrop) {
    backdrop.addEventListener('click', closeSidebar);
  }

  window.addEventListener('resize', function () {
    if (window.innerWidth >= 992) {
      closeSidebar();
    }
  });

  function showLoading() {
    if (loading) loading.hidden = false;
  }

  function setButtonLoading(btn) {
    if (!btn) return;
    btn.classList.add('loading');
    btn.disabled = true;
    if (!btn.dataset.originalHtml) {
      btn.dataset.originalHtml = btn.innerHTML;
    }
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memproses...';
  }

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      const message = form.getAttribute('data-confirm');
      if (message && !window.confirm(message)) {
        e.preventDefault();
      }
    });
  });

  document.querySelectorAll('form[data-loading-form], form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      showLoading();
      const btn = form.querySelector('[data-loading-btn], button[type="submit"]');
      setButtonLoading(btn);
    });
  });

  document.querySelectorAll('#masterFilterForm').forEach(function (form) {
    form.addEventListener('submit', function () {
      showLoading();
    });
  });

  // Collapsible Filter Data panels (dashboard / cases / monitoring / alerts)
  document.querySelectorAll('.dash-filter-shell').forEach(function (shell) {
    const toggle = shell.querySelector('.dash-filter-toggle');
    const collapse = shell.querySelector('.dash-filter-collapse');
    if (!toggle || !collapse) return;

    toggle.addEventListener('click', function () {
      const open = !collapse.classList.contains('is-open');
      collapse.classList.toggle('is-open', open);
      collapse.setAttribute('data-collapsed', open ? 'false' : 'true');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    const form = shell.querySelector('form');
    if (form) {
      form.addEventListener('submit', function () {
        collapse.classList.remove('is-open');
        collapse.setAttribute('data-collapsed', 'true');
        toggle.setAttribute('aria-expanded', 'false');
      });
    }
  });

  // Date inputs: toggle empty state so blank fields remain readable on mobile/iOS
  document.querySelectorAll('.app-filter-date').forEach(function (input) {
    const wrap = input.closest('.app-filter-period__item');
    if (!wrap) return;
    const sync = function () {
      wrap.classList.toggle('is-empty', !String(input.value || '').trim());
    };
    sync();
    input.addEventListener('change', sync);
    input.addEventListener('input', sync);
    input.addEventListener('blur', sync);
  });
})();
