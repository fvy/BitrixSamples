<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$APPLICATION->IncludeComponent(
    "korus:agreements.view",
    "",
    [
        'AGREEMENT_ID' => $arResult['VARIABLES']['AGREEMENT_ID'],
        'BACK_URL'     => $arParams["SEF_FOLDER"] . 'list/',
    ],
    $component
);
