<?php

namespace Awz\Ydelivery;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Entity;
use Bitrix\Main\NotImplementedException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\Result;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\EntityPropertyValue;
use Bitrix\Sale\Location\LocationTable;
use Bitrix\Sale\Order;

Loc::loadMessages(__FILE__);

class OffersTable extends Entity\DataManager
{
    public static function getFilePath()
    {
        return __FILE__;
    }

    public static function getTableName()
    {
        return 'b_awz_ydelivery_offer';
        /*
        CREATE TABLE IF NOT EXISTS `b_awz_ydelivery_offer` (
        `ID` int(18) NOT NULL AUTO_INCREMENT,
        `ORDER_ID` int(18) NOT NULL,
        `OFFER_ID` varchar(255) NOT NULL,
        `HISTORY` varchar(6255) DEFAULT NULL,
        `HISTORY_FIN` varchar(1) DEFAULT NULL,
        `CREATE_DATE` datetime NOT NULL,
        `LAST_DATE` datetime NOT NULL,
        PRIMARY KEY (`ID`)
        ) AUTO_INCREMENT=1;
        */
    }

    public static function getMap()
    {
        return array(
            new Entity\IntegerField('ID', array(
                    'primary' => true,
                    'autocomplete' => false,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_OFFERS_FIELDS_ID')
                )
            ),
            new Entity\IntegerField('ORDER_ID', array(
                    'required' => true,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_OFFERS_FIELDS_ORDER_ID')
                )
            ),
            new Entity\StringField('OFFER_ID', array(
                    'required' => true,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_OFFERS_FIELDS_OFFER_ID')
                )
            ),
            new Entity\StringField('HISTORY_FIN', array(
                    'required' => false,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_OFFERS_FIELDS_HISTORY_FIN')
                )
            ),
            new Entity\StringField('HISTORY', array(
                    'required' => false,
                    'serialized'=>true,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_OFFERS_FIELDS_HISTORY')
                )
            ),
            new Entity\DatetimeField('CREATE_DATE', array(
                    'required' => true,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_OFFERS_FIELDS_CREATE_DATE')
                )
            ),
            new Entity\DatetimeField('LAST_DATE', array(
                    'required' => true,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_OFFERS_FIELDS_LAST_DATE')
                )
            ),
            new Entity\StringField('LAST_STATUS', array(
                    'required' => false,
                    'title'=>Loc::getMessage('AWZ_YDELIVERY_OFFERS_FIELDS_LAST_STATUS')
                )
            ),
            new Entity\ReferenceField('ORD', '\Bitrix\Sale\Internals\OrderTable',
                array('=this.ORDER_ID' => 'ref.ID')
            ),
        );
    }

    /**
     * @param $orderId int Идентификатор заказа
     * @return Result
     * @throws ArgumentNullException
     */
    public static function addFromOrderId($orderId){
        $order = Order::load($orderId);
        return self::addFromOrder($order);
    }

    /**
     * Отмена заказа на логистической платформе
     *
     * @param string $offerId Идентификатор заявки
     * @return Result
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws LoaderException
     * @throws SystemException
     */
    public static function canselOffer($offerId){

        $result = new Result();

        $data = self::getRowById($offerId);

        $config = Helper::getConfigFromOrder(Order::load($data['ORDER_ID']));
        $api = Helper::getApiFromConfig($config);

        $rYd = $api->offerCansel($data['OFFER_ID']);

        if($rYd->isSuccess()){
            $dt = $rYd->getData();
            if($dt['result']['status'] == 'ERROR'){
                $result->addError(new Error($dt['result']['description']));
            }else{
                $result->setData($rYd->getData());
            }
        }else{
            $result->addErrors($rYd->getErrors());
        }

        return $result;

    }

