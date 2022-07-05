import sys
sys.path.append("../build/")
from tools import *
import re

module_path = os.path.abspath('../bitrix/modules/awz.ydelivery/')
version = get_module_version(module_path)

lang_prefix = 'AWZ_YDELIVERY_'

deprecated_uncheck = [
    os.path.join('install', 'unstep.php')
]
disabled_lang = (
    'AWZ_PARTNER_NAME', 'AWZ_PARTNER_URI',
    'ACCESS_DENIED'
)

def get_all_files(path, uncheck_dir=[]):
    files = set()
    for f_name in os.listdir(path):
        if not os.path.isdir(os.path.join(path, f_name)):
            pt = os.path.join(path, f_name)
            if '.php' == pt[-4:] and os.path.join('lang','ru') not in pt:
                files.add(os.path.join(path, f_name))
        else:
            uncheck = False
            for _ in uncheck_dir:
                if _ in os.path.join(path, f_name):
                    uncheck = True
            if not uncheck:
                f = get_all_files(os.path.join(path, f_name), uncheck_dir)
                for _ in f:
                    files.add(_)
    return files

if version:
    all_files = get_all_files(module_path, [os.path.join('install', 'components')])
    for _ in all_files:
        file = _[len(module_path):]
        lang_file = os.path.join(module_path, 'lang', 'ru', file[1:])
        lang_values = set()
        if os.path.exists(lang_file):
            with open(lang_file, 'r', encoding='utf-8') as fv:
                for line in fv:
                    result = re.findall(r'\$MESS\s?\[(?:"|\')([A-z0-9_]+)', line)
                    if len(result):
                        lang_values.add(*result)
        with open(_, 'r', encoding='utf-8') as fv:
            for line in fv:
                dep_check = True
                for check_path in deprecated_uncheck:
                    if check_path in _:
                        dep_check = False
                if dep_check:
                    result = re.findall(r'GetMessage\s?\((?:\((?:"|\')|"|\')([A-z0-9_]+)', line)
                    if len(result):
                        for ln in result:
                            print('deprecated', ln, _)
                result = re.findall(r'Loc::getMessage\s?\((?:\((?:"|\')|"|\')([A-z0-9_]+)', line)
                if len(result):
                    for ln in result:
                        if not ln in disabled_lang:
                            if not lang_prefix in ln:
                                 print('unknown code', ln, 'in file', _)
                            if ln in lang_values:
                                pass
                            else:
                                print('not found', ln, 'in lang file', lang_file)
