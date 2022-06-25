<?php
namespace Awz\Ydelivery\Profiles;

use Awz\Ydelivery\Handler;
use Awz\Ydelivery\Helper;
use Bitrix\Main\Context;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Security;

Loc::loadMessages(__FILE__);

class Pickup extends \Bitrix\Sale\Delivery\Services\Base
{
    protected static $isProfile = true;
    protected static $parent = null;

    public function __construct(array $initParams)
    {
        if(empty($initParams["PARENT_ID"]))
            throw new \Bitrix\Main\ArgumentNullException('initParams[PARENT_ID]');
        parent::__construct($initParams);
        $this->parent = \Bitrix\Sale\Delivery\Services\Manager::getObjectById($this->parentId);
        if(!($this->parent instanceof Handler))
            throw new ArgumentNullException('parent is not instance of \Awz\Ydelivery\Handler');
        if(isset($initParams['PROFILE_ID']) && intval($initParams['PROFILE_ID']) > 0)
            $this->serviceType = intval($initParams['PROFILE_ID']);
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
                'TITLE' => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_INTG'),
                'DESCRIPTION' => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_INTG_DESC'),
                'ITEMS' => array(
                    'TEST_MODE' => array(
                        'TYPE' => 'Y/N',
                        "NAME" => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_TEST_MODE'),
                        "DEFAULT" => 'Y'
                    ),
                    'TOKEN' => array(
                        'TYPE' => 'STRING',
                        "NAME" => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_TOKEN'),
                        "DEFAULT" => ''
                    ),
                    'TOKEN_TEST' => array(
                        'TYPE' => 'STRING',
                        "NAME" => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_TOKEN_TEST'),
                        "DEFAULT" => ''
                    ),
                    'STORE_ID' => array(
                        'TYPE' => 'STRING',
                        "NAME" => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_SOURCE'),
                        "DEFAULT" => ''
                    ),
                    'STORE_ID_TEST' => array(
                        'TYPE' => 'STRING',
                        "NAME" => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_SOURCE_TEST'),
                        "DEFAULT" => ''
                    ),
                    'BTN_CLASS' => array(
                        'TYPE' => 'STRING',
                        "NAME" => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_BTN_CLASS'),
                        "DEFAULT" => 'btn btn-primary'
                    ),
                    'ERROR_COST_DSBL' => array(
                        'TYPE' => 'Y/N',
                        "NAME" => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_DSBL1'),
                        "DEFAULT" => 'Y'
                    ),
                    'ERROR_COST_DSBL_SROK' => array(
                        'TYPE' => 'Y/N',
                        "NAME" => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_DSBL2'),
                        "DEFAULT" => 'Y'
                    ),
                    'ERROR_COST' => array(
                        'TYPE' => 'NUMBER',
                        "NAME" => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_COST_DEF'),
                        "DEFAULT" => '500.00'
                    ),
                    'WEIGHT_DEFAULT' => array(
                        'TYPE' => 'NUMBER',
                        "NAME" => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_WEIGHT_DEF'),
                        "DEFAULT" => '3000'
                    ),
                    'PRED_DEFAULT' => array(
                        'TYPE' => 'NUMBER',
                        "NAME" => Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_SETT_DEM_DEF'),
                        "DEFAULT" => '10'
                    ),
                )
            )
        );
        return $result;
    }

    protected function calculateConcrete(\Bitrix\Sale\Shipment $shipment = null)
    {

        $config = $this->getConfigValues();
        $api = Helper::getApiFromProfile($this);
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
            $result->addError(new \Bitrix\Main\Error(Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_ERR_REGION')));
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

        $api->setCacheParams(md5(serialize(array($data, $config, 'calc'))), 86400);
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
                $result->setDescription('<p style="color:red;">'.Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_ERR_COST').'</p>');

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

            $api->setCacheParams(md5(serialize(array($data, $config, 'grafik'))), 3600);
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
                            $result->setPeriodDescription($fromDay.' '.GetMessage("AWZ_YDELIVERY_D"));
                        }
                        $toDay = ceil((strtotime($offer['from']) - time())/86400);
                        if($toDay>0 && $toDay!=$fromDay){
                            $result->setPeriodTo($toDay);
                            $result->setPeriodDescription($fromDay.'-'.$toDay.' '.GetMessage("AWZ_YDELIVERY_D"));
                        }

                        break;
                    }
                }elseif($config['MAIN']['ERROR_COST_DSBL_SROK'] == 'Y'){
                    $result->addError(
                        new \Bitrix\Main\Error(
                            Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_ERR_NOGRAF')
                        )
                    );
                    return $result;
                }
            }elseif($config['MAIN']['ERROR_COST_DSBL_SROK'] == 'Y'){
                $result->addError(
                    new \Bitrix\Main\Error(
                        Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_ERR_NOGRAF')
                    )
                );
                return $result;
            }

            $result->setDeliveryPrice(
                roundEx(
                    $calkData['result']['pricing_total'],
                    SALE_VALUE_PRECISION
                )
            );
        }

        $signer = new Security\Sign\Signer();

        $signedParameters = $signer->sign(base64_encode(serialize(array(
            'address'=>$locationName,
            'geo_id'=>$locationGeoId,
            'profile_id'=>$this->getId(),
            's_id'=>bitrix_sessid()
        ))));

        $pointId = false;
        $pointHtml = '';
        $request = Context::getCurrent()->getRequest();
        if($request->get('AWZ_YD_POINT_ID')){
            $pointId = preg_replace('/([^0-9A-z\-])/is', '', $request->get('AWZ_YD_POINT_ID'));
        }
        if($pointId){
            $blnRes = Helper::getBaloonHtml($pointId, true);
            if($blnRes->isSuccess()){
                $blnData = $blnRes->getData();
                $pointHtml = $blnData['html'];
            }
        }

        $buttonHtml = '<a id="AWZ_YD_POINT_LINK" class="'.$config['MAIN']['BTN_CLASS'].'" href="#" onclick="window.awz_yd_modal.show(\''.Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_BTN_OPEN').'\',\''.$signedParameters.'\');return false;">'.Loc::getMessage('AWZ_YDELIVERY_PROFILE_PICKUP_BTN_OPEN').'</a><div id="AWZ_YD_POINT_INFO">'.$pointHtml.'</div>';
        $result->setDescription($result->getDescription().
            '<!--btn-ydost-start-->'.
            $buttonHtml
            .'<!--btn-ydost-end-->'
        );

        $event = new \Bitrix\Main\Event(
            Handler::MODULE_ID, "OnCalcBeforeReturn",
            array('shipment'=>$shipment, 'calcResult'=>$result)
        );
        $event->send();
        if ($event->getResults()) {
            foreach ($event->getResults() as $evenResult) {
                if ($evenResult->getType() == \Bitrix\Main\EventResult::SUCCESS) {
                    $r = $evenResult->getParameters();
                    if(isset($r['result'])){
                        $r = $r['result'];
                        if($r instanceof \Bitrix\Main\Result){
                            if(!$r->isSuccess()) {
                                foreach ($r->getErrors() as $error) {
                                    $result->addError($error);
                                }
                            }
                        }
                    }
                }
            }
        }

        //DATE_CODE_
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        if(!$request->isAdminSection()){
            $AWZ_YD_POINT_DATE = date('d.m.Y', time() + $result->getPeriodFrom()*86400);
            if($ydProfileId = Helper::getProfileId($order, Helper::DOST_TYPE_ALL)){
                $code = Helper::getPropDateCode($this->getId());
                if($code){
                    /* @var \Bitrix\Sale\EntityPropertyValue $prop*/
                    foreach($props as $prop){
                        if($prop->getField('CODE') == $code){
                            $prop->setValue($AWZ_YD_POINT_DATE);
                            break;
                        }
                    }
                }
            }
        }

        return $result;

    }

    public static function onBeforeAdd(array &$fields = array()): \Bitrix\Main\Result
    {
        if(!$fields['LOGOTIP']){
            $fields['LOGOTIP'] = Handler::getLogo();
        }
        return new \Bitrix\Main\Result();
    }
}