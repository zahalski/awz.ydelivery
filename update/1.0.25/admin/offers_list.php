<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
global $APPLICATION;
$module_id = "awz.ydelivery";

\Bitrix\Main\Loader::includeModule($module_id);
\Bitrix\Main\Loader::includeModule('sale');

use Awz\Ydelivery\Handler;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Localization\Loc;
use Awz\Ydelivery\Helper;
use Awz\Ydelivery\Lists\Edit;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Page\Asset;
Loc::loadMessages(__FILE__);

$POST_RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($POST_RIGHT == "D")
	$APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$APPLICATION->SetTitle(Loc::getMessage("AWZ_YDELIVERY_ADMIN_OL_TITLE"));
$APPLICATION->SetAdditionalCSS("/bitrix/css/".$module_id."/style.css");
Asset::getInstance()->addString('<style>.adm-filter-main-table {width: 100%!important;}</style>');

global $STATUS_LIST;
$STATUS_LIST = unserialize(Option::get($module_id, 'YD_STATUSLIST', '', ''));

$val_stat_disabled = Option::get($module_id, "CHECKER_FIN_DSBL", "", '');
$val_stat_disabled = unserialize($val_stat_disabled);
if(!is_array($val_stat_disabled)) $val_stat_disabled = array();
foreach($val_stat_disabled as $code){
    unset($STATUS_LIST[$code]);
}

$statusOb = \CSaleStatus::GetList();
$statusAr = array();
while($d = $statusOb->fetch()){
    $statusAr[$d['ID']] = '['.$d['ID'].'] - '.$d['NAME'];
}

$statusValues = array(
    'reference'=>array(Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_DELIVERY_ALL_FILTER')),
    'reference_id'=>array('')
);
foreach($statusAr as $k=>$v){
    $statusValues['reference_id'][] = $k;
    $statusValues['reference'][] = $v;
}

$deliveryProfileList = Helper::getActiveProfileIds();
$deliveryValue = array(
    'reference'=>array(Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_DELIVERY_ALL_FILTER')),
    'reference_id'=>array('')
);
foreach($deliveryProfileList as $k=>$v){
    $deliveryValue['reference_id'][] = $k;
    $deliveryValue['reference'][] = '['.$k.'] - '.$v;
}

$lastStatusValue = array(
    'reference'=>array(Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_DELIVERY_ALL_FILTER')),
    'reference_id'=>array('')
);
foreach($STATUS_LIST as $k=>$v){
    $lastStatusValue['reference_id'][] = $k;
    $lastStatusValue['reference'][] = '['.$k.'] - '.$v;
}

$arParams = array(
	"PRIMARY" => "ID",
	"LANG_CODE" => "AWZ_YDELIVERY_OFFERS_",
	"ENTITY" => "\\Awz\\Ydelivery\\OffersTable",
	"FILE_EDIT" => 'awz_ydelivery_offers_list_edit.php',
	"BUTTON_CONTECST" => array(),
    "ADD_GROUP_ACTION" => array(
        array("key"=>"labels1", "title"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_LABEL_TS')." v1"),
        array("key"=>"labels2", "title"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_LABEL_TS')." v2"),
        array("key"=>"invoice", "title"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_INVOICE_T')),
    ),
	"COLS" => array('ID','ORDER_ID','OFFER_ID','HISTORY_FIN','HISTORY','CREATE_DATE','LAST_DATE','LAST_STATUS',
        'ORD_STATUS_NAME'=>
        array("id" => 'ORD.STATUS.NAME',
        "content" => Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_ORDER_FIELD_STATUS'),
        "sort" => 'ORD.STATUS.NAME',
        "default" => true
        )
    ),
    "FIND" => array('ORDER_ID','OFFER_ID',
        array(
            "NAME"=>"HISTORY_FIN", "KEY"=>"HISTORY_FIN", "GROUP"=>"HISTORY_FIN", "FILTER_TYPE"=>"=","TYPE"=>"LIST",
            "VALUES"=> array(
                'reference'=>array(
                    Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_DELIVERY_ALL_FILTER'),
                    Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_Y'),
                    Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_N')
                ),
                'reference_id'=>array('','Y','N')
            )
        ),
        array(
            "NAME"=>"LAST_STATUS",
            "KEY"=>"LAST_STATUS", "GROUP"=>"LAST_STATUS", "FILTER_TYPE"=>"=","TYPE"=>"LIST",
            "VALUES"=> $lastStatusValue,
            "MULTIPLE"=>'Y'
        ),
        array(
            "NAME"=>"ORD.STATUS_ID","TITLE"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_STATUS_FILTER'),
            "KEY"=>"ORD.STATUS_ID", "GROUP"=>"ORD.STATUS_ID", "FILTER_TYPE"=>"=","TYPE"=>"LIST",
            "VALUES"=> $statusValues,
            "MULTIPLE"=>'Y'
        ),
        array(
            "NAME"=>"ORD.DELIVERY_ID","TITLE"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_DELIVERY_ID_FILTER'),
            "KEY"=>"ORD.DELIVERY_ID", "GROUP"=>"ORD.DELIVERY_ID", "FILTER_TYPE"=>"=","TYPE"=>"LIST",
            "VALUES"=> $deliveryValue,
            "MULTIPLE"=>'Y'
        )
    ),
	"LIST" => array("ACTIONS" => array(
	    "cansel"=>array(
            "ICON"=>"delete",
            "DEFAULT"=>false,
            "TEXT"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_DELETE_OFFER'),
            "TITLE"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_DELETE_OFFER'),
            "ACTION"=>"if(confirm('".Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_DELETE_OFFER_CONFIRM')."')) #PRIMARY#",
        ),
        "labels1"=>array(
            "ICON"=>"view",
            "DEFAULT"=>false,
            "TEXT"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_LABEL_T').' v1',
            "TITLE"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_LABEL_T').' v1',
            "ACTION"=>"#PRIMARY#",
        ),
        "labels2"=>array(
            "ICON"=>"view",
            "DEFAULT"=>false,
            "TEXT"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_LABEL_T').' v2',
            "TITLE"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_LABEL_T').' v2',
            "ACTION"=>"#PRIMARY#",
        ),
        "invoice"=>array(
            "ICON"=>"view",
            "DEFAULT"=>false,
            "TEXT"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_INVOICE_T'),
            "TITLE"=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_INVOICE_T'),
            "ACTION"=>"#PRIMARY#",
        ),
    )),
	"CALLBACK_ACTIONS" => array(
	    "cansel"=>array("\Awz\Ydelivery\Lists\Edit", "canselAction"),
	    "labels1"=>array("\Awz\Ydelivery\Lists\Edit", "labels1Action"),
	    "labels2"=>array("\Awz\Ydelivery\Lists\Edit", "labels2Action"),
	    "invoice"=>array("\Awz\Ydelivery\Lists\Edit", "invoiceAction"),
    )
);

$customPrint = false;
$event = new Event(
    Handler::MODULE_ID,
    "onBeforeShowListItems",
    array('params'=>$arParams, 'custom'=>false)
);
$event->send();
if ($event->getResults()) {
    foreach ($event->getResults() as $evenResult) {
        if ($evenResult->getType() == EventResult::SUCCESS) {
            $r = $evenResult->getParameters();
            if(isset($r['params']) && is_array($r['params'])){
                $arParams = $r['params'];
            }
            if(isset($r['custom'])){
                $customPrint = $r['custom'];
            }
        }
    }
}

if(!$customPrint) {
    $adminCustom = new Edit($arParams);
    $adminCustom->defaultInterface();
}

