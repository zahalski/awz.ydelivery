import os
from tools import *

module_path = os.path.abspath('../bitrix/modules/awz.ydelivery/')
zip_name = os.path.abspath('../dist/.last_version.zip')

build_main(module_path, zip_name)
