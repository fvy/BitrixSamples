<?php

use \Bitrix\Main\Localization\Loc;

/**
 * Комплексный компонент дополнений
 * Class AgreementsComplex
 */
class AgreementsComplex extends \CBitrixComponent
{
    /**
     * Массив для задания путей по умолчанию для работы в ЧПУ режиме
     *
     * @var array
     */
    public $arDefaultUrlTemplatesSEF = [
        'add'      => "create_agreement/",
        'view'     => "view/#AGREEMENT_ID#/",
        'edit'     => "edit/#AGREEMENT_ID#/",
        'edit_raw' => "edit_raw/#AGREEMENT_ID#/",
    ];

    /**
     * @return mixed|void
     */
    public function executeComponent()
    {
        global $APPLICATION;
        $arVariables = [];

        $componentPage = CComponentEngine::ParseComponentPath(
            $this->arParams['SEF_FOLDER'],
            $this->arDefaultUrlTemplatesSEF,
            $arVariables
        );
        if (!$componentPage) {
            $componentPage = 'list';
        }

        CComponentEngine::InitComponentVariables($componentPage, [], [], $arVariables);

        $sTitle = CComponentEngine::makePathFromTemplate(
            Loc::getMessage('AGREEMENTS_TITLE_' . mb_strtoupper($componentPage)),
            $arVariables
        );
        $APPLICATION->SetTitle($sTitle);

        $this->arResult['VARIABLES'] = $arVariables;

        $this->includeComponentTemplate($componentPage);
    }
}
