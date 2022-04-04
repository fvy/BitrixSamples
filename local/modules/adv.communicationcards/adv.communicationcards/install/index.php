<?php

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\IO\Directory;

Loc::loadLanguageFile(__FILE__);

class adv_communicationcards extends CModule
{
    public $MODULE_ID           = 'adv.communicationcards';
    public $MODULE_GROUP_RIGHTS = 'Y';

    /**
     * @var bool
     */
    private $eventManager;

    public function __construct()
    {
        $arModuleVersion = [];

        include_once(__DIR__ . '/version.php');

        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion)
        ) {
            $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }

        $this->MODULE_NAME        = Loc::getMessage("ADV_COMM_CARDS_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("ADV_COMM_CARDS_DESCRIPTION");
        $this->PARTNER_NAME       = Loc::getMessage("ADV_COMM_CARDS_PARTNER_NAME");
        $this->PARTNER_URI        = Loc::getMessage("ADV_COMM_CARDS_PARTNER_URI");

        $this->eventManager = Bitrix\Main\EventManager::getInstance();
    }

    /**
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        Loader::includeModule($this->MODULE_ID);

        $this->eventManager->addEventHandler(
            $this->MODULE_ID,
            'OnUserTypeBuildList',
            ['Adv\CommunicationCards\UserType\CUserTypeUserDataByPhone', 'GetUserTypeDescription']
        );

        RegisterModuleDependences(
            "main",
            "OnUserTypeBuildList",
            $this->MODULE_ID,
            "Adv\CommunicationCards\UserType\CUserTypeUserDataByPhone",
            "GetUserTypeDescription"
        );

        $this->InstallFiles();
    }

    /**
     * @throws \Bitrix\Main\Db\SqlQueryException
     * @throws \Bitrix\Main\LoaderException
     */
    public function DoUninstall()
    {
        Loader::includeModule($this->MODULE_ID);

        $this->eventManager->removeEventHandler(
            $this->MODULE_ID,
            'OnUserTypeBuildList',
            ['\Adv\CommunicationCards\UserType\CUserTypeUserDataByPhone', 'GetUserTypeDescription']
        );

        UnRegisterModuleDependences(
            "main",
            "OnUserTypeBuildList",
            $this->MODULE_ID,
            "Adv\CommunicationCards\UserType\CUserTypeUserDataByPhone",
            "GetUserTypeDescription"
        );

        ModuleManager::unRegisterModule($this->MODULE_ID);
        $this->UnInstallFiles();
    }

    public function InstallFiles()
    {
        CopyDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');

        CopyDirFiles(
            __DIR__ . "/assets/scripts",
            Application::getDocumentRoot() . "/bitrix/js/" . $this->MODULE_ID . "/",
            true,
            true
        );
    }

    public function UnInstallFiles()
    {
        Directory::deleteDirectory(
            Application::getDocumentRoot() . "/bitrix/js/" . $this->MODULE_ID
        );
    }

    /**
     * @return \Bitrix\Main\DB\Connection
     * @throws \Bitrix\Main\SystemException
     */
    protected function getConnection()
    {
        return Application::getInstance()->getConnection();
    }

    public function InstallDb()
    {
    }

    public function UnInstallDB()
    {
    }
}
