<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$items = [];
foreach ($arResult['deliveryPoints'] as $value) {
    $items[] = [
        'id' => $value['UF_1C_ID'],
        'text' => $value['UF_NAME']
    ];
}

echo json_encode(
    [
        'items' => $items,
    ]
);
