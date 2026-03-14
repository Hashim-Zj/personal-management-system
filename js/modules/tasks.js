const tasks = {
  init() {
    // Open Modal
    document.getElementById('btn-add-task').addEventListener('click', () => {
      this.resetForm();
      document.getElementById('task-modal-title').innerText = 'New Task';
      ui.openModal('task-modal');
    });

    // Submit Form (Create / Update)
    document.getElementById('task-form').addEventListener('submit', async (e) => {
      e.preventDefault();

      const id = document.getElementById('task-id').value;
      const data = {
        title: document.getElementById('task-title').value,
        description: document.getElementById('task-desc').value,
        deadline: document.getElementById('task-deadline').value.replace('T', ' '),
        reminder_start_days_before: document.getElementById('task-rem-days').value,
        reminder_interval_hours: document.getElementById('task-rem-hours').value
      };

      try {
        if (id) {
          await api.put(`/tasks/${id}`, data);
          ui.showToast('Task updated successfully', 'success');
        } else {
          await api.post('/tasks', data);
          ui.showToast('Task created successfully', 'success');
        }

        ui.closeModal('task-modal');
        this.loadTasks();
      } catch (error) {
        ui.showToast(error.message, 'error');
      }
    });
  },

  resetForm() {
    document.getElementById('task-id').value = '';
    document.getElementById('task-form').reset();
  },

  async loadTasks() {
    try {
      const data = await api.get('/tasks');
      const tbody = document.querySelector('#tasks-table tbody');

      if (!Array.isArray(data) || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No tasks found.</td></tr>';
        document.getElementById('widget-tasks-count').innerText = 0;
        return;
      }

      tbody.innerHTML = '';

      let openTasks = 0;

      if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No tasks found.</td></tr>';
      } else {
        data.forEach(task => {
          openTasks++;
          const row = document.createElement('tr');
          row.innerHTML = `
                        <td>
                            <strong>${task.title}</strong><br>
                            <small class="text-muted" style="color:var(--text-muted)">${task.description || 'No description'}</small>
                        </td>
                        <td>${new Date(task.deadline).toLocaleString()}</td>
                        <td>Start: ${task.reminder_start_days_before} days before<br>Repeat: ${task.reminder_interval_hours} hrs</td>
                        <td>
                            <button class="action-btn edit" onclick="tasks.editTask(${task.id})"><i class="fa-solid fa-pen-to-square"></i></button>
                            <button class="action-btn delete" onclick="tasks.deleteTask(${task.id})"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    `;
          tbody.appendChild(row);
        });
      }

      // Update dashboard widget
      document.getElementById('widget-tasks-count').innerText = openTasks;

    } catch (error) {
      ui.showToast('Failed to load tasks', 'error');
    }
  },

  async editTask(id) {
    try {
      const task = await api.get(`/tasks/${id}`);
      document.getElementById('task-id').value = task.id;
      document.getElementById('task-title').value = task.title;
      document.getElementById('task-desc').value = task.description;

      // Format datetime-local
      const dt = task.deadline.replace(' ', 'T');
      document.getElementById('task-deadline').value = dt.slice(0, 16);

      document.getElementById('task-rem-days').value = task.reminder_start_days_before;
      document.getElementById('task-rem-hours').value = task.reminder_interval_hours;

      document.getElementById('task-modal-title').innerText = 'Edit Task';
      ui.openModal('task-modal');
    } catch (error) {
      ui.showToast('Failed to load task details', 'error');
    }
  },

  async deleteTask(id) {
    if (confirm('Are you sure you want to delete this task?')) {
      try {
        await api.delete(`/tasks/${id}`);
        ui.showToast('Task deleted', 'success');
        this.loadTasks();
      } catch (error) {
        ui.showToast(error.message, 'error');
      }
    }
  }
};

window.tasks = tasks;
