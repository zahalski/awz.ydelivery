<?
defined('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or die();

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
$module_id = 'awz.ydelivery';

Loc::loadMessages(__FILE__);

Loader::includeModule($module_id);

if ($APPLICATION->GetGroupRight("sale") != "D" && $APPLICATION->GetGroupRight($module_id) != "D") {

$APPLICATION->AddHeadString('<style>
.adm-submenu-item-name-link .adm-submenu-item-link-icon.menu_awzydelivery {
	background: url(/bitrix/images/'.$module_id.'/yandex-logo.svg) no-repeat center;
	background-size: 100% auto;
	width: 18px;
	margin-left: -5px;
 	margin-right: 9px;
 	margin-top: 0px;
}
</style>');

	$aMenu = array(
		"parent_menu" => "global_menu_store",
		"section" => "awz.ydelivery",
		"sort" => 100,
		"module_id" => "awz.ydelivery",
		"text" => Loc::getMessage('AWZ_YDELIVERY_MENU_NAME'),
		"title" => Loc::getMessage('AWZ_YDELIVERY_MENU_NAME'),
		"items_id" => "menu_awzydelivery",
		"icon" => "menu_awzydelivery",
		"items" => array(
			array(
				"text" => Loc::getMessage('AWZ_YDELIVERY_MENU_LIST'),
				"url" => "awz_ydelivery_offers_list.php?lang=" . LANGUAGE_ID,
				"more_url" => array('awz_ydelivery_offers_list_edit.php?lang=' . LANGUAGE_ID),
				"title" => Loc::getMessage('AWZ_YDELIVERY_MENU_LIST'),
			)
		)
	);

	return $aMenu;
}

