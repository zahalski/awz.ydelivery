<?
$moduleId = "awz.ydelivery";
$connection = \Bitrix\Main\Application::getConnection();
$sql = 'ALTER TABLE `b_awz_ydelivery_offer` IF EXISTS `LAST_STATUS` ADD `LAST_STATUS` varchar(65) DEFAULT NULL;';
$connection->queryExecute($sql);