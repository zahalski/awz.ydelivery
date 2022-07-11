<?php
namespace Awz\Ydelivery;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Sale\BasketItem;
use Bitrix\Sale\Delivery\Services\Base;
use Bitrix\Sale\Delivery\Services\Manager as DeliveryManager;
use Bitrix\Main\SystemException;
use Bitrix\Sale\Order;

Loc::loadMessages(__FILE__);

class Helper {

    const DOST_TYPE_ALL = 'all';
    const DOST_TYPE_PVZ = 'pvz';
    const DOST_TYPE_ADR = 'address';

    const YANDEX_PAYED = 'already_paid';

    public static $cacheConfig = array();
    public static $cacheActiveDelivery = array();

    public static $cacheBar = array();
    /**
     * Получение объекта доступа к api яндекс доставки
     *
     * @param $config array параметры обработчика службы доставки
     * @return Ydapi|null
     */
    public static function getApiFromConfig(array $config){
        $api = Ydapi::getInstance(array('token'=>$config['MAIN']['token']));

        $isTest = $config['MAIN']['TEST_MODE'] == 'Y';
        if($isTest){
            $api->setToken($config['MAIN']['TOKEN_TEST']);
        }else{
            $api->setToken($config['MAIN']['TOKEN']);
        }

        if($isTest){
            $api->setSandbox();
        }else{
            $api->setProdMode();
        }

        return $api;
    }


    /**
     * Получение объекта апи по объекту профиля доставки
     * @param Base $profile
     * @return Ydapi|null
     */
    public static function getApiFromProfile(Base $profile){
        $config = $profile->getConfigValues();
        return self::getApiFromConfig($config);
    }


    /**
     * Получение объекта апи по ид профиля доставки
     *
     * @param int $profileId
     * @return Ydapi|null
     * @throws SystemException
     * @throws LoaderException
     */
    public static function getApiByProfileId($profileId){
        $config = self::getConfigByProfileId($profileId);
        return self::getApiFromConfig($config);
    }


    /**
     * Получение конфигурации доставки по ид профиля
     *
     * @param int $profileId
     * @return array
     * @throws SystemException
     * @throws LoaderException
     */
    public static function getConfigByProfileId($profileId){
        if(isset(self::$cacheConfig['PROFILE_'.$profileId])){
            return self::$cacheConfig['PROFILE_'.$profileId];
        }
        $delivery = self::deliveryGetByProfileId($profileId);
        self::$cacheConfig['PROFILE_'.$profileId] = $delivery['CONFIG'];
        return self::$cacheConfig['PROFILE_'.$profileId];
    }


    /**
     * Получение параметров профиля доставки из базы
     *
     * @param int $profileId
     * @return array
     * @throws SystemException
     * @throws LoaderException
     */
    public static function deliveryGetByProfileId($profileId){
        if(!Loader::includeModule('sale')){
            throw new SystemException(
                Loc::getMessage('AWZ_YDELIVERY_HELPER_NOT_SALE_MODULE')
            );
        }
        return DeliveryManager::getById($profileId);
    }


