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
use \Awz\Ydelivery\Helper;
use \Awz\Ydelivery\OffersTable;
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

class MlifeRowListAdmin extends \Awz\Ydelivery\Main {
	
	public function __construct($params) {
		parent::__construct($params);
	}
	
	public function getMlifeRowListAdminCustomRow($row){

        global $STATUS_LIST;

	    $status = Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EMPTY_STATUS');
	    $status2 = Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EMPTY_STATUS');

	    if(!empty($row->arRes['HISTORY']['hist'])){
	        $last = array_pop($row->arRes['HISTORY']['hist']);
            $status = $last['description'];
        }
        $row->AddViewField("HISTORY", $status);

	    if(!empty($row->arRes['LAST_STATUS'])){
            $status2 = '['.$row->arRes['LAST_STATUS'].']';
            if(isset($STATUS_LIST[$row->arRes['LAST_STATUS']])){
                $status2 .= ' - '.$STATUS_LIST[$row->arRes['LAST_STATUS']];
            }
        }
        $row->AddViewField("LAST_STATUS", $status2);

        $row->AddViewField("ORD.STATUS.NAME", $row->arRes['AWZ_YDELIVERY_OFFERS_ORD_STATUS_NAME'],'FULL');
        $val = $row->arRes['HISTORY_FIN'] == 'Y' ? 'Y' : 'N';
        $row->AddViewField("HISTORY_FIN", Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_'.$val));
        $row->AddViewField("OFFER_ID", '<a href="/bitrix/admin/awz_ydelivery_offers_list_edit.php?lang='.LANGUAGE_ID.'&id='.$row->arRes['ID'].'">'.$row->arRes['OFFER_ID'].'</a>');
        $row->AddViewField("ORDER_ID", '<a href="/bitrix/admin/sale_order_view.php?lang='.LANGUAGE_ID.'&ID='.$row->arRes['ORDER_ID'].'">'.$row->arRes['ORDER_ID'].'</a>');

	}

	public function canselAction($idAr){
        foreach($idAr as $id){
            $result = OffersTable::canselOffer($id);
            if($result->isSuccess()){
                $data = $result->getData();
                CAdminMessage::ShowMessage(array('TYPE'=>'OK', 'MESSAGE'=>$data['result']['description']));
            }else{
                //array("MESSAGE"=>"", "TYPE"=>("ERROR"|"OK"|"PROGRESS"), "DETAILS"=>"", "HTML"=>true)
                CAdminMessage::ShowMessage(array('TYPE'=>'ERROR', 'MESSAGE'=>implode("; ",$result->getErrorMessages())));
            }
        }
    }

    public static function labelsAction($idAr, $type='one'){

        $profiles = Helper::getActiveProfileIds();
        $profilesData = array();
        foreach($profiles as $id=>$name){
            $profilesData[$id] = array();
        }
        if(\Bitrix\Main\Loader::includeModule('sale')){
            foreach($idAr as $id){
                $data = OffersTable::getRowById($id);
                $order = \Bitrix\Sale\Order::load($data['ORDER_ID']);
                $profile = Helper::getProfileId($order);
                $profilesData[$profile][] = $data['OFFER_ID'];
            }
        }
        $finFilesProfiles = array();
        foreach($profilesData as $profileId=>$orders){
            if(empty($orders)) continue;
            $api = Helper::getApiByProfileId($profileId);
            $config = Helper::getConfigByProfileId($profileId);
            if($api->isTest()){
                $key = md5($api->getToken(), $config['STORE_ID_TEST']);
            }else{
                $key = md5($api->getToken(), $config['STORE_ID']);
            }
            if(!isset($finFilesProfiles[$key])) $finFilesProfiles[$key] = array();
            $finFilesProfiles[$key][] = $profileId;
        }
        $finFiles = array_keys($finFilesProfiles);
        if(!empty($finFiles)){
            foreach($finFiles as $key){
                $normalProfile = $finFilesProfiles[$key][0];
                $api = Helper::getApiByProfileId($normalProfile);
                $orders = array();
                foreach($finFilesProfiles[$key] as $profileId){
                    foreach($profilesData[$profileId] as $offerId){
                        $orders[] = $offerId;
                    }
                }

                if($type == 'invoice'){
                    $res = $api->getInvoice(array('request_id'=>$orders));
                }else{
                    $res = $api->getLabels(array('generate_type'=>$type, 'request_ids'=>$orders));
                }


                if($res->isSuccess()){
                    $resData = $res->getData();
                    $fileContent = $resData['result'];
                    $tmpName = 'labels-'.time().'-'.\Bitrix\Main\Security\Random::getString(20).'.pdf';
                    $fileOb = new \Bitrix\Main\IO\File(\Bitrix\Main\Application::getDocumentRoot() . "/upload/tmp/".$tmpName);
                    $fileOb->putContents($fileContent);
                    echo '<a style="margin:5px;" href="/upload/tmp/'.$tmpName.'" target="_blank">'.Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_DOWNLOAD').' ['.$tmpName.']</a><br><br>';
                }else{
                    CAdminMessage::ShowMessage(array(
                        'TYPE'=>'ERROR',
                        'MESSAGE'=>implode('; ', $res->getErrorMessages())
                    ));
                }

            }
        }

    }

    public static function labels1Action($idAr){
	    return self::labelsAction($idAr);
    }

    public static function labels2Action($idAr){
        return self::labelsAction($idAr, 'many');
    }

    public static function invoiceAction($idAr){
        return self::labelsAction($idAr, 'invoice');
    }
	
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
	    "cansel"=>array("MlifeRowListAdmin", "canselAction"),
	    "labels1"=>array("MlifeRowListAdmin", "labels1Action"),
	    "labels2"=>array("MlifeRowListAdmin", "labels2Action"),
	    "invoice"=>array("MlifeRowListAdmin", "invoiceAction"),
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
    $adminCustom = new MlifeRowListAdmin($arParams);
    $adminCustom->defaultInterface();
}

