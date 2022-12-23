<?
$moduleId = "awz.ydelivery";
if(IsModuleInstalled($moduleId)) {
    $updater->CopyFiles(
        "install/js",
        "js/".$moduleId,
        true,
        true
    );
    $updater->CopyFiles(
        "install/css",
        "css/".$moduleId,
        true,
        true
    );
}
