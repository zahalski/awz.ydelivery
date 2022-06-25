<?php
namespace Awz\Ydelivery\Api\Controller;

use Awz\Ydelivery\Handler;
use Awz\Ydelivery\PvzTable;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter\Scope;
use Awz\Ydelivery\Api\Filters\Sign;
use Awz\Ydelivery\Helper;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

class pickPoints extends Controller
{

    public function configureActions()
    {
        return array(
            'list' => array(
                'prefilters' => array(
                    new Scope(Scope::AJAX),
                    new Sign(array('address','geo_id','profile_id','page','order','user','s_id'))
                )
            ),
            'baloon' => array(
                'prefilters' => array(
                    new Scope(Scope::AJAX),
                    new Sign(array('address','geo_id','profile_id','page','order','user','s_id'))
                )
            ),
            'setorder' => array(
                'prefilters' => array(
                    new Scope(Scope::AJAX),
                    new Sign(array('address','geo_id','profile_id','page','order','user','s_id'))
                )
            )
        );
    }

    public function setorderAction($address = '', $geo_id = '', $profile_id = '', $page = '', $user = '', $order='', $point=''){

        \Bitrix\Main\Loader::includeModule('sale');

        if(!$user || !$order || !$point || !$page){
            $this->addError(
                new Error(
                    Loc::getMessage('AWZ_YDELIVERY_API_CONTROL_PICKPOINTS_ERR_REQ'),
                    100
                )
            );
            return null;
        }

        $orderOb = \Bitrix\Sale\Order::load($order);
        $propertyCollection = $orderOb->getPropertyCollection();
        $res = null;

        if(!$profile_id){
            $profile_id = Helper::getProfileId($orderOb, Helper::DOST_TYPE_PVZ);
        }

        $pointData = PvzTable::getPvz($point);
        if(!$pointData){
            $this->addError(
                new Error(
                    Loc::getMessage('AWZ_YDELIVERY_API_CONTROL_PICKPOINTS_ERR_POINT_DATA'),
                    100
                )
            );
            return null;
        }
        $addressPvz = Helper::formatPvzAddress($profile_id, $pointData);

        $isSet = false;
        $addMessAddress = '';
        foreach($propertyCollection as $prop){
            if($prop->getField('CODE') == Helper::getPropPvzCode($profile_id)){
                $prop->setValue($point);
                $isSet = true;
            }elseif($addressPvz && ($prop->getField('CODE') == Helper::getPropAddress($profile_id))){
                $prop->setValue($addressPvz);
                $isSet = true;
                $addMessAddress .= ', '.Loc::getMessage('AWZ_YDELIVERY_API_CONTROL_PICKPOINTS_OK_ADDR_ADD', array('#PROP#'=>$prop->getField('CODE')));
            }
        }
        if($isSet){
            $res = $orderOb->save();
        }
        if(!$res){
            $this->addError(
                new Error(
                    Loc::getMessage('AWZ_YDELIVERY_API_CONTROL_PICKPOINTS_ERR_PROP'), 100
                )
            );
            return null;
        }else{
            if($res->isSuccess()){
                return Loc::getMessage(
                    'AWZ_YDELIVERY_API_CONTROL_PICKPOINTS_OK_ADDR',
                    array("#POINT#"=>$point, "#PROP#"=>Helper::getPropPvzCode($profile_id))
                ).$addMessAddress;
            }else{
                $this->addErrors($res->getErrors());
                return null;
            }

        }

    }
    public function baloonAction($s_id='', $address = '', $geo_id = '', $profile_id = '', $page = '', $id = '')
    {
        if(!$id){
            $this->addError(
                new Error(Loc::getMessage('AWZ_YDELIVERY_API_CONTROL_PICKPOINTS_ID_ERR'), 100)
            );
            return null;
        }
        if(bitrix_sessid() != $s_id){
            $this->addError(
                new Error(Loc::getMessage('AWZ_YDELIVERY_API_CONTROL_PICKPOINTS_SESS_ERR'), 100)
            );
            return null;
        }

        $hideBtn = ($page === 'pvz-edit') ? true : false;

        $bResult = Helper::getBaloonHtml($id, $hideBtn);
        if(!$bResult->isSuccess()){
            $this->addErrors($bResult->getErrors());
            return null;
        }
        $resultData = $bResult->getData();

        return $resultData['html'];
    }

    public function listAction($address = '', $geo_id = '', $profile_id = '', $page = '')
    {

        if(!$profile_id){
            $this->addError(
                new Error(Loc::getMessage('AWZ_YDELIVERY_API_CONTROL_PICKPOINTS_PROFILE_ERR'), 100)
            );
            return null;
        }
        if(!$address && !$geo_id){
            $this->addError(
                new Error(Loc::getMessage('AWZ_YDELIVERY_API_CONTROL_PICKPOINTS_ADDRGEO_ERR'), 100)
            );
            return null;
        }

        $api = Helper::getApiByProfileId($profile_id);

        $api->setCacheParams(md5(serialize(array($address, $geo_id, $profile_id))), 86400);
        $pickpointsResult = $api->getPickpoints(array('geo_id'=>intval($geo_id)));
        if(!$pickpointsResult->isSuccess()){
            $this->addErrors($pickpointsResult->getErrors());
            return null;
        }
        $pickpoints = $pickpointsResult->getData();
        if(empty($pickpoints['result']['points'])){
            $this->addError(
                new Error(Loc::getMessage('AWZ_YDELIVERY_API_CONTROL_PICKPOINTS_PVZ_ERR'), 100)
            );
            return null;
        }
        $items = array();

        foreach($pickpoints['result']['points'] as $point){
            $items[] = array(
                'id'=>$point['id'],
                'position'=>$point['position'],
                'payment_methods'=>$point['payment_methods'],
                'type'=>$point['type']
            );
        }

        if($api->getLastResponse() && (Option::get(Handler::MODULE_ID, "UPDATE_PVZ_BG", "Y", "") == 'Y')) {
            Application::getInstance()->addBackgroundJob(
                array("\\Awz\\Ydelivery\\Checker", "runJob"),
                array($pickpoints['result']['points']),
                \Bitrix\Main\Application::JOB_PRIORITY_NORMAL
            );
        }

        return array(
            'page'=>$page,
            'address' => $address,
            'geo_id' => $geo_id,
            'profile_id' => $profile_id,
            'items' => $items,
            'from_cache'=>$api->getLastResponse() ? 0 : 1,
        );
    }
}