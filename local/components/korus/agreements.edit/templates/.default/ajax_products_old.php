<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$data = [];

foreach ($arResult['storeProducts'] as $product1cId => $productData) {
    if (($productData['available'] <= 0 && empty($arResult['additionProducts'][$product1cId]))
        || empty($arResult['priceTypes'][$product1cId])
    ) {
        unset($arResult['products'][$product1cId]);
        continue;
    }
    
    $data[] = [
        'product'   => $product1cId,
        'matched'   => $productData['matched'],
        'available' => $productData['available'],
    ];
}

echo json_encode(
    [
        'data'                  => $data,
        'products'              => $arResult['products'],
        'brands'                => $arResult['brands'],
        'storeType'             => $arResult['storeType'],
        'priceTypes'            => $arResult['priceTypes'],
        'priceAgreementsFilter' => $arResult['priceAgreementsFilter'],
        'priceTypeFilter'       => $arResult['priceTypeFilter'],
    ]
);
