<?php 

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use \Bitrix\Main\Localization\Loc;

$arComponentDescription = [
    'NAME'        => Loc::getMessage('K_A_RAW_COMPONENT_NAME'),
    'DESCRIPTION' => Loc::getMessage('K_A_RAW_COMPONENT_DESC'),
    'ICON'        => '',
    'SORT'        => 5,
    'CACHE_PATH'  => 'Y',
    'PATH'        => [
        'ID'    => 'content',
        'CHILD' => [
            'ID'   => 'korus',
            'NAME' => Loc::getMessage('K_A_RAW_COMPONENT_GROUP'),
            'SORT' => 1,
        ],
    ],
];