const utils = {
    escapeHtml: function(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, match => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[match]);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    loadAttendanceRequests();
    loadLeaveRequests();
});

async function loadAttendanceRequests() {
    const listEl = document.getElementById('attendanceRequestsList');
    try {
        const res = await window.api.get('/attendance-requests/admin/all');
        const requests = res.data.data || res.data; // Depending on pagination structure
        
        if (!requests || requests.length === 0) {
            listEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted" style="padding:32px">No pending attendance requests</td></tr>';
            return;
        }

        listEl.innerHTML = requests.map(req => {
            const isPending = req.status === 'pending' || req.status === 'under_process';
            return `
                <tr>
                    <td>
                        <div class="font-medium">${utils.escapeHtml(req.employee_name || 'Unknown')}</div>
                        <div class="text-sm text-muted">ID: ${req.employee_id}</div>
                    </td>
                    <td><span class="badge badge-gray">${utils.escapeHtml(req.request_type)}</span></td>
                    <td>
                        <div class="text-sm">${utils.escapeHtml(req.start_date || '-')} to ${utils.escapeHtml(req.end_date || '-')}</div>
                        <div class="text-xs text-muted">${utils.escapeHtml(req.request_time || '')}</div>
                    </td>
                    <td><div class="text-sm" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${utils.escapeHtml(req.reason || '')}">${utils.escapeHtml(req.reason || '-')}</div></td>
                    <td><span class="badge badge-${getStatusColor(req.status)}">${utils.escapeHtml(req.status)}</span></td>
                    <td class="text-right">
                        ${isPending ? `
                            <button class="btn btn-sm btn-primary" onclick="approveAttendanceRequest(${req.id})">Approve</button>
                            <button class="btn btn-sm btn-danger" onclick="rejectAttendanceRequest(${req.id})">Reject</button>
                        ` : '-'}
                    </td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        console.error('Failed to load attendance requests:', error);
        listEl.innerHTML = '<tr><td colspan="6" class="text-center text-red" style="padding:32px">Failed to load requests</td></tr>';
    }
}

async function loadLeaveRequests() {
    const listEl = document.getElementById('leaveRequestsList');
    try {
        const res = await window.api.get('/leaves/admin/all');
        const requests = res.data.data || res.data; 
        
        if (!requests || requests.length === 0) {
            listEl.innerHTML = '<tr><td colspan="7" class="text-center text-muted" style="padding:32px">No pending leave requests</td></tr>';
            return;
        }

        listEl.innerHTML = requests.map(req => {
            const isPending = req.status === 'pending' || req.status === 'under_process';
            return `
                <tr>
                    <td>
                        <div class="font-medium">${utils.escapeHtml(req.employee_name || 'Unknown')}</div>
                        <div class="text-sm text-muted">ID: ${req.employee_id}</div>
                    </td>
                    <td><span class="badge badge-gray">${utils.escapeHtml(req.leave_type)}</span></td>
                    <td><div class="text-sm">${utils.escapeHtml(req.start_date)} to ${utils.escapeHtml(req.end_date)}</div></td>
                    <td><div class="text-sm">${utils.escapeHtml(req.leave_duration || 'full_day')}</div></td>
                    <td><div class="text-sm" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${utils.escapeHtml(req.reason || '')}">${utils.escapeHtml(req.reason || '-')}</div></td>
                    <td><span class="badge badge-${getStatusColor(req.status)}">${utils.escapeHtml(req.status)}</span></td>
                    <td class="text-right">
                        ${isPending ? `
                            <button class="btn btn-sm btn-primary" onclick="approveLeaveRequest(${req.id})">Approve</button>
                            <button class="btn btn-sm btn-danger" onclick="rejectLeaveRequest(${req.id})">Reject</button>
                        ` : '-'}
                    </td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        console.error('Failed to load leave requests:', error);
        listEl.innerHTML = '<tr><td colspan="7" class="text-center text-red" style="padding:32px">Failed to load leave requests</td></tr>';
    }
}

async function approveAttendanceRequest(id) {
    if (!confirm('Are you sure you want to approve this request?')) return;
    try {
        await window.api.post('/attendance-requests/admin/approve/' + id);
        if (typeof showToast === 'function') showToast('Request approved successfully', 'success');
        loadAttendanceRequests();
    } catch (e) {
        if (typeof showToast === 'function') showToast(e.message || 'Failed to approve', 'error');
    }
}

async function rejectAttendanceRequest(id) {
    if (!confirm('Are you sure you want to reject this request?')) return;
    try {
        await window.api.post('/attendance-requests/admin/reject/' + id);
        if (typeof showToast === 'function') showToast('Request rejected successfully', 'success');
        loadAttendanceRequests();
    } catch (e) {
        if (typeof showToast === 'function') showToast(e.message || 'Failed to reject', 'error');
    }
}

async function approveLeaveRequest(id) {
    if (!confirm('Are you sure you want to approve this leave request?')) return;
    try {
        await window.api.put('/leaves/' + id + '/status', { status: 'approved' });
        if (typeof showToast === 'function') showToast('Leave request approved successfully', 'success');
        loadLeaveRequests();
    } catch (e) {
        if (typeof showToast === 'function') showToast(e.message || 'Failed to approve', 'error');
    }
}

async function rejectLeaveRequest(id) {
    if (!confirm('Are you sure you want to reject this leave request?')) return;
    try {
        await window.api.put('/leaves/' + id + '/status', { status: 'rejected' });
        if (typeof showToast === 'function') showToast('Leave request rejected successfully', 'success');
        loadLeaveRequests();
    } catch (e) {
        if (typeof showToast === 'function') showToast(e.message || 'Failed to reject', 'error');
    }
}

function getStatusColor(status) {
    switch (status) {
        case 'approved': return 'green';
        case 'rejected': return 'red';
        case 'pending': 
        case 'under_process': return 'yellow';
        default: return 'gray';
    }
}
