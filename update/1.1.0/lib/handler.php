<?php

namespace Awz\Ydelivery;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Result;
use Bitrix\Main\SystemException;
use Bitrix\Sale\Delivery\Services\Base;
use Bitrix\Sale\Delivery\Services\Manager;
use Bitrix\Sale\Shipment;

Loc::loadMessages(__FILE__);

class Handler extends Base {

    const MODULE_ID = 'awz.ydelivery';

    protected static $isCalculatePriceImmediately = true;
    protected static $whetherAdminExtraServicesShow = false;

    protected static $canHasProfiles = true;

    public static function getClassTitle()
    {
        return Loc::getMessage('AWZ_YDELIVERY_HANDLER_NAME');
    }

    public static function getClassDescription()
    {
        return Loc::getMessage('AWZ_YDELIVERY_HANDLER_DESC');
    }

    public function createProfileObject($fields)
    {
        $profiles = self::getChildrenClassNames();
        if(!$fields['ID']) {
            if (!$fields['PROFILE_ID'] && !$fields['SERVICE_TYPE']) {
                $fields['SERVICE_TYPE'] = $profiles[0];
                $fields['CLASS_NAME'] = $profiles[0];
            } elseif ($fields['PROFILE_ID'] == 1 && !$fields['SERVICE_TYPE']) {
                $fields['SERVICE_TYPE'] = $profiles[1];
                $fields['CLASS_NAME'] = $profiles[1];
            }elseif ($fields['PROFILE_ID'] == 2 && !$fields['SERVICE_TYPE']) {
                $fields['SERVICE_TYPE'] = $profiles[2];
                $fields['CLASS_NAME'] = $profiles[2];
            }
        }
        return Manager::createObject($fields);
    }

    public function isCalculatePriceImmediately()
    {
        return self::$isCalculatePriceImmediately;
    }

    public static function whetherAdminExtraServicesShow()
    {
        return self::$whetherAdminExtraServicesShow;
    }

    public static function canHasProfiles()
    {
        return self::$canHasProfiles;
    }

    public static function getChildrenClassNames()
    {
        return [
            'Awz\Ydelivery\Profiles\Pickup',
            'Awz\Ydelivery\Profiles\Standart',
            'Awz\Ydelivery\Profiles\Express',
        ];
    }

    public function getProfilesList()
    {
        return [
            Profiles\Pickup::getClassTitle(),
            Profiles\Standart::getClassTitle(),
            Profiles\Express::getClassTitle()
        ];
    }

    protected function calculateConcrete(Shipment $shipment = null)
    {
        throw new SystemException(Loc::getMessage('AWZ_YDELIVERY_HANDLER_NOPROFILE'));
    }

    public static function getLogo(){

        $arFile = \CFile::MakeFileArray('/bitrix/images/'.self::MODULE_ID.'/yandex-dost.png');
        $arSavedFile = \CFile::SaveFile($arFile, "sale/delivery/logotip");
        return $arSavedFile;

    }

    public static function onBeforeAdd(array &$fields = []): Result
    {
        if(!$fields['LOGOTIP']){
            $fields['LOGOTIP'] = Handler::getLogo();
        }
        return new Result();
    }

}