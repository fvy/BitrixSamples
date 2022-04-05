<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use \Bitrix\Main\Localization\Loc;
use \Korus\Basic\Helpers\View;
use \Korus\Basic\Helpers\Formatter;

/**
 * Class AgreementsView
 * Детальный просмотр дополнения
 */
class AgreementsView extends \CBitrixComponent
{
    use \Korus\Basic\Traits\GetManager;
    use \Korus\Basic\Traits\Errors;
    
    /**
     * CatalogRequestsCreate constructor.
     *
     * @inheritdoc
     *
     * @param CBitrixComponent|null $component
     *
     * @throws \Bitrix\Main\LoaderException
     */
    public function __construct(?CBitrixComponent $component = null)
    {
        parent::__construct($component);

        \Korus\Basic\Helpers\Page::initModules(['korus.b2b']);

        $this->initError();
    }

    /**
     * @inheritdoc
     * @param $arParams
     * @return array
     */
    public function onPrepareComponentParams($arParams)
    {
        return parent::onPrepareComponentParams($arParams);
    }

    /**
     * @inheritdoc
     * @return mixed|void
     * @throws \Bitrix\Main\SystemException
     */
    public function executeComponent()
    {
        $this->loadAdditional();
        $this->loadContract();
        if ($this->accessCheck()) {
            $this->setPageTitle();
            $this->getData();
        }
        
        $this->arResult['ERRORS'] = $this->errorCollection;

        $this->includeComponentTemplate();
    }

    /**
     * Получение Дополнения
     */
    protected function loadAdditional()
    {
        $mngAddition = $this->getManagerAdditionals();
        $this->arResult['ADDITIONAL'] = $mngAddition->getRow(
            [
                'select' => ['*', 'CONTRACT_ID' => 'UF_CONTRACT_REF.ID'],
                'filter' => [
                    '=ID'        => (int)$this->arParams['AGREEMENT_ID'],
                    '=UF_ACTIVE' => 1,
                ]
            ]
        );
        $this->arResult['ADDITIONAL']['ACCESS_INVOICE'] = $mngAddition->getStatusAgreedId() == $this->arResult['ADDITIONAL']['UF_STATUS'];
        $this->arResult['ADDITIONAL']['CAN_EDIT'] = $this->getManagerAdditionals()->canEdit($this->arResult['ADDITIONAL']);
        $this->arResult['ADDITIONAL']['CAN_CANCEL'] = current(\Korus\B2b\Additions\Cancel::canList([$this->arResult['ADDITIONAL']['ID']]));
    }

    /**
     * Получение договора по Дополнению
     */
    protected function loadContract()
    {
        $this->arResult['CONTRACT'] = [];
        
        if(!empty($this->arResult['ADDITIONAL']['UF_CONTRACT'])) {
            $mngContracts = $this->getManagerContracts();
            $this->arResult['CONTRACT'] = $mngContracts->findBy1cId($this->arResult['ADDITIONAL']['UF_CONTRACT']);
        }
    }

