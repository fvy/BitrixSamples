<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;
use Korus\Basic\Helpers\Formatter;

Loc::loadMessages(__FILE__);

$oAssets = \Bitrix\Main\Page\Asset::getInstance();
$oAssets->addJs("/local/assets/js/vendors/tables/datatable/datatables.min.js");
$oAssets->addJs(SITE_DIR . 'local/assets/js/agreements/cancel.js');
$oAssets->addCss("/local/assets/css/vendors/tables/datatable/datatables.min.css");
$oAssets->addCss('/local/assets/css/flat/blue.css');
$oAssets->addJs('/local/assets/js/icheck.min.js');
$oAssets->addJs('/local/assets/js/FileSaver.js');
$oAssets->addJs("/local/assets/js/datatable/render.js");

/**
 * @var $oErrors Bitrix\Main\ErrorCollection|null
 */
$oErrors = $arResult['ERRORS'];
$showErrorList = function ($sCode) use ($oErrors) {
    /**
     * @var $oError Bitrix\Main\Error|null
     */
    foreach ($oErrors->toArray() as $oError) {
        if ($oError->getCode() == $sCode) {
            echo "<li>" . $oError->getMessage() . "</li>";
        }
    }
};
$dataTableRows = [];
$additional = $arResult['ADDITIONAL'];
$visibleSpecFields = false;
?>
<div class="content-header row">
    <div class="content-header-left col-md-6 col-12 mb-2">
        <h3 class="content-header-title"><? $APPLICATION->ShowTitle() ?></h3>
    </div>
    <div class="text-right col-md∂-6 col-12">
        <?php if ($additional['CAN_EDIT']) : ?>
            <a href="/agreements/edit/<?= $additional['ID']; ?>/"
                class="btn btn-round btn-warning">
                <?= Loc::getMessage('AV_BTN_EDIT') ?>
            </a>
        <?php endif; ?>
        <?php if (!empty($arResult['PRODUCTS']) && $arResult['ADDITIONAL']['ACCESS_INVOICE']) : ?>
            <button type="submit"
                    name="action"
                    value="request_invoice"
                    class="request_invoice_btn btn btn-round btn-info">
                <?= Loc::getMessage('AV_BTN_REQUEST_INVOICE') ?>
            </button>
        <?php endif; ?>
    </div>
