<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
$additionId = (int)$arResult['VARIABLES']['AGREEMENT_ID'];

$APPLICATION->IncludeComponent(
    "korus:agreements.edit",
    "",
    [
        'AGREEMENT_ID'   => $additionId,
        'BACK_URL'       => $arParams["SEF_FOLDER"] . 'view/' . $additionId . '/',
        'AFTER_SAVE_URL' => $arParams["SEF_FOLDER"] . 'view/' . $additionId . '/',
        'SUCCESS_URL'    => $arParams["SEF_FOLDER"] . 'edit/#AGREEMENT_ID#/?page=success',
    ],
    $component
);
