window.onerror = function (message, source, lineno, colno) {
  fetch('/pms/api/log-js-error.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message, source, lineno, colno })
  });
};

document.addEventListener('DOMContentLoaded', async () => {
  // Init UI
  ui.initModals();

  // Navigation
  document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const viewId = e.currentTarget.dataset.view;
      ui.switchSection(viewId);
    });
  });

  // Initialize API & Modules
  await initAPI();

  auth.init();
  tasks.init();
  transactions.init();

  // Load Data only if authenticated
  if (auth.checkAuth()) {
    await tasks.loadTasks();
    await transactions.loadTransactions();
  }


  const sidebarToggle = document.getElementById('sidebar-toggle');
  const dashboardView = document.getElementById('dashboard-view');
  const sidebarIcon = sidebarToggle.querySelector('i');


  // Close sidebar when clicking outside
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 && dashboardView.classList.contains('sidebar-open')) {
      const sidebar = document.querySelector('.sidebar');
      if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
        dashboardView.classList.remove('sidebar-open');
      }
    }
  });

  sidebarToggle.addEventListener('click', () => {
    dashboardView.classList.toggle('sidebar-open');
  
    if (dashboardView.classList.contains('sidebar-open')) {
      sidebarIcon.classList.replace('fa-bars', 'fa-xmark');
    } else {
      sidebarIcon.classList.replace('fa-xmark', 'fa-bars');
    }
  });

});