<?php
use \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\EventManager,
    \Bitrix\Main\ModuleManager,
    \Bitrix\Main\Application;

Loc::loadMessages(__FILE__);

class awz_ydelivery extends CModule {

    var $MODULE_ID = "awz.ydelivery";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_CSS;
    var $MODULE_GROUP_RIGHTS = "Y";

    function __construct()
    {
        $arModuleVersion = array();

        include(__DIR__.'/version.php');

        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];

        $this->MODULE_NAME = Loc::getMessage("AWZ_YDELIVERY_MODULE_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("AWZ_YDELIVERY_MODULE_DESCRIPTION");
        $this->PARTNER_NAME = Loc::getMessage("AWZ_PARTNER_NAME");
        $this->PARTNER_URI = Loc::getMessage("AWZ_PARTNER_URI");
    }

    function InstallDB()
    {
        return true;
    }


    function UnInstallDB()
    {
        return true;
    }

    function InstallHandlers()
    {
        $eventManager = EventManager::getInstance();
        $eventManager->registerEventHandler(
            'sale', 'onSaleDeliveryHandlersClassNamesBuildList',
            $this->MODULE_ID, '\Awz\Ydelivery\Handler', 'registerHandler'
        );
        $eventManager->registerEventHandlerCompatible("sale", "OnSaleComponentOrderOneStepDelivery",
            $this->MODULE_ID, "\Awz\Ydelivery\Handler", 'OrderDeliveryBuildList'
        );

        return true;
    }

    function UnInstallHandlers()
    {
        $eventManager = EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            'sale', 'onSaleDeliveryHandlersClassNamesBuildList',
            $this->MODULE_ID, '\Awz\Ydelivery\Handler', 'registerHandler'
        );
        $eventManager->unRegisterEventHandler(
            'sale', 'OnSaleComponentOrderOneStepDelivery',
            $this->MODULE_ID, '\Awz\Ydelivery\Handler', 'OrderDeliveryBuildList'
        );

        return true;
    }

    function InstallEvents()
    {
        return true;
    }

    function UnInstallEvents()
    {
        return true;
    }

    function InstallFiles()
    {
        return true;
    }

    function UnInstallFiles()
    {
        return true;
    }

    function DoInstall()
    {
        global $APPLICATION, $step;

        $this->InstallFiles();
        $this->InstallDB();
        $this->InstallEvents();
        $this->InstallHandlers();

        ModuleManager::RegisterModule($this->MODULE_ID);

        return true;
    }

    function DoUninstall()
    {
        global $APPLICATION, $step;

        $this->UnInstallDB();
        $this->UnInstallFiles();
        $this->UnInstallEvents();
        $this->UnInstallHandlers();

        ModuleManager::UnRegisterModule($this->MODULE_ID);
        return true;
    }

}