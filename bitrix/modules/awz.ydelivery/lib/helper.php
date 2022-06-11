<?php
namespace Awz\Ydelivery;

use Bitrix\Main\DB\Exception;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Sale\Delivery\Services\Base;

class Helper {

    public static function getYdApi(Base $profile){
        $config = $profile->getConfigValues();

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

}