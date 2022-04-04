<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

if (!Loader::includeModule('adv.communicationcards')) {
    return;
}

Loc::loadMessages(__FILE__);

if ($GLOBALS['APPLICATION']->GetUserRight('adv.communicationcards') < 'R') {
    return [];
}

return [
    'parent_menu' => 'global_menu_store',
    'sort'        => 150,
    'icon'        => 'statistic_icon_online',
    'page_icon'   => 'statistic_icon_online',
    'text'        => 'Коммуникации',
    "items_id"    => 'adv_communications',
    'more-url'    => [
        '/bitrix/admin/adv_communication_cards_route.php'
    ],
    'items'       => [
        [
            'sort'        => 150,
            'text'        => 'Cписок коммуникаций',
            "items_id"    => 'adv_communication_cards',
            'url'         => '/bitrix/admin/adv_communication_cards_route.php',
        ]
    ]
];
