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

class Standart extends Controller
{

    public function configureActions()
    {
        return [
            'gpsmap' => [
                'prefilters' => [
                    new Scope(Scope::AJAX),
                    new Sign(['address','geo_id','profile_id','page','order','user','s_id'])
                ]
            ]
        ];
    }

    public function gpsmapAction($address = '', $geo_id = '', $profile_id = '', $page = '')
    {
        if(!$profile_id){
            $this->addError(
                new Error(Loc::getMessage('AWZ_YDELIVERY_API_CONTROL_STANDART_PROFILE_ERR'), 100)
            );
            return null;
        }
        if(!$address && !$geo_id){
            $this->addError(
                new Error(Loc::getMessage('AWZ_YDELIVERY_API_CONTROL_STANDART_ADDRGEO_ERR'), 100)
            );
            return null;
        }

        $api = Helper::getApiByProfileId($profile_id);

        return [
            'page'=>$page,
            'address' => $address,
            'geo_id' => $geo_id,
            'profile_id' => $profile_id,
            'from_cache'=>$api->getLastResponse() ? 0 : 1
        ];
    }

}