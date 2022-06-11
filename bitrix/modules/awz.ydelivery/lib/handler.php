<?php

namespace Awz\Ydelivery;

use Bitrix\Main\Localization\Loc;

class Handler extends \Bitrix\Sale\Delivery\Services\Base {

    protected static $isCalculatePriceImmediately = true;
    protected static $whetherAdminExtraServicesShow = false;

    protected static $canHasProfiles = true;

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

    public static function OrderDeliveryBuildList(&$arResult, &$arUserResult, $arParams)
    {
        global $APPLICATION;
        \CUtil::InitJSCore(array('ajax', 'awz_yd_lib'));

        $key = \Bitrix\Main\Config\Option::get("fileman", "yandex_map_api_key");
        $APPLICATION->AddHeadString('<script src="//api-maps.yandex.ru/2.1/?lang=ru_RU&amp;apikey='.$key.'"></script>', true);

    }

    public static function getClassTitle()
    {
        return Loc::getMessage('AWZ_YDELIVERY_HANDLER_NAME');
    }

    public static function getClassDescription()
    {
        return Loc::getMessage('AWZ_YDELIVERY_HANDLER_DESC');
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
        throw new \Bitrix\Main\SystemException('Use profiles');
    }

}