<?php
namespace Awz\Ydelivery;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\UI\Extension;
use Bitrix\Sale\Order;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class handlersBx {

    public static function registerHandler(){

        $result = new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            array(
                'Awz\Ydelivery\Handler' => '/bitrix/modules/awz.ydelivery/lib/handler.php',
                'Awz\Ydelivery\Profiles\Pickup' => '/bitrix/modules/awz.ydelivery/lib/profiles/pickup.php',
                'Awz\Ydelivery\Profiles\Standart' => '/bitrix/modules/awz.ydelivery/lib/profiles/standart.php',
            )
        );

        return $result;

    }

    public static function OnAdminSaleOrderEditDraggable($args){
        $res = array(
            'getScripts'=>array('\Awz\Ydelivery\handlersBx','editDraggableAddScript')
        );
        return $res;
    }

    public static function editDraggableAddScript($args){
        if(isset($args['ORDER']) && $args['ORDER'] instanceof Order){

            $order = $args['ORDER'];
            $propertyCollection = $order->getPropertyCollection();
            /* @var \Bitrix\Sale\EntityPropertyValue $prop*/
            $profileId = Helper::getProfileId($order, Helper::DOST_TYPE_PVZ);
            $prop = $propertyCollection->getItemByOrderPropertyCode(Helper::getPropPvzCode($profileId));

            if(!$prop) return '';

            $content = 'BX.addCustomEvent("onAfterSaleOrderTailsLoaded", function(){';
            $content .= "BX.insertAfter(BX.create('a', {
                      attrs: {
                         className: 'adm-btn adm-btn-green adm-btn-add',
                         href: '/',
                         onclick: 'BX.SidePanel.Instance.open(\"/bitrix/admin/awz_ydelivery_picpoint_list.php?LANG=ru&page=order_edit&order=".$order->getId()."\",{cacheable: false});return false;'
                      },
                      text: '".Loc::getMessage('AWZ_YDELIVERY_HANDLERBX_CHOISE')."'
                   }), BX.findChild(BX('tab_order_edit_table'), {tag: 'input', attribute: {name: 'PROPERTIES[".$prop->getPropertyId()."]'}}, true));";

            $content .= '});';
            return '<script>'.$content.'</script>';
        }
    }

    public static function OnEndBufferContent(&$content){
        global $APPLICATION;
        if($APPLICATION->getCurPage(false) == '/bitrix/admin/sale_order_ajax.php'){
            if($_REQUEST['action'] == 'changeDeliveryService' && $_REQUEST['formData']['order_id']){
                if(!\Bitrix\Main\Loader::includeModule('awz.ydelivery')) return;

                $profileId = $_REQUEST['formData']['SHIPMENT'][1]['PROFILE'];
                if($profileId){
                    $delivery = \Awz\Ydelivery\Helper::deliveryGetByProfileId($profileId);
                    if($delivery['CLASS_NAME'] == '\Awz\Ydelivery\Profiles\Pickup'){
                        $json = \Bitrix\Main\Web\Json::decode($content);
                        $json['SHIPMENT_DATA']['PROFILES'] .= '<br><a href="#" class="adm-btn adm-btn-green adm-btn-add" onclick="BX.SidePanel.Instance.open(\'/bitrix/admin/awz_ydelivery_picpoint_list.php?LANG=ru&profile_id='.$profileId.'&order='.intval($_REQUEST['formData']['order_id']).'&from=changeDeliveryService\',{cacheable: false});return false;">'.Loc::getMessage('AWZ_YDELIVERY_HANDLERBX_CHOISE_PVZ').'</a>';
                        $content = \Bitrix\Main\Web\Json::encode($json);
                    }
                }


            }
        }
    }

    public static function OnSaleOrderBeforeSaved(\Bitrix\Main\Event $event){

        $request = Context::getCurrent()->getRequest();
        /* @var Order $order*/
        $order = $event->getParameter("ENTITY");
        $propertyCollection = $order->getPropertyCollection();

        $checkMyDeliveryPvz = Helper::getProfileId($order, Helper::DOST_TYPE_PVZ);

        if(!$checkMyDeliveryPvz) {
            $event->addResult(
                new \Bitrix\Main\EventResult(
                    \Bitrix\Main\EventResult::SUCCESS, $order
                )
            );
        }else{
            $errorText = '';
            $setPoints = false;
            if($request->get('AWZ_YD_POINT_ID')){
                $pointId = preg_replace('/([^0-9A-z\-])/is', '', $request->get('AWZ_YD_POINT_ID'));
            }

            /* @var \Bitrix\Sale\EntityPropertyValue $prop*/
            $checkIsProp = false;
            $propAddress = false;
            foreach($propertyCollection as $prop){
                if($prop->getField('CODE') == Helper::getPropPvzCode($checkMyDeliveryPvz)){
                    $checkIsProp = true;
                    if($pointId){
                        $prop->setValue($pointId);
                    }
                    if($prop->getValue()){
                        $setPoints = true;
                    }
                }elseif($prop->getField('CODE') == Helper::getPropAddress($checkMyDeliveryPvz)){
                    $propAddress = $prop;
                }
            }

            if($pointId){
                $pointData = PvzTable::getPvz($pointId);
                if($pointData){

                    if($propAddress){
                        $propAddress->setValue(Helper::formatPvzAddress($checkMyDeliveryPvz, $pointData));
                    }
                    $paymentYandexAr = Helper::getYandexPaymentIdFromOrder($order, $checkMyDeliveryPvz);
                    $checkRightPayment = false;
                    foreach($paymentYandexAr as $paymentYandex){
                        if(in_array($paymentYandex, $pointData['PRM']['payment_methods'])){
                            $checkRightPayment = true;
                            break;
                        }
                    }
                    if(!$checkRightPayment){
                        $errorText = Loc::getMessage('AWZ_YDELIVERY_HANDLERBX_ERR_PAY1');
                    }
                }else{
                    //$setPoints = false;
                    $errorText = Loc::getMessage('AWZ_YDELIVERY_HANDLERBX_ERR_PVZDATA');
                }
            }

            if(!$setPoints || $errorText){
                if(!$errorText) $errorText = Loc::getMessage('AWZ_YDELIVERY_HANDLERBX_ERR_PVZ');
                if(!$checkIsProp){
                    $errorText = Loc::getMessage('AWZ_YDELIVERY_HANDLERBX_ERR_PVZ_PROP');
                }
                $event->addResult(
                    new \Bitrix\Main\EventResult(
                        \Bitrix\Main\EventResult::ERROR,
                        \Bitrix\Sale\ResultError::create(
                            new \Bitrix\Main\Error($errorText, "DELIVERY")
                        )
                    )
                );
            }else{
                $event->addResult(
                    new \Bitrix\Main\EventResult(
                        \Bitrix\Main\EventResult::SUCCESS, $order
                    )
                );
            }
        }

        $results = $event->getResults();

        foreach($results as $result){
            if($result->getType() == \Bitrix\Main\EventResult::ERROR){
                return;
            }
        }

        $statusCheck = false;
        $checkMyDeliveryAdr = Helper::getProfileId($order, Helper::DOST_TYPE_ADR);
        if($checkMyDeliveryPvz){
            $statusCheck = Helper::getStatusAutoCreate($checkMyDeliveryPvz);
        }elseif($checkMyDeliveryAdr){
            $statusCheck = Helper::getStatusAutoCreate($checkMyDeliveryAdr);
        }

        //print_r($statusCheck);
        //die();

        if($statusCheck){
            $oldValues = $event->getParameter("VALUES");
            $arOrderVals = $order->getFields()->getValues();
            if(
                isset($oldValues['STATUS_ID']) &&
                ($oldValues['STATUS_ID'] != $arOrderVals['STATUS_ID']) &&
                ($statusCheck == $arOrderVals['STATUS_ID'])
            ){
                $result = OffersTable::addFromOrder($order);
                if(!$result->isSuccess()){
                    foreach($result->getErrors() as $err){
                        $event->addResult(
                            new \Bitrix\Main\EventResult(
                                \Bitrix\Main\EventResult::ERROR,
                                \Bitrix\Sale\ResultError::create(
                                    $err
                                )
                            )
                        );
                    }
                }
            }
        }

        //SaleOrderAjax::$ar

    }

    public static function OrderDeliveryBuildList(&$arResult, &$arUserResult, $arParams)
    {
        \CUtil::InitJSCore(array('ajax', 'awz_yd_lib'));

        $key = Option::get("fileman", "yandex_map_api_key");
        $setSearchAddress = Option::get(Handler::MODULE_ID, "MAP_ADDRESS", "N", "");
        Asset::getInstance()->addString('<script>window._awz_yd_lib_setSearchAddress = "'.$setSearchAddress.'";</script>', true);
        Asset::getInstance()->addString('<script src="//api-maps.yandex.ru/2.1/?lang=ru_RU&apikey='.$key.'"></script>', true);

    }

    public static function OnAdminContextMenuShow(&$items){
        $isPage = ($GLOBALS['APPLICATION']->GetCurPage()=='/bitrix/admin/sale_order_edit.php') ||
            ($GLOBALS['APPLICATION']->GetCurPage()=='/bitrix/admin/sale_order_view.php');
        $orderId = intval($_REQUEST['ID']);
        if($isPage && $orderId){
            $order = Order::load($orderId);
            if(!$order) return;
            $checkProfileId = Helper::getProfileId($order);
            if(!$checkProfileId) return;
            $resOrder = OffersTable::getList(array('select'=>array('ID'),'filter'=>array('ORDER_ID'=>$order->getId())))->fetch();
            if($resOrder){
                $items[] = array(
                    "TEXT"=>Loc::getMessage('AWZ_YDELIVERY_HANDLERBX_BTN_OPEN_OLD'),
                    "LINK"=>"javascript:BX.SidePanel.Instance.open(\"/bitrix/admin/awz_ydelivery_offers_list_edit.php?LANG=ru&page=order_edit&id=".$resOrder['ID']."\",{cacheable: true})",
                    "TITLE"=>Loc::getMessage('AWZ_YDELIVERY_HANDLERBX_BTN_OPEN_OLD'),
                    "ICON"=>"adm-btn",
                );
            }else{
                $items[] = array(
                    "TEXT"=>Loc::getMessage('AWZ_YDELIVERY_HANDLERBX_BTN_OPEN_NEW'),
                    "LINK"=>"javascript:BX.SidePanel.Instance.open(\"/bitrix/admin/awz_ydelivery_offers_list_edit.php?LANG=ru&page=order_edit&ORDER_ID=".$order->getId()."\",{cacheable: true})",
                    "TITLE"=>Loc::getMessage('AWZ_YDELIVERY_HANDLERBX_BTN_OPEN_NEW'),
                    "ICON"=>"adm-btn",
                );
            }
        }

    }

    public static function OnEpilog()
    {
        global $APPLICATION;
        $page = $APPLICATION->GetCurPage(false);
        if(strpos($page,'/shop/orders/details/')!==false){
            $orderId = preg_replace('/.*details\/([0-9]+)\/.*?/',"$1",$page);
            if($orderId){
                $order = Order::load($orderId);
                if(!$order) return;
                $checkProfileId = Helper::getProfileId($order);
                if(!$checkProfileId) return;

                Extension::load('ui.buttons');
                Extension::load('ui.buttons.icons');

                $resOrder = OffersTable::getList(array('select'=>array('ID'),'filter'=>array('ORDER_ID'=>$order->getId())))->fetch();
                if($resOrder){
                    $link = "javascript:BX.SidePanel.Instance.open(\"/bitrix/admin/awz_ydelivery_offers_list_edit.php?LANG=ru&page=order_edit&id=".$resOrder['ID']."\",{cacheable: true})";
                    $containerHTML = "<div class=\"pagetitle-container\" id=\"awz_ydelivery_btn_admin\"><a href='".$link."' class=\"ui-btn ui-btn-light-border ui-btn-icon-info\" style=\"margin-left:12px;\">".Loc::getMessage('AWZ_YDELIVERY_HANDLERBX_HANDLER_NAME')."</a></div>";
                }else{
                    $link = "javascript:BX.SidePanel.Instance.open(\"/bitrix/admin/awz_ydelivery_offers_list_edit.php?LANG=ru&page=order_edit&ORDER_ID=".$order->getId()."\",{cacheable: true})";
                    $containerHTML = "<div class=\"pagetitle-container\" id=\"awz_ydelivery_btn_admin\"><a href='".$link."' class=\"ui-btn ui-btn-light-border ui-btn-icon-add\" style=\"margin-left:12px;\">".Loc::getMessage('AWZ_YDELIVERY_HANDLERBX_HANDLER_NAME')."</a></div>";
                }
                $APPLICATION->AddViewContent('inside_pagetitle', $containerHTML, 21000);
            }

        }
    }

    /**
     * отключает платежки в случае выбора пвз
     *
     * @param $order
     * @param $arUserResult
     * @param $request
     * @param $arParams
     * @param $arResult
     * @param $arDeliveryServiceAll
     * @param $arPaySystemServiceAll
     */
    public static function OnSaleComponentOrderCreated($order, $arUserResult, $request, $arParams, $arResult, &$arDeliveryServiceAll, &$arPaySystemServiceAll){

        /* @var $order Order */

        $profilePvz = Helper::getProfileId($order, Helper::DOST_TYPE_PVZ);
        if($profilePvz){

            $hidePaySystems = Option::get(
                Handler::MODULE_ID,
                'HIDE_PAY_ON_'.$profilePvz,
                'N', ''
            );

            if($hidePaySystems != 'Y') return;

            $pointId = false;
            if($request->get('AWZ_YD_POINT_ID')){
                $pointId = preg_replace('/([^0-9A-z\-])/is', '', $request->get('AWZ_YD_POINT_ID'));
            }
            if($pointId){
                $pointData = PvzTable::getPvz($pointId);

                $paymentCollection = $order->getPaymentCollection();
                $paymentId = 0;
                $paymentOb = null;
                if($paymentCollection){
                    foreach ($paymentCollection as $payment) {
                        $paymentOb = $payment;
                        $paymentId = $payment->getPaymentSystemId();
                    }
                }

                foreach($arPaySystemServiceAll as $key=>$payment){
                    $ydPayCodesActive = Helper::checkYandexPaymentId($profilePvz, $payment['ID']);

                    $checkRightPayment = false;
                    foreach($ydPayCodesActive as $paymentYandex){
                        if(in_array($paymentYandex, $pointData['PRM']['payment_methods'])){
                            $checkRightPayment = true;
                            break;
                        }
                    }

                    if(!$checkRightPayment){
                        if($payment['ID'] == $paymentId) $paymentId = 0;
                        unset($arPaySystemServiceAll[$key]);
                    }

                }

                if(!$paymentId) {
                    foreach($arPaySystemServiceAll as $key=>$payment){
                        if($paymentOb){
                            $paySystemService = \Bitrix\Sale\PaySystem\Manager::getObjectById($payment['ID']);
                            $paymentOb->setFields(array(
                                'PAY_SYSTEM_ID' => $paySystemService->getField("PAY_SYSTEM_ID"),
                                'PAY_SYSTEM_NAME' => $paySystemService->getField("NAME"),
                            ));
                            break;
                        }
                    }
                }

            }
        }
    }

}