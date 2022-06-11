<?php
namespace Awz\Ydelivery\Profiles;

use Awz\Ydelivery\Helper;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Security;

class Pickup extends \Bitrix\Sale\Delivery\Services\Base
{
    protected static $isProfile = true;
    protected static $parent = null;

    public function __construct(array $initParams)
    {
        parent::__construct($initParams);
        $this->parent = \Bitrix\Sale\Delivery\Services\Manager::getObjectById($this->parentId);
    }

    public static function getClassTitle()
    {
        return Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_NAME');
    }

    public static function getClassDescription()
    {
        return Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_DESC');
    }

    public function getParentService()
    {
        return $this->parent;
    }

    public function isCalculatePriceImmediately()
    {
        return $this->getParentService()->isCalculatePriceImmediately();
    }

    public static function isProfile()
    {
        return self::$isProfile;
    }

    public function isCompatible(\Bitrix\Sale\Shipment $shipment)
    {
        $calcResult = self::calculateConcrete($shipment);
        return $calcResult->isSuccess();
    }

    protected function getConfigStructure()
    {
        $result = array(
            "MAIN" => array(
                'TITLE' => 'Интеграция',
                'DESCRIPTION' => 'Основные настройки интеграции',
                'ITEMS' => array(
                    'TEST_MODE' => array(
                        'TYPE' => 'Y/N',
                        "NAME" => 'Тестовый режим',
                        "DEFAULT" => 'Y'
                    ),
                    'TOKEN' => array(
                        'TYPE' => 'STRING',
                        "NAME" => 'Ключ API',
                        "DEFAULT" => ''
                    ),
                    'TOKEN_TEST' => array(
                        'TYPE' => 'STRING',
                        "NAME" => 'Ключ API Тестовый',
                        "DEFAULT" => ''
                    ),
                    'STORE_ID' => array(
                        'TYPE' => 'STRING',
                        "NAME" => 'ИД склада отгрузки',
                        "DEFAULT" => ''
                    ),
                    'STORE_ID_TEST' => array(
                        'TYPE' => 'STRING',
                        "NAME" => 'ИД склада отгрузки тестовый',
                        "DEFAULT" => ''
                    ),
                    'STORE_ADRESS' => array(
                        'TYPE' => 'STRING',
                        "NAME" => 'Адрес отгрузки',
                        "DEFAULT" => ''
                    ),
                    'BTN_CLASS' => array(
                        'TYPE' => 'STRING',
                        "NAME" => 'Класс кнопки выбора ПВЗ',
                        "DEFAULT" => 'btn btn-primary'
                    ),
                    'ERROR_COST_DSBL' => array(
                        'TYPE' => 'Y/N',
                        "NAME" => 'Отключить доставку при ошибках расчета',
                        "DEFAULT" => 'Y'
                    ),
                    'ERROR_COST_DSBL_SROK' => array(
                        'TYPE' => 'Y/N',
                        "NAME" => 'Отключить доставку при отсутствии сроков доставки',
                        "DEFAULT" => 'Y'
                    ),
                    'ERROR_COST' => array(
                        'TYPE' => 'NUMBER',
                        "NAME" => 'Стоимость доставки при ошибке расчета',
                        "DEFAULT" => '500.00'
                    ),
                    'WEIGHT_DEFAULT' => array(
                        'TYPE' => 'NUMBER',
                        "NAME" => 'Вес посылки в граммах по умолчанию (если не найден вес товара)',
                        "DEFAULT" => '3000'
                    ),
                )
            )
        );
        return $result;
    }

    protected function calculateConcrete(\Bitrix\Sale\Shipment $shipment = null)
    {

        $config = $this->getConfigValues();
        $api = Helper::getYdApi($this);
        if($api->isTest()){
            $config['MAIN']['STORE_ID'] = $config['MAIN']['STORE_ID_TEST'];
        }

        $result = new \Bitrix\Sale\Delivery\CalculationResult();

        $weight = $shipment->getWeight();
        if(!$weight) $weight = $config['MAIN']['WEIGHT_DEFAULT'];

        $order = $shipment->getCollection()->getOrder();
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
            $result->addError(new \Bitrix\Main\Error('Не указан регион доставки'));
            return $result;
        }
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

