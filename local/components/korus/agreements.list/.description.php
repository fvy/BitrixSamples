<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

$arComponentDescription = [
    'NAME'        => Loc::getMessage('AGRLST_INFO_COMPONENT_NAME'),
    'DESCRIPTION' => Loc::getMessage('AGRLST_INFO_REQUEST_COMPONENT_DESC'),
    'PATH'        => [
        'ID'    => 'content',
        'CHILD' => [
            'ID'   => 'korus',
            'NAME' => Loc::getMessage('AGRLST_INFO_COMPONENT_GROUP'),
        ],
    ],
];