    /**
     * получение кода свойства для записи ид ПВЗ с настроек модуля
     *
     * @param int $profileId
     * @return string
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public static function getPropPvzCode($profileId){
        return Option::get(Handler::MODULE_ID,
                           'PVZ_CODE_'.$profileId,
                           'AWZ_YD_POINT_ID', '');
    }


    /**
     * получение кода свойства для записи даты доставки
     *
     * @param int $profileId
     * @return string
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public static function getPropDateCode($profileId){
        return Option::get(Handler::MODULE_ID,
                           'DATE_CODE_'.$profileId,
                           '', '');
    }


    /**
     * получение кода свойства для записи адреса
     *
     * @param int $profileId
     * @return string
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public static function getPropAddress($profileId){
        return Option::get(Handler::MODULE_ID,
                           'PVZ_ADDRESS_'.$profileId,
                           '', '');
    }

    /**
     * код свойства для получения координат
     *
     * @param int $profileId
     * @return array
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public static function getPropAddressCord($profileId){
        $prop = Option::get(Handler::MODULE_ID,
                           'PVZ_ADDRESS_CORD_'.$profileId,
                           '', '');
        if(!$prop) return array();
        return explode(',', $prop);
    }


    /**
     * статус заказа для автоматического создания заявки
     *
     * @param int $profileId
     * @return string
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public static function getStatusAutoCreate($profileId){
        return Option::get(Handler::MODULE_ID,
                           'OFFERS_ORDER_STATUS_'.$profileId,
                           '', '');
    }


    /**
     * Генерация штрих кода для места в доставке
     *
     * @param Order $order
     * @return string
     */
    public static function generateBarCode(Order $order){

        if($order){
            $orderId = $order->getId();
        }

        $tmpl = Option::get(Handler::MODULE_ID, "BAR_CODE_TEMPLATE", "", "");

        $macros = array(
            '#DATE#'=>date('Ymd', strtotime($order->getField('DATE_INSERT'))),
            '#DATE_M#'=>date('m', strtotime($order->getField('DATE_INSERT'))),
            '#DATE_D#'=>date('d', strtotime($order->getField('DATE_INSERT'))),
            '#DATE_Y#'=>date('Y', strtotime($order->getField('DATE_INSERT'))),
            '#ORDER#'=>$orderId,
            '#SEC#'=>time() % 86400,
            '#RAND2#'=>\Bitrix\Main\Security\Random::getString(2),
            '#RAND3#'=>\Bitrix\Main\Security\Random::getString(3),
        );

        if(isset(self::$cacheBar[$orderId])){
            $barCode = self::$cacheBar[$orderId];
        }else{
            $barCode = str_replace(array_keys($macros),array_values($macros), $tmpl);
        }

        $event = new Event(
            Handler::MODULE_ID,
            "onBeforeReturnBarCode",
            array('order'=>$order, 'barCode'=>$barCode)
        );
        $event->send();
        if ($event->getResults()) {
            foreach ($event->getResults() as $evenResult) {
                if ($evenResult->getType() == EventResult::SUCCESS) {
                    $r = $evenResult->getParameters();
                    if($r['barCode']){
                        $barCode = (string) $r['barCode'];
                    }
                }
            }
        }

        self::$cacheBar[$orderId] = $barCode;

        return $barCode;
    }


    /**
     * Автоматический выбор оффера для отгрузки
     *
     * @param Order $order
     * @param array $offers
     */
    public static function autoOffer(Order $order, array $offers){

        $event = new Event(
            Handler::MODULE_ID,
            "setOffer",
            array('offers'=>$offers, 'order'=>$order)
        );
        $event->send();
        if ($event->getResults()) {
            foreach ($event->getResults() as $evenResult) {
                if ($evenResult->getType() == EventResult::SUCCESS) {
                    $r = $evenResult->getParameters();
                    if(isset($r['result'])){
                        $r = $r['result'];
                        if($r instanceof Result){
                            return $r;
                        }
                    }
                }
            }
        }

        $result = new Result();

        if(empty($offers['result']['offers'])){
            $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_HELPER_ERR_NO_OFFERS')));
            return $result;
        }

        $minTime = time()+86400*30;
        $findOffer = null;
        foreach($offers['result']['offers'] as $offer){
            if($minTime > strtotime($offer['offer_details']['delivery_interval']['min'])){
                $minTime = strtotime($offer['offer_details']['delivery_interval']['min']);
                $findOffer = $offer;
            }
        }

        if(!$findOffer){
            $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_HELPER_ERR_NO_OFFERS')));
            return $result;
        }

        $result->setData(array('offer_id'=>$findOffer['offer_id']));

        return $result;

    }


