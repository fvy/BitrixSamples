<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

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

$this->setFrameMode(true);

$buildAgreementsList = function ($additionsArr) {
    $additionsOutArr = [];
    foreach ($additionsArr as $id) {
        $additionsOutArr[] = '<a href="/agreements/view/' . $id . '/">' . Loc::getMessage(
                'K_C_ADDITIONAL_SUCCESS_ADDITION_URL_TEXT'
            ) . $id . '</a>';
    }

    return join($additionsOutArr, ', ');
};
?>
<div class="content-header row mb-2">
    <div class="content-header-left col-12">
        <h3 class="content-header-title"><? $APPLICATION->ShowTitle() ?></h3>
    </div>
</div>
<div class="card">
    <div class="card-block">
        <div class="card-body">

            <p><?php if ($arResult['ADDITIONS_LIST_COUNT'] == 1): ?>
                    <?= Loc::getMessage('K_C_ADDITIONAL_SUCCESS_WAS_CREATED_ADDITION'); ?>
                <?php elseif ($arResult['ADDITIONS_LIST_COUNT'] > 1): ?>
                    <?= Loc::getMessage('K_C_ADDITIONAL_SUCCESS_WAS_CREATED_ADDITIONS'); ?>:
                <?php endif; ?>
                <?= $buildAgreementsList($arResult['ADDITIONS_LIST']); ?><?= $arResult['ADDITIONS_LIST_COUNT'] ? '.' : ''; ?>


                <?php if ($arResult['ADDITIONS_LIST_COUNT'] == 0 && $arResult['CROSS_ADDITIONS_LIST_COUNT'] == 1): ?>
                    <?= Loc::getMessage('K_C_ADDITIONAL_SUCCESS_CORRECTED_ONLY_ADDITION'); ?>
                <?php elseif ($arResult['ADDITIONS_LIST_COUNT'] == 0 && $arResult['CROSS_ADDITIONS_LIST_COUNT'] > 1): ?>
                    <?= Loc::getMessage('K_C_ADDITIONAL_SUCCESS_CORRECTED_ONLY_ADDITIONS'); ?>:
                <?php elseif ($arResult['ADDITIONS_LIST_COUNT'] == 1 && $arResult['CROSS_ADDITIONS_LIST_COUNT'] == 1): ?>
                    <?= Loc::getMessage('K_C_ADDITIONAL_SUCCESS_CORRECTED_ADDITION'); ?>:
                <?php elseif ($arResult['ADDITIONS_LIST_COUNT'] >= 1 && $arResult['CROSS_ADDITIONS_LIST_COUNT'] == 1): ?>
                    <?= Loc::getMessage('K_C_ADDITIONAL_SUCCESS_CORRECTED_ADDITION'); ?>:
                <?php elseif ($arResult['ADDITIONS_LIST_COUNT'] >= 1 && $arResult['CROSS_ADDITIONS_LIST_COUNT'] > 1): ?>
                    <?= Loc::getMessage('K_C_ADDITIONAL_SUCCESS_CORRECTED_ADDITIONS'); ?>:
                <?php endif; ?>
                <?= $buildAgreementsList($arResult['CROSS_ADDITIONS_LIST']); ?><?= $arResult['CROSS_ADDITIONS_LIST_COUNT'] ? '.' : ''; ?></p>

            <?php if (($arResult['ADDITIONS_LIST_COUNT'] == 1 && $arResult['CROSS_ADDITIONS_LIST_COUNT'] == 0)
                || ($arResult['ADDITIONS_LIST_COUNT'] == 0 && $arResult['CROSS_ADDITIONS_LIST_COUNT'] == 1)): ?>
                <p><?= Loc::getMessage('K_C_ADDITIONAL_SUCCESS_EPILOGUE_TEXT'); ?></p>
            <?php else: ?>
                <p><?= Loc::getMessage('K_C_ADDITIONAL_SUCCESS_EPILOGUE_TEXTS'); ?></p>
            <?php endif; ?>

        </div>
    </div>
</div>