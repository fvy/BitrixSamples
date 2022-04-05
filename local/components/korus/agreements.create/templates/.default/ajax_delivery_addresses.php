<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$items = [];
foreach ($arResult['deliveryAddresses'] as $value) {
    $items[] = [
        'id' => $value['UF_1C_ID'],
        'text' => $value['UF_ADDRESS']
    ];
}

echo json_encode(
    [
        'items' => $items,
    ]
);