    /**
     * Получение истории изменения по Дополнению
     */
    protected function loadHistory()
    {
        $history = [];
        
        if (!empty($this->arResult["ADDITIONAL"]["ID"])) {
            $history = $this->getManagerAdditionHistory()->find([
                "filter" => ["=UF_ADDITION_ID" => $this->arResult["ADDITIONAL"]["ID"]],
                "order" => ["UF_DATE" => "DESC", "UF_PRODUCT" => "ASC"],
            ]);
        }

        $historyProductNames = $this->getHistoryProductNames(array_column($history, 'UF_PRODUCT', 'UF_PRODUCT'));

        $this->arResult["HISTORY_DATE"] = [];
        $this->arResult["HISTORY_CODE"] = [];
        $historyProducts = [];

        foreach ($history as $history) {
            if (!empty($history["UF_PRODUCT"])) {
                $historyProducts[$history["UF_PRODUCT"]]["NAME"] = $historyProductNames[$history["UF_PRODUCT"]];
                $historyProducts[$history["UF_PRODUCT"]]["CODE"] = $history["UF_PRODUCT"];
                $historyProducts[$history["UF_PRODUCT"]]["FIELDS"] = array_merge(
                    unserialize($history["UF_CHANGES_FIELDS"]),
                    (is_array($historyProducts[$history["UF_PRODUCT"]]["FIELDS"]) ? $historyProducts[$history["UF_PRODUCT"]]["FIELDS"] : []));
                $historyProducts[$history["UF_PRODUCT"]]["HISTORY"][] = [
                    "date"    => $history["UF_DATE"]->format("d.m.Y"),
                    "changes" => $history["UF_CHANGES"],
                ];
            }

            $date = $history["UF_DATE"]->format("d.m.Y");
            $this->arResult["HISTORY_DATE"][$date][$history["UF_PRODUCT"]] = $this->arResult["HISTORY_DATE"][$date][$history["UF_PRODUCT"]] . $history["UF_CHANGES"];
            $this->arResult["HISTORY_CODE"][$history["UF_PRODUCT"]][$date] = $this->arResult["HISTORY_CODE"][$history["UF_PRODUCT"]][$date] . $history["UF_CHANGES"];
        }

        $this->arResult["HISTORY_PRODUCT"] = $historyProducts;
    }

    /**
     * Проверка на доступ к дополнению
     *
     * @return boolean
     * @throws \Bitrix\Main\SystemException
     */
    protected function accessCheck()
    {
        $contractIdCookie = $this->getManagerContracts()->getContractIdCookie();
        if ($contractIdCookie != $this->arResult['ADDITIONAL']['CONTRACT_ID']) {
            LocalRedirect("/agreements/");
        }

        if (!$this->arResult['ADDITIONAL']) {
            $this->addError(Loc::getMessage('AGREEMENTS_VIEW_NOT_FOUND'), 'CRITICAL_ERROR');
            return false;
        }

        if (!$this->arResult['CONTRACT']) {
            $this->addError(Loc::getMessage('AGREEMENTS_VIEW_CONTRACT_NOT_FOUND'), 'CRITICAL_ERROR');
            return false;
        }

        $user = $this->getManagerUser()->getCurrentUserData();
        if ($this->arResult['CONTRACT']['UF_CONTRACTOR'] != $user['UF_CONTRACTOR']) {
            $this->addError(Loc::getMessage('AGREEMENTS_VIEW_ACCESS_DENIED'), 'CRITICAL_ERROR');
            return false;
        }

        return true;
    }

    protected function getHistoryProductNames(array $prod1cIds = [])
    {
        $result = [];

        if (empty($prod1cIds)) {
            return $result;
        }

        $productsObj = $this->getManagerCatalog()->managerElements()->getByParameters(
            [
                'filter' => [
                    'PROPERTIES_VALUES' => [
                        '=IDENTIFIER_1C' => $prod1cIds
                    ]
                ],
                'select' => ['NAME', 'PROPERTIES_VALUES' => ['IDENTIFIER_1C']],
            ],
            true,
            true
        );

        foreach ($productsObj as $product) {
            $result[$product['PROPERTIES']['IDENTIFIER_1C']] = $product['NAME'];
        }
        
        return $result;
    }

    protected function getData()
    {
        $this->arResult['TRANSPORTATION'] = $this->getTransportation();
        $this->arResult['RECEIVER'] = $this->getReceiver();
        $this->arResult['SHIPPING_STORE'] = $this->getShippingStore();
        $this->arResult['SHIPPING_POINT'] = $this->getShippingPoint();
        $this->arResult['TRANSPORT_TYPE'] = $this->getTransportType();
        $this->arResult['DELIVERY_TYPE'] = $this->getDeliveryType();
        $this->arResult['DELIVERY_POINT'] = $this->getDeliveryPoint();
        $this->arResult['DELIVERY_ADDRESS'] = $this->getDeliveryAddress();
        $this->arResult['PRODUCTS'] = $this->getProducts();
        $this->arResult['HTML'] = $this->generateTotalTr();
        $this->arResult['STATUSES_LIST'] = $this->getStatusesList();
        $this->loadHistory();
    }

