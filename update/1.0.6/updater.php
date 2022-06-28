<?
$moduleId = "awz.ydelivery";
if(IsModuleInstalled($moduleId)) {
	CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$moduleId."/install/js/", $_SERVER["DOCUMENT_ROOT"]."/bitrix/js/".$moduleId, true);
	CopyDirFiles($_SERVER['DOCUMENT_ROOT']."/bitrix/modules/".$moduleId."/install/components/ydelivery.baloon/", $_SERVER['DOCUMENT_ROOT']."/bitrix/components/awz/ydelivery.baloon", true, true);
}
?>