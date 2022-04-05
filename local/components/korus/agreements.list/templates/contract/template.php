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
$asset->addCss(SITE_DIR . 'local/assets/css/vendors/tables/datatable/datatables.min.css');
?>
<div class="content-body">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><?= Loc::getMessage('AGRLST_LIST_TITLE'); ?></h4>
                    <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                </div>
                <div class="card-content collapse show">
                    <div class="card-body card-dashboard">
                        <div class="row">
                            <div class="row col-md-6">
                                <fieldset class="form-group position-relative has-icon-left col-md-8">
                                    <input type="text"
                                           class="form-control" 
                                           id="agreements_search_field"
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
                            <table class="table table-sm table-striped table-middle table-bordered <?= !empty($arResult['ADDITIONALS']) ? 'agreements-data-table' : ''; ?>">
                                <thead>
                                <tr>
                                    <th><?= Loc::getMessage('AGRLST_FORM_TH_ORDER_ID'); ?></th>
                                    <th><?= Loc::getMessage('AGRLST_FORM_TH_ORDER_ID_LAW'); ?></th>
                                    <th><?= Loc::getMessage('AGRLST_FORM_TH_DATE_CREATED'); ?></th>
                                    <th><?= Loc::getMessage('AGRLST_FORM_TH_PERIOD'); ?></th>
                                    <th><?= Loc::getMessage('AGRLST_FORM_TH_CREATE_BY_EMAIL_USER'); ?></th>
                                    <th><?= Loc::getMessage('AGRLST_FORM_TH_COUNT'); ?></th>
                                    <th><?= Loc::getMessage('AGRLST_FORM_TH_WEIGHT'); ?></th>
                                    <th><?= Loc::getMessage('AGRLST_FORM_TH_STATUS'); ?></th>
                                    <th><?= Loc::getMessage('AGRLST_FORM_TH_CHANGED'); ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($arResult['ADDITIONALS'])) : ?>
                                    <tr>
                                        <td colspan="10" style="text-align: center;">
                                            <?= Loc::getMessage('AGRLST_FORM_DATA_NOT_FOUND'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    foreach ($arResult['ADDITIONALS'] as $additional) : ?>
                                        <tr>
                                            <td>
                                                <a href="/agreements/view/<?= $additional['ID'] ?>/">
                                                    <?= Loc::getMessage('AGRLST_FORM_ADD_NUMBER'); ?><?= $additional['ID'] ?>
                                                </a>
                                            </td>
                                            <td><?= $additional['UF_ADDITION_NUMBER'] ?></td>
                                            <td><?= is_a($additional['UF_DATE'], '\Bitrix\Main\Type\Date') ?
                                                    $additional['UF_DATE']->format('d.m.Y')
                                                    : '';
                                                ?></td>
                                            <td><?= is_a($additional['UF_PERIOD'], '\Bitrix\Main\Type\Date') ?
                                                    Loc::getMessage('MONTH_' . $additional['UF_PERIOD']->format('n')) . ' ' . $additional['UF_PERIOD']->format('Y')
                                                    : '';
                                                ?></td>
                                            <td><?= $additional['UF_USER'] == 1
                                                    ? Loc::getMessage('AGRLST_SYSTEM_USER_NAME')
                                                    : $arResult['USERS_LIST'][$additional['UF_USER']] ?></td>
                                            <td><?= $additional['COUNT'] ?></td>
                                            <td><?= Formatter::formatDouble($additional['WEIGHT'], 3, '.', ' ') ?></td>
                                            <td><?= $arResult['STATUSES_LIST'][$additional['UF_STATUS']]['VALUE'] ?></td>
                                            <td><?= $additional['UF_CHANGED'] ? Loc::getMessage('AGRLST_CHANGED') : ''; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>