<?php

namespace Adv\CommunicationCards\Render;


use Bitrix\Sale\Internals\BasketTable;
use CAdminList;
use CAdminPage;
use CAdminResult;
use CAdminSorting;
use CIBlockElement;
use CSaleBasket;
use CSaleBasketHelper;

class CartsRender
{
    private $tabName;

    public function __construct($tabName = "cart-tab")
    {
        $this->tabName = $tabName;
    }

    function display($userId)
    {
        //BUYERS BASKET
        $cartTableID    = $this->tabName;
        $cartCAdminSort = new CAdminSorting($cartTableID, false, false, "basket_by", "basket_sort");
        $cartCListAdmin = new CAdminList($cartTableID, $cartCAdminSort);
        $adminPage = new CAdminPage();
        $selfFolderUrl = $adminPage->getSelfFolderUrl();

        //FILTER BASKET
        $arFilterFields = [
            "filter_basket_lid",
            "basket_name_product",
        ];
        $cartCListAdmin->InitFilter($arFilterFields);

        $arBasketSort = ["DATE_INSERT" => "DESC", "LID" => "ASC"];
        $arBasketFilter = [
            "=FUSER.USER_ID" => (int) $userId,
            ">FUSER.USER_ID" => 0,
            "ORDER_ID"       => "NULL",
        ];

        $dbBasketList = BasketTable::getList(
            [
                'order'  => array_merge(
                    [
                        "SET_PARENT_ID" => "DESC",
                        "TYPE"          => "DESC",
                    ],
                    $arBasketSort
                ),
                'filter' => $arBasketFilter,
            ]
        );
        $dbBasketList = new CAdminResult($dbBasketList, $cartTableID);
        $dbBasketList->NavStart();
        $cartCListAdmin->NavText($dbBasketList->GetNavPrint(GetMessage('BUYER_BASKET_BASKET'), false));

        $BasketHeader = [
            [
                "id"      => "DATE_INSERT",
                "content" => "Дата добавления",
                "default" => true,
            ],
            [
                "id"      => "NAME",
                "content" => "Название",
                "default" => true,
            ],
            [
                "id"      => "PRICE",
                "content" => "Цена",
                "default" => true,
            ],
            [
                "id"      => "QUANTITY",
                "content" => "Количество",
                "default" => true,
            ],
        ];
        $cartCListAdmin->AddHeaders($BasketHeader);

        $arSetData    = [];
        $arBasketData = [];
        while ($arBasket = $dbBasketList->GetNext()) {
            if (CSaleBasketHelper::isSetItem($arBasket)) {
                $arSetData[$arBasket["SET_PARENT_ID"]][] = $arBasket;
                continue;
            }
            $arBasketData[] = $arBasket;
        }

        foreach ($arBasketData as $arBasket) {
            $row =& $cartCListAdmin->AddRow($arBasket["PRODUCT_ID"], $arBasket, '', '');

            $orderProductUrl    = $arBasket["DETAIL_PAGE_URL"];
            $elementQueryObject = CIBlockElement::getList(
                [],
                [
                    "ID" => $arBasket["PRODUCT_ID"],
                ],
                false,
                false,
                ["IBLOCK_ID", "IBLOCK_TYPE_ID"]
            );
            if ($elementData = $elementQueryObject->fetch()) {
                $orderProductUrl = $selfFolderUrl . "cat_product_edit.php?IBLOCK_ID=" . $elementData["IBLOCK_ID"] .
                    "&type=" . $elementData["IBLOCK_TYPE_ID"] . "&ID=" . $arBasket["PRODUCT_ID"] . "&lang=" . LANGUAGE_ID . "&WF=Y";
            }

            $name = "<a target='_blank' href=\""
                . $orderProductUrl . "\">"
                . $arBasket["NAME"] . "</a><input type=\"hidden\" value=\""
                . $arBasket["PRODUCT_ID"] . "\" name=\"PRODUCT_ID["
                . $arBasket["LID"] . "][]\" />";

            $dbProp = (new CSaleBasket)->GetPropsList(
                ["SORT" => "ASC", "ID" => "ASC"],
                [
                    "BASKET_ID" => $arBasket["ID"],
                    "!CODE"     => ["CATALOG.XML_ID", "PRODUCT.XML_ID"],
                ]
            );
            while ($arProp = $dbProp->GetNext()) {
                $name .= "<div><small>" . $arProp["NAME"] . ": " . $arProp["VALUE"] . "</small></div>";
            }

            if (CSaleBasketHelper::isSetParent($arBasket)) {

                if (!empty($arSetData) && array_key_exists($arBasket["ID"], $arSetData)) {
                    $name .= "<div class=\"set_item_b2" . $arBasket["ID"] . "\" style=\"display:none\">";
                    foreach ($arSetData[$arBasket["ID"]] as $set) {
                        $name .= "<p style=\"display:inline; font-style:italic\">" . $set["NAME"] . "</p><br/>";
                    }
                    $name .= "</div>";
                }
            }

            $row->AddField("NAME", $name);
            $row->AddField("PRICE", SaleFormatCurrency($arBasket["PRICE"], $arBasket["CURRENCY"]));
        }

        $cartCListAdmin->DisplayList(["FIX_HEADER" => false, "FIX_FOOTER" => false]);
    }
}