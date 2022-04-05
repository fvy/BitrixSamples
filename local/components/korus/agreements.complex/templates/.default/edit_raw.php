<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$APPLICATION->IncludeComponent(
    "korus:agreements.edit_raw",
    "",
    [
        'AGREEMENT_ID' => $arResult['VARIABLES']['AGREEMENT_ID'],
        'SUCCESS_URL' => $arParams["SEF_FOLDER"] . 'create_agreement/?page=success',
    ],
    $component
);
