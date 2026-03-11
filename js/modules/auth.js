const auth = {
  init() {
    // Tab switching
    const tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(tab => {
      tab.addEventListener('click', (e) => {
        const target = e.target.dataset.target;

        // Active state for tabs
        tabs.forEach(t => t.classList.remove('active'));
        e.target.classList.add('active');

        // Active state for forms
        document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
        document.getElementById(target).classList.add('active');
      });
    });

    // Login
    document.getElementById('login-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = document.getElementById('login-email').value;
      const password = document.getElementById('login-password').value;

      try {
        console.log('Start LOgin with:',email,'and pass ',password);
        const res = await api.post('/auth/login', { email, password });
        console.log('Login response:', res); // <-- log the response
        this.handleLoginSuccess(res.token, res.user);
      } catch (error) {
        console.log('Login error:', error.response ? error.response.data : error);
        ui.showToast(error.message, 'error');
      }
    });

    // Register
    document.getElementById('register-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = document.getElementById('reg-username').value;
      const email = document.getElementById('reg-email').value;
      const password = document.getElementById('reg-password').value;

      try {
        const res = await api.post('/auth/register', { username, email, password });
        ui.showToast(res.message, 'success');
        // Switch to login tab automatically
        document.querySelector('.tab-btn[data-target="login-form"]').click();
      } catch (error) {
        ui.showToast(error.message, 'error');
      }
    });

    // Logout
    document.getElementById('logout-btn').addEventListener('click', () => {
      this.logout();
    });
  },

  handleLoginSuccess(token, user) {
    localStorage.setItem('pms_token', token);
    localStorage.setItem('pms_user', JSON.stringify(user));

    ui.showToast('Login successful!', 'success');
    this.checkAuth();
  },

  logout() {
    localStorage.removeItem('pms_token');
    localStorage.removeItem('pms_user');
    ui.switchView('auth-view');
    ui.showToast('Logged out successfully', 'info');
  },

  checkAuth() {
    const token = localStorage.getItem('pms_token');
    const user = JSON.parse(localStorage.getItem('pms_user') || 'null');

    if (token && user) {
      document.getElementById('user-display-name').innerText = user.username;

      // Admin Sidebar Links Toggle
      const adminLinks = document.querySelectorAll('.admin-only');
      if (user.role === 'admin') {
        adminLinks.forEach(link => link.style.display = 'block');
      } else {
        adminLinks.forEach(link => link.style.display = 'none');
      }

      ui.switchView('dashboard-view');
      return true;
    } else {
      ui.switchView('auth-view');
      return false;
    }
  }
};

window.auth = auth;
