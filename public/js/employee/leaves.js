document.addEventListener('DOMContentLoaded', () => {
    loadBalances();
    loadHistory();
});

async function loadBalances() {
    try {
        const res = await api.get('/leaves/balances');
        if (res.success) {
            const grid = document.getElementById('balancesGrid');
            const balances = res.data || [];
            
            if (balances.length === 0) {
                grid.innerHTML = '<div class="text-muted text-center" style="grid-column: span 3">No leave policies found for this year.</div>';
                return;
            }

            grid.innerHTML = balances.map(b => `
                <div class="card stat-card animate-fade-in-up">
                    <div class="stat-icon" style="background: var(--accent-alpha); color: var(--accent-primary)">🌴</div>
                    <div class="stat-info">
                        <div class="stat-label">${b.leave_type} Balance</div>
                        <div class="stat-value">${b.remaining_days} <span style="font-size: 0.9rem; color: var(--text-muted)">/ ${b.allocated_days}</span></div>
                        <div class="text-sm mt-xs" style="color: var(--text-tertiary)">Used: ${b.used_days} days</div>
                    </div>
                </div>
            `).join('');
        }
    } catch (err) {
        console.error('Failed to load balances:', err);
    }
}

async function loadHistory() {
    try {
        const res = await api.get('/leaves/history');
        if (res.success) {
            const tbody = document.getElementById('leaveHistory');
            const history = res.data || [];
            
            if (history.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No leaves found</td></tr>';
                return;
            }

            tbody.innerHTML = history.map(l => {
                let dates = formatDate(l.start_date);
                if (l.start_date !== l.end_date) dates += ` to ${formatDate(l.end_date)}`;
                
                let duration = l.leave_duration === 'full_day' ? 'Full Day' : (l.leave_duration === 'half_day_start' ? 'Half Day (Start)' : 'Half Day (End)');
                
                let actionBtn = '';
                if (l.status === 'pending') {
                    actionBtn = `<button class="btn btn-ghost text-danger text-sm" onclick="deleteLeave(${l.id})">Delete</button>`;
                } else if (l.status === 'approved' && new Date(l.start_date) > new Date()) {
                    actionBtn = `<button class="btn btn-ghost text-warning text-sm" onclick="cancelLeave(${l.id})">Cancel</button>`;
                } else {
                    actionBtn = '<span class="text-muted text-sm">—</span>';
                }

                return `
                <tr>
                    <td class="font-semibold">${l.leave_type}</td>
                    <td class="text-sm">${dates}</td>
                    <td class="text-sm">${duration}</td>
                    <td>${statusBadge(l.status)}</td>
                    <td>${actionBtn}</td>
                </tr>
            `}).join('');
        }
    } catch (err) {
        console.error('Failed to load history:', err);
    }
}

function openApplyModal() {
    document.getElementById('applyLeaveForm').reset();
    document.getElementById('dateInfoAlert').style.display = 'none';
    document.getElementById('applyLeaveModal').classList.add('open');
}

function closeApplyModal() {
    document.getElementById('applyLeaveModal').classList.remove('open');
}

async function checkDateInfo() {
    const date = document.getElementById('start_date').value;
    if (!date) return;
    
    // In a real app we might call /api/leaves/date-info here to warn the user
    // if there is a conflict. For brevity, assuming backend validation catches it.
}

async function handleApplyLeave(e) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Submitting...';

    const data = {
        leave_type: form.leave_type.value,
        leave_duration: form.leave_duration.value,
        start_date: form.start_date.value,
        end_date: form.end_date.value,
        reason: form.reason.value
    };

    try {
        const res = await api.post('/leaves/apply', data);
        if (res.success) {
            closeApplyModal();
            loadHistory();
            loadBalances(); // In case LOP or similar affected UI
        } else {
            alert(res.message || 'Failed to apply leave');
        }
    } catch (err) {
        alert(err.message || 'An error occurred');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Submit Request';
    }
}

async function deleteLeave(id) {
    if (!confirm('Are you sure you want to delete this pending leave application?')) return;
    try {
        const res = await api.delete(`/leaves/${id}`);
        if (res.success) {
            loadHistory();
        } else {
            alert(res.message);
        }
    } catch (err) {
        alert(err.message);
    }
}

async function cancelLeave(id) {
    if (!confirm('Are you sure you want to cancel this approved leave?')) return;
    try {
        const res = await api.post(`/leaves/${id}/cancel`);
        if (res.success) {
            loadHistory();
            loadBalances();
        } else {
            alert(res.message);
        }
    } catch (err) {
        alert(err.message);
    }
}