    /**
     * Получение списка товаров из заказа
     *
     * @param Order $order
     * @return array
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     * @throws LoaderException
     * @throws SystemException
     */
    public static function getItems(Order $order){

        $deliveryConfig = self::getConfigFromOrder($order);
        $arFinItems = array();
        $basket = $order->getBasket();
        /* @var BasketItem $basketItem */

        $disable_assessed_unit_price = true;

        foreach($basket as $basketItem){

            $artCode = $basketItem->getProductId();
            $barCode = $basketItem->getProductId();

            $weight = $basketItem->getWeight();
            if(!intval($weight)){
                $weight = $deliveryConfig['MAIN']['WEIGHT_DEFAULT'];
            }
            if (isset($deliveryConfig)) {
                $predefined = $deliveryConfig['MAIN']['PRED_DEFAULT'] ? $deliveryConfig['MAIN']['PRED_DEFAULT'] : 10;
            }

            $arFinItems[] = array(
                "count"          	=> intval($basketItem->getQuantity()),
                "name"           	=> $basketItem->getField('NAME'),
                "article"        	=> (string) $artCode,
                "barcode"        	=> (string) $barCode,
                "billing_details" 	=> array(
                    "unit_price"         => ($basketItem->getPrice() * 100),
                    "assessed_unit_price"=> (!$disable_assessed_unit_price ? ($basketItem->getPrice() * 100) : 0)
                ),
                "physical_dims"     => array(
                    "predefined_volume"=> intval($predefined),
                    "weight_gross"     => intval($weight)
                ),
                "place_barcode"  => self::generateBarCode($order)
            );

        }

        $event = new Event(
            Handler::MODULE_ID,
            "onBeforeReturnItems",
            array('order'=>$order, 'items'=>$arFinItems)
        );
        $event->send();
        if ($event->getResults()) {
            foreach ($event->getResults() as $evenResult) {
                if ($evenResult->getType() == EventResult::SUCCESS) {
                    $r = $evenResult->getParameters();
                    if(isset($r['items']) && is_array($r['items'])){
                        $arFinItems = $r['items'];
                    }
                }
            }
        }

        return $arFinItems;

    }


    /**
     * Получение конфига доставки по ид заказа
     *
     * @param $orderId
     * @return array|mixed
     * @throws ArgumentNullException
     */
    public static function getConfigFromOrderId($orderId){
        $order = Order::load($orderId);
        return self::getConfigFromOrder($order);
    }


    /**
     * Получение конфига доставки по объекту заказа
     *
     * @param Order $order
     * @return array|mixed
     * @throws SystemException
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws LoaderException
     */
    public static function getConfigFromOrder(Order $order){

        if(isset(self::$cacheConfig['ORDER_'.$order->getId()])){
            return self::$cacheConfig['ORDER_'.$order->getId()];
        }

        /* @var \Bitrix\Sale\ShipmentCollection $shipmentCollection */
        $shipmentCollection = $order->getShipmentCollection();

        $checkMyDelivery = self::getProfileId($order);

        if($checkMyDelivery){
            $delivery = self::deliveryGetByProfileId($checkMyDelivery);
            self::$cacheConfig['ORDER_'.$order->getId()] = $delivery['CONFIG'];
            self::$cacheConfig['PROFILE_'.$checkMyDelivery] = $delivery['CONFIG'];
        }else{
            return array();
        }

        return self::$cacheConfig['ORDER_'.$order->getId()];

    }


