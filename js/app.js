// Initialize Applcation

document.addEventListener('DOMContentLoaded', () => {
  // 1. Init UI components (Modals)
  ui.initModals();

  // 2. Navigation
  document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const viewId = e.currentTarget.dataset.view;
      ui.switchSection(viewId);
    });
  });

  // 3. Init Modules
  auth.init();
  tasks.init();
  transactions.init();

  // 4. Check Auth and Load Data
  // The following block seems to be a routing configuration that was intended to be added.
  // Assuming it should be part of a UI or routing module initialization,
  // but without context, it's placed here as per the provided "Code Edit" structure.
  // Note: The provided "Code Edit" had a syntax error at the end of this block.
  // It has been corrected to maintain the original `if (auth.checkAuth())` structure.
  // If this routing configuration belongs elsewhere (e.g., in a `ui.js` file or a specific routing module),
  // please provide more context for a precise placement.

  // Example routing configuration (as provided in the "Code Edit")
  // This block is syntactically incomplete as a standalone statement here.
  // It looks like it's meant to be part of an object definition or a function call.
  // For the purpose of faithfully incorporating the provided text, it's placed here.
  // If this is meant to be part of a `ui.routes` object, for example, the context is missing.
  /*
            'login-view': { route: 'login', title: 'Login - PMS' },
            'register-view': { route: 'register', title: 'Register - PMS' },
            'dashboard-view': { route: 'dashboard', title: 'Dashboard - PMS', requiresAuth: true },
            'tasks-view': { route: 'tasks', title: 'Tasks - PMS', requiresAuth: true },
            'transactions-view': { route: 'finances', title: 'Finances - PMS', requiresAuth: true },
            'reports-view': { route: 'reports', title: 'Reports - PMS', requiresAuth: true },
            'admin-users-view': { route: 'admin-users', title: 'Manage Users - PMS', requiresAuth: true },
            'admin-logs-view': { route: 'admin-logs', title: 'System Logs - PMS', requiresAuth: true }
        };
  */

  if (auth.checkAuth()) {
    tasks.loadTasks();
    transactions.loadTransactions();
  }
});
