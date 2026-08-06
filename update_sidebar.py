import glob
import re

files = glob.glob('public/company/*.html')
for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    new_links = r'<a href="settings.html" class="nav-item\1"><span class="nav-icon">⚙️</span> Settings</a>' + "\n" + '                <a href="branches.html" class="nav-item"><span class="nav-icon">🏢</span> Branches</a>' + "\n" + '                <a href="holidays.html" class="nav-item"><span class="nav-icon">🎉</span> Holidays</a>' + "\n" + '                <a href="leave-policies.html" class="nav-item"><span class="nav-icon">📄</span> Leave Policies</a>' + "\n" + '                <a href="document-types.html" class="nav-item"><span class="nav-icon">📁</span> Document Types</a>'
                
    content = re.sub(r'<a href="settings.html" class="nav-item(\s+active)?"><span class="nav-icon">⚙️</span> Settings</a>', new_links, content)
    
    with open(file, 'w', encoding='utf-8') as f:
        f.write(content)
print('Updated 9 files')
