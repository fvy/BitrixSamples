<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$APPLICATION->IncludeComponent(
    "korus:agreements.create",
    "",
    [
        'AFTER_SAVE_RAW_URL' => $arParams["SEF_FOLDER"] . 'edit_raw/#AGREEMENT_ID#/',
        'SUCCESS_URL'        => $arParams["SEF_FOLDER"] . 'create_agreement/?page=success',
    ],
    $component
);
