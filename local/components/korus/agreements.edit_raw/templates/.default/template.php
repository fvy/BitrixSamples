<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

$oAssets = \Bitrix\Main\Page\Asset::getInstance();

$oAssets->addCss("/local/assets/css/plugins/forms/validation/form-validation.min.css");
$oAssets->addJs("/local/assets/js/jquery.number.js");

$oAssets->addJs("/local/assets/js/jquery.bootstrap-touchspin.js");
$oAssets->addJs("/local/assets/js/dialog-tooltip.min.js");

$oAssets->addJs("/local/assets/js/vendors/tables/datatable/datatables.min.js");
$oAssets->addCss("/local/assets/css/vendors/tables/datatable/datatables.min.css");

$oAssets->addJs("/local/assets/js/datatable/render.js");

$oAssets->addJs('/local/assets/js/icheck.min.js');
$oAssets->addCss('/local/assets/css/square/square.css');
$oAssets->addCss('/local/assets/css/square/blue.css');
$oAssets->addCss('/local/assets/css/square/aero.css');
$oAssets->addCss('/local/assets/css/flat/blue.css');

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
?>
<div class="content-header row mb-2">
    <div class="content-header-left col-md-6 col-12">
        <h3 class="content-header-title"><? $APPLICATION->ShowTitle() ?></h3>
    </div>
