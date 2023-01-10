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

    var $errors = false;

    public function __construct()
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
        global $DB, $DBType, $APPLICATION;
        $this->errors = false;
        $this->errors = $DB->RunSQLBatch($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/". $this->MODULE_ID ."/install/db/".mb_strtolower($DB->type)."/install.sql");
        if (!$this->errors) {
            return true;
        } else {
            $APPLICATION->ThrowException(implode("", $this->errors));
            return $this->errors;
        }
        return true;
    }


    function UnInstallDB()
    {
        global $DB, $DBType, $APPLICATION;

        $this->errors = false;
        $this->errors = $DB->RunSQLBatch($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/". $this->MODULE_ID ."/install/db/".mb_strtolower($DB->type)."/uninstall.sql");
        if (!$this->errors) {
            return true;
        }
        else {
            $APPLICATION->ThrowException(implode("", $this->errors));
            return $this->errors;
        }
    }


    function InstallEvents()
    {
        $eventManager = EventManager::getInstance();
        $eventManager->registerEventHandler(
            'sale', 'onSaleDeliveryHandlersClassNamesBuildList',
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'registerHandler'
        );
        $eventManager->registerEventHandlerCompatible("sale", "OnSaleComponentOrderOneStepDelivery",
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'OrderDeliveryBuildList'
        );
        $eventManager->registerEventHandlerCompatible("sale", "OnSaleComponentOrderCreated",
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'OnSaleComponentOrderCreated'
        );
        $eventManager->registerEventHandlerCompatible("main", "OnEndBufferContent",
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'OnEndBufferContent'
        );
        $eventManager->registerEventHandlerCompatible("main", "OnAdminSaleOrderEditDraggable",
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'OnAdminSaleOrderEditDraggable'
        );
        $eventManager->registerEventHandler("sale", "OnSaleOrderBeforeSaved",
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', "OnSaleOrderBeforeSaved");
        $eventManager->registerEventHandlerCompatible("main", "OnAdminContextMenuShow",
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', "OnAdminContextMenuShow");
        $eventManager->registerEventHandlerCompatible("main", "OnEpilog",
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', "OnEpilog");
        return true;
    }

    function UnInstallEvents()
    {
        $eventManager = EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            'sale', 'onSaleDeliveryHandlersClassNamesBuildList',
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'registerHandler'
        );
        $eventManager->unRegisterEventHandler(
            'sale', 'OnSaleComponentOrderOneStepDelivery',
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'OrderDeliveryBuildList'
        );
        $eventManager->unRegisterEventHandler(
            'sale', 'OnSaleComponentOrderCreated',
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'OnSaleComponentOrderCreated'
        );
        $eventManager->unRegisterEventHandler(
            'main', 'OnEndBufferContent',
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'OnEndBufferContent'
        );
        $eventManager->unRegisterEventHandler(
            'main', 'OnAdminSaleOrderEditDraggable',
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'OnAdminSaleOrderEditDraggable'
        );
        $eventManager->unRegisterEventHandler(
            'sale', 'OnSaleOrderBeforeSaved',
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'OnSaleOrderBeforeSaved'
        );
        $eventManager->unRegisterEventHandler(
            'main', 'OnAdminContextMenuShow',
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'OnAdminContextMenuShow'
        );
        $eventManager->unRegisterEventHandler(
            'main', 'OnEpilog',
            $this->MODULE_ID, '\Awz\Ydelivery\handlersBx', 'OnEpilog'
        );
        return true;
    }

    function InstallFiles()
    {
        CopyDirFiles($_SERVER['DOCUMENT_ROOT']."/bitrix/modules/".$this->MODULE_ID."/install/admin/", $_SERVER['DOCUMENT_ROOT']."/bitrix/admin/", true);
        CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/install/js/", $_SERVER["DOCUMENT_ROOT"]."/bitrix/js/".$this->MODULE_ID, true);
        CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/install/css/", $_SERVER["DOCUMENT_ROOT"]."/bitrix/css/".$this->MODULE_ID, true);
        CopyDirFiles($_SERVER['DOCUMENT_ROOT']."/bitrix/modules/".$this->MODULE_ID."/install/images/", $_SERVER['DOCUMENT_ROOT']."/bitrix/images/".$this->MODULE_ID, true);
        CopyDirFiles($_SERVER['DOCUMENT_ROOT']."/bitrix/modules/".$this->MODULE_ID."/install/components/ydelivery.baloon/", $_SERVER['DOCUMENT_ROOT']."/bitrix/components/awz/ydelivery.baloon", true, true);
        return true;
    }

    function UnInstallFiles()
    {
        DeleteDirFilesEx("/bitrix/js/".$this->MODULE_ID);
        DeleteDirFilesEx("/bitrix/css/".$this->MODULE_ID);
        DeleteDirFilesEx("/bitrix/images/".$this->MODULE_ID);
        DeleteDirFilesEx("/bitrix/components/awz/ydelivery.baloon");
        DeleteDirFiles(
            $_SERVER['DOCUMENT_ROOT']."/bitrix/modules/".$this->MODULE_ID."/install/admin",
            $_SERVER['DOCUMENT_ROOT']."/bitrix/admin"
        );

        return true;
    }

    function DoInstall()
    {
        global $APPLICATION, $step;

        $this->InstallFiles();
        $this->InstallDB();
		$this->checkOldInstallTables();
        $this->InstallEvents();
        $this->createAgents();

        ModuleManager::RegisterModule($this->MODULE_ID);

        return true;
    }

    function DoUninstall()
    {
        global $APPLICATION, $step;

        $step = intval($step);
        if($step < 2) { //выводим предупреждение
            $APPLICATION->IncludeAdminFile(Loc::getMessage('AWZ_YDELIVERY_INSTALL_TITLE'), $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/'. $this->MODULE_ID .'/install/unstep.php');
        }
        elseif($step == 2) {
            //проверяем условие
            if($_REQUEST['save'] != 'Y' && !isset($_REQUEST['save'])) {
                $this->UnInstallDB();
            }
            $this->UnInstallFiles();
            $this->UnInstallEvents();
            $this->deleteAgents();

            ModuleManager::UnRegisterModule($this->MODULE_ID);

            return true;
        }
    }

    function createAgents() {
        CAgent::AddAgent(
            "\\Awz\\Ydelivery\\Checker::agentGetStatus();",
            $this->MODULE_ID,
            "N",
            600);
        CAgent::AddAgent(
            "\\Awz\\Ydelivery\\Checker::agentGetPickpoints();",
            $this->MODULE_ID,
            "N",
            86400*7);
    }

    function deleteAgents() {
        CAgent::RemoveAgent("\\Awz\\Ydelivery\\Checker::agentGetStatus();", $this->MODULE_ID);
        CAgent::RemoveAgent("\\Awz\\Ydelivery\\Checker::agentGetPickpoints();", $this->MODULE_ID);
    }

	function checkOldInstallTables(){
		
		$connection = \Bitrix\Main\Application::getConnection();
		$checkColumn = false;
		$checkTable = false;
		$recordsRes = $connection->query("select * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='b_awz_ydelivery_offer'");
		while($dt = $recordsRes->fetch()){
			$checkTable = true;
			if($dt['COLUMN_NAME'] == 'LAST_STATUS'){
				$checkColumn = true;
				break;
			}

		}
		if($checkTable && !$checkColumn){
			$sql = 'ALTER TABLE `b_awz_ydelivery_offer` ADD `LAST_STATUS` varchar(65) DEFAULT NULL';
			$connection->queryExecute($sql);
		}
		
	}
}