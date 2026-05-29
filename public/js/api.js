/**
 * Attendify — API Client
 */

const API_BASE = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
    ? '/api' : '/api';

class ApiClient {
    static async request(endpoint, options = {}) {
        const url = `${API_BASE}${endpoint}`;
        const token = localStorage.getItem('attendify_token');
        const headers = { 'Content-Type': 'application/json', ...options.headers };
        if (token) headers['Authorization'] = `Bearer ${token}`;

        try {
            const response = await fetch(url, { ...options, headers });
            const data = await response.json();

            if (response.status === 401) {
                localStorage.removeItem('attendify_token');
                localStorage.removeItem('attendify_user');
                window.location.href = '/login.html';
                return;
            }
            if (!response.ok) throw { status: response.status, ...data };
            return data;
        } catch (error) {
            if (error.message === 'Failed to fetch') {
                showToast('Network error. Please check your connection.', 'error');
            }
            throw error;
        }
    }

    static get(endpoint) { return this.request(endpoint); }
    static post(endpoint, body) { return this.request(endpoint, { method: 'POST', body: JSON.stringify(body) }); }
    static put(endpoint, body) { return this.request(endpoint, { method: 'PUT', body: JSON.stringify(body) }); }
    static patch(endpoint, body) { return this.request(endpoint, { method: 'PATCH', body: JSON.stringify(body) }); }
    static delete(endpoint) { return this.request(endpoint, { method: 'DELETE' }); }
}

window.api = ApiClient;
