<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */

/** @var CBitrixComponent $component */
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;
use Korus\Basic\Helpers\Formatter;

$asset = Asset::getInstance();
$asset->addJs(SITE_DIR . 'local/assets/js/vendors/tables/datatable/datatables.min.js');
$asset->addJs(SITE_DIR . 'local/assets/js/datatable/render.js');
$asset->addCss(SITE_DIR . 'local/assets/css/vendors/tables/datatable/datatables.min.css');
$asset->addJs(SITE_DIR . 'local/assets/js/agreements/cancel.js');

$dataTable = [];
?>
<div class="content-header row">
    <div class="content-header-left col-md-6 col-12 mb-2">
        <h3 class="content-header-title"><? $APPLICATION->ShowTitle() ?></h3>
    </div>
    <div class="content-header-right col-md-6 col-12">
        <div class="btn-group float-md-right" role="group" aria-label="Create addition">
            <a href="/agreements/create_agreement/"
               class="btn btn-round btn-info px-2"
               type="button"
               aria-haspopup="false"
               aria-expanded="false"><?= Loc::getMessage('AGRLST_CREATE_ADDITION'); ?></a>
        </div>
    </div>
</div>
<div class="content-body">
    <div class="card">
        <div class="card-body card-dashboard">
            <div class="row">
                <div class="row col-md-6">
                    <fieldset class="form-group position-relative has-icon-left col-md-8">
                        <input type="text"
                               class="form-control" id="agreements_search_field"
                               placeholder="<?= Loc::getMessage('AGRLST_FILTER_SEARCH'); ?>">
                        <div class="form-control-position">
                            <i class="ficon la la-search"></i>
                        </div>
                    </fieldset>
                    <fieldset class="form-group">
                        <a href="#" id="clear_agreements_search_field"
                           style="display:flex; margin:10px 0 0;text-decoration: underline;">
                               <?= Loc::getMessage('AGRLST_FILTER_SEARCH_CLEAR'); ?>
                        </a>
                    </fieldset>
                </div>
                <div class="row col-md-6"></div>
            </div>
            <div class="col-12 pl-0">
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
                <div id="agreements-overflow-hidden" class="agreements-overflow-hidden invisible">
                    <table class="table table-sm table-striped table-middle table-bordered table-white-space <?= !empty($arResult['ADDITIONALS']) ? 'agreements-data-table' : '' ?>">
                        <thead>
                            <tr>
                                <th><?= Loc::getMessage('AGRLST_FORM_TH_ORDER_ID'); ?></th>
                                <th><?= Loc::getMessage('AGRLST_FORM_TH_ORDER_ID_LAW'); ?></th>
                                <th><?= Loc::getMessage('AGRLST_FORM_TH_DATE_CREATED'); ?></th>
                                <th><?= Loc::getMessage('AGRLST_FORM_TH_PERIOD'); ?></th>
                                <th><?= Loc::getMessage('AGRLST_FORM_TH_CREATE_BY_EMAIL_USER'); ?></th>
                                <th><?= Loc::getMessage('AGRLST_FORM_TH_STORE'); ?></th>
                                <th><?= Loc::getMessage('AGRLST_FORM_TH_COUNT'); ?></th>
                                <th><?= Loc::getMessage('AGRLST_FORM_TH_WEIGHT'); ?></th>
                                <th><?= Loc::getMessage('AGRLST_FORM_TH_WEIGHT_BRUTTO'); ?></th>
                                <th><?= Loc::getMessage('AGRLST_FORM_TH_STATUS'); ?></th>
                                <th><?= Loc::getMessage('AGRLST_FORM_TH_CHANGED'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($arResult['ADDITIONALS']) && empty($arResult['ADDITIONALS_RAW'])) : ?>
                                <tr>
                                    <td colspan="11" style="text-align: center;">
                                        <?= Loc::getMessage('AGRLST_FORM_DATA_NOT_FOUND'); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                foreach ($arResult['ADDITIONALS_RAW'] as $additional) {
                                    $dataTable[] = [
                                        '<a href="/agreements/edit_raw/' . $additional['ID'] . '/" id="rawId-'. $additional['ID'] .'">' .
                                            Loc::getMessage('AGRLST_FORM_ADD_NUMBER_RAW') . $additional['ID'] .
                                        '</a>',
                                        "{$additional['UF_ADDITION_NUMBER']}",
                                        $additional['UF_CREATED_AT']->format('d.m.Y') ,
                                        is_a($additional['UF_PERIOD'], '\Bitrix\Main\Type\Date') ?
                                            Loc::getMessage('MONTH_' . $additional['UF_PERIOD']->format('n')) . ' ' . $additional['UF_PERIOD']->format('Y') : '',
                                        $additional['UF_USER'] == 1 ? Loc::getMessage('AGRLST_SYSTEM_USER_NAME') : $arResult['USERS_LIST'][$additional['UF_USER']],
                                        $arResult['STORES_LIST'][$additional['UF_SHIPPING_STORE']],
                                        $additional['COUNT'],
                                        Formatter::formatDouble($additional['WEIGHT'], 3, '.', ' '),
                                        Formatter::formatDouble($additional['UF_WEIGHT_BRUTTO'], 3, '.', ' '),
                                        Loc::getMessage('AGRLST_FORM_STATUS_RAW'),
                                        $additional['UF_CHANGED'] ? Loc::getMessage('AGRLST_CHANGED') : '',
                                        '<span>
                                            <a href="/agreements/edit_raw/' . $additional['ID'] . '/" class="btn btn-icon btn-sm btn-outline-primary">
                                                <i class="la la-edit"></i>
                                            </a>
                                        </span>
                                        <span 
                                            class="btn btn-icon btn-outline-danger btn-sm conf-modal-dialog-btn" 
                                            data-toggle="tooltip" 
                                            data-placement="left" 
                                            data-raw-id="' . $additional['ID'] . '" 
                                            title="" data-original-title="Удалить Проект Дополнения" aria-describedby="tooltip471419">
                                            <i class="la la-remove"></i>
                                        </span>',
                                    ];
                                }
                                
                                foreach ($arResult['ADDITIONALS'] as $additional) {
                                    $dataTable[] = [
                                        '<a href="/agreements/view/' . $additional['ID'] . '/">' .
                                            Loc::getMessage('AGRLST_FORM_ADD_NUMBER') . $additional['ID'] .
                                        '</a>',
                                        "{$additional['UF_ADDITION_NUMBER']}",
                                        is_a($additional['UF_DATE'], '\Bitrix\Main\Type\Date') ? $additional['UF_DATE']->format('d.m.Y') : '',
                                        is_a($additional['UF_PERIOD'], '\Bitrix\Main\Type\Date') ?
                                            Loc::getMessage('MONTH_' . $additional['UF_PERIOD']->format('n')) . ' ' . $additional['UF_PERIOD']->format('Y') : '',
                                        $additional['UF_USER'] == 1 ? Loc::getMessage('AGRLST_SYSTEM_USER_NAME') : $arResult['USERS_LIST'][$additional['UF_USER']],
                                        $arResult['STORES_LIST'][$additional['UF_SHIPPING_STORE']],
                                        $additional['COUNT'],
                                        Formatter::formatDouble($additional['WEIGHT'], 3, '.', ' '),
                                        Formatter::formatDouble($additional['UF_WEIGHT_BRUTTO'], 3, '.', ' '),
                                        $arResult['STATUSES_LIST'][$additional['UF_STATUS']]['VALUE'],
                                        $additional['UF_CHANGED'] ? Loc::getMessage('AGRLST_CHANGED') : '',
                                        ($additional['EDIT'] ?
                                        '<span>
                                            <a href="/agreements/edit/' . $additional['ID'] . '/" class="btn btn-icon btn-sm btn-outline-primary">
                                                <i class="la la-edit"></i>
                                            </a>
                                        </span>' : '') .
                                        ($additional['CANCEL'] ?
                                        '<span 
                                            class="btn btn-icon btn-outline-warning btn-sm cancel-modal-dialog-btn" 
                                            data-toggle="tooltip" 
                                            data-placement="left" 
                                            data-id="' . $additional['ID'] . '" 
                                            title="" data-original-title="Отменить Проект Дополнения" aria-describedby="tooltip471420">
                                            <i class="la la-ban"></i>
                                        </span>' : '')
                                        ,
                                    ];
                                }
                                ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="conf-modal-dialog" style="display: none;">
                    <p>Вы уверены, что хотите удалить Проект Дополнения?</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var DATA_AGGREMENTS = <?= json_encode($dataTable); ?>
</script>
