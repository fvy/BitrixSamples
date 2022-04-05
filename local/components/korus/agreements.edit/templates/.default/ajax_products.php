<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use \Bitrix\Main\Localization\Loc;
use \Korus\Basic\Helpers\Formatter;

$data = [];
$volumeDef = 1000000000;
$ndsCoef = 1.2;
$fiftyCoef = 0.9;
$advert = (int) $arResult['addition']['UF_ADVERT_50_50'];

foreach ($arResult['storeProducts'] as $product1cId => $productData) {
    if (($productData['available'] <= 0 && empty($arResult['additionProducts'][$product1cId]))
        || empty($arResult['priceTypes'][$product1cId])
    ) {
        unset($arResult['products'][$product1cId]);
        continue;
    }
    
    $product = $arResult['products'][$product1cId];
    $priceTypes = $arResult['priceTypes'][$product1cId];
    $priceType = '';
    $additionProduct = $arResult['additionProducts'][$product1cId];
    $orderProducts = $arResult['orderProducts'][$product1cId];
    $matchedCount = $productData['matched'];
    $availableCount = $productData['available'];
    $limit = $productData['available'] ?: 0;
    $volume = $additionProduct['VOLUME'] ?: 0;
    $max = 0;
    $maxVolume = 0;
    $minVolume = 0;

    if (!empty($priceTypes) && !empty($additionProduct) && !empty($additionProduct['PRICE_TYPE'])) {
        $priceType = $priceTypes[$additionProduct['PRICE_TYPE']];
        $max = !empty($priceType['VOLUME']) ? $priceType['VOLUME'] : $volumeDef;
    }  else if (!empty($priceTypes) && count($priceTypes) == 1) {
        $priceType = current($priceTypes);
        $max = !empty($priceType['VOLUME']) ? $priceType['VOLUME'] : $volumeDef;
    }
    
    $price = !empty($priceType) && !empty($priceType['PRICE']) ? $priceType['PRICE'] : 0;
    
    if ($advert == 1) {
        $price *= $fiftyCoef;
    }
    
    $priceNds = $price * $ndsCoef;
    $priceTotal = $priceNds * $volume;

    if ($arResult['storeType'] === 'packing') {
        $matchedCount *= $product['PALLET_RATE'];
        $availableCount *= $product['PALLET_RATE'];
        $priceTotal *= $product['PALLET_RATE'];
        $maxVolume = floor($max / $product['PALLET_RATE']);
    }

    if ($arResult['storeType'] === 'pouring') {
        $matchedCount = Formatter::formatDouble($matchedCount, 3, '.', ' ');
        $availableCount = Formatter::formatDouble($availableCount, 3, '.', ' ');
        $maxVolume = round($max, 3);
    }
    
    if (!empty($orderProducts)) {
        foreach ($orderProducts as $orderProduct) {
            $minVolume += $orderProduct['VOLUME'];
        }
    }
    
    $symbol = $max == $volumeDef ? "∞" : $maxVolume;

    // Чекбокс для пометки на удаление
    $row[0] = "<fieldset class='custom-checkbox'>
                <input type='checkbox' 
                class='form-control js-to-delete' 
                name='product[{$product1cId}][delete][]' 
                id='product-{$product1cId}-delete'
                value='{$product1cId}' />
            </fieldset>";

    // Колонка с информацией о товаре
    $row[1] = "<div>
                <span>{$product['NAME']}</span>
                <span class='product_info' data-capacity='{$product['CAPACITY']}' data-id='{$product1cId}'>Код: {$product['CODE']} " .
                ( !empty($product['CAPACITY']) && $product['MEASUREMENT'] !== 'т'
                    ? "Емкость: {$product['CAPACITY']}л"
                    : ""
                ) . "</span>
                <input type='hidden' name='product[{$product1cId}][measure]' value='{$product['MEASURE']}'>
            </div>";

    // Колонка с название бренда
    $row[2] = $arResult['brands'][$product['BRAND']];

    // Колонка с согласованым объемом в паллетах
    $row[3] = "<span class='td-matched-pallet'>{$productData['matched']}</span> " . Loc::getMessage('K_C_ADDITIONAL_EDIT_UNIT_PALLET') . '.';

    // Колонка с согласованым объемом в штуках
    $row[4] = "<span class='td-matched-count'>{$matchedCount}</span> {$product['MEASUREMENT']}";

    // Колонка с доступным объемом в паллетах
    $row[5] = "<span class='td-available-pallet'>{$productData['available']}</span> " . Loc::getMessage('K_C_ADDITIONAL_EDIT_UNIT_PALLET') . '.';

    // Колонка с доступным объемом в штуках
    $row[6] = "<span class='td-available-count'>{$availableCount}</span> {$product['MEASUREMENT']}";

    // Колонка Фасовки
    if ($arResult['storeType'] === 'packing') {
        $row[7] = "<div class='js-div-source'>
                    <div style='display: flex; align-items: center;'>
                        <div class='input-group bootstrap-touchspin' style='width:150px'>
                            <input 
                                type='text' 
                                class='touchspin-quantity decimal-inputmask form-control js-volume text-right' 
                                value='{$volume}'
                                name='products[{$product1cId}][volume][]'
                                data-limit='{$limit}'
                                data-max='{$maxVolume}'
                                data-min='{$minVolume}'
                                data-fifty-fifty='{$advert}'
                                data-id='{$product1cId}'
                                data-pieces='1'
                                data-weight='{$product['WEIGHT']}'
                                data-pallet-rate='{$product['PALLET_RATE']}'                               
                                data-readonly='" . (!empty($priceType['READONLY']) ? $priceType['PRICE_TYPE_1C_ID'] : false) . "'
                                data-bts-button-down-class='btn bootstrap-touchspin-down btn-light'
                                data-bts-button-up-class='btn bootstrap-touchspin-up btn-light'
                                data-price-type-id='" . ($priceType['PRICE_TYPE_1C_ID'] ?? '') . "'
                                data-price='{$priceNds}' />
                        </div>
                        <span style='padding-left: 10px'>" . Loc::getMessage('K_C_ADDITIONAL_EDIT_UNIT_PALLET') . "</span>
                        <span style='padding-left: 10px; width: 85px; text-align: left;'>
                            max - <span class='max-count'>{$symbol}</span>
                        </span>
                    </div>
                </div>";
    } else {
        $row[7] = "";
    }

    // Колонка для налива
    if ($arResult['storeType'] === 'pouring') {
        $row[8] = "<div class='js-div-source'>
                    <div style='display: flex; align-items: center;'>
                        <div class='input-group bootstrap-touchspin' style='width:150px'>
                            <input 
                                type='text' 
                                class='touchspin-quantity decimal-inputmask form-control js-volume text-right' 
                                value='{$volume}'
                                name='products[{$product1cId}][volume][]'
                                data-limit='{$limit}'
                                data-max='{$maxVolume}'
                                data-min='{$minVolume}'
                                data-fifty-fifty='{$advert}'
                                data-id='{$product1cId}'
                                data-pieces='0'
                                data-readonly='" . (!empty($priceType['READONLY']) ? $priceType['PRICE_TYPE_1C_ID'] : false) . "'
                                data-weight='{$product['WEIGHT']}'
                                data-pallet-rate='{$product['PALLET_RATE']}'
                                data-bts-button-down-class='btn bootstrap-touchspin-down btn-light'
                                data-bts-button-up-class='btn bootstrap-touchspin-up btn-light'
                                data-price-type-id='" . ($priceType['PRICE_TYPE'] ?? '') . "'
                                data-price='" . ($price * ndsCoef) . "' />
                        </div>
                        <span style='padding-left: 10px'>" . Loc::getMessage('K_C_ADDITIONAL_EDIT_FORM_TOTAL_UNIT_TON') . "</span>
                        <span style='padding-left: 10px; width: 85px; text-align: left;'>
                            max - <span class='max-count'>{$symbol}</span>
                        </span>
                    </div>
                </div>";
    } else {
        $piece = $volume * $product['PALLET_RATE'];
        $row[8] = "<div class='js-div-source'/><p><span class='js-pieces'>{$piece}</span> " . Loc::getMessage('K_C_ADDITIONAL_EDIT_UNIT_PIECES') . "</p></div>";
    }

    $row[9] = "";

    // Список прайсов
    $select = "<div class='js-div-source' style='float:left'>
                <select 
                    class='price-type-select' 
                    data-placeholder='Выберите прайс' 
                    " . (!empty($orderProducts) ? 'disabled="disabled"' : '') . ">";
    
    if (empty($priceType)) {
        $select .= "<option></option>";
    }
    
    if (!empty($priceTypes)) {
        foreach ($priceTypes as $priceTypeEl) {
            $select .= "<option 
                value='{$priceTypeEl["PRICE_TYPE_1C_ID"]}'
                data-agreement-id='{$priceTypeEl["AGREEMENT_1C_ID"]}'
                " . ((!empty($priceType) && $priceType["PRICE_TYPE_1C_ID"] == $priceTypeEl["PRICE_TYPE_1C_ID"]) ? 'selected="selected"' : '') . "
                data-type='{$priceTypeEl["TYPE"]}'>{$priceTypeEl["PRICE_TYPE_NAME"]}</option>";
        }
    }
    
    $select .= "</select></div>";
    
    if (!empty($orderProducts)) {
        $select .= "<i 
            class='icon-info icon-info-select' 
            data-toggle='tooltip' 
            data-placement='right' 
            data-original-title='".Loc::getMessage("K_C_ADDITIONAL_EDIT_FORM_PRICE_DISABLED_HINT")."'></i>";
    }
    
    // Колонка с прайсами
    $row[10] = $select;

    $advertDisabled = empty($priceType) || ($advert && !$additionProduct) || !empty($orderProducts) || (!empty($priceType) && $priceType['TYPE'] == 'individual');
    // Колонка скидка 50/50
    $row[11] = "<div class='js-div-source'>
                    <fieldset class='custom-checkbox'>
                        <input type='checkbox' 
                            class='form-control js-50-50' 
                            name='product[{$product1cId}][50-50][]'
                            " . (!empty($advert) ? "checked='checked'" : "") . "
                            " . ($advertDisabled ? "disabled='disabled'" : "") . "
                            value='1' />
                    </fieldset>
                </div>";

    $specialPriceDisabled = !empty($advert) || !empty($orderProducts) || empty($priceType) || (!empty($priceType) && $priceType['TYPE'] == 'individual');
    // Колонка спец цена
    $row[12] = "<div class='js-div-source'>
                    <fieldset class='custom-checkbox'>
                        <input type='checkbox' 
                            class='form-control js-special-price' 
                            name='product[{$product1cId}][special-price][]'
                            " . ((!empty($additionProduct) && !empty($additionProduct['SPECIAL_PRICE'])) ? "checked='checked'" : "") . "
                            " . ($specialPriceDisabled ? "disabled='disabled'" : "") . "
                            value='1' />
                    </fieldset>
                </div>";

    $priceFormatted = !empty($price) ? Formatter::formatDouble($price, 2, '.', ' ') : "0.00";
    // Колонка цена
    $row[13] = "<div class='js-div-source price-fixed'><span class='price-piece'>{$priceFormatted}</span> руб.</div>";

    $priceNdsFormatted = !empty($priceNds) ? Formatter::formatDouble($priceNds, 2, '.', ' ') : "0.00";
    // Колонка цена с ндс
    $row[14] = "<div class='js-div-source price-fixed'><span class='price-piece-nds'>{$priceNdsFormatted}</span> руб.</div>";

    $priceTotalFormatted = !empty($priceTotal) ? Formatter::formatDouble($priceTotal, 2, '.', ' ') : "0.00";
    // Колонка общая цена
    $row[15] = "<div class='js-div-source price-fixed'><span class='price-piece-total'>{$priceTotalFormatted}</span> руб.</div>";

    $data[] = $row;
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
