<?php

namespace Awz\Ydelivery\Lists;

use Awz\Ydelivery\Main;
use Bitrix\Main\Application;
use Bitrix\Main\IO\File;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Awz\Ydelivery\OffersTable;
use Awz\Ydelivery\Helper;
use Bitrix\Main\Security\Random;
use Bitrix\Sale\Order;

Loc::loadMessages(__FILE__);

class Edit extends Main {

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
        $valMsg = $row->arRes['HISTORY_FIN'] == 'Y' ? Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_Y') : Loc::getMessage('AWZ_YDELIVERY_ADMIN_OL_N');
        $row->AddViewField("HISTORY_FIN", $valMsg);
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
        if(Loader::includeModule('sale')){
            foreach($idAr as $id){
                $data = OffersTable::getRowById($id);
                $order = Order::load($data['ORDER_ID']);
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
                    $tmpName = 'labels-'.time().'-'. Random::getString(20).'.pdf';
                    $fileOb = new File(Application::getDocumentRoot() . "/upload/tmp/".$tmpName);
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