</div>
<div class="content-body">
    <?php if ($isSCriticalErrors = $oErrors->getErrorByCode('CRITICAL_ERROR') ?? false): ?>
        <div class="alert alert-danger mb-2" role="alert">
            <ul>
                <? $showErrorList('CRITICAL_ERROR') ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header pr-0">
                        <div class="row col-12 pr-0">
                            <div class="col-6">
                                <div class="form-group">
                                    <h4 class="form-section col-12 pl-0 mb-0">
                                        <i class="la la-comment"></i> <?= Loc::getMessage('AV_INFO_TITLE_GENERAL_INFORMATION'); ?>
                                    </h4>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <h4 class="form-section col-12 pl-0 mb-0">
                                        <i class="la la-truck"></i> <?= Loc::getMessage('AV_INFO_TITLE_DELIVERY'); ?>
                                    </h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-content collpase show">
                        <div class="card-body">
                            <div class="form-body">
                                <div class="row col-12">
                                    <div class="col-6">
                                        <div class="form-group row">
                                            <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_CONTRACT'); ?>:</div>
                                            <div class="col-6">
                                                <a href="/contracts/view/<?= $arResult['CONTRACT']['ID']; ?>/"
                                                   class="general-info__contract"><?= $arResult['CONTRACT']['UF_CONTRACT_NUMBER']; ?></a>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_PERIOD'); ?>:</div>
                                            <div class="col-6"><?php
                                                if (is_a($additional['UF_PERIOD'], '\Bitrix\Main\Type\Date')) {
                                                    echo getMessage('MONTH_' . $additional['UF_PERIOD']->format('n')) . ' ' . $additional['UF_PERIOD']->format('Y');
                                                }
                                                ?></div>
                                        </div>
                                        <?php if (!empty($additional['UF_DATE'])) : ?>
                                        <div class="form-group row">
                                            <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_DATE'); ?>:</div>
                                            <div class="col-6">
                                                <?= is_a($additional['UF_DATE'], '\Bitrix\Main\Type\Date') ? $additional['UF_DATE']->format('d.m.Y') : '';?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($additional['UF_COMMENT'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_COMMENT'); ?>:</div>
                                                <div class="col-6"><?= $additional['UF_COMMENT']; ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($additional['UF_1C_ID'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_ASKU_NUMBER'); ?>:</div>
                                                <div class="col-6"><?= $additional['UF_1C_ID']; ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="form-group row">
                                            <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_STATUS'); ?>:</div>
                                            <div class="col-6"><?= $arResult['STATUSES_LIST'][$additional['UF_STATUS']]; ?></div>
                                        </div>
                                        <div class="form-group row">
                                            <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_TAX_PRICE'); ?>:</div>
                                            <div class="col-6">
                                                <?= Formatter::formatDouble($additional['UF_TAX_PRICE'], 2, '.', ' '); ?> <?= Loc::getMessage('AV_CURRENCY'); ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($additional['UF_ADVERT_50_50']) && !empty($additional['UF_ADVERT_50_50_COST'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_ADVERT_50_50_COST'); ?>:</div>
                                                <div class="col-6">
                                                    <?= Formatter::formatDouble($additional['UF_ADVERT_50_50_COST'], 2, '.', ' '); ?> <?= Loc::getMessage('AV_CURRENCY'); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($additional['UF_NONPALLET'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><input type="checkbox" id="nonpallet" checked="checked" disabled="disabled"/></div>
                                                <div class="col-6"><?= Loc::getMessage('AV_INFO_NONPALLET'); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-6">
                                        <?php if (!empty($arResult['TRANSPORTATION'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_TRANSPORTATION_TY'); ?>:</div>
                                                <div class="col-6"><?= $arResult['TRANSPORTATION']['UF_NAME']; ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($arResult['RECEIVER'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_RECEIVER'); ?>:</div>
                                                <div class="col-6"><?= $arResult['RECEIVER']['UF_NAME']; ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($arResult['SHIPPING_STORE'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_SHIPPING_STORE'); ?>:</div>
                                                <div class="col-6"><?= $arResult['SHIPPING_STORE']['TITLE']; ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($arResult['SHIPPING_POINT'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_SHIPPING_POINT'); ?>:</div>
                                                <div class="col-6"><?= $arResult['SHIPPING_POINT']['UF_NAME']; ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($arResult['TRANSPORT_TYPE'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_TRANSPORT_TYPE'); ?>:</div>
                                                <div class="col-6"><?= $arResult['TRANSPORT_TYPE']['UF_NAME']; ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($arResult['DELIVERY_TYPE'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_DELIVERY_TYPE'); ?>:</div>
                                                <div class="col-6"><?= $arResult['DELIVERY_TYPE']['UF_NAME']; ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($arResult['DELIVERY_POINT'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_DELIVERY_POINT'); ?>:</div>
                                                <div class="col-6"><?= $arResult['DELIVERY_POINT']['UF_NAME']; ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($arResult['DELIVERY_ADDRESS'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_DELIVERY_ADDRESS'); ?>:</div>
                                                <div class="col-6"><?= $arResult['DELIVERY_ADDRESS']['UF_ADDRESS']; ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($additional['UF_SHIPPING_AGENT'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><?= Loc::getMessage('AV_INFO_SHIPPING_AGENT'); ?>:</div>
                                                <div class="col-6"><?= $additional['UF_SHIPPING_AGENT']; ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($additional['UF_DELIVERY_IN_PRICE'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><input type="checkbox" id="delivery_price" checked="checked" disabled="disabled"/></div>
                                                <div class="col-6"><?= Loc::getMessage('AV_INFO_DELIVERY_IN_PRICE'); ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($additional['UF_PASSING_OF_PROP'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><input type="checkbox" id="passing_prop" checked="checked" disabled="disabled" /></div>
                                                <div class="col-6"><?= Loc::getMessage('AV_INFO_PASSING_OF_PROP'); ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($additional['UF_ADVERT_50_50'])) : ?>
                                            <div class="form-group row">
                                                <div class="col-6 text-right"><input type="checkbox" id="delivery_price" checked="checked" disabled="disabled"/></div>
                                                <div class="col-6"><?= Loc::getMessage('AV_INFO_ADVERT_50_50'); ?></div>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="content-header row">
                    <div class="content-header-left col-md-6 col-12 mb-2">
                        <h4 class="content-header-title">
                            <?= Loc::getMessage('AV_TABLE_TITLE') ?>
                            <?php if(array_sum(array_column($arResult['PRODUCTS'],'SUM')) <= 0): ?>
                                <i class="icon-info pl-1"
                                   data-toggle="tooltip"
                                   data-placement="right"
                                   data-original-title="<?= Loc::getMessage('AV_NOT_SUM_HINT')?>"></i>
                            <?php endif; ?>
                        </h4>
                        <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                    </div>
                </div>
                <div class="card">
                    <div class="card-content collapse show">
                        <div class="card-body card-dashboard">
                            <div class="row">
                                <div class="row col-md-6 mr-auto">
                                    <fieldset class="form-group position-relative has-icon-left col-md-8">
                                        <input type="text" class="form-control" id="search_field" placeholder="<?= Loc::getMessage('AV_FILTER_SEARCH'); ?>">
                                        <div class="form-control-position">
                                            <i class="ficon la la-search"></i>
                                        </div>
                                    </fieldset>
                                    <fieldset class="form-group">
                                        <a href="#" id="clear_search_field" style="display:flex; margin:10px 0 0;text-decoration: underline;">
                                            <?= Loc::getMessage('AV_FILTER_SEARCH_CLEAR'); ?>
                                        </a>
                                    </fieldset>
                                </div>
                            </div>
                            <div class="loader-wrapper">
                                <div class="loader-container">
                                    <div class="ball-spin-fade-loader loader-blue">
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                    </div>
                                </div>
                            </div>
                            <div id="gpnsm-dynamic-overflow" class="gpnsm-dynamic-overflow invisible">
                                <table class="table table-sm table-striped table-bordered table-middle additional-products table-white-space"
                                       data-additional="<?= $additional['ID']; ?>"
                                       data-additional-number="<?= $additional['UF_ADDITION_NUMBER']; ?>">
                                    <thead>
                                        <tr>
                                            <th class="align-middle"><?= Loc::getMessage('AV_TABLE_TH_NAME'); ?></th>
                                            <th class="text-center align-middle"><?= Loc::getMessage('AV_TABLE_TH_COUNT'); ?></th>
                                            <th class="text-center align-middle"><?= Loc::getMessage('AV_TABLE_TH_PRICE_AGREEMENT'); ?></th>
                                            <th class="text-center align-middle"><?= Loc::getMessage('AV_TABLE_TH_SPECIAL_PRICE'); ?></th>
                                            <th class="text-center align-middle"><?= Loc::getMessage('AV_TABLE_TH_PRICE'); ?></th>
                                            <th class="text-center"><?= Loc::getMessage('AV_TABLE_TH_TAX_PRICE'); ?></th>
                                            <th class="text-center align-middle"><?= Loc::getMessage('AV_TABLE_TH_DISCOUNT'); ?></th>
                                            <th class="text-center"><?= Loc::getMessage('AV_TABLE_TH_PRICE_DISCOUNT'); ?></th>
                                            <th class="text-center"><?= Loc::getMessage('AV_TABLE_TH_TAX_PRICE_DISCOUNT'); ?></th>
                                            <th class="text-center align-middle"><?= Loc::getMessage('AV_TABLE_TH_VALUE'); ?></th>
                                            <th class="text-center align-middle"><?= Loc::getMessage('AV_TABLE_TH_TAX_RUB'); ?></th>
                                            <th class="text-center align-middle"><?= Loc::getMessage('AV_TABLE_TH_TAX_VALUE'); ?></th>
                                            <th class="text-center align-middle"><?= Loc::getMessage('AV_TABLE_TH_DISPATCHED'); ?></th>
                                            <th class="text-center align-middle"><?= Loc::getMessage('AV_TABLE_TH_BALANCE_REMAINS'); ?></th>
                                            <th class="text-center align-middle"><?= Loc::getMessage('AV_TABLE_TH_CHANGED'); ?></th>
                                        </tr>
                                        <?= $arResult['HTML']; ?>
                                    </thead>
                                    <tbody>
                                        <?php if (!$arResult['PRODUCTS']) : ?>
                                            <td colspan="15"><?= Loc::getMessage('REQ_EDIT_EMPTY_PRODUCT_LIST'); ?></td>
                                        <?php else : ?>
                                            <?php foreach ($arResult['PRODUCTS'] as $product1cId => $product) : ?>
                                                <?php
                                                if(!$visibleSpecFields && !empty($product['UF_DISCOUNT'])) {
                                                    $visibleSpecFields = true;
                                                }
                                                $measurement = ($product['IS_TON'] || $product['UF_NONPALLET'])
                                                    ? $product['MEASUREMENT']['SYMBOL'] 
                                                    : Loc::getMessage('AV_TABLE_PRODUCT_UNIT_PALLET');
                                                $changesFields = !empty($arResult['HISTORY_PRODUCT'][$product1cId]['FIELDS']) && is_array($arResult['HISTORY_PRODUCT'][$product1cId]['FIELDS']) 
                                                    ? $arResult['HISTORY_PRODUCT'][$product1cId]['FIELDS']
                                                    : [];
                                                
                                                $count = Formatter::formatDouble($product['UF_PRODUCTS_CNT'], ($product['IS_TON'] ? 3 : 0), '.', ' ') . ' ' . $measurement;
                                                $price = $product['UF_PRICE'] ? Formatter::formatDouble($product['UF_PRICE'], 2, '.', ' ') : '';
                                                $taxPrice = $product['UF_TAX_PRICE'] ? Formatter::formatDouble($product['UF_TAX_PRICE'], 2, '.', ' ') : '';
                                                $discount = $product['UF_DISCOUNT'] ? Formatter::formatDouble($product['UF_DISCOUNT'], 2, '.', ' ') : '';
                                                $priceDiscount = $product['UF_PRICE_DISCOUNT'] ? Formatter::formatDouble($product['UF_PRICE_DISCOUNT'], 2, '.', ' ') : '';
                                                $taxPriceDiscont = $product['UF_TAX_PRICE_DISCONT'] ? Formatter::formatDouble($product['UF_TAX_PRICE_DISCONT'], 2, '.', ' ') : '';
                                                $value = $product['UF_VALUE'] ? Formatter::formatDouble($product['UF_VALUE'], 2, '.', ' ') : '';
                                                $taxRub = $product['UF_TAX_RUB'] ? Formatter::formatDouble($product['UF_TAX_RUB'], 2, '.', ' ') : '';
                                                $taxValue = $product['UF_TAX_VALUE'] ? Formatter::formatDouble($product['UF_TAX_VALUE'], 2, '.', ' ') : '';
                                                
                                                $dataTableRows[] = [
                                                    '<div class="product" data-code="' . $product['CODE'] . '">' .
                                                        '<span class="product_name">' . $product['NAME'] . '</span>' .
                                                        '<span class="product_info">' .
                                                            Loc::getMessage('AV_TABLE_PRODUCT_NUMBER') . ': ' . $product['CODE'] . ' ' .
                                                            (!$product['IS_TON']
                                                                ? Loc::getMessage('AV_TABLE_PRODUCT_CAPACITY') . ': ' . $product['CAPACITY'] . Loc::getMessage('AV_TABLE_PRODUCT_CAPACITY_MEAS')
                                                                : ''
                                                            ) .
                                                        '</span>' .
                                                    '</div>',
                                                    !empty($changesFields['UF_PRODUCTS_CNT']) ? "<b>{$count}</b>" : $count,
                                                    $product['PRICE_TYPE'],
                                                    !empty($product['UF_SPECIAL_PRICE']) ? '<div class="text-center align-middle"><i class="la la-check"></i></div>' : '',
                                                    !empty($changesFields['UF_PRICE']) ? "<b>{$price}</b>" : $price,
                                                    !empty($changesFields['UF_TAX_PRICE']) ? "<b>{$taxPrice}</b>" : $taxPrice,
                                                    !empty($changesFields['UF_DISCOUNT']) ? "<b>{$discount}</b>" : $discount,
                                                    !empty($changesFields['UF_PRICE_DISCOUNT']) ? "<b>{$priceDiscount}</b>" : $priceDiscount,
                                                    !empty($changesFields['UF_TAX_PRICE_DISCONT']) ? "<b>{$taxPriceDiscont}</b>" : $taxPriceDiscont,
                                                    !empty($changesFields['UF_VALUE']) ? "<b>{$value}</b>" : $value,
                                                    !empty($changesFields['UF_TAX_RUB']) ? "<b>{$taxRub}</b>" : $taxRub,
                                                    !empty($changesFields['UF_TAX_VALUE']) ? "<b>{$taxValue}</b>" : $taxValue,
                                                    $additional['UF_1C_ID'] 
                                                        ? Formatter::formatDouble($product['UF_DISPATCHED'], ($product['IS_TON'] ? 3 : 0), '.', ' ') . ' ' . $measurement
                                                        : '',
                                                    $additional['UF_1C_ID'] 
                                                        ? Formatter::formatDouble($product['UF_BALANCE_REMAINS'], ($product['IS_TON'] ? 3 : 0), '.', ' ') . ' ' . $measurement
                                                        : '',
                                                    $product['CHANGED'] ? Loc::getMessage('AV_CHANGED') : ''
                                                ];
                                                ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <?= $arResult['HTML']; ?>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php if ($additional['CAN_EDIT'] || $additional['CAN_CANCEL']
                            || (!empty($arResult['PRODUCTS']) && $additional['ACCESS_INVOICE'])) : ?>
                        <div class="card-body">
                            <div class="form-actions text-right">
                                <?php if ($additional['CAN_CANCEL']) : ?>
                                    <a href="javascript:void();"
                                       onclick="JsAgreementsCancel.cancel(<?= $additional['ID']; ?>);"
                                        class="btn btn-round btn-secondary">
                                        <?= Loc::getMessage('AV_BTN_CANCEL') ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($additional['CAN_EDIT']) : ?>
                                    <a href="/agreements/edit/<?= $additional['ID']; ?>/"
                                        class="btn btn-round btn-warning">
                                        <?= Loc::getMessage('AV_BTN_EDIT') ?>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($arResult['PRODUCTS']) && $arResult['ADDITIONAL']['ACCESS_INVOICE']) : ?>
                                    <button type="submit"
                                            name="action" 
                                            value="request_invoice"
                                            class="request_invoice_btn btn btn-round btn-info">
                                        <?= Loc::getMessage('AV_BTN_REQUEST_INVOICE') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="history-product-content">
                <?php foreach($arResult['HISTORY_PRODUCT'] as $historyProduct) : ?>
                    <?php if (!empty($historyProduct['HISTORY'])) : ?>
                        <div class="product-history-<?=$historyProduct['CODE']; ?> hidden tooltip-inner-product p-1">
                            <p class="mt-1 ml-1"><b><?= Loc::getMessage("AV_HISTORY_PRODUCT_NAME", ["#NAME#" => $historyProduct['NAME']]); ?></b></p>
                            <table class="table table-sm">
                                <?php foreach($historyProduct['HISTORY'] as $history) : ?>
                                    <tr>
                                        <td><?= $history['date']; ?></td>
                                        <td><?= $history['changes']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (!empty($arResult['HISTORY_DATE'])) : ?>
            <div class="row">
                <div class="col-12">
                    <div class="content-header row">
                        <div class="content-header-left col-6 mb-2 mt-1">
                            <h4 class="content-header-title"><?= Loc::getMessage('AV_HISTORY_TITLE'); ?></h4>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-content collapse show">
                            <div class="card-body card-dashboard">
                                <div class="content-header row">
                                    <div class="content-header-left col-6 mb-2 mt-1">
                                        <div class="sort-history ">
                                            <?= Loc::getMessage('AV_HISTORY_SORT_BY'); ?>
                                            <a href="#" class="sort-history-button ml-1" data-sort="date"><?= Loc::getMessage('AV_HISTORY_SORT_BY_DATE'); ?></a>
                                            <a href="#" class="sort-history-button ml-1 sort-history-selected" data-sort="code"><?= Loc::getMessage('AV_HISTORY_SORT_BY_PRODUCT'); ?></a>
                                        </div>
                                    </div>
                                    <div class="content-header-left col-6 mb-2">
                                        <span type="button" class="btn btn-warning btn-warning-gpnsm round btn-min-width history-toggle pull-right">
                                            <span id="history-toggle-text"><?= Loc::getMessage('AV_HISTORY_ACTION_ALL_DOWN'); ?></span>
                                            <i class="ft-chevron-down" style="font-weight: 700;"></i>
                                        </span>
                                    </div>
                                </div>
                                <table class="table-sm additional-history-date hidden table-history">
                                    <thead>
                                        <tr>
                                            <th class="table-history-date"><?= Loc::getMessage('AV_HISTORY_TABLE_COLUMN_DATE'); ?></th>
                                            <th class="table-history-code"><?= Loc::getMessage('AV_HISTORY_TABLE_COLUMN_PRODUCT'); ?></th>
                                            <th><?= Loc::getMessage('AV_HISTORY_TABLE_COLUMN_EVENT'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($arResult['HISTORY_DATE'] as $date => $products) : ?>
                                            <?php $i = 0; ?>
                                            <tr><td colspan="3"><hr></td></tr>
                                            <?php foreach ($products as $code => $changes) : ?>
                                                <tr>
                                                    <td><b><?= $i == 0 ? $date : ""; ?></b></td>
                                                    <td>
                                                        <?php if ($code) { ?>
                                                            <div class="product">
                                                                <span class="product_name"><?= $arResult['HISTORY_PRODUCT'][$code]['NAME'] ?: ''; ?></span>
                                                                <span class="product_info"><?= Loc::getMessage('AV_HISTORY_TABLE_PRODUCT_CODE'); ?><?= $code ?: ''; ?></span>
                                                            </div>
                                                        <?php } ?>
                                                    </td>
                                                    <td><?= $changes; ?></td>
                                                </tr>
                                                <?php $i++; ?>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <table class="table-sm additional-history-code hidden table-history">
                                    <thead>
                                        <tr>
                                            <th class="table-history-code"><?= Loc::getMessage('AV_HISTORY_TABLE_COLUMN_PRODUCT'); ?></th>
                                            <th class="table-history-date"><?= Loc::getMessage('AV_HISTORY_TABLE_COLUMN_DATE'); ?></th>
                                            <th><?= Loc::getMessage('AV_HISTORY_TABLE_COLUMN_EVENT'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($arResult['HISTORY_CODE'] as $code => $products) : ?>
                                            <?php $i = 0; ?>
                                            <tr><td colspan="3"><hr></td></tr>
                                            <?php foreach ($products as $date => $changes) : ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($i == 0 && !empty($code)) { ?>
                                                            <div class="product">
                                                                <span class="product_name"><?= $arResult['HISTORY_PRODUCT'][$code]['NAME'] ?: ''; ?></span>
                                                                <span class="product_info"><?= Loc::getMessage('AV_HISTORY_TABLE_PRODUCT_CODE'); ?><?= $code ?: ''; ?></span>
                                                            </div>
                                                        <?php } ?>
                                                    </td>
                                                    <td><b><?= $date; ?></b></td>
                                                    <td><?= $changes; ?></td>
                                                </tr>
                                                <?php $i++; ?>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <script type="text/javascript">
            var dataTable = <?= json_encode($dataTableRows) ?>;
            var visibleSpecFields = <?= (int)$visibleSpecFields ?>;
            BX.message({
                popupInfoPath: '<?= Loc::getMessage('AV_BTN_POPUP_INFO_PATH'); ?>',
                downloadInvoice: '<?= Loc::getMessage('AV_BTN_DOWNLOAD_INVOICE'); ?>',
                close: '<?= Loc::getMessage('AV_BTN_CLOSE'); ?>',
                actionAllUp: '<?= Loc::getMessage('AV_HISTORY_ACTION_ALL_UP'); ?>',
                actionAllDown: '<?= Loc::getMessage('AV_HISTORY_ACTION_ALL_DOWN'); ?>'
            });
        </script>
    <?php endif; ?>
</div>
<div class="conf-modal-request-invoice" style="display: none;"></div>
