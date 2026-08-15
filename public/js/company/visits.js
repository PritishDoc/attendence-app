// Ensure user is company admin
Auth.requireRole(['company_admin', 'super_admin']);
populateSidebarUser();

let allVisits = [];
let allEmployees = [];

document.addEventListener('DOMContentLoaded', () => {
    loadEmployees();
    loadVisits();
    loadStats();

    // Event listeners for filters
    document.getElementById('empFilter').addEventListener('change', renderVisitsTable);
    document.getElementById('statusFilter').addEventListener('change', renderVisitsTable);
});

async function loadStats() {
    try {
        const res = await api.get('/api/visits/stats');
        if (res.success && res.data) {
            document.getElementById('statTotal').textContent = res.data.total || 0;
            document.getElementById('statCompleted').textContent = res.data.completed || 0;
            document.getElementById('statInProgress').textContent = res.data.pending || 0; // The API returns 'pending' for pending, but in UI 'In Progress' usually maps to 'in_progress' and 'Pending' maps to 'pending'. Wait, let's just fetch stats.
            
            // Re-fetch all visits to count accurately if API doesn't separate pending vs in-progress clearly
            const allRes = await api.get('/api/visits');
            if(allRes.success) {
                const visits = allRes.data;
                const completed = visits.filter(v => v.status === 'completed').length;
                const inProgress = visits.filter(v => v.status === 'in_progress').length;
                const pending = visits.filter(v => v.status === 'pending').length;
                
                document.getElementById('statTotal').textContent = visits.length;
                document.getElementById('statCompleted').textContent = completed;
                document.getElementById('statInProgress').textContent = inProgress;
                document.getElementById('statPending').textContent = pending;
            }
        }
    } catch (e) {
        console.error("Failed to load stats", e);
    }
}

async function loadEmployees() {
    try {
        const res = await api.get('/employees');
        if (res.success) {
            allEmployees = res.data;
            const empFilter = document.getElementById('empFilter');
            const assigneeSelect = document.getElementById('assigneeSelect');
            const coAssigneeSelect = document.getElementById('coAssigneeSelect');
            
            let optionsHTML = '<option value="">All Employees</option>';
            let selectHTML = '<option value="">Select Employee</option>';
            let coSelectHTML = '<option value="">None</option>';
            
            allEmployees.forEach(emp => {
                const opt = `<option value="${emp.id}">${emp.name}</option>`;
                optionsHTML += opt;
                selectHTML += opt;
                coSelectHTML += opt;
            });
            
            empFilter.innerHTML = optionsHTML;
            assigneeSelect.innerHTML = selectHTML;
            if(coAssigneeSelect) coAssigneeSelect.innerHTML = coSelectHTML;
        }
    } catch (e) {
        console.error("Failed to load employees", e);
    }
}

async function loadVisits() {
    const tbody = document.getElementById('visitsTableBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted" style="padding:32px">Loading visits...</td></tr>';
    
    try {
        const res = await api.get('/api/visits');
        if (res.success) {
            allVisits = res.data;
            renderVisitsTable();
            loadStats(); // Update stats based on loaded visits
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${res.message}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Failed to load visits.</td></tr>`;
    }
}

function renderVisitsTable() {
    const tbody = document.getElementById('visitsTableBody');
    const empFilter = document.getElementById('empFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    
    let filtered = allVisits;
    
    if (empFilter) {
        filtered = filtered.filter(v => v.assignee_id == empFilter);
    }
    
    if (statusFilter) {
        filtered = filtered.filter(v => v.status === statusFilter);
    }
    
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted" style="padding:32px">No visits found.</td></tr>';
        return;
    }
    
    tbody.innerHTML = filtered.map(v => {
        // Find employee name
        const emp = allEmployees.find(e => e.id == v.assignee_id);
        const empName = emp ? emp.name : `Emp #${v.assignee_id}`;
        
        // Format Date & Time
        const dateStr = new Date(v.visit_date).toLocaleDateString();
        const timeStr = v.visit_time; // Already string like 14:30:00
        
        return `
            <tr>
                <td>
                    <div class="flex items-center gap-sm">
                        <div class="avatar avatar-sm">${getInitials(empName)}</div>
                        <span class="font-medium">${empName}</span>
                    </div>
                </td>
                <td>
                    <div class="font-medium">${v.customer_name}</div>
                    <div class="text-sm text-muted" style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${v.address || 'No address'}">
                        ${v.address || '-'}
                    </div>
                </td>
                <td>
                    <div>${dateStr}</div>
                    <div class="text-sm text-muted">${timeStr}</div>
                </td>
                <td>${formatStatusBadge(v.status)}</td>
                <td>
                    <button class="btn btn-ghost btn-sm" onclick='openVisitDetails(${JSON.stringify(v).replace(/'/g, "&apos;")})'>View</button>
                </td>
            </tr>
        `;
    }).join('');
}

function formatStatusBadge(status) {
    if (status === 'completed') return '<span class="badge badge-success">Completed</span>';
    if (status === 'in_progress') return '<span class="badge badge-warning">In Progress</span>';
    return '<span class="badge badge-gray">Pending</span>';
}

function openVisitDetails(visit) {
    const emp = allEmployees.find(e => e.id == visit.assignee_id);
    document.getElementById('detCustomer').textContent = visit.customer_name;
    document.getElementById('detEmployee').textContent = emp ? emp.name : `Emp #${visit.assignee_id}`;
    
    const checkinImg = document.getElementById('checkinImg');
    const checkoutImg = document.getElementById('checkoutImg');
    
    checkinImg.src = visit.checkin_selfie ? getApiUrl(visit.checkin_selfie) : '';
    checkoutImg.src = visit.checkout_selfie ? getApiUrl(visit.checkout_selfie) : '';
    
    document.getElementById('checkinTime').textContent = visit.checkin_time ? formatDateTime(visit.checkin_time) : '--:--';
    document.getElementById('checkoutTime').textContent = visit.checkout_time ? formatDateTime(visit.checkout_time) : '--:--';
    
    openModal('visitDetailsModal');
}

function getApiUrl(path) {
    if (!path) return '';
    // If the path starts with /, prepend the API base URL (which might be the same origin)
    // api.js usually handles base URL in fetch, but for images we need full URL if backend is on another domain
    // Let's assume standard origin for now, or just return the path directly if it's on the same server.
    return path;
}

function formatDateTime(datetimeStr) {
    const d = new Date(datetimeStr);
    return isNaN(d) ? datetimeStr : d.toLocaleString();
}

async function handleVisitSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    const btn = document.getElementById('btnSubmitVisit');
    const originalText = btn.textContent;
    btn.textContent = 'Scheduling...';
    btn.disabled = true;
    
    try {
        const res = await api.post('/api/visits', data);
        if (res.success) {
            showToast('Visit scheduled successfully', 'success');
            closeModal('addVisitModal');
            form.reset();
            loadVisits();
        } else {
            showToast(res.message || 'Failed to schedule visit', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('An error occurred: ' + (err.message || 'Unknown'), 'error');
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}