    /**
     * Получение ид профиля Яндекс Доставки из заказа
     *
     * @param Order $order
     * @param string $type all|pvz|address - тип профилей яндекс доставки
     * @return false|int
     * @throws SystemException
     * @throws ArgumentException
     * @throws ArgumentNullException
     */
    public static function getProfileId(Order $order, $type='all'){

        /* @var \Bitrix\Sale\ShipmentCollection $shipmentCollection */
        $shipmentCollection = $order->getShipmentCollection();
        $checkMyDelivery = false;
        /* @var \Bitrix\Sale\Shipment $shipment*/
        foreach ($shipmentCollection as $shipment) {
            if ($shipment->isSystem()) continue;
            /* @var $delivery \Bitrix\Sale\Delivery\Services\Base */
            $delivery = $shipment->getDelivery();
            if($delivery instanceof \Bitrix\Sale\Delivery\Services\Base && $delivery->isInstalled()){
                $classNames = Handler::getChildrenClassNames();
                if($type == self::DOST_TYPE_ALL || $type == self::DOST_TYPE_PVZ){
                    $className = '\\'.$classNames[0];
                    $params = \Bitrix\Sale\Delivery\Services\Manager::getById($delivery->getId());
					//bug php 7.3 main 20.200.300, sale 20.5.43
					$bug = false;
					if($params['CLASS_NAME'] == '\Bitrix\Sale\Delivery\Services\EmptyDeliveryService'){
						$params = DeliveryManager::getById($order->getField('DELIVERY_ID'));
						$bug = true;
					}
                    if($params['CLASS_NAME'] == $className){
						if($bug){
							$checkMyDelivery = $order->getField('DELIVERY_ID');
						}else{
							$checkMyDelivery = $delivery->getId();
						}
                    }
                }
                if($type == self::DOST_TYPE_ALL || $type == self::DOST_TYPE_ADR) {
                    $className = '\\' . $classNames[1];
                    $params = \Bitrix\Sale\Delivery\Services\Manager::getById($delivery->getId());
					//bug php 7.3 main 20.200.300, sale 20.5.43
					$bug = false;
					if($params['CLASS_NAME'] == '\Bitrix\Sale\Delivery\Services\EmptyDeliveryService'){
						$params = DeliveryManager::getById($order->getField('DELIVERY_ID'));
						$bug = true;
					}
                    if($params['CLASS_NAME'] == $className){
						if($bug){
							$checkMyDelivery = $order->getField('DELIVERY_ID');
						}else{
							$checkMyDelivery = $delivery->getId();
						}
                    }
                }
            }
        }
        return $checkMyDelivery;
    }


    /**
     * Транслитерация русского текста в латиницу
     *
     * @param $text
     * @return mixed
     */
    public static function translit($text){
        $paramsTranslit = array(
            "max_len" => "30",
            "change_case" => "L",
            "replace_space" => "_",
            "replace_other" => "_",
            "delete_repeat_replace" => "true",
            "use_google" => "false",
        );
        return \CUtil::translit($text, "ru", $paramsTranslit);
    }


    /**
     * Получение активных профилей доставки
     *
     * @return array array(array('ID'=>'NAME'))
     */
    public static function getActiveProfileIds($type='all'){

        if(!empty(self::$cacheActiveDelivery[$type])){
            return self::$cacheActiveDelivery[$type];
        }

        $deliveryProfileList = array();
        $classNames = Handler::getChildrenClassNames();
        foreach($classNames as &$cl){
            $cl = '\\'.$cl;
        }
        unset($cl);
        if($type == Helper::DOST_TYPE_PVZ){
            unset($classNames[1]);
        }else if($type == Helper::DOST_TYPE_ADR){
            unset($classNames[0]);
        }
        $r = \Bitrix\Sale\Delivery\Services\Table::getList(array(
            'select'=>array('*'),
            'filter'=>array('=CLASS_NAME'=>$classNames, '=ACTIVE'=>'Y')
        ));
        while($dt = $r->fetch()){
            $deliveryProfileList[$dt['ID']] = $dt['NAME'];
        }
        self::$cacheActiveDelivery[$type] = $deliveryProfileList;

        return $deliveryProfileList;
    }


    /**
     * Формирование html данных о пвз
     *
     * @param false $id ид пвз в службе доставки
     * @param false $hideBtn скрыть кнопку выбора
     * @param string $tempate шаблон вывода
     * @return Result
     */
    public static function getBaloonHtml($id=false, $hideBtn=false, $tempate='.default'){

        global $APPLICATION;

        $result = new Result();

        if(!$id){
            $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_HELPER_ERR_EMPTY_ID')));
            return $result;
        }

        $resPoint = PvzTable::getPvz($id);
        if(!$resPoint){
            $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_HELPER_ERR_EMPTY_DATA')));
            return $result;
        }

        $item = $resPoint['PRM'];

        ob_start();
        $APPLICATION->IncludeComponent("awz:ydelivery.baloon",	$tempate,
            Array(
                "DATA" => $item,
                "HIDE_BTN"=>$hideBtn ? 'Y' : 'N'
            ),
            null, array("HIDE_ICONS"=>"Y")
        );
        $html = ob_get_contents();
        ob_end_clean();

        $result->setData(array('html'=>$html));
        return $result;

    }


