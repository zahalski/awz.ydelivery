<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
global $APPLICATION;
$module_id = "awz.ydelivery";

\Bitrix\Main\Loader::includeModule($module_id);
\Bitrix\Main\Loader::includeModule('sale');

use Awz\Ydelivery\Helper;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Security;

Loc::loadMessages(__FILE__);

$POST_RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($POST_RIGHT == "D")
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$APPLICATION->SetTitle(Loc::getMessage("AWZ_YDELIVERY_ADMIN_PL_TITLE"));
$APPLICATION->SetAdditionalCSS("/bitrix/css/".$module_id."/style.css");

\CUtil::InitJSCore(array('ajax', 'awz_yd_lib'));

$key = Option::get("fileman", "yandex_map_api_key");
Asset::getInstance()->addString('<script src="//api-maps.yandex.ru/2.1/?lang=ru_RU&apikey='.$key.'"></script>', true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");



$order = \Bitrix\Sale\Order::load(intval($_REQUEST['order']));

if($_REQUEST['page'] == 'order_edit'){
    $profileId = Helper::getProfileId($order, Helper::DOST_TYPE_PVZ);
}else{
    $profileId = intval($_REQUEST['profile_id']);
}

if($profileId){
    $api = Helper::getApiByProfileId($profileId);

    $props = $order->getPropertyCollection();
    $locationCode = $props->getDeliveryLocation()->getValue();
    if ($loc = \Bitrix\Sale\Location\LocationTable::getRowById($locationCode)) {
        $locationCode = $loc['CODE'];
    }
    $locationName = '';
    $locationGeoId = '';

    $res = \Bitrix\Sale\Location\LocationTable::getList(array(
        'filter' => array(
            '=CODE' => $locationCode,
            '=PARENTS.NAME.LANGUAGE_ID' => LANGUAGE_ID,
            '=PARENTS.TYPE.NAME.LANGUAGE_ID' => LANGUAGE_ID,
        ),
        'select' => array(
            'I_ID' => 'PARENTS.ID',
            'I_NAME_LANG' => 'PARENTS.NAME.NAME',
            'I_TYPE_CODE' => 'PARENTS.TYPE.CODE',
            'I_TYPE_NAME_LANG' => 'PARENTS.TYPE.NAME.NAME',
        ),
        'order' => array(
            'PARENTS.DEPTH_LEVEL' => 'asc'
        )
    ));
    while($item = $res->fetch())
    {
        if($locationName){
            $locationName .= ', '.$item['I_NAME_LANG'];
        }else{
            $locationName = $item['I_NAME_LANG'];
        }
    }
    if(!$locationName){
        CAdminMessage::ShowMessage(
            array(
                'TYPE'=>'ERROR',
                'MESSAGE'=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_PL_ERR_REGION')
            )
        );
    }else{
        $res = \Bitrix\Sale\Location\LocationTable::getList(array(
            'filter' => array(
                '=CODE' => $locationCode,
                '=EXTERNAL.SERVICE.CODE' => 'YAMARKET',
            ),
            'select' => array(
                'EXTERNAL.*',
                //'EXTERNAL.SERVICE.*'
            )
        ))->fetch();
        if(isset($res['SALE_LOCATION_LOCATION_EXTERNAL_XML_ID'])){
            $locationGeoId = $res['SALE_LOCATION_LOCATION_EXTERNAL_XML_ID'];
        }

        global $USER;
        $signer = new Security\Sign\Signer();

        $signedParameters = $signer->sign(base64_encode(serialize(array(
            'address'=>$locationName,
            'geo_id'=>$locationGeoId,
            'profile_id'=>$profileId,
            'user'=>$USER->getId(),
            'page'=>'admin',
            'order'=>$order->getId(),
            's_id'=>bitrix_sessid()
        ))));



    }


    ?>
    <script>
        $(document).ready(function(){
            window.awz_yd_modal.getPickpointsList('<?=$signedParameters?>');
        });
    </script>
    <div style="position:relative;">
        <div id="awz-yd-map" style="width:100%;height:400px;"></div>
        <div class="awz-yd-modal-filter-payment-wrap">
            <a href="#" class="awz-yd-modal-filter-payment" data-payment="cash_on_receipt">
                <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_PL_PAY1')?>
            </a>
            <a href="#" class="awz-yd-modal-filter-payment" data-payment="card_on_receipt">
                <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_PL_PAY2')?>
            </a>
            <a href="#" class="awz-yd-modal-filter-payment" data-payment="already_paid">
                <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_PL_PAY3')?>
            </a>
            <a href="#" class="awz-yd-modal-filter-type" data-type="pickup_point">
                <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_PL_PAY4')?>
            </a>
            <a href="#" class="awz-yd-modal-filter-type" data-type="terminal">
                <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_PL_PAY5')?>
            </a>
        </div>
    </div>
    <div>
        <form id="awz-ydelivery-send-id-form">
            <input type="hidden" name="sign" value="<?=$signedParameters?>" id="awz-ydelivery-send-id-sign">
            <br><br><?=Loc::getMessage("AWZ_YDELIVERY_ID_POSTAMATA")?><input type="text" name="AWZ_YD_POINT_ID" id="AWZ_YD_POINT_ID" value="">
            <?if($_REQUEST['page'] == 'order_edit'){?>
                <p><?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_PL_COPY')?></p>
            <?}else{?>
                <a class="awz-ydelivery-send-id adm-btn adm-btn-green adm-btn-add" href="#" onclick="window.awz_yd_modal.setPickpointToOrder();return false;">
                    <?=Loc::getMessage('AWZ_YDELIVERY_ADMIN_PL_ORDER_SEND')?> = <?=$order->getId()?></a>
            <?}?>

        </form>
    </div>
    <?php
}else{
    CAdminMessage::ShowMessage(
        array(
            'TYPE'=>'ERROR',
            'MESSAGE'=>Loc::getMessage('AWZ_YDELIVERY_ADMIN_PL_ERR_DOST_TYPE')
        )
    );
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");