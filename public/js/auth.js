/**
 * Attendify — Auth Helpers
 */

let _accessToken = null;

const Auth = {
    USER_KEY: 'attendify_user',

    setSession(token, user) {
        _accessToken = token;
        localStorage.setItem(this.USER_KEY, JSON.stringify(user));
        localStorage.setItem('attendify_session', 'true');
    },

    setToken(token) {
        _accessToken = token;
    },

    getToken() { 
        return _accessToken || null; 
    },

    getUser() { 
        try { return JSON.parse(localStorage.getItem(this.USER_KEY)); } catch { return null; } 
    },
    
    getRole() { const u = this.getUser(); return u ? u.role : null; },
    getCompanyId() { const u = this.getUser(); return u ? u.company_id : null; },
    isAuthenticated() { 
        return !!this.getToken() || localStorage.getItem('attendify_session') === 'true'; 
    },

    clear() {
        _accessToken = null;
        localStorage.removeItem(this.USER_KEY);
        localStorage.removeItem('attendify_session');
    },

    async initAuth() {
        try {
            const res = await fetch('/api/auth/refresh-token', {
                method: 'POST',
                credentials: 'include'
            });

            if (res.ok) {
                const data = await res.json();
                if (data.data && data.data.token) {
                    this.setToken(data.data.token);
                }
            } else {
                this.clear();
                if (window.location.pathname !== '/login.html') {
                    window.location.href = '/login.html';
                }
            }
        } catch (e) {
            this.clear();
            if (window.location.pathname !== '/login.html') {
                window.location.href = '/login.html';
            }
        }
    },

    async logout() {
        try {
            await fetch('/api/auth/logout', {
                method: 'POST',
                credentials: 'include'
            });
        } catch (e) {
            // Ignore network errors, force local logout
        }
        this.clear();
        window.location.href = '/login.html';
    },

    requireAuth() {
        if (!this.isAuthenticated()) { 
            window.location.href = '/login.html'; 
            return false; 
        }
        return true;
    },

    requireRole(roles) {
        if (!this.requireAuth()) return false;
        const role = this.getRole();
        if (!roles.includes(role)) {
            this.redirectToDashboard();
            return false;
        }
        return true;
    },

    redirectToDashboard() {
        const role = this.getRole();
        switch (role) {
            case 'super_admin': window.location.href = '/admin/index.html'; break;
            case 'company_admin': window.location.href = '/company/index.html'; break;
            case 'employee': window.location.href = '/employee/index.html'; break;
            default: window.location.href = '/login.html';
        }
    },

    async login(identifier, password) {
        const device_uuid = window.getDeviceFingerprint ? window.getDeviceFingerprint() : null;
        const res = await api.post('/auth/login', { identifier, password, device_uuid });
        if (res.success) {
            this.setSession(res.data.token, res.data.user);
            
            // Intercept for first-time login password change
            if (res.data.user.is_first_login == 1) {
                return { ...res, requires_password_change: true };
            }

            this.redirectToDashboard();
        }
        return res;
    },

    async register(data) {
        const res = await api.post('/auth/register', data);
        if (res.success) {
            this.setSession(res.data.token, res.data.user);
            this.redirectToDashboard();
        }
        return res;
    }
};

window.Auth = Auth;