    /**
     * Получение вида транспортировки
     *
     * @return array
     */
    protected function getTransportation()
    {
        if (empty($this->arResult['ADDITIONAL']['UF_TRANSPORTATION_TY'])) {
            return [];
        }

        $mngTransportationTypes = $this->getManagerTransportationTypes();

        return $mngTransportationTypes->findBy1cId($this->arResult['ADDITIONAL']['UF_TRANSPORTATION_TY']);
    }

    /**
     * Получение грузополучателя
     *
     * @return array
     */
    protected function getReceiver()
    {
        if (empty($this->arResult['ADDITIONAL']['UF_RECEIVER'])) {
            return [];
        }

        return $this->getManagerContractors()->findBy1cId($this->arResult['ADDITIONAL']['UF_RECEIVER']);
    }

    /**
     * Получение склада отгрузки
     *
     * @return array
     */
    protected function getShippingStore()
    {
        if (empty($this->arResult['ADDITIONAL']['UF_SHIPPING_STORE'])) {
            return [];
        }

        $mngStore = $this->getManagerStore();
        $stores = $mngStore->findByCodeAskuList([$this->arResult['ADDITIONAL']['UF_SHIPPING_STORE']]);

        return current($stores);
    }

    /**
     * Получение пункта отгрузки
     *
     * @return array
     */
    protected function getShippingPoint()
    {
        if (empty($this->arResult['ADDITIONAL']['UF_SHIPPING_POINT'])) {
            return [];
        }

        $mngShippingPoints = $this->getManagerShippingPoints();

        return $mngShippingPoints->findBy1cId($this->arResult['ADDITIONAL']['UF_SHIPPING_POINT']);
    }

    /**
     * Получение типа ТС
     *
     * @return array
     */
    protected function getTransportType()
    {
        if (empty($this->arResult['ADDITIONAL']['UF_TRANSPORT_TYPE'])) {
            return [];
        }

        $mngTransportTypes = $this->getManagerTransportTypes();

        return $mngTransportTypes->findBy1cId($this->arResult['ADDITIONAL']['UF_TRANSPORT_TYPE']);
    }

    /**
     * Получение способа доставки
     *
     * @return array
     */
    protected function getDeliveryType()
    {
        if (empty($this->arResult['ADDITIONAL']['UF_DELIVERY_TYPE'])) {
            return [];
        }

        return $this->getManagerDeliveryTypes()->findBy1cId($this->arResult['ADDITIONAL']['UF_DELIVERY_TYPE']);
    }

    /**
     * Получение пункта доставки
     *
     * @return array
     */
    protected function getDeliveryPoint()
    {
        if (empty($this->arResult['ADDITIONAL']['UF_DELIVERY_POINT'])) {
            return [];
        }

        $mngDeliveryPoints = $this->getManagerDeliveryPoints();

        return $mngDeliveryPoints->findBy1cId($this->arResult['ADDITIONAL']['UF_DELIVERY_POINT']);
    }

    /**
     * Получение адреса доставки
     *
     * @return array
     */
    protected function getDeliveryAddress()
    {
        if (empty($this->arResult['ADDITIONAL']['UF_DELIVERY_ADDRESS'])) {
            return [];
        }

        $mngDeliveryAddresses = $this->getManagerDeliveryAddresses();

        return $mngDeliveryAddresses->findBy1cId($this->arResult['ADDITIONAL']['UF_DELIVERY_ADDRESS']);
    }

