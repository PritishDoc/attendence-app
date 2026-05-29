/**
 * Attendify — Auth Helpers
 */

const Auth = {
    TOKEN_KEY: 'attendify_token',
    USER_KEY: 'attendify_user',

    setSession(token, user) {
        localStorage.setItem(this.TOKEN_KEY, token);
        localStorage.setItem(this.USER_KEY, JSON.stringify(user));
    },

    getToken() { return localStorage.getItem(this.TOKEN_KEY); },
    getUser() { try { return JSON.parse(localStorage.getItem(this.USER_KEY)); } catch { return null; } },
    getRole() { const u = this.getUser(); return u ? u.role : null; },
    getCompanyId() { const u = this.getUser(); return u ? u.company_id : null; },
    isAuthenticated() { return !!this.getToken(); },

    logout() {
        localStorage.removeItem(this.TOKEN_KEY);
        localStorage.removeItem(this.USER_KEY);
        window.location.href = '/login.html';
    },

    requireAuth() {
        if (!this.isAuthenticated()) { window.location.href = '/login.html'; return false; }
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

    async login(email, password) {
        const device_uuid = window.getDeviceFingerprint ? window.getDeviceFingerprint() : null;
        const res = await api.post('/auth/login', { email, password, device_uuid });
        if (res.success) {
            this.setSession(res.data.token, res.data.user);
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
