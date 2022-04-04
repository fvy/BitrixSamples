<?php

namespace Adv\CommunicationCards\Render;


use Bitrix\Sale\Internals\OrderTable;
use CAdminList;
use CAdminResult;
use CAdminSorting;

class OrdersRender
{

    /**
     * @var string
     */
    private $tabName;

    public function __construct($tabName = "order-tab")
    {
        $this->tabName = $tabName;
    }

    function display($userId)
    {
        global $USER;

        $sOrderTableID = $this->tabName;
        $oOrderSort    = new CAdminSorting($sOrderTableID);
        $lAdminOrders  = new CAdminList($sOrderTableID, $oOrderSort);

        $arOrderSort   = ["DATE_INSERT" => "DESC"];
        $arOrderFilter = ["USER_ID" => (int) $userId];

        $getListParams = [
            'filter' => $arOrderFilter,
            'order'  => $arOrderSort,
            'select' => [
                'ID',
                'LID',
                'DATE_INSERT',
                'USER_ID',
                'STATUS_ID',
                'PRICE',
                'CURRENCY',
                'ACCOUNT_NUMBER',
            ],
            'limit'  => 3,
        ];

        $dbOrderList = new CAdminResult(
            OrderTable::getList($getListParams),
            $sOrderTableID
        );
        $dbOrderList->NavStart();
        $lAdminOrders->NavText($dbOrderList->GetNavPrint(GetMessage('Заказы'), false));

        $orderHeader = [
            [
                "id"      => "DATE_INSERT",
                "content" => "Дата заказа",
                "sort"    => "",
                "default" => true,
            ],
            ["id" => "ACCOUNT_NUMBER", "content" => "Номер заказа", "sort" => "", "default" => true],
            ["id" => "STATUS_ID", "content" => "Статус", "sort" => "", "default" => true],
            ["id" => "PRODUCT", "content" => "Позиции", "sort" => "", "default" => true],
        ];

        $lAdminOrders->AddHeaders($orderHeader);

        $statusesList = \Bitrix\Sale\OrderStatus::getAllowedUserStatuses(
            $USER->GetID(),
            \Bitrix\Sale\OrderStatus::getInitialStatus()
        );
        foreach ($statusesList as $statusId => $statusName) {
            $arStatusList[$statusId] = "[" . $statusId . "] " . $statusName;
        }

        $orderList = [];
        while ($arOrder = $dbOrderList->Fetch()) {
            $orderList[$arOrder['ID']] = $arOrder;
        }

        //BASKET POSITIONS
        if (\Bitrix\Main\Analytics\Catalog::isOn()) {
            $dbItemsList   = \Bitrix\Sale\Internals\BasketTable::getList(
                [
                    'order'  => ['ID' => 'ASC'],
                    'filter' => ['=ORDER_ID' => array_keys($orderList)],
                ]
            );

            while ($item = $dbItemsList->fetch()) {
                $basketList[$item['ORDER_ID']][$item['ID']] =
                    sprintf(
                        "[%s] %s (%s шт.)",
                        $item['ID'],
                        $item['NAME'],
                        $item['QUANTITY']
                    );
            }
        }

        foreach ($orderList as $orderId => $order) {
            $row =& $lAdminOrders->AddRow(
                $orderId,
                $order,
                "sale_order_view.php?ID=" . $orderId . "&lang=" . LANG,
                "Детальная информация заказа"
            );

            $orderLinkUrl = "sale_order_view.php?ID=" . $orderId . "&lang=" . LANG;
            $orderLink    = "<a target='_blank' href=\"" . $orderLinkUrl . "\">" . $order["ACCOUNT_NUMBER"] . "</a>";
            $row->AddField("ACCOUNT_NUMBER", $orderLink);

            $row->AddField("STATUS_ID", $arStatusList[$order["STATUS_ID"]]);

            $row->AddField("PRODUCT", join('<br>', $basketList[$orderId]));
        }

        $lAdminOrders->DisplayList(["FIX_HEADER" => false, "FIX_FOOTER" => false]);
    }
}