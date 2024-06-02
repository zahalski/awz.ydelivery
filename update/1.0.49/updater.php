<?
$moduleId = "awz.ydelivery";

$connection = \Bitrix\Main\Application::getConnection();
$recordsRes = $connection->query("select * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='b_awz_ydelivery_offer'");
while($dt = $recordsRes->fetch()){
	$checkTable = true;
	$sql = 'ALTER TABLE `b_awz_ydelivery_offer` MODIFY `HISTORY` longtext';
	$connection->queryExecute($sql);
}