    /**
     * Получение товаров по дополнению
     *
     * @return array
     */
    protected function getProducts()
    {
        $mngAdditionalProducts = $this->getManagerAdditionalProducts();
        $mngCatalog = $this->getManagerCatalog();
        $mngMeasurement = $this->getManagerMeasurement();

        $result = [];

        $additionalProducts = $mngAdditionalProducts->find([
            'select' => ['*', 'PRICE_AGREEMENT' => 'UF_PRICE_AGREEMENT_REF.UF_NAME', 'PRICE_TYPE' => 'UF_PRICE_TYPE_REF.UF_NAME'],
            'filter' => ['=UF_ADDITION' => $this->arResult['ADDITIONAL']['ID']]
        ]);
        if (!$additionalProducts) {
            return $result;
        }

        $productsObj = $mngCatalog->managerElements()->getCatalogProductsList(
            ['PROPERTIES_VALUES' => ['=IDENTIFIER_1C' => array_column($additionalProducts, 'UF_PRODUCTS', 'UF_PRODUCTS')]],
            ['NAME', 'PROPERTIES_VALUES', 'WEIGHT' => 'catalog.WEIGHT'],
            true,
            true
        );

        $measurements = $mngMeasurement->findByIdList(array_column($additionalProducts, 'UF_MEASURE', 'UF_MEASURE'));
        $tonMeasurement = $mngMeasurement->getMeasurementByRusCode('т');

        $products = [];
        foreach ($productsObj as $productObj) {
            $products[$productObj['PROPERTIES']['IDENTIFIER_1C']] = $productObj;
        }

        foreach ($additionalProducts as $additionalProduct) {
            $product = $products[$additionalProduct['UF_PRODUCTS']];
            $result[$additionalProduct['UF_PRODUCTS']] = [
                'NAME'                 => $product['NAME'],
                'CODE'                 => $additionalProduct['UF_PRODUCTS'],
                'CAPACITY'             => (int) $product['PROPERTIES']['CAPACITY'],
                'WEIGHT'               => $product['WEIGHT'],
                'PALLET_RATE'          => $product['PROPERTIES']['NORM_PALLETIZING'],
                'UF_PRODUCTS_CNT'      => $additionalProduct['UF_PRODUCTS_CNT'],
                'MEASUREMENT'          => $measurements[$additionalProduct['UF_MEASURE']],
                'IS_TON'               => $additionalProduct['UF_MEASURE'] == $tonMeasurement['ID'],
                'UF_PRICE_LIST'        => $additionalProduct['UF_PRICE_LIST'],
                'UF_PRICE'             => $additionalProduct['UF_PRICE'],
                'UF_TAX_PRICE'         => $additionalProduct['UF_TAX_PRICE'],
                'UF_DISCOUNT'          => $additionalProduct['UF_DISCOUNT'],
                'UF_PRICE_DISCOUNT'    => $additionalProduct['UF_PRICE_DISCOUNT'],
                'UF_TAX_PRICE_DISCONT' => $additionalProduct['UF_TAX_PRICE_DISCONT'],
                'UF_TAX_PERCENT'       => $additionalProduct['UF_TAX_PERCENT'],
                'UF_TAX_RUB'           => $additionalProduct['UF_TAX_RUB'],
                'UF_TAX_VALUE'         => $additionalProduct['UF_TAX_VALUE'],
                'UF_VALUE'             => $additionalProduct['UF_VALUE'],
                'UF_DISPATCHED'        => $additionalProduct['UF_DISPATCHED'],
                'UF_BALANCE_REMAINS'   => $additionalProduct['UF_BALANCE_REMAINS'],
                'CHANGED'              => $additionalProduct['UF_CHANGED'],
                'PRICE_AGREEMENT'      => $additionalProduct['PRICE_AGREEMENT'],
                'PRICE_TYPE'           => $additionalProduct['PRICE_TYPE'],
                'UF_NONPALLET'         => $additionalProduct['UF_NONPALLET'],
                'UF_SPECIAL_PRICE'     => $additionalProduct['UF_SPECIAL_PRICE'],
            ];
        }

        return $result;
    }