    /**
     * Получение данных по умолчанию из заказа для отправки в логистическую платформу
     *
     * @param Order $order
     * @param bool $noErrors Не возвращать ошибки
     * @return Result
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     * @throws LoaderException
     * @throws NotImplementedException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function getPrepare(Order $order, $noErrors=false){

        $result = new Result();

        $propertyCollection = $order->getPropertyCollection();

        $checkMyDeliveryPvz = Helper::getProfileId($order, Helper::DOST_TYPE_PVZ);
        $checkMyDeliveryAddress = Helper::getProfileId($order, Helper::DOST_TYPE_ADR);

        if($checkMyDeliveryPvz){
            $config = Helper::getConfigFromOrder($order);

            $prepareData = array(
                'billing_info'=>array(
                    'payment_method'=>'already_paid',
                    'delivery_cost'=>round($order->getDeliveryPrice(), 2)*100
                ),
                'destination'=>array(
                    'type'=>'platform_station',
                    'platform_station'=>array(
                        'platform_id'=>''
                    )
                ),
                'info'=>array(
                    'operator_request_id'=>(string)$order->getId()
                ),
                'recipient_info'=>array(
                    'phone'=>$propertyCollection->getPhone()->getValue(),
                    'first_name'=>$propertyCollection->getPayerName()->getValue(),
                    'email'=>$propertyCollection->getUserEmail()->getValue()
                ),
                'source'=>array(
                    'platform_station'=>array(
                        'platform_id'=>($config['MAIN']['TEST_MODE'] == 'Y' ? $config['MAIN']['STORE_ID_TEST'] : $config['MAIN']['STORE_ID'])
                    )
                ),
                'places'=>array(
                    array(
                        'barcode'=>Helper::generateBarCode($order),
                        'physical_dims'=>array(
                            'weight_gross'=>0
                        )
                    )
                )
            );
            $prepareData['last_mile_policy'] = 'self_pickup';
            $prepareData['bx_external'] = array();

            /* @var EntityPropertyValue $prop*/
            foreach($propertyCollection as $prop){
                if($prop->getField('CODE') == Helper::getPropPvzCode($checkMyDeliveryPvz)){
                    $platformId = $prop->getValue();
                    if(Option::get(Handler::MODULE_ID, "SEARCH_EXT", "N","") == 'Y'){
                        $extRes = PvzExtTable::getList(array(
                            'select'=>array('PVZ_ID'),
                            'filter'=>array('=EXT_ID'=>$platformId),
                            'limit'=>1
                        ))->fetch();
                        if($extRes) $platformId = $extRes['PVZ_ID'];
                    }
                    $prepareData['destination']['platform_station']['platform_id'] = $platformId;
                }
            }

            /* @var EntityPropertyValue $prop*/
            foreach($propertyCollection as $prop){
                if($prop->getField('CODE') == Helper::getPropDateCode($checkMyDeliveryPvz)){
                    if($prop->getValue()){
                        $addTime = 0;
                        if($config['MAIN']['ADD_HOUR']){
                            $addTime = intval($config['MAIN']['ADD_HOUR']) * 60 * 60;
                        }
                        $prepareData['destination']['interval'] = array(
                            'from'=>strtotime($prop->getValue())+$addTime,
                            'to'=>strtotime($prop->getValue())+$addTime,
                        );
                        $prepareData['bx_external']['delivery_date'] = $prop->getValue();
                    }
                }
            }

            if(!$noErrors && empty($prepareData['destination']['platform_station']['platform_id'])){
                $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_OFFERS_ERR_NO_PVZ')));
                return $result;
            }

            if(!$noErrors && empty($prepareData['source']['platform_station']['platform_id'])){
                $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_OFFERS_ERR_NO_POINT')));
                return $result;
            }

            $val_userComment = '';
            $userComment = $order->getField('USER_DESCRIPTION');
            if($userComment){
                $val_userComment .= '; '.$userComment;
            }
            $prepareData['info']['comment'] = $val_userComment;

            $prepareData['items'] = Helper::getItems($order);

            $allWeight = 0;
            $allPredefined = 0;
            foreach($prepareData['items'] as $item){
                $allWeight += $item['count']*$item['physical_dims']['weight_gross'];
                $allPredefined += $item['count']*$item['physical_dims']['predefined_volume'];
            }
            //$allPredefined = $allPredefined * 1.5;
            $prepareData['places'][0]['physical_dims']['weight_gross'] = intval($allWeight);
            $prepareData['places'][0]['physical_dims']['predefined_volume'] = intval($allPredefined);