        $data = array(
            //'client_price'=>1000,
            //'total_assessed_price'=>1000,
            'tariff'=>'self_pickup',
            'total_weight'=>(int)$weight,
            'source'=>array(),
            'destination'=>array(
                'address'=>$locationName
                //'geo_id'=>$locationGeoId
            )
        );

        if($config['MAIN']['STORE_ID']){
            $data['source'] = array('platform_station_id'=>$config['MAIN']['STORE_ID']);
        }elseif($config['MAIN']['STORE_ADRESS']){
            $data['source'] = array('address'=>$config['MAIN']['STORE_ADRESS']);
        }

        $r = $api->calc($data);

        if($r->isSuccess()){
            $calkData = $r->getData();
            if($calkData['result']['message']){
                $r->addError(new \Bitrix\Main\Error($calkData['result']['message']));
            }
        }

        if(!$r->isSuccess()){
            if($config['MAIN']['ERROR_COST_DSBL']=='Y'){
                $result->addErrors($r->getErrors());
                return $result;
            }else{
                $result->setDescription('<p style="color:red;">Произошла ошибка при расчете стоимости доставки</p>');

                global $USER;
                if($USER->isAdmin()){
                    $result->setDescription($result->getDescription()."<br>### ".implode("; ",$r->getErrorMessages())." ###");
                }

                $result->setDeliveryPrice($config['MAIN']['ERROR_COST']);
                $result->setPeriodDescription('-');
                return $result;
            }
        }

        $calkData = $r->getData();
        if($calkData['result']['pricing_total']){

            $r = $api->grafik(array('station_id'=>$config['MAIN']['STORE_ID'], 'full_address'=>$locationName));
            //print_r($r->getData());
            if($r->isSuccess()){
                $grafikData = $r->getData();
                if(isset($grafikData['result']['offers']) && !empty($grafikData['result']['offers'])){
                    //$result->addData();
                    $tmpData = $result->getTmpData();
                    $tmpData['offers'] = $grafikData['result']['offers'];
                    $result->setTmpData($tmpData);
                    foreach($grafikData['result']['offers'] as $offer){
                        $fromDay = ceil((strtotime($offer['from']) - time())/86400);
                        if($fromDay>0){
                            $result->setPeriodFrom($fromDay);
                            $result->setPeriodDescription($fromDay.' д.');
                        }
                        $toDay = ceil((strtotime($offer['from']) - time())/86400);
                        if($toDay>0 && $toDay!=$fromDay){
                            $result->setPeriodTo($toDay);
                            $result->setPeriodDescription($fromDay.'-'.$toDay.' д.');
                        }

                        break;
                        //$offer['from']
                        //$offer['to']
                    }
                }elseif($config['MAIN']['ERROR_COST_DSBL_SROK'] == 'Y'){
                    $result->addError(new \Bitrix\Main\Error('Нет графика доставки'));
                    return $result;
                }
                //echo'<pre>';print_r($grafikData);echo'</pre>';
                //die();
            }elseif($config['MAIN']['ERROR_COST_DSBL_SROK'] == 'Y'){
                $result->addError(new \Bitrix\Main\Error('Нет графика доставки'));
                return $result;
            }

            $result->setDeliveryPrice(
                roundEx(
                    $calkData['result']['pricing_total'],
                    SALE_VALUE_PRECISION
                )
            );
        }

        //print_r($r->getData());
        //die();

        //print_r($r);
        //die();

        $signer = new Security\Sign\Signer;

        $signedParameters = $signer->sign(base64_encode(serialize(array(
            'address'=>$locationName,
            'geo_id'=>$locationGeoId
        ))));

        $buttonHtml = '<a class="'.$config['MAIN']['BTN_CLASS'].'" href="#" onclick="window.awz_yd_modal.show(\'Выберите пункт самовывоза\',\''.$signedParameters.'\');return false;">Выбрать пункт выдачи</a>';
        $result->setDescription($result->getDescription().
            '<!--btn-ydost-start-->'.
            $buttonHtml
            .'<!--btn-ydost-end-->'
        );

        return $result;

        //$basket = $shipment->getOrder()->getBasket();
        //foreach($basket as $basketItem){
            //echo'<pre>';print_r($basketItem);echo'</pre>';
        //}

    }
}