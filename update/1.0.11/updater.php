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
if($checkTable && !$checkColumn){
	$sql = 'ALTER TABLE `b_awz_ydelivery_offer` ADD `LAST_STATUS` varchar(65) DEFAULT NULL';
	$connection->queryExecute($sql);
}