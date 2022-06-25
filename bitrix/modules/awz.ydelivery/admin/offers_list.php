<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
global $APPLICATION;
$module_id = "awz.ydelivery";

\Bitrix\Main\Loader::includeModule($module_id);
\Bitrix\Main\Loader::includeModule('sale');
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

$POST_RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($POST_RIGHT == "D")
	$APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$APPLICATION->SetTitle(Loc::getMessage("AWZ_YDELIVERY_ADMIN_OL_TITLE"));
$APPLICATION->SetAdditionalCSS("/bitrix/css/".$module_id."/style.css");
	
class MlifeRowListAdmin extends \Awz\Ydelivery\Main {
	
	public function __construct($params) {
		parent::__construct($params);
	}
	
	public function getMlifeRowListAdminCustomRow($row){

	    $status = Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_EMPTY_STATUS');

	    if(!empty($row->arRes['HISTORY']['hist'])){
	        $last = array_pop($row->arRes['HISTORY']['hist']);
            $status = $last['description'];
        }
        $row->AddViewField("HISTORY", $status);
        $row->AddViewField("ORD.STATUS.NAME", $row->arRes['AWZ_YDELIVERY_OFFERS_ORD_STATUS_NAME'],'FULL');
        $val = $row->arRes['HISTORY_FIN'] == 'Y' ? 'Y' : 'N';
        $row->AddViewField("HISTORY_FIN", Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_'.$val));
        $row->AddViewField("OFFER_ID", '<a href="/bitrix/admin/awz_ydelivery_offers_list_edit.php?lang='.LANGUAGE_ID.'&id='.$row->arRes['ID'].'">'.$row->arRes['OFFER_ID'].'</a>');

	}

	public function canselAction($idAr){
        foreach($idAr as $id){
            $result = \Awz\Ydelivery\OffersTable::canselOffer($id);
            if($result->isSuccess()){
                $data = $result->getData();
                CAdminMessage::ShowMessage(array('TYPE'=>'OK', 'MESSAGE'=>$data['result']['description']));
            }else{
                //array("MESSAGE"=>"", "TYPE"=>("ERROR"|"OK"|"PROGRESS"), "DETAILS"=>"", "HTML"=>true)
                CAdminMessage::ShowMessage(array('TYPE'=>'ERROR', 'MESSAGE'=>implode("; ",$result->getErrorMessages())));
            }
        }
    }
	
}

$arParams = array(
	"PRIMARY" => "ID",
	"LANG_CODE" => "AWZ_YDELIVERY_OFFERS_",
	"ENTITY" => "\\Awz\\Ydelivery\\OffersTable",
	"FILE_EDIT" => 'awz_ydelivery_offers_list_edit.php',
	"BUTTON_CONTECST" => array(),
	"ADD_GROUP_ACTION" => array(),
	"COLS" => array('ID','ORDER_ID','OFFER_ID','HISTORY_FIN','HISTORY','CREATE_DATE','LAST_DATE',
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
                    Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_Y'),
                    Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_N')
                ),
                'reference_id'=>array('Y','N')
            )
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
    )),
	"CALLBACK_ACTIONS" => array("cansel"=>array("MlifeRowListAdmin", "canselAction"))
);

$adminCustom = new MlifeRowListAdmin($arParams);
$adminCustom->defaultInterface();