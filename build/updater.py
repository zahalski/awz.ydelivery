import os
from tools import *

module_path = os.path.abspath('../bitrix/modules/awz.ydelivery/')
updates_path = os.path.abspath('../update/')
version = get_module_version(module_path)

if version:
    zip_name = os.path.abspath('../dist/update/'+version+'.zip')
    updater_path = os.path.join(updates_path, version)
    build_main(updater_path, zip_name, version)
