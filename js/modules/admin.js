class AdminModule {
  constructor() {
    this.usersTableBody = document.querySelector('#admin-users-table tbody');
    this.logsTableBody = document.querySelector('#admin-logs-table tbody');
    this.logsHeader = document.querySelector('#logs-table-header');

    this.logTypeSelector = document.getElementById('log-type-selector');
    this.btnRefreshLogs = document.getElementById('btn-refresh-logs');

    this.permissionsModal = document.getElementById('permissions-modal');
    this.permissionsForm = document.getElementById('permissions-form');

    this.initEventListeners();
  }

  initEventListeners() {
    if (this.btnRefreshLogs) {
      this.btnRefreshLogs.addEventListener('click', () => this.loadLogs());
    }

    if (this.logTypeSelector) {
      this.logTypeSelector.addEventListener('change', () => this.loadLogs());
    }

    if (this.permissionsForm) {
      this.permissionsForm.addEventListener('submit', (e) => this.handlePermissionsSubmit(e));
    }

    // Delegate clicks for Edit Permissions buttons
    if (this.usersTableBody) {
      this.usersTableBody.addEventListener('click', (e) => {
        if (e.target.closest('.btn-edit-perms')) {
          const btn = e.target.closest('.btn-edit-perms');
          const userId = btn.dataset.id;
          const username = btn.dataset.username;
          const perms = JSON.parse(btn.dataset.perms);
          this.openPermissionsModal(userId, username, perms);
        }
      });
    }
  }

  async loadUsers() {
    try {
      const users = await API.get('/admin/users');
      this.usersTableBody.innerHTML = '';

      users.forEach(user => {
        const perms = {
          access_tasks: user.access_tasks,
          access_transactions: user.access_transactions,
          access_reports: user.access_reports,
          export_pdf: user.export_pdf,
          smtp_reminders: user.smtp_reminders
        };

        const tr = document.createElement('tr');
        tr.innerHTML = `
                    <td><strong>#${user.id}</strong> - ${user.username}</td>
                    <td>${user.email}</td>
                    <td><span class="badge ${user.role === 'admin' ? 'badge-primary' : 'badge-secondary'}">${user.role.toUpperCase()}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline btn-edit-perms" 
                            data-id="${user.id}" 
                            data-username="${user.username}" 
                            data-perms='${JSON.stringify(perms)}'>
                            <i class="fa-solid fa-user-pen"></i> Edit
                        </button>
                    </td>
                `;
        this.usersTableBody.appendChild(tr);
      });
    } catch (error) {
      UI.showToast('Failed to load users: ' + error.message, 'error');
    }
  }

  async loadLogs() {
    if (!this.logTypeSelector) return;
    const type = this.logTypeSelector.value;

    try {
      const logs = await API.get(`/admin/logs/${type}`);
      this.logsTableBody.innerHTML = '';

      // Adjust headers based on log type
      if (type === 'errors') {
        this.logsHeader.innerHTML = `<th>ID</th><th>User</th><th>Module</th><th>Message</th><th>Time</th>`;
      } else if (type === 'actions') {
        this.logsHeader.innerHTML = `<th>ID</th><th>User</th><th>Module</th><th>Action</th><th>Time</th>`;
      } else if (type === 'smtp') {
        this.logsHeader.innerHTML = `<th>ID</th><th>Recipient</th><th>Subject</th><th>Status</th><th>Time</th>`;
      }

      logs.forEach(log => {
        const tr = document.createElement('tr');
        const time = new Date(log.created_at).toLocaleString();
        const userStr = log.username ? log.username : 'System/Guest';

        if (type === 'errors') {
          tr.innerHTML = `<td>${log.id}</td><td>${userStr}</td><td>${log.module}</td><td><span style="color:var(--danger-color)">${log.message}</span></td><td>${time}</td>`;
        } else if (type === 'actions') {
          tr.innerHTML = `<td>${log.id}</td><td>${userStr}</td><td>${log.module}</td><td>${log.action}</td><td>${time}</td>`;
        } else if (type === 'smtp') {
          const statusColor = log.status === 'success' ? 'var(--success-color)' : 'var(--danger-color)';
          tr.innerHTML = `<td>${log.id}</td><td>${log.recipient}</td><td>${log.subject}</td><td><span style="color:${statusColor}"><strong>${log.status.toUpperCase()}</strong></span></td><td>${time}</td>`;
        }
        this.logsTableBody.appendChild(tr);
      });
    } catch (error) {
      UI.showToast('Failed to load logs', 'error');
    }
  }

  openPermissionsModal(userId, username, perms) {
    document.getElementById('perm-user-id').value = userId;
    document.getElementById('perm-user-name').textContent = `Editing: ${username}`;

    // Form might return null for null db fields, treat as false if not strictly 1
    document.getElementById('perm-tasks').checked = (perms.access_tasks == 1 || perms.access_tasks === true || perms.access_tasks === null);
    document.getElementById('perm-tx').checked = (perms.access_transactions == 1 || perms.access_transactions === true || perms.access_transactions === null);
    document.getElementById('perm-rep').checked = (perms.access_reports == 1 || perms.access_reports === true || perms.access_reports === null);
    document.getElementById('perm-pdf').checked = (perms.export_pdf == 1 || perms.export_pdf === true || perms.export_pdf === null);
    document.getElementById('perm-smtp').checked = (perms.smtp_reminders == 1 || perms.smtp_reminders === true || perms.smtp_reminders === null);

    UI.openModal(this.permissionsModal);
  }

  async handlePermissionsSubmit(e) {
    e.preventDefault();
    const userId = document.getElementById('perm-user-id').value;
    const payload = {
      access_tasks: document.getElementById('perm-tasks').checked ? 1 : 0,
      access_transactions: document.getElementById('perm-tx').checked ? 1 : 0,
      access_reports: document.getElementById('perm-rep').checked ? 1 : 0,
      export_pdf: document.getElementById('perm-pdf').checked ? 1 : 0,
      smtp_reminders: document.getElementById('perm-smtp').checked ? 1 : 0
    };

    try {
      await API.put(`/admin/permissions/${userId}`, payload);
      UI.showToast('Permissions updated successfully', 'success');
      UI.closeModal(this.permissionsModal);
      this.loadUsers(); // refresh table
    } catch (error) {
      UI.showToast('Failed to update permissions: ' + error.message, 'error');
    }
  }
}

// Global instance
window.Admin = new AdminModule();
