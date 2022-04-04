<?php
$arJsConfig = array(
    'adv_ajax' => array(
        'js' => '/bitrix/js/adv.communicationcards/adv_ajax.js',
    )
);

foreach ($arJsConfig as $ext => $arExt) {
    \CJSCore::RegisterExt($ext, $arExt);
}