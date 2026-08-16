import glob
import re

files = glob.glob('public/company/*.html')
for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    finance_section = """
            <div class="nav-section">
                <div class="nav-section-title">Finance</div>
                <a href="expenses.html" class="nav-item"><span class="nav-icon">💸</span> Expenses</a>
                <a href="advances.html" class="nav-item"><span class="nav-icon">💳</span> Advances</a>
                <a href="incentives.html" class="nav-item"><span class="nav-icon">🏆</span> Incentives</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">System</div>"""
                
    content = re.sub(r'<div class="nav-section">\s*<div class="nav-section-title">System</div>', finance_section, content)
    
    with open(file, 'w', encoding='utf-8') as f:
        f.write(content)
print(f'Updated {len(files)} files')
