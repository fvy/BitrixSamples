<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$APPLICATION->IncludeComponent(
    "korus:agreements.list",
    "",
    [
        'CONTRACT_ID' => \Korus\Basic\Manager\ManagerFramework\ManagerContracts::getContractIdCookie(),
    ],
    $component
);
