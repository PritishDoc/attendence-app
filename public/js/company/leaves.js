let currentPage = 1;
const limit = 20;
let leavesData = [];

document.addEventListener('DOMContentLoaded', () => {
    loadLeaves();
});

async function loadLeaves() {
    try {
        const res = await api.get(`/leaves/admin/all?page=${currentPage}&limit=${limit}`);
        if (res.success) {
            leavesData = res.data || [];
            const tbody = document.getElementById('leavesTable');
            
            if (leavesData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted" style="padding:48px">No leaves found</td></tr>';
                document.getElementById('nextBtn').disabled = true;
                return;
            }

            // Simple heuristic to disable next button
            document.getElementById('nextBtn').disabled = leavesData.length < limit;

            tbody.innerHTML = leavesData.map(l => {
                let dates = formatDate(l.start_date);
                if (l.start_date !== l.end_date) dates += ` to ${formatDate(l.end_date)}`;
                if (l.leave_duration !== 'full_day') {
                    dates += ` <span class="text-sm text-muted">(${l.leave_duration.replace('_', ' ')})</span>`;
                }

                let actions = '';
                if (l.status === 'pending' || l.status === 'under_process') {
                    actions = `
                        <div class="flex gap-xs">
                            <button class="btn btn-sm btn-primary" onclick="openApproveModal(${l.id})">Approve</button>
                            <button class="btn btn-sm btn-ghost text-danger" onclick="updateStatus(${l.id}, 'rejected')">Reject</button>
                        </div>
                    `;
                } else {
                    actions = `<span class="text-muted text-sm">Processed</span>`;
                }

                return `
                <tr>
                    <td>
                        <div class="font-medium">${l.employee_name}</div>
                    </td>
                    <td class="font-semibold">${l.leave_type}</td>
                    <td class="text-sm">${dates}</td>
                    <td class="text-sm" style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${l.reason}">${l.reason}</td>
                    <td>${statusBadge(l.status)}</td>
                    <td>${actions}</td>
                </tr>
            `}).join('');
        }
    } catch (err) {
        console.error('Failed to load leaves:', err);
    }
}

function changePage(delta) {
    if (currentPage + delta > 0) {
        currentPage += delta;
        document.getElementById('pageInfo').textContent = `Page ${currentPage}`;
        document.getElementById('prevBtn').disabled = currentPage === 1;
        loadLeaves();
    }
}

async function updateStatus(id, status) {
    if (!confirm(`Are you sure you want to mark this leave as ${status}?`)) return;
    try {
        const res = await api.put(`/leaves/${id}/status`, { status });
        if (res.success) {
            loadLeaves();
        } else {
            alert(res.message);
        }
    } catch (err) {
        alert(err.message);
    }
}

function openApproveModal(id) {
    const leave = leavesData.find(l => l.id === id);
    if (!leave) return;
    
    document.getElementById('approve_leave_id').value = leave.id;
    document.getElementById('approved_start').value = leave.start_date;
    document.getElementById('approved_end').value = leave.end_date;
    
    // Limits based on original request
    document.getElementById('approved_start').min = leave.start_date;
    document.getElementById('approved_start').max = leave.end_date;
    document.getElementById('approved_end').min = leave.start_date;
    document.getElementById('approved_end').max = leave.end_date;

    document.getElementById('approveModal').classList.add('open');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.remove('open');
}

async function handleApprove(e) {
    e.preventDefault();
    const id = document.getElementById('approve_leave_id').value;
    const approvedStart = document.getElementById('approved_start').value;
    const approvedEnd = document.getElementById('approved_end').value;

    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Approving...';

    try {
        const res = await api.put(`/leaves/${id}/status`, { 
            status: 'approved',
            approved_start_date: approvedStart,
            approved_end_date: approvedEnd
        });
        if (res.success) {
            closeApproveModal();
            loadLeaves();
        } else {
            alert(res.message || 'Failed to approve leave');
        }
    } catch (err) {
        alert(err.message || 'An error occurred');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Approve Leave';
    }
}
