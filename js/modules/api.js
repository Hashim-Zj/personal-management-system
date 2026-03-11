const API_URL = 'http://localhost:8000/api';
// const API_URL = 'http://localhost:8000/pms/api';

const api = {
    async request(endpoint, options = {}) {
        const token = localStorage.getItem('pms_token');
        const defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };

        if (token) {
            defaultHeaders['Authorization'] = `Bearer ${token}`;
        }
        
        const isFormData = options.body instanceof FormData;
        if (isFormData) {
            delete defaultHeaders['Content-Type']; // Let browser set boundary
        }

        const config = {
            method: options.method || 'GET',
            headers: {
                ...defaultHeaders,
                ...options.headers
            },
            ...options
        };

        try {
            const response = await fetch(`${API_URL}${endpoint}`, config);
            const data = await response.json();

            if (!response.ok) {
                if (response.status === 401) {
                    // Unauthorized - clear token and go to login
                    localStorage.removeItem('pms_token');
                    localStorage.removeItem('pms_user');
                    window.location.reload();
                }
                throw new Error(data.message || 'Something went wrong');
            }

            return data;
        } catch (error) {
            throw error;
        }
    },

    get(endpoint) {
        return this.request(endpoint);
    },

    post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: data instanceof FormData ? data : JSON.stringify(data)
        });
    },

    put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE'
        });
    }
};

window.api = api;
