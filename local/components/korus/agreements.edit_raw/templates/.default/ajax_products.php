<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * @var array  $arParams
 * @var array  $arResult
 * @global /CMain $APPLICATION
 * @global /CUser $USER
 * @var    /CBitrixComponentTemplate $this
 * @global /CDatabase $DB
 * @var string $componentPath Имя вызванного компонента
 * @var string templateName Имя шаблона компонента
 * @var string templateFile Путь к файлу шаблона от DOCUMENT_ROOT
 * @var string templateFolder Путь к папке с шаблоном от DOCUMENT_ROOT
 */

$data = [];
foreach ($arResult['storeProducts'] as $product1cId => $productData) {
    if($productData['available'] <= 0 || empty($arResult['priceTypes'][$product1cId])) {
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
        'data'            => $data,
        'products'        => $arResult['products'],
        'brands'          => $arResult['brands'],
        'storeType'       => $arResult['storeType'],
        'priceTypes'            => $arResult['priceTypes'],
        'priceAgreementsFilter' => $arResult['priceAgreementsFilter'],
        'priceTypeFilter'       => $arResult['priceTypeFilter'],
    ]
);