            $locationCodeProp = $propertyCollection->getDeliveryLocation();
			if($locationCodeProp){
				$locationCode = $locationCodeProp->getValue();
			}else{
				$locationCode = '';
			}
			if($locationCode && (strlen($locationCode) == strlen(intval($locationCode)))){
				if ($loc = LocationTable::getRowById($locationCode)) {
					$locationCode = $loc['CODE'];
				}
			}
            
            $locationName = '';
			if($locationCode){
				$res = LocationTable::getList(array(
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
			}
            $prepareData['bx_external']['location_name'] = $locationName;
            $prepareData['bx_external']['location_code'] = $locationCode;

            //получение платежного кода яндекса из заказа
            $valMethodYandex = '';
            $valMethodYandexAr = Helper::getYandexPaymentIdFromOrder($order, $checkMyDeliveryPvz);
            if(!empty($valMethodYandexAr)){
                $valMethodYandex = $valMethodYandexAr[0];
            }
            if(!$valMethodYandex) $valMethodYandex = Helper::YANDEX_PAYED;
            if($valMethodYandex){
                $prepareData['billing_info']['payment_method'] = $valMethodYandex;
            }
            if(!$noErrors && ($valMethodYandex == Helper::YANDEX_PAYED)){
                if(!$order->isPaid()){
                    $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_OFFERS_ERR_NO_PAY')));
                    return $result;
                }
            }

            $result->setData($prepareData);

        }elseif($checkMyDeliveryAddress){

            $config = Helper::getConfigFromOrder($order);

            $prepareData = array(
                'billing_info'=>array(
                    'payment_method'=>'already_paid',
                    'delivery_cost'=>round($order->getDeliveryPrice(), 2)*100
                ),
                'destination'=>array(
                    'type'=>'custom_location',
                    'custom_location'=>array(
                        'details'=>array(
                            'full_address'=>''
                        )
                    )
                ),
                'info'=>array(
                    'operator_request_id'=>(string)$order->getId()
                ),
                'recipient_info'=>array(
                    'phone'=>$propertyCollection->getPhone()->getValue(),
                    'first_name'=>$propertyCollection->getPayerName()->getValue(),
                    'email'=>$propertyCollection->getUserEmail()->getValue()
                ),
                'source'=>array(
                    'platform_station'=>array(
                        'platform_id'=>($config['MAIN']['TEST_MODE'] == 'Y' ? $config['MAIN']['STORE_ID_TEST'] : $config['MAIN']['STORE_ID'])
                    )
                ),
                'places'=>array(
                    array(
                        'barcode'=>Helper::generateBarCode($order),
                        'physical_dims'=>array(
                            'weight_gross'=>0
                        )
                    )
                )
            );
            $prepareData['bx_external'] = array();
            $prepareData['last_mile_policy'] = 'time_interval';

            /* @var EntityPropertyValue $prop*/
            $adressProp = $propertyCollection->getAddress();
			if($adressProp)
				$prepareData['destination']['custom_location']['details']['full_address'] = $adressProp->getValue();

			$addressCord = Helper::getPropAddressCord($checkMyDeliveryAddress);
            $addressCordValue = array('','');


            /* @var EntityPropertyValue $prop*/
            foreach($propertyCollection as $prop){
                if($prop->getField('CODE') == Helper::getPropDateCode($checkMyDeliveryAddress)){
                    if($prop->getValue()){
                        $addTime = 0;
                        if($config['MAIN']['ADD_HOUR']){
                            $addTime = intval($config['MAIN']['ADD_HOUR']) * 60 * 60;
                        }
                        $prepareData['destination']['interval'] = array(
                            'from'=>strtotime($prop->getValue())+$addTime,
                            'to'=>strtotime($prop->getValue())+86400+$addTime,
                        );
                        $prepareData['bx_external']['delivery_date'] = $prop->getValue();
                    }
                }
                if(!empty($addressCord)){
                    if($prop->getField('CODE') == $addressCord[0]){
                        $addressCordValue[0] = $prop->getValue();
                    }
                    if(isset($addressCord[1]) && $prop->getField('CODE') == $addressCord[1]){
                        $addressCordValue[1] = $prop->getValue();
                    }
                }
            }
            if($addressCordValue[0]){
                if(mb_strpos($addressCordValue[0], ',')!==false){
                    $addressCordValue = explode(',', $addressCordValue[0]);
                }
            }
            if(!$addressCordValue[1]){
                $addressCordValue = array();
            }

            if(!empty($addressCordValue)){
                $prepareData['destination']['custom_location']['latitude'] = doubleval($addressCordValue[0]);
                $prepareData['destination']['custom_location']['longitude'] = doubleval($addressCordValue[1]);
            }

            if(!$noErrors &&
                empty($prepareData['destination']['custom_location']['details']['full_address']) &&
                !isset($prepareData['destination']['custom_location']['latitude'])
            )
            {
                $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_OFFERS_ERR_NO_ADDRESS')));
                return $result;
            }

			$locationCodeProp = $propertyCollection->getDeliveryLocation();
			if($locationCodeProp){
				$locationCode = $locationCodeProp->getValue();
			}else{
				$locationCode = '';
			}
            if($locationCode && (strlen($locationCode) == strlen(intval($locationCode)))){
				if ($loc = LocationTable::getRowById($locationCode)) {
					$locationCode = $loc['CODE'];
				}
			}
            $locationName = '';
			if($locationCode){
				$res = LocationTable::getList(array(
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
			}
            $prepareData['bx_external']['location_name'] = $locationName;
            $prepareData['bx_external']['location_code'] = $locationCode;
            if(!$noErrors && !$locationName){
                $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_OFFERS_ERR_NO_REGION')));
                return $result;
            }

            if(!$noErrors && empty($prepareData['source']['platform_station']['platform_id'])){
                $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_OFFERS_ERR_NO_POINT')));
                return $result;
            }

            $prepareData['destination']['custom_location']['details']['full_address'] =
                $locationName . ', '.$prepareData['destination']['custom_location']['details']['full_address'];


            $addressProp = $propertyCollection->getAddress();
            $val_userComment = '';
            if($addressProp){
                $val_userComment = $addressProp->getValue();
                $userComment = $order->getField('USER_DESCRIPTION');
                if($userComment){
                    $val_userComment .= '; '.$userComment;
                }
            }
            $prepareData['info']['comment'] = $val_userComment;

            $prepareData['items'] = Helper::getItems($order);

            $allWeight = 0;
            $allPredefined = 0;
            foreach($prepareData['items'] as $item){
                $allWeight += $item['count']*$item['physical_dims']['weight_gross'];
                $allPredefined += $item['count']*$item['physical_dims']['predefined_volume'];
            }
            //$allPredefined = $allPredefined * 1.5;
            $prepareData['places'][0]['physical_dims']['weight_gross'] = intval($allWeight);
            $prepareData['places'][0]['physical_dims']['predefined_volume'] = intval($allPredefined);

            //получение платежного кода яндекса из заказа
            $valMethodYandex = '';
            $valMethodYandexAr = Helper::getYandexPaymentIdFromOrder($order, $checkMyDeliveryAddress);
            if(!empty($valMethodYandexAr)){
                $valMethodYandex = $valMethodYandexAr[0];
            }
            if(!$valMethodYandex) $valMethodYandex = Helper::YANDEX_PAYED;
            if($valMethodYandex){
                $prepareData['billing_info']['payment_method'] = $valMethodYandex;
            }

            if(!$noErrors && ($valMethodYandex == Helper::YANDEX_PAYED)){
                if(!$order->isPaid()){
                    $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_OFFERS_ERR_NO_PAY')));
                    return $result;
                }
            }


            $result->setData($prepareData);

        }else{
            $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_OFFERS_ERR_NO_YANDEX')));

        }

        $event = new Event(
            Handler::MODULE_ID,
            "changePrepareData",
            array('result'=>$result, 'order'=>$order)
        );
        $event->send();
        if ($event->getResults()) {
            foreach ($event->getResults() as $evenResult) {
                if ($evenResult->getType() == EventResult::SUCCESS) {
                    $r = $evenResult->getParameters();
                    if(isset($r['result'])){
                        $r = $r['result'];
                        if($r instanceof Result){
                            $result = $r;
                        }
                    }
                }
            }
        }

        return $result;

    }

    public static function getOffersFromData(Result $prepareResult, Ydapi $api){

        $result = new Result();
        if(!$prepareResult->isSuccess()){
            return $prepareResult;
        }

        $prepareData = $prepareResult->getData();

        if(!empty($prepareData)) {

            if(isset($prepareData['bx_external']))
                unset($prepareData['bx_external']);

            if(isset($prepareData['destination']['custom_location']['latitude'])){
                unset($prepareData['destination']['custom_location']['details']);
            }

            $offersRes = $api->getOffers($prepareData);
            return $offersRes;

        }else{
            $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_OFFERS_ERR_NO_PREP_DATA')));
            return $result;
        }

    }

    /**
     * Автоматическая отправка заявки в логистическую платформу
     *
     * @param Order $order
     * @return Result
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     * @throws LoaderException
     * @throws NotImplementedException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function addFromOrder(Order $order){

        $result = new Result();

        $prepareResult = self::getPrepare($order);
        if(!$prepareResult->isSuccess()){
            return $prepareResult;
        }

        $prepareData = $prepareResult->getData();
        if(isset($prepareData['bx_external']))
            unset($prepareData['bx_external']);

        if(isset($prepareData['destination']['custom_location']['latitude'])){
            unset($prepareData['destination']['custom_location']['details']);
        }

        $checkMyDeliveryPvz = Helper::getProfileId($order, Helper::DOST_TYPE_PVZ);
        $checkMyDeliveryAdress = Helper::getProfileId($order, Helper::DOST_TYPE_ADR);
        if($checkMyDeliveryPvz) {
            $api = Helper::getApiByProfileId($checkMyDeliveryPvz);
        }else if($checkMyDeliveryAdress){
            $api = Helper::getApiByProfileId($checkMyDeliveryAdress);
        }else{
            $result->addError(new Error(Loc::getMessage('AWZ_YDELIVERY_OFFERS_ERR_API_NOT_FOUND')));
            return $result;
        }

        if(!empty($prepareData)){

            $offersRes = $api->getOffers($prepareData);

            if(!$offersRes->isSuccess()){
                $result->addErrors($offersRes->getErrors());
                return $result;
            }else{
                $offers = $offersRes->getData();
                $autoFind = Helper::autoOffer($order, $offers);

                if($autoFind->isSuccess()){

                    $currentData = $autoFind->getData();
                    $offerId = $currentData['offer_id'];

                    $bronResult = $api->offerConfirm($offerId);
                    if(!$bronResult->isSuccess()){
                        $result->addErrors($bronResult->getErrors());
                        return $result;
                    }else{

                        $bronData = $bronResult->getData();

                        $resultAdd = self::add(
                            array(
                                'ORDER_ID'=>$order->getId(),
                                'OFFER_ID'=>$bronData['result']['request_id'],
                                'CREATE_DATE'=> DateTime::createFromTimestamp(time()),
                                'LAST_DATE'=> DateTime::createFromTimestamp(time()),
                                'HISTORY_FIN'=>'N'
                            )
                        );

                        if(!$resultAdd->isSuccess()){
                            $result->addErrors($resultAdd->getErrors());
                            return $result;
                        }else{
                            $result->setData(array('offer_id'=>$resultAdd->getId()));
                            return $result;
                        }

                    }

                }else{
                    $result->addErrors($autoFind->getErrors());
                    return $result;
                }

            }

        }
        return $result;
    }
}