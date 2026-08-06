import os

with open('public/company/departments.html', 'r', encoding='utf-8') as f:
    content = f.read()

# Make branches.html
branches = content.replace('Departments', 'Branches')
branches = branches.replace('Department Name', 'Branch Name')
branches = branches.replace('department', 'branch')
branches = branches.replace('departments', 'branches')
with open('public/company/branches.html', 'w', encoding='utf-8') as f:
    f.write(branches)

# Make holidays.html
holidays = content.replace('Departments', 'Holidays')
holidays = holidays.replace('Department Name', 'Holiday Name')
holidays = holidays.replace('department', 'holiday')
holidays = holidays.replace('departments', 'holidays')
holidays = holidays.replace('Description', 'Date')
holidays = holidays.replace('description', 'holiday_date')
with open('public/company/holidays.html', 'w', encoding='utf-8') as f:
    f.write(holidays)

# Make leave-policies.html
leave = content.replace('Departments', 'Leave Policies')
leave = leave.replace('Department Name', 'Leave Type (CL, SL, etc.)')
leave = leave.replace('department', 'leave-policy')
leave = leave.replace('departments', 'leave-policies')
leave = leave.replace('Description', 'Allocated Days')
leave = leave.replace('description', 'allocated_days')
leave = leave.replace('type="text" id="leave-policyName"', 'type="text" id="leave-policyName" required>\n                        </div>\n                        <div class="form-group">\n                            <label class="form-label">Year</label>\n                            <input type="number" class="form-control" id="leave-policyYear" value="2026"')
with open('public/company/leave-policies.html', 'w', encoding='utf-8') as f:
    f.write(leave)

# Make document-types.html
docs = content.replace('Departments', 'Document Types')
docs = docs.replace('Department Name', 'Document Type Name')
docs = docs.replace('department', 'document-type')
docs = docs.replace('departments', 'document-types')
docs = docs.replace('Description', 'Required')
docs = docs.replace('description', 'is_required')
docs = docs.replace('<textarea class="form-control" id="document-typeis_required" rows="3"></textarea>', '<select class="form-control" id="document-typeis_required"><option value="1">Yes</option><option value="0">No</option></select>')
with open('public/company/document-types.html', 'w', encoding='utf-8') as f:
    f.write(docs)

print('Generated HTML files.')
