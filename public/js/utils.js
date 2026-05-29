/**
 * Attendify — Utility Functions
 */

// Toast notification system
function showToast(message, type = 'info', duration = 4000) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
    toast.innerHTML = `<span>${icons[type] || 'ℹ'}</span> ${message}`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; setTimeout(() => toast.remove(), 300); }, duration);
}

// Date formatting
function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatTime(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function formatDateTime(dateStr) {
    return `${formatDate(dateStr)} ${formatTime(dateStr)}`;
}

function timeAgo(dateStr) {
    const seconds = Math.floor((new Date() - new Date(dateStr)) / 1000);
    const intervals = [
        { label: 'year', seconds: 31536000 }, { label: 'month', seconds: 2592000 },
        { label: 'week', seconds: 604800 }, { label: 'day', seconds: 86400 },
        { label: 'hour', seconds: 3600 }, { label: 'minute', seconds: 60 }
    ];
    for (const i of intervals) {
        const count = Math.floor(seconds / i.seconds);
        if (count > 0) return `${count} ${i.label}${count !== 1 ? 's' : ''} ago`;
    }
    return 'just now';
}

// Modal helpers
function openModal(modalId) {
    const m = document.getElementById(modalId);
    if (m) m.classList.add('active');
}
function closeModal(modalId) {
    const m = document.getElementById(modalId);
    if (m) m.classList.remove('active');
}

// Form serialization
function serializeForm(form) {
    const data = {};
    new FormData(form).forEach((value, key) => { data[key] = value; });
    return data;
}

// Generate initials for avatar
function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

// Status badge HTML
function statusBadge(status) {
    const map = { present: 'success', late: 'warning', absent: 'danger', half_day: 'info', active: 'success', inactive: 'danger', pending: 'warning' };
    return `<span class="badge badge-${map[status] || 'neutral'}">${status.replace('_', ' ')}</span>`;
}

// Currency formatter
function formatCurrency(amount, currency = 'INR') {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency }).format(amount);
}

// Debounce
function debounce(fn, delay = 300) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
}

// Loading state
function setLoading(element, loading) {
    if (loading) {
        element.dataset.originalText = element.innerHTML;
        element.innerHTML = '<span class="spinner"></span>';
        element.disabled = true;
    } else {
        element.innerHTML = element.dataset.originalText || element.innerHTML;
        element.disabled = false;
    }
}

// Populate sidebar user info
function populateSidebarUser() {
    const user = Auth.getUser();
    if (!user) return;
    const nameEl = document.querySelector('.sidebar-user-name');
    const roleEl = document.querySelector('.sidebar-user-role');
    const avatarEl = document.querySelector('.sidebar-user .avatar');
    if (nameEl) nameEl.textContent = user.name;
    if (roleEl) roleEl.textContent = user.role.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    if (avatarEl) avatarEl.textContent = getInitials(user.name);
}

// Confirm dialog
function confirmAction(message) { return confirm(message); }

function getDeviceFingerprint() {
    let fingerprint = localStorage.getItem('attendify_device_uuid');
    if (!fingerprint) {
        const randomArray = new Uint32Array(4);
        window.crypto.getRandomValues(randomArray);
        const hexParts = Array.from(randomArray).map(num => num.toString(16).padStart(8, '0'));
        const screenSig = `${screen.width}x${screen.height}x${screen.colorDepth}`;
        const lang = navigator.language || 'en';
        fingerprint = `dev-${hexParts.join('-')}-${screenSig}-${lang}`;
        localStorage.setItem('attendify_device_uuid', fingerprint);
    }
    return fingerprint;
}

window.showToast = showToast;
window.formatDate = formatDate;
window.formatTime = formatTime;
window.formatDateTime = formatDateTime;
window.timeAgo = timeAgo;
window.openModal = openModal;
window.closeModal = closeModal;
window.serializeForm = serializeForm;
window.getInitials = getInitials;
window.statusBadge = statusBadge;
window.formatCurrency = formatCurrency;
window.debounce = debounce;
window.setLoading = setLoading;
window.populateSidebarUser = populateSidebarUser;
window.getDeviceFingerprint = getDeviceFingerprint;
