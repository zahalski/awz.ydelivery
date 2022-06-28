<?php

namespace Awz\Ydelivery;

use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Delivery\Services\Manager;

Loc::loadMessages(__FILE__);

class Handler extends \Bitrix\Sale\Delivery\Services\Base {

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
        return array(
            'Awz\Ydelivery\Profiles\Pickup',
            'Awz\Ydelivery\Profiles\Standart',
        );
    }

    public function getProfilesList()
    {
        return array(Profiles\Pickup::getClassTitle(), Profiles\Standart::getClassTitle());
    }

    protected function calculateConcrete(\Bitrix\Sale\Shipment $shipment = null)
    {
        throw new \Bitrix\Main\SystemException(Loc::getMessage('AWZ_YDELIVERY_HANDLER_NOPROFILE'));
    }

    public static function getLogo(){

        $arFile = \CFile::MakeFileArray('/bitrix/images/'.self::MODULE_ID.'/yandex-dost.png');
        $arSavedFile = \CFile::SaveFile($arFile, "sale/delivery/logotip");
        return $arSavedFile;

    }

    public static function onBeforeAdd(array &$fields = array()): \Bitrix\Main\Result
    {
        if(!$fields['LOGOTIP']){
            $fields['LOGOTIP'] = Handler::getLogo();
        }
        return new \Bitrix\Main\Result();
    }

}