</div>
<div class="content-body">
    <?php if ($oErrors->getErrorByCode('CRITICAL_ERROR') ?? false): ?>
        <div class="alert alert-danger mb-2" role="alert">
            <ul>
                <?php $showErrorList('CRITICAL_ERROR') ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="row" id="agreement-data-block">
            <div class="col-12">
                <form class="form" id="gpnsm_additional_raw" action="" method="post" enctype="multipart/form-data">
                    <section class="basic-form-layouts">
                        <div class="card">
                            <div class="card-header"></div>
                            <div class="card-content collapse show">
                                <div class="card-body">
                                    <div class="card-text">
                                        <p><?= Loc::getMessage('K_C_ADDITIONAL_RAW_HEADER_TEXT') ?></p>
                                    </div>
                                    <div class="form-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <input name="contract" type="hidden" value="<?= $arResult['contract']['UF_1C_ID'] ?>">
                                                <div class="form-group">
                                                    <label for="contract" class="required">
                                                        <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_CONTRACT_TITLE') ?> <span class="required">*</span>
                                                    </label>
                                                    <div class="row">
                                                        <div class="form-group col-md-11">
                                                            <input id="contract"
                                                                   class="form-control"
                                                                   value="<?= $arResult['contract']['UF_CONTRACT_NUMBER'] ?>"
                                                                   readonly/>
                                                        </div>
                                                        <div class="ml-1">
                                                            <i class="icon-info"
                                                               data-toggle="tooltip"
                                                               data-placement="right"
                                                               data-original-title="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_CONTRACT_HINT') ?>"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="receiver">
                                                        <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_RECEIVER_TITLE') ?>
                                                    </label>
                                                    <div class="row">
                                                        <div class="form-group col-md-11">
                                                            <select id="receiver"
                                                                    name="receiver"
                                                                    data-placeholder="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_RECEIVER_PLACEHOLDER') ?>"
                                                                    class="form-control">
                                                                <option></option>
                                                                <?php foreach ($arResult['receivers'] as $receiver) {
                                                                    $title = [$receiver['UF_NAME']];
                                                                    if(!empty($receiver['UF_INN'])) {
                                                                        $title[] = Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_RECEIVER_INN') . ': ' . $receiver['UF_INN'];
                                                                    }
                                                                    if(!empty($receiver['UF_KPP'])) {
                                                                        $title[] = Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_RECEIVER_KPP') . ': ' . $receiver['UF_KPP'];
                                                                    }
                                                                    $optionTitle = implode(', ', $title);
                                                                    ?>
                                                                    <option 
                                                                        value="<?= $receiver['UF_1C_ID'] ?>" 
                                                                        <?= $arResult['addition']['UF_RECEIVER'] == $receiver['UF_1C_ID'] ? "selected='selected'" : "";?>
                                                                        >
                                                                        <?= $optionTitle ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>
                                                        </div>
                                                        <div class="ml-1">
                                                            <i class="icon-info"
                                                               data-toggle="tooltip"
                                                               data-placement="right"
                                                               data-original-title="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_RECEIVER_HINT') ?>"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group<?= empty($arResult['periodList']) ? ' error' : '' ?>">
                                                    <label for="period">
                                                        <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_PERIOD_TITLE') ?> <span class="required">*</span>
                                                    </label>
                                                    <div class="row">
                                                        <div class="form-group col-md-11">
                                                            <select id="period"
                                                                    name="period"
                                                                    class="form-control">
                                                                <?php foreach ($arResult['periodList'] as $periodKey => $period) {
                                                                    $periodTitle = Loc::getMessage('MONTH_' . $period->format('n')) . ' ' . $period->format('Y');
                                                                    ?>
                                                                    <option value="<?= $periodKey ?>"><?= $periodTitle ?></option>
                                                                <?php } ?>
                                                            </select>
                                                            <?php if (empty($arResult['periodList'])) { ?>
                                                                <div class="help-block">
                                                                    <ul>
                                                                        <li><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_PERIOD_ERROR') ?></li>
                                                                    </ul>
                                                                </div>
                                                            <?php } ?>
                                                        </div>
                                                        <div class="ml-1">
                                                            <i class="icon-info"
                                                               data-toggle="tooltip"
                                                               data-placement="right"
                                                               data-original-title="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_PERIOD_HINT') ?>"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="delivery-type">
                                                        <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_DELIVERY_TYPE_TITLE') ?> <span class="required">*</span>
                                                    </label>
                                                    <div class="row">
                                                        <div class="form-group col-md-11">
                                                            <select id="delivery-type"
                                                                    name="deliveryType"
                                                                    data-placeholder="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_DELIVERY_TYPE_PLACEHOLDER') ?>"
                                                                    class="form-control">
                                                                <option value=""></option>
                                                                <?php foreach ($arResult['deliveryTypes'] as $deliveryType) { ?>
                                                                    <option 
                                                                        value="<?= $deliveryType['UF_1C_ID'] ?>"
                                                                        <?= $arResult['addition']['UF_DELIVERY_TYPE'] == $deliveryType['UF_1C_ID'] ? "selected='selected'" : "";?>>
                                                                        <?= $deliveryType['UF_NAME'] ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>
                                                            <div class="help-block hidden">
                                                                <ul>
                                                                    <li><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_DELIVERY_TYPE_ERROR') ?></li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="store">
                                                        <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_STORE_TITLE') ?> <span class="required">*</span>
                                                    </label>
                                                    <div class="row">
                                                        <div class="form-group col-md-11">
                                                            <select id="store"
                                                                    name="store"
                                                                    class="form-control">
                                                                <?php foreach ($arResult['stores'] as $store) { ?>
                                                                    <option 
                                                                        value="<?= $store['UF_CODE_ASKU'] ?>"
                                                                        <?= $arResult['addition']['UF_SHIPPING_STORE'] == $store['UF_CODE_ASKU'] ? "selected='selected'" : "";?>>
                                                                        <?= $store['TITLE'] ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="transportation-type">
                                                        <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TRANSPORTATION_TYPE_TITLE') ?> <span class="required">*</span>
                                                    </label>
                                                    <div class="row">
                                                        <div class="form-group col-md-11">
                                                            <select id="transportation-type"
                                                                    name="transportationType"
                                                                    data-placeholder="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TRANSPORTATION_TYPE_PLACEHOLDER') ?>"
                                                                    class="form-control">
                                                                <option value=""></option>
                                                                <?php foreach ($arResult['transportationTypes'] as $transportationType) { ?>
                                                                    <option 
                                                                        value="<?= $transportationType['UF_1C_ID'] ?>"
                                                                        <?= $arResult['addition']['UF_TRANSPORTATION_TY'] == $transportationType['UF_1C_ID'] ? "selected='selected'" : "";?>>
                                                                        <?= $transportationType['UF_NAME'] ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>
                                                            <div class="help-block hidden">
                                                                <ul>
                                                                    <li><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TRANSPORTATION_TYPE_ERROR') ?></li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="comments">
                                                        <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_COMMENT_TITLE') ?>
                                                    </label>
                                                    <div class="row">
                                                        <div class="form-group col-md-11">
                                                            <textarea rows="6"
                                                                      id="comments"
                                                                      class="form-control"
                                                                      placeholder="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_COMMENT_PLACEHOLDER') ?>"
                                                                      name="comments"><?= $arResult['addition']['UF_COMMENT'];?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group js-toggle">
                                                    <label for="delivery-point">
                                                        <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_DELIVERY_POINT_TITLE') ?>
                                                    </label>
                                                    <div class="row">
                                                        <div class="form-group col-md-11">
                                                            <select id="delivery-point"
                                                                    name="deliveryPoint"
                                                                    data-placeholder="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_DELIVERY_POINT_PLACEHOLDER') ?>"
                                                                    class="form-control title-disabled">
                                                                <?php if ($arResult['addition']['UF_DELIVERY_POINT']) : ?>
                                                                <option value="<?=$arResult['addition']['UF_DELIVERY_POINT'];?>" selected="selected">
                                                                    <?=$arResult['addition']['DELIVERY_POINT_NAME'];?>
                                                                </option>
                                                                <?php endif; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group js-toggle" id="obj-delivery-address">
                                                    <label for="delivery-address" class="obj-delivery-address">
                                                        <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_DELIVERY_ADDRESS_TITLE') ?>
                                                    </label>
                                                    <div class="row">
                                                        <div class="form-group col-md-11">
                                                            <select id="delivery-address"
                                                                    name="deliveryAddress"
                                                                    data-placeholder="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_DELIVERY_ADDRESS_PLACEHOLDER') ?>"
                                                                    class="form-control title-disabled">
                                                                <?php if ($arResult['addition']['UF_DELIVERY_ADDRESS']) : ?>
                                                                <option value="<?=$arResult['addition']['UF_DELIVERY_ADDRESS'];?>" selected="selected">
                                                                    <?=$arResult['addition']['DELIVERY_ADDRESS_NAME'];?>
                                                                </option>
                                                                <?php endif; ?>
                                                            </select>
                                                        </div>
                                                        <div class="ml-1">
                                                            <i class="icon-info"
                                                               data-toggle="tooltip"
                                                               data-placement="right"
                                                               data-original-title="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_DELIVERY_ADDRESS_HINT') ?>"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="content-header row">
                    <div class="content-header-left col-md-6 col-12 mb-2">
                        <h4 class="content-header-title"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_PRODUCTS_TITLE'); ?></h4>
                    </div>
                </div>
                <div class="card js-data-table-block">
                    <div class="card-content collapse show overflow-hidden">
                        <div class="card-body card-dashboard pb-0">
                            <form class="form">
                                <div class="row">
                                    <div class="row col-6">
                                        <fieldset class="form-group position-relative has-icon-left col-md-8">
                                            <input type="text" class="form-control" id="search_field" placeholder="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FILTER_SEARCH'); ?>">
                                            <div class="form-control-position">
                                                <i class="ficon la la-search"></i>
                                            </div>
                                        </fieldset>
                                        <fieldset class="form-group">
                                            <a href="#" id="clear_search_field" style="display:flex; margin:10px 0 0;text-decoration: underline;">
                                                <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FILTER_SEARCH_CLEAR'); ?>
                                            </a>
                                        </fieldset>
                                    </div>
                                    <div class="row col-6 pr-1 ml-auto">
                                        <fieldset class="form-group col-xl-3 col-lg-6">
                                            <select id="filter-price-agreements" class="form-control">
                                                <option value="" selected=""><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FILTER_PRICE_AGREEMENTS'); ?></option>
                                            </select>
                                        </fieldset>
                                        <fieldset class="form-group col-xl-3 col-lg-6">
                                            <select id="filter-price-type" class="form-control">
                                                <option value="" selected=""><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FILTER_PRICE_TYPE'); ?></option>
                                            </select>
                                        </fieldset>
                                        <fieldset class="form-group col-xl-3 col-lg-6">
                                            <select id="filter-brand" class="form-control">
                                                <option value="" selected=""><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FILTER_BRAND'); ?></option>
                                            </select>
                                        </fieldset>
                                        <fieldset class="form-group col-xl-3 col-lg-6 icheckbox-field">
                                            <div class="checkbox-container">
                                                <label for="search-not-empty" class="checkbox-container__label">
                                                    <input type="checkbox" id="search-not-empty" class="checkbox-container__input"/>
                                                    <span class="checkbox-container__span">
                                                        <?= Loc::getMessage('K_C_ADDITIONAL_RAW_CHECKBOX_HIDE_EMPTY'); ?>
                                                    </span>
                                                </label>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <?php
                                $totalRowHtml =
                                    '<tr class="totals">' .
                                        '<td></td>' .
                                        '<td colspan="2" class="text-left">' .
                                            Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TOTAL_TITLE') .
                                            ' <span class="js-table-totals-count">0</span> ' .
                                            ' <span class="js-table-totals-position">' . Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TABLE_TOTAL_POSITION')[2] . '</span>, ' .
                                            Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TOTAL_TEXT_1') .
                                            ' <span class="js-table-totals-weight">0.000</span> ' .
                                            Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TOTAL_UNIT_TON') .
                                            ' <i class="icon-info" ' .
                                               'data-toggle="tooltip" ' .
                                               'data-placement="right" ' .
                                               'data-original-title="' . Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TOTAL_HINT') . '"></i>' .
                                        '</td>' .
                                        '<td class="text-center td-matched js-matched_pallet"><span class="matched-pallet-total"></span></td>' .
                                        '<td class="text-center td-matched js-matched_count"><span class="matched-count-total"></span></td>' .
                                        '<td class="text-center td-allowed js-allowed_pallet"><span class="allowed-pallet-total"></span></td>' .
                                        '<td class="text-center td-allowed js-allowed_count"><span class="allowed-count-total"></span></td>' .
                                        '<td class="text-center js-volume_pallet"><span class="volume-pallet-total"></span></td>' .
                                        '<td class="text-center js-volume_count"><span class="volume-count-total"></span></td>' .
                                        '<td></td>' .
                                        '<td colspan="3"></td>' .
                                        '<td></td>' .
                                        '<td></td>' .
                                        '<td class="text-center"><span class="price-total">0.00</span> руб.</td>' .
                                        '<td></td>' .
                                    '</tr>';
                                ?>
                                <table class="table table-sm table-bordered table-middle table-striped zero-configuration table-white-space" style="width: 100%">
                                    <thead>
                                        <tr>
                                            <td rowspan="2" class="text-center"><i class="ficon la la-trash js-remove-rows"></i></td>
                                            <th rowspan="2"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_NAME') ?></th>
                                            <th rowspan="2"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_BRAND') ?></th>
                                            <th colspan="2" class="js-th-toggle th-matched"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_MATCHED') ?></th>
                                            <th colspan="2" class="js-th-toggle th-allowed"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_AVAILABLE') ?></th>
                                            <th colspan="2" class="js-th-toggle"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_REQUEST') ?></th>
                                            <th rowspan="2"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_NOT_PALLET') ?></th>
                                            <th rowspan="2" style="min-width: 300px;"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_PRICE') ?></th>
                                            <th rowspan="2">
                                                <div class="fix-hind-title">
                                                    <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_50_50') ?> 
                                                </div>
                                                <i class="icon-info" 
                                                   data-toggle="tooltip" 
                                                   data-placement="right" 
                                                   data-original-title="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_50_50_HINT');?>"></i>
                                            </th>
                                            <th rowspan="2">
                                                <div class="fix-hind-title">
                                                    <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_IS_PRICE_SPECIAL') ?> 
                                                </div>
                                                <i class="icon-info" 
                                                   data-toggle="tooltip" 
                                                   data-placement="right" 
                                                   data-original-title="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_IS_PRICE_SPECIAL_HINT');?>"></i>
                                            </th>
                                            <th rowspan="2"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_IS_PRICE_PIECE') ?></th>
                                            <th rowspan="2">
                                                <div class="fix-hind-title">
                                                    <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_IS_PRICE_PIECE_NDS') ?> 
                                                </div>
                                                <i class="icon-info" 
                                                   data-toggle="tooltip" 
                                                   data-placement="right" 
                                                   data-original-title="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_IS_PRICE_HINT');?>"></i>
                                            </th>
                                            <th rowspan="2">
                                                <div class="fix-hind-title">
                                                    <?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_IS_PRICE_TOTAL') ?> 
                                                </div>
                                                <i class="icon-info" 
                                                   data-toggle="tooltip" 
                                                   data-placement="right" 
                                                   data-original-title="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_IS_PRICE_HINT');?>"></i>
                                            </th>
                                            <th rowspan="2"></th>
                                        </tr>
                                        <tr class="js-header-units">
                                            <th class="th-matched"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_PALLET') ?></th>
                                            <th class="th-matched"><span class="store-unit"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_UNIT') ?></span></th>
                                            <th class="th-allowed"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_PALLET') ?></th>
                                            <th class="th-allowed"><span class="store-unit"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_UNIT') ?></span></th>
                                            <th><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_PALLET') ?></th>
                                            <th class="brw-1"><span class="store-unit"><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_UNIT') ?></span></th>
                                        </tr>
                                        <?= $totalRowHtml ?>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                    <tfoot>
                                        <tr class="hidden"><th colspan="13"></tr>
                                            <?= $totalRowHtml ?>
                                        <tr>
                                            <td class="text-center"><i class="ficon la la-trash js-remove-rows"></i></td>
                                            <td colspan="16">
                                                <div class="row col-md-5">
                                                    <select class="form-control"
                                                            id="revert-deleted-row"
                                                            data-placeholder="<?= Loc::getMessage('K_C_ADDITIONAL_RAW_REVERT_PLACEHOLDER'); ?>">
                                                        <option></option>
                                                    </select>
                                                </div>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </form>
                        </div>
                    </div>
                    <form>
                        <div class="card-body pt-0">
                            <div class="form-actions right">
                                <button id="save_raw_btn" 
                                        type="button"
                                        value="saveRaw" 
                                        class="js-submit btn btn-round btn-warning-gpnsm">
                                    <?= Loc::getMessage('K_C_ADDITIONAL_RAW_BTN_SEND_RAW') ?>
                                </button>
                                <button id="save_btn"
                                        class="js-submit btn btn-round btn-warning-gpnsm"
                                        type="button"
                                        value="save"
                                        >
                                    <?= Loc::getMessage('K_C_ADDITIONAL_RAW_BTN_SEND') ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="conf-modal-change-contract" style="display: none;">
    <p><?= Loc::getMessage('CONTRACT_CHANGE_ALERT') ?></p>
</div>
<div class="conf-modal-change-period" style="display: none;">
    <p><?= Loc::getMessage('K_C_ADDITIONAL_RAW_PERIOD_CHANGE_ALERT') ?></p>
</div>
<div class="conf-modal-change-store" style="display: none;">
    <p><?= Loc::getMessage('K_C_ADDITIONAL_RAW_STORE_CHANGE_ALERT') ?></p>
</div>
<div class="conf-modal-advert" style="display: none;">
    <p><?= Loc::getMessage('K_C_ADDITIONAL_RAW_ADVERT_ALERT') ?></p>
</div>
<div class="conf-modal-price-agreements" style="display: none;">
    <p><?= Loc::getMessage('K_C_ADDITIONAL_RAW_FILTER_PRICE_AGREEMENTS_ALERT') ?></p>
</div>

<script type="text/javascript">
  $(document).ready(function(){
    BX.message({
      'TH_UNIT_TON': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_UNIT_TON') ?>',
      'TH_UNIT': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TH_UNIT') ?>',
      'PRODUCT_NUMBER': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_PRODUCT_NUMBER') ?>',
      'PRODUCT_CAPACITY': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_PRODUCT_CAPACITY') ?>',
      'PRODUCT_CAPACITY_UNIT': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_PRODUCT_CAPACITY_UNIT') ?>',
      'UNIT_PALLET': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_UNIT_PALLET') ?>',
      'UNIT_PIECES': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_UNIT_PIECES') ?>',
      'UNIT_TON': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TOTAL_UNIT_TON') ?>',
      'FILTER_BRAND_NULL_VALUE': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FILTER_BRAND'); ?>',
      'FILTER_PRICE_AGREEMENT_NULL_VALUE': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FILTER_PRICE_AGREEMENTS'); ?>',
      'FILTER_PRICE_TYPE_NULL_VALUE': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FILTER_PRICE_TYPE'); ?>',
      'PRICE_PLACEHOLDER': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_PRICE_PLACEHOLDER'); ?>',
      'CROSS_MESSAGE_TEMPLATE': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_PRODUCTS_CROSS_TEMPLATE'); ?>',
      'positionArr': <?= json_encode(Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_TABLE_TOTAL_POSITION')); ?>,
      'EXCEEDED_LIMIT_MESSAGE_TEMPLATE_DISABLED_HINT': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_PRODUCTS_EXCEEDED_LIMIT_TEMPLATE_DISABLED_HINT'); ?>',
      'EXCEEDED_LIMIT_MESSAGE_TEMPLATE': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_PRODUCTS_EXCEEDED_LIMIT_TEMPLATE'); ?>',
      'EXCEEDED_LIMIT_MESSAGE_TEMPLATE_RESET_LIMIT': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_PRODUCTS_RESET_LIMIT'); ?>',
      'EXCEEDED_LIMIT_MESSAGE_TEMPLATE_INCREASE_LIMIT': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_PRODUCTS_INCREASE_LIMIT'); ?>',
      'K_C_ADDITIONAL_MODAL_AVAILABLE_VOLUME': '<?= Loc::getMessage('K_C_ADDITIONAL_MODAL_AVAILABLE_VOLUME'); ?>',
      'K_C_ADDITIONAL_MODAL_DECLARED_VOLUME': '<?= Loc::getMessage('K_C_ADDITIONAL_MODAL_DECLARED_VOLUME'); ?>',
      'productsEndingArr': <?= json_encode(Loc::getMessage('K_C_ADDITIONAL_RAW_PRODUCTS_ENDING')); ?>,
      'K_C_ADDITIONAL_MODAL_NAME_OF_PRODUCT': '<?= Loc::getMessage('K_C_ADDITIONAL_MODAL_NAME_OF_PRODUCT'); ?>',
      'K_C_ADDITIONAL_MODAL_PRICE_AGREEMENT': '<?= Loc::getMessage('K_C_ADDITIUNIT_PALLETONAL_MODAL_PRICE_AGREEMENT'); ?>',
      'K_C_ADDITIONAL_MODAL_PRICE_TYPE': '<?= Loc::getMessage('K_C_ADDITIONAL_MODAL_PRICE_TYPE'); ?>',
      'K_C_ADDITIONAL_MODAL_ADDITIONAL_NUMBER': '<?= Loc::getMessage('K_C_ADDITIONAL_MODAL_ADDITIONAL_NUMBER'); ?>',
      'K_C_ADDITIONAL_MODAL_ADDITIONAL_NUM': '<?= Loc::getMessage('K_C_ADDITIONAL_MODAL_ADDITIONAL_NUM'); ?>',
      'K_C_ADDITIONAL_MODAL_TITLE': '<?= Loc::getMessage('K_C_ADDITIONAL_MODAL_TITLE'); ?>',
      'K_C_ADDITIONAL_MODAL_BUTTON_OK_TITLE': '<?= Loc::getMessage('K_C_ADDITIONAL_MODAL_BUTTON_OK_TITLE'); ?>',
      'K_C_ADDITIONAL_MODAL_BUTTON_CANCEL_TITLE': '<?= Loc::getMessage('K_C_ADDITIONAL_MODAL_BUTTON_CANCEL_TITLE'); ?>',
      'K_C_ADDITIONAL_MODAL_ADDITIONAL_AMOUNT': '<?= Loc::getMessage('K_C_ADDITIONAL_MODAL_ADDITIONAL_AMOUNT'); ?>',
      'K_C_ADDITIONAL_RAW_FORM_RECEIVER_NOT_ELEMENT': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_FORM_RECEIVER_NOT_ELEMENT') ?>',
      'BTN_ALL_VOLUES': '<?= Loc::getMessage('K_C_ADDITIONAL_RAW_BTN_ALL_VOLUES') ?>'
    });

    Additionals.Raw.init({
      ajaxUrl: '/agreements/create_agreement_ajax.php',
      ajaxUrlRaw: '/agreements/raw_agreement_ajax.php',
      measures: <?= json_encode($arResult['measures']); ?>,
      additionProducts: <?= json_encode($arResult['additionProducts']); ?>,
      remains5050: <?= (float)$arResult['contract']['UF_REMAINS_50_50']; ?>,
      successUrl: '<?= $arParams['SUCCESS_URL']; ?>',
      afterSaveUrl: '<?= $arParams['AFTER_SAVE_URL']; ?>',
      afterSaveRawUrl: '<?= $arParams['AFTER_SAVE_RAW_URL']; ?>',
      rawId: '<?= $arResult['addition']['ID']; ?>'
    });
  });
</script>
