let API_URL = '';

async function loadConfig() {
  const res = await fetch('/api/config.php');
  const data = await res.json();
  API_URL = data.API_URL;
}

// Wrap everything in an async initializer
async function initAPI() {
  await loadConfig();

  const api = {
    async request(endpoint, options = {}) {
      const token = localStorage.getItem('pms_token');
      const defaultHeaders = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      };

      if (token) defaultHeaders['Authorization'] = `Bearer ${token}`;

      const isFormData = options.body instanceof FormData;
      if (isFormData) delete defaultHeaders['Content-Type'];

      const config = {
        method: options.method || 'GET',
        headers: { ...defaultHeaders, ...options.headers },
        ...options
      };

      const response = await fetch(`${API_URL}${endpoint}`, config);
      const data = await response.json();

      if (!response.ok) {
        if (response.status === 401) {
          localStorage.removeItem('pms_token');
          localStorage.removeItem('pms_user');
          window.location.reload();
        }
        throw new Error(data.message || 'Something went wrong');
      }

      return data;
    },

    get(endpoint) { return this.request(endpoint); },
    post(endpoint, data) {
      return this.request(endpoint, {
        method: 'POST',
        body: data instanceof FormData ? data : JSON.stringify(data)
      });
    },
    put(endpoint, data) {
      return this.request(endpoint, { method: 'PUT', body: JSON.stringify(data) });
    },
    delete(endpoint) { return this.request(endpoint, { method: 'DELETE' }); }
  };

  window.api = api; // <-- make it global
}

// Initialize API
initAPI();