    private function generateTotalTr()
    {
        if (empty($this->arResult['PRODUCTS'])) {
            return '';
        }

        $isPallet = $isNonpallet = false;
        $measurement = '';

        $countTotal = 0;
        $discountTotal = 0;
        $priceWithoutTaxTotal = 0;
        $taxTotal = 0;
        $priceWithTaxTotal = 0;
        $orderedTotal = 0;
        $remainsTotal = 0;
        $weightTotal = 0;

        foreach ($this->arResult['PRODUCTS'] as $product) {
            $product['UF_NONPALLET'] ? $isNonpallet = true : $isPallet = true;

            if (empty($measurement)) {
                ($product['IS_TON'] || $product['UF_NONPALLET'])
                    ? $measurement = $product['MEASUREMENT']['SYMBOL']
                    : $measurement = Loc::getMessage('AGREEMENTS_VIEW_UNIT_PALLET');
            }

            $countTotal++;
            $discountTotal += $product['UF_DISCOUNT'];
            $priceWithoutTaxTotal += $product['UF_VALUE'];
            $taxTotal += $product['UF_TAX_RUB'];
            $priceWithTaxTotal += $product['UF_TAX_VALUE'];
            $weightTotal += $product['UF_PRODUCTS_CNT'] * $product['PALLET_RATE'] * $product['WEIGHT'] / 1000;

            if ($isNonpallet && $isPallet) {
                $orderedTotal = '';
                $remainsTotal = '';
            } else {
                $orderedTotal += $product['UF_DISPATCHED'];
                $remainsTotal += $product['UF_BALANCE_REMAINS'];
            }
        }

        return '<tr class="totals">' .
                    '<td colspan="2"><b>' .
                        Loc::getMessage('AGREEMENTS_VIEW_TOTAL_CONTRACTED') .
                        $countTotal . ' ' .
                        View::pluralForm(
                            $countTotal,
                            ...Loc::getMessage('AGREEMENTS_VIEW_TABLE_TOTAL_POSITION')
                        ) . ', ' .
                        Loc::getMessage('AGREEMENTS_VIEW_TOTAL_WEIGHT') .
                        Formatter::formatDouble($weightTotal, 3, '.', ' ') . ' ' .
                        Loc::getMessage('AGREEMENTS_VIEW_UNIT_TON') .
                    '</b></td>' .
                    '<td colspan="4"></td>' .
                    '<td><b>' . Formatter::formatDouble($discountTotal, 2, '.', ' ') . '</b></td>' .
                    '<td colspan="2"></td>' .
                    '<td><b>' . Formatter::formatDouble($priceWithoutTaxTotal, 2, '.', ' ') . '</b></td>' .
                    '<td><b>' . Formatter::formatDouble($taxTotal, 2, '.', ' ') . '</b></td>' .
                    '<td><b>' . Formatter::formatDouble($priceWithTaxTotal, 2, '.', ' ') . '</b></td>' .
                    '<td><b>' . (is_string($orderedTotal) ? '' : $orderedTotal . ' ' . $measurement) . '</b></td>' .
                    '<td><b>' . (is_string($remainsTotal) ? '' : $remainsTotal . ' ' . $measurement) . '</b></td>' .
                    '<td></td>' .
                '</tr>';
    }

    private function setPageTitle()
    {
        global $APPLICATION;

        $statusesArray = array_column($this->getManagerAdditionals()->getStatusValuesList(), 'ID', 'XML_ID');

        if ($this->arResult['ADDITIONAL']['UF_STATUS'] == $statusesArray['OA']) {
            $headerTitle = Loc::getMessage('AGREEMENTS_VIEW_ADDITION_PROJECT', [
                '#ADDITION_NUMBER#' => $this->arResult['ADDITIONAL']['ID']
            ]);
        } else {
            $headerTitle = Loc::getMessage('AGREEMENTS_VIEW_ADDITION', [
                '#ADDITION_NUMBER#' => $this->arResult['ADDITIONAL']['UF_ADDITION_NUMBER']
            ]);
        }

        $APPLICATION->SetTitle($headerTitle);
    }

    /**
     * Список статусов Дополнения
     * @return array
     */
    protected function getStatusesList(): array
    {
        return array_column($this->getManagerAdditionals()->getStatusValuesList(), 'VALUE', 'ID');
    }
}
