/**
 * Attendify — API Client
 */

const API_BASE = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
    ? '/api' : '/api';

let isRefreshing = false;
let pendingRequests = [];

class ApiClient {
    static async request(endpoint, options = {}) {
        const url = `${API_BASE}${endpoint}`;
        let token = window.Auth ? window.Auth.getToken() : null;
        
        // PROACTIVE TOKEN RESTORATION: 
        // If memory token is missing but session exists, fetch token BEFORE making the request to avoid 401
        if (!token && window.Auth && localStorage.getItem('attendify_session') === 'true' && !endpoint.includes('/auth/')) {
            if (!isRefreshing) {
                isRefreshing = true;
                try {
                    const refreshResponse = await fetch(`${API_BASE}/auth/refresh-token`, {
                        method: 'POST',
                        credentials: 'include'
                    });
                    if (refreshResponse.ok) {
                        const data = await refreshResponse.json();
                        window.Auth.setToken(data.data.token);
                        token = data.data.token; // Update token for current request
                        
                        isRefreshing = false;
                        const queued = pendingRequests;
                        pendingRequests = [];
                        queued.forEach(cb => cb());
                    } else {
                        isRefreshing = false;
                        window.Auth.clear();
                        window.location.href = '/login.html';
                        return Promise.reject('Session expired');
                    }
                } catch (e) {
                    isRefreshing = false;
                }
            } else {
                // If another request already triggered the proactive refresh, queue this one
                return new Promise((resolve) => {
                    pendingRequests.push(async () => {
                        resolve(this.request(endpoint, options));
                    });
                });
            }
        }
        
        const headers = { 
            'Content-Type': 'application/json', 
            ...options.headers 
        };
        
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            const response = await fetch(url, { 
                ...options, 
                headers,
                credentials: 'include' 
            });

            // Prevent infinite loops if the refresh endpoint itself fails
            if (response.status === 401 && endpoint.includes('/auth/refresh-token')) {
                if (window.Auth) window.Auth.clear();
                window.location.href = '/login.html';
                return;
            }

            if (response.status === 401) {
                if (!isRefreshing) {
                    isRefreshing = true;

                    const refreshResponse = await fetch(`${API_BASE}/auth/refresh-token`, {
                        method: 'POST',
                        credentials: 'include'
                    });

                    if (refreshResponse.ok) {
                        const data = await refreshResponse.json();
                        if (window.Auth && data.data && data.data.token) {
                            window.Auth.setToken(data.data.token);
                        }
                        
                        isRefreshing = false;

                        // Retry all pending requests
                        const queued = pendingRequests;
                        pendingRequests = [];
                        queued.forEach(cb => {
                            try { cb(); } catch (e) { console.error('Queued request failed:', e); }
                        });
                        
                        // Immediately retry the original request
                        return this.request(endpoint, options);
                    } else {
                        // Refresh failed, kick user out
                        isRefreshing = false;
                        if (window.Auth) window.Auth.clear();
                        window.location.href = '/login.html';
                        return Promise.reject('Session expired');
                    }
                }

                // Queue request until refresh finishes
                return new Promise((resolve) => {
                    pendingRequests.push(async () => {
                        resolve(this.request(endpoint, options));
                    });
                });
            }

            const data = await response.json();
            if (!response.ok) throw { status: response.status, ...data };
            return data;
        } catch (error) {
            if (error.message === 'Failed to fetch') {
                if (typeof showToast === 'function') showToast('Network error. Please check your connection.', 'error');
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
