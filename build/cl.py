import os
from tools import *

change_log = []
change_log.append('## История версий')

updates_path = os.path.abspath('../update/')
readme_file = os.path.abspath('../README.md')
for name in os.listdir(updates_path):
    with open(os.path.join(updates_path, name, 'description.ru'), 'r', encoding='utf-8') as fr:
        change_log.append('*v'+str(name)+'*    ')
        for line in fr:
            change_log.append(line.strip()+'    ')

all_rows = []
find_cl = False
with open(readme_file, 'r', encoding='utf-8') as fr:
    for line in fr:
        if not find_cl:
            all_rows.append(line)
            if '<!-- cl-start -->' in line:
                find_cl = True
        else:
            if '<!-- cl-end -->' in line:
                for ln in change_log:
                    all_rows.append(ln+"\n")
                all_rows.append(line)
                find_cl = False

with open(readme_file, 'w', encoding='utf-8') as fr:
    for wr in all_rows:
        fr.write(wr)