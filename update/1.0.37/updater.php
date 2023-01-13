<?
$moduleId = "awz.ydelivery";

$connection = \Bitrix\Main\Application::getConnection();
$checkColumn = false;
$checkTable = false;
$recordsRes = $connection->query("select * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='b_awz_ydelivery_offer'");
while($dt = $recordsRes->fetch()){
    $checkTable = true;
    if($dt['COLUMN_NAME'] == 'LAST_STATUS'){
        $checkColumn = true;
        break;
    }

}
$checkTablePvz = false;
$columnInterval = false;
$columnIntervalDate = false;
$recordsRes = $connection->query("select * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='b_awz_ydelivery_pvz'");
while($dt = $recordsRes->fetch()){
    $checkTablePvz = true;
    if($dt['COLUMN_NAME'] == 'DOST_DAY'){
        $columnInterval = true;
    }
    if($dt['COLUMN_NAME'] == 'LAST_UP'){
        $columnIntervalDate = true;
    }
}
if($checkTable && !$checkColumn){
    $sql = 'ALTER TABLE `b_awz_ydelivery_offer` ADD `LAST_STATUS` varchar(65) DEFAULT NULL';
    $connection->queryExecute($sql);
}
if($checkTablePvz && !$columnInterval){
    $sql = 'ALTER TABLE `b_awz_ydelivery_pvz` ADD `DOST_DAY` int(7) DEFAULT NULL';
    $connection->queryExecute($sql);
}
if($checkTablePvz && !$columnIntervalDate){
    $sql = 'ALTER TABLE `b_awz_ydelivery_pvz` ADD `LAST_UP` datetime DEFAULT NULL';
    $connection->queryExecute($sql);
}

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
    CopyDirFiles(
        $_SERVER['DOCUMENT_ROOT']."/bitrix/modules/".$moduleId."/install/components/ydelivery.baloon/",
        $_SERVER['DOCUMENT_ROOT']."/bitrix/components/awz/ydelivery.baloon",
        true,
        true
    );
}