    /**
     * справочник оплат с названиями
     *
     * @return string[]
     */
    public static function getYandexPayMethods(){
        return array(
            'already_paid'=>Loc::getMessage('AWZ_YDELIVERY_HELPER_PAYNAMES_PREPAY'),
            'cash_on_receipt'=>Loc::getMessage('AWZ_YDELIVERY_HELPER_PAYNAMES_CASH'),
            'card_on_receipt'=>Loc::getMessage('AWZ_YDELIVERY_HELPER_PAYNAMES_CARD'),
            //'cashless'=>'cashless',
        );
    }


    /**
     * Возвращает массив доступных платежных систем с битрикса
     * настройки связей в модуле
     *
     * @param int $profileId идентификатор доставки
     * @return array
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public static function getYandexPayMethodParams($profileId){

        $methods = self::getYandexPayMethods();
        $methodsParams = array();
        foreach($methods as $methodId=>$methodName){
            $valP = Option::get(
                Handler::MODULE_ID,
                "PAY_LINK_".$profileId.'_'.mb_strtoupper($methodId),
                '','');
            $valP = unserialize($valP);
            if(!is_array($valP)) $valP = array();
            $methodsParams[$methodId] = $valP;
        }
        return $methodsParams;
    }


    /**
     * Возвращает код оплаты службы доставки
     *
     * @param Order $order
     * @param false $profileId идентификатор профиля доставки
     * @return array коды вариантов оплаты
     * @throws SystemException
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public static function getYandexPaymentIdFromOrder(Order $order, $profileId=false){

        if(!$profileId){
            $profileId = self::getProfileId($order);
        }

        $paymentCollection = $order->getPaymentCollection();
        $paymentId = 0;
        if($paymentCollection){
            foreach ($paymentCollection as $payment) {
                $paymentId = $payment->getPaymentSystemId();
            }
        }
        return self::checkYandexPaymentId($profileId, $paymentId);

    }

    /**
     * возвращает массив доступных кодов оплат службы доставки
     *
     * @param $profileId ид профиля доставки
     * @param $paymentId ид платежной системы
     * @return array
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public static function checkYandexPaymentId($profileId, $paymentId){
        $valMethodYandex = array();
        $methodsParams = self::getYandexPayMethodParams($profileId);
        foreach ($methodsParams as $keyMethod=>$paysIdAr){
            if(in_array($paymentId, $paysIdAr)){
                $valMethodYandex[] = $keyMethod;
            }
        }
        return $valMethodYandex;
    }


    /**
     * Доступные политики доставки
     *
     * @param string $type тип доставки
     * @return array|string[]
     */
    public static function getYandexLastMilePolicy($type){

        $values = array();
        if($type == self::DOST_TYPE_PVZ){
            $values = array(
                'self_pickup'=>Loc::getMessage('AWZ_YDELIVERY_HELPER_LASTMILE_SELF')
            );
        }elseif($type == self::DOST_TYPE_ADR){
            $values = array(
                'time_interval'=>Loc::getMessage('AWZ_YDELIVERY_HELPER_LASTMILE_TI'),
                'on_demand'=>Loc::getMessage('AWZ_YDELIVERY_HELPER_LASTMILE_OD'),
                'express'=>Loc::getMessage('AWZ_YDELIVERY_HELPER_LASTMILE_EX')
            );
        }
        return $values;

    }

    public static function formatPvzAddress($profileId, $pointData){

        if(empty($pointData)) return '';

        $template = Option::get(Handler::MODULE_ID, "PVZ_ADDRESS_TMPL_".$profileId, "#ADDRESS#", "");
        $pvzType = Loc::getMessage('AWZ_YDELIVERY_HELPER_TYPE_PVZ_'.strtoupper($pointData['PRM']['type']));
        if(!$pvzType) $pvzType = $pointData['PRM']['type'];
        $templateData = array(
            '#PHONE#'=>$pointData['PRM']['contact']['phone'],
            '#ADDRESS#'=>$pointData['PRM']['address']['full_address'],
            '#ID#'=>$pointData['PRM']['id'],
            '#NAME#'=>$pointData['PRM']['name'],
            '#TYPE#'=>$pvzType,
        );

        return str_replace(array_keys($templateData),array_values($templateData),$template);

    }

}