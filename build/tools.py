from chardet.universaldetector import UniversalDetector
import os
import shutil
import zipfile
import tempfile
import re


def add_zip(arch, add_folder, mode, root_zip_folder=''):
    z = zipfile.ZipFile(arch, mode, zipfile.ZIP_DEFLATED, True)
    for root, dirs, files in os.walk(add_folder):
        for file in files:
            # Создание относительных путей и запись файлов в архив
            path = os.path.join(root, file)
            len_rm = len(add_folder)
            z.write(path, root_zip_folder+path[len_rm:])
    z.close()
    print('created zip', arch)


def encode_bx(filename, encoding_from='utf-8', encoding_to='windows-1251', original_file=''):
    with open(filename, 'r', encoding=encoding_from) as fr:
        with open(filename+'.tmp', 'w', encoding=encoding_to) as fw:
            for line in fr:
                fw.write(line)
    shutil.copyfile(filename+'.tmp', filename)
    os.remove(filename+'.tmp')
    print('converting ', original_file, 'from', encoding_from, 'to', encoding_to)


def check_encoding(file_path):
    detector = UniversalDetector()
    with open(file_path, 'rb') as fh:
        for line in fh:
            detector.feed(line)
            if detector.done:
                break
        detector.close()
    return {'charset': detector.result['encoding'], 'path': file_path}


def get_files(file_path, copy_dir):
    for name in os.listdir(file_path):
        if not os.path.isdir(os.path.join(file_path,name)):
            shutil.copyfile(os.path.join(file_path,name), os.path.join(copy_dir,name))
            if os.path.join('lang','ru') in os.path.join(file_path,name) or 'description.ru' in name:
                res = check_encoding(os.path.join(copy_dir,name))
                if res['charset'] != 'utf-8':
                    raise Exception('incorrect charset: '+res['charset']+' from file '+res['path'])
                else:
                    encode_bx(os.path.join(copy_dir,name), original_file=os.path.join(file_path,name))
        else:
            if not os.path.isdir(os.path.join(copy_dir,name)):
                os.mkdir(os.path.join(copy_dir,name))
            get_files(os.path.join(file_path,name), os.path.join(copy_dir,name))


def build_main(module_path, zip_name, folder=".last_version/"):
    version = get_module_version(module_path)
    if not version:
        raise Exception('is bitrix module? path: '+module_path)
    print('creating ', zip_name, 'module version', version)
    tmp_dir = tempfile.mkdtemp()
    get_files(module_path, tmp_dir)
    add_zip(zip_name, tmp_dir, "w", folder)
    shutil.rmtree(tmp_dir)


def get_module_version(module_path, encoding_file='utf-8'):
    version = False
    version_file = os.path.join(module_path, 'install/version.php')
    if not os.path.isfile(version_file):
        return version
    with open(version_file, 'r', encoding=encoding_file) as fv:
        for line in fv:
            if 'VERSION' in line and not 'VERSION_DATE' in line:
                try:
                    ob_re = re.search(re.compile("([0-9.]+)"), line)
                    version = ob_re.group(1)
                    if len(version) < 3:
                        version = False
                except Exception as e:
                    print(e)
                    version = False
    return version