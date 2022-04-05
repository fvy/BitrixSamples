<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use \Bitrix\Main\Application;
use \Bitrix\Main\SystemException;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\ORM\Fields\ExpressionField;
use \Korus\Basic\Manager\ManagerFramework\ManagerContracts;
use \Korus\Basic\Helpers\Page;
use Korus\Catalog\Components\Agreements\Price;
use \Korus\Framework\Manager\Data\ManagerRegistry;
use \Korus\Logging\Logging;

class AdditionalsCreate extends \CBitrixComponent
{
    use \Korus\Basic\Traits\GetManager;
    use \Korus\Basic\Traits\Transaction;

    protected $crossingAdditions = [];
    protected $changes = [];

    public function __construct($component = null)
    {
        Page::initModules(['korus.framework', 'korus.b2b', 'korus.logging', 'korus.exchange']);

        parent::__construct($component);
    }

    public function executeComponent()
    {
        /** @global \CMAIN $APPLICATION */
        global $APPLICATION;

        if (isset($this->arParams['PAGE_TITLE'])) {
            $APPLICATION->SetTitle($this->arParams['PAGE_TITLE']);
        }

        if ($this->request->getQuery('page') == 'success') {
            if (!empty($additionsArr = $this->request->getQuery('aIds'))) {
                $this->arResult['ADDITIONS_LIST'] = $additionsArr;
                $this->arResult['ADDITIONS_LIST_COUNT'] = sizeof($additionsArr);
            }
            if (!empty($crossAdditionsArr = $this->request->getQuery('caIds'))) {
                $this->arResult['CROSS_ADDITIONS_LIST'] = $crossAdditionsArr;
                $this->arResult['CROSS_ADDITIONS_LIST_COUNT'] = sizeof($crossAdditionsArr);
            }
            $APPLICATION->SetTitle(Loc::getMessage('K_C_ADDITIONAL_SUCCESS_PAGE_TITLE'));

            $this->IncludeComponentTemplate('success');

            return;
        }

        $this->arResult['contract'] = $this->getContractData();
        $this->arResult['period'] = $this->getPeriod();
        $this->arResult['requestsData'] = $this->getRequestListWithMatchedVolumeProducts();
        $this->arResult['periodList'] = $this->getPeriods();
        $this->arResult['stores'] = $this->getStores();
        $this->arResult['user'] = $this->getManagerUser()->getCurrentUserData();

        $managerMeasurement = new \Korus\Basic\Manager\ManagerMeasurement;
        $this->arResult['measures'] = $managerMeasurement->getAll();

        if ($this->request->isAjaxRequest()) {
            $this->handleAjax();

            return;
        }

        $this->arResult['transportationTypes'] = $this->getTransportationTypes();
        $this->arResult['deliveryPoints'] = $this->getDeliveryPoints();
        $this->arResult['deliveryTypes'] = $this->getDeliveryTypes();
        $this->arResult['receivers'] = $this->getReceivers();
        $this->arResult['deliveryAddresses'] = $this->getDeliveryAddresses();

        $this->includeComponentTemplate();
    }
    
    private function getContractData()
    {
        $filter = [
            '=UF_ACTIVITY' => 1,
            '=UF_CONTRACTOR' => $this->getManagerUser()->getCurrentUserData()["UF_CONTRACTOR"],
        ];
        if (!empty($this->request->getPost('contract'))) {
            $filter['=UF_1C_ID'] = $this->request->getPost('contract');
        } else {
            $filter['=ID'] = ManagerContracts::getContractIdCookie();
        }
        
        return $this->getManagerContracts()->getRow([
            'filter' => $filter,
            'select' => ['ID', 'UF_1C_ID', 'UF_CONTRACT_NUMBER', 'UF_BRAND', 'UF_WAREHOUSE', 'UF_CONTRACTOR', 'UF_CLIENT_MANAGER', 'UF_REMAINS_50_50'],
        ]);
    }
    
    
    /**
     * @return array
     * @throws SystemException
     */
    private function getRequestListWithMatchedVolumeProducts()
    {
        if (!$this->arResult['contract'] || !$this->arResult['contract']['UF_1C_ID'] || !$this->arResult['period']) {
            return [];
        }
    
        $date = new DateTime('now');
        $date->modify('first day of this month');
        $periodNow = $date->format("d.m.Y");
        $date->modify('+1 month');
        $periodNext = $date->format("d.m.Y");
        
        $mngRequests = $this->getManagerRequests();
        
        $filter = [
            [
                'LOGIC' => 'OR',
                [
                    '=UF_REQUEST_REF.UF_TYPE' => $mngRequests->getMainTypeId(),
                ],
                [
                    '=UF_REQUEST_REF.UF_TYPE' => $mngRequests->getResourcesTypeId(),
                    '!=UF_REQUEST_REF.UF_MAIN_REQUEST' => '',
                ]
            ],
            '=UF_REQUEST_REF.UF_CONTRACT'  => $this->arResult['contract']['UF_1C_ID'],
            '!=UF_VOLUME_MATCHED'          => 0,
            '=UF_REQUEST_REF.UF_MONTH'     => [$periodNow, $periodNext],
            '=UF_REQUEST_REF.UF_IS_ACTIVE' => true,
            '=UF_NONPALLET'                => 0,
        ];
        
        if ($this->request->getPost('period')) {
            $objDate = \Bitrix\Main\Type\Date::createFromTimestamp(strtotime($this->request->getPost('period') . '01'));
            $filter['=UF_REQUEST_REF.UF_MONTH'] = $objDate;
        }
        
        $requestProductsDbResults = $this->getManagerRequestProducts()->find([
            'select' => [
                'REQUEST_1C_ID'          => 'UF_REQUEST_REF.UF_1C_ID',
                'REQUEST_MAIN_REQUEST'   => 'UF_REQUEST_REF.UF_MAIN_REQUEST',
                'REQUEST_ID'             => 'UF_REQUEST_REF.ID',
                'REQUEST_MONTH'          => 'UF_REQUEST_REF.UF_MONTH',
                'PRODUCT_STORE'          => 'UF_STORE',
                'REQUEST_TYPE'           => 'UF_REQUEST_REF.UF_TYPE',
                'PRODUCT_1C_ID'          => 'UF_PRODUCT',
                'PRODUCT_VOLUME_MATCHED' => 'UF_VOLUME_MATCHED',
            ],
            'filter' => $filter,
            'order'  => ['REQUEST_ID' => 'asc'],
        ]);

        $result = [];
        foreach ($requestProductsDbResults as $requestProduct) {
            $requestId = ($requestProduct['REQUEST_TYPE'] == $mngRequests->getMainTypeId()) ? $requestProduct['REQUEST_1C_ID'] : $requestProduct['REQUEST_MAIN_REQUEST'];
            if (!isset($result[$requestId])) {
                $result[$requestId] = [
                    'REQUEST_ID'    => $requestId,
                    'REQUEST_MONTH' => $requestProduct['REQUEST_MONTH'],
                    'stores'        => [],
                ];
            }

            $stores = $result[$requestId]['stores'];
            $available = $matched = 0;
            if ($stores[$requestProduct['PRODUCT_STORE']][$requestProduct['PRODUCT_1C_ID']]) {
                $matched = $stores[$requestProduct['PRODUCT_STORE']][$requestProduct['PRODUCT_1C_ID']]['matched'];
                $available = $stores[$requestProduct['PRODUCT_STORE']][$requestProduct['PRODUCT_1C_ID']]['available'];
            }
            $stores[$requestProduct['PRODUCT_STORE']][$requestProduct['PRODUCT_1C_ID']] = [
                'matched'   => $matched + $requestProduct['PRODUCT_VOLUME_MATCHED'],
                'available' => $available + $requestProduct['PRODUCT_VOLUME_MATCHED'],
            ];

            $result[$requestId]['stores'] = $stores;
        }

        return $result;
    }

    private function getPeriods(): array
    {
        if (empty($this->arResult['requestsData'])) {
            return [];
        }

        $periodList = [];
        /** @var \Bitrix\Main\Type\Date $month */
        foreach (array_column($this->arResult['requestsData'], 'REQUEST_MONTH') as $month) {
            if ($month) {
                $periodList[$month->format('Ym')] = $month;
            }
        }

        return $periodList;
    }

    private function getStores(): array
    {
        if (empty($this->arResult['periodList'])) {
            return [];
        }

        $selectedPeriod = $this->request->getPost('period');
        $period = $selectedPeriod
            ? $this->arResult['periodList'][$selectedPeriod]
            : current($this->arResult['periodList']);
        $requestStores = [];
        foreach ($this->arResult['requestsData'] as $request) {
            if ($request['REQUEST_MONTH'] == $period) {
                $requestStores = array_merge($requestStores, array_keys($request['stores']));
                break;
            }
        }

        return $this->getManagerStore()->findByCodeAskuList(
            array_intersect($requestStores, $this->arResult['contract']['UF_WAREHOUSE'])
        );
    }

    private function getRequest()
    {
        $period = $this->arResult['periodList'][$this->request->getPost('period')];
        $request = null;
        foreach ($this->arResult['requestsData'] as $requestData) {
            if ($requestData['REQUEST_MONTH'] == $period) {
                $request = $requestData;
                break;
            }
        }

        return $request;
    }

    /**
     * Формирование данных по пересекающимся товарам дополнений с группировкой по дополнениям
     *
     * @param string $store1cId
     * @param bool   $filterByPost
     * @return array
     * @throws SystemException
     */
    private function getCrossingAdditions($store1cId, bool $filterByPost = false)
    {
        $additions = [];

        if (empty($store1cId)) {
            return $additions;
        }

        $filter = [
            '=UF_PRODUCTS'                       => array_keys($this->arResult['storeProducts']),
            '=UF_ADDITION_REF.UF_NONPALLET'      => 0,
            '=UF_ADDITION_REF.UF_CONTRACT'       => $this->arResult['contract']['UF_1C_ID'],
            '=UF_ADDITION_REF.UF_PERIOD'         => $this->arResult['request']['REQUEST_MONTH'],
            '=UF_ADDITION_REF.UF_SHIPPING_STORE' => $store1cId,
            '=UF_ADDITION_REF.UF_ACTIVE'         => true,
            '=UF_ADDITION_REF.UF_STATUS'            => [
                $this->getManagerAdditionals()->getStatusAgreedId(),
                $this->getManagerAdditionals()->getStatusOnAgreedId()
            ],
        ];

        if ($filterByPost) {
            $filter['=UF_ADDITION_REF.UF_TRANSPORTATION_TY'] = $this->request->getPost('transportationType');
            $filter['=UF_ADDITION_REF.UF_DELIVERY_TYPE'] = $this->request->getPost('deliveryType');
            $filter['=UF_ADDITION_REF.UF_DELIVERY_ADDRESS'] = $this->request->getPost('deliveryAddress');
            $filter['=UF_ADDITION_REF.UF_RECEIVER'] = $this->request->getPost('receiver');
            $filter['=UF_ADDITION_REF.UF_DELIVERY_POINT'] = $this->request->getPost('deliveryPoint');
        }

        $additionalProducts = $this->getManagerAdditionalProducts()->find([
            'select' => [
                'ID', 'UF_ADDITION', 'UF_PRODUCTS', 'UF_PRODUCTS_CNT', 'UF_PRICE_AGREEMENT', 'UF_PRICE_TYPE', 'UF_SPECIAL_PRICE',
                'AGREEMENT_TYPE'             => 'UF_PRICE_AGREEMENT_REF.UF_TYPE',
                'ADDITION_ID'                => 'UF_ADDITION_REF.ID',
                'ADDITION_1C_ID'             => 'UF_ADDITION_REF.UF_1C_ID',
                'ADDITION_NUMBER'            => 'UF_ADDITION_REF.UF_ADDITION_NUMBER',
                'ADDITION_TRANSPORTATION_TY' => 'UF_ADDITION_REF.UF_TRANSPORTATION_TY',
                'ADDITION_DELIVERY_TYPE'     => 'UF_ADDITION_REF.UF_DELIVERY_TYPE',
                'ADDITION_ADVERT_50_50'      => 'UF_ADDITION_REF.UF_ADVERT_50_50',
                'ADDITION_RECEIVER'          => 'UF_ADDITION_REF.UF_RECEIVER',
                'ADDITION_DELIVERY_ADDRESS'  => 'UF_ADDITION_REF.UF_DELIVERY_ADDRESS',
                'PRICE_AGREEMENT'            => 'UF_PRICE_AGREEMENT_REF.UF_NAME',
                'PRICE_TYPE'                 => 'UF_PRICE_TYPE_REF.UF_NAME',
            ],
            'filter' => $filter,
            'order' => ['UF_PRODUCTS' => 'ASC']
        ]);

        foreach ($additionalProducts as $additionalProduct) {
            $additions[$additionalProduct['ADDITION_ID']]['info'] = [
                'ID'                => $additionalProduct['ADDITION_ID'],
                '1C_ID'             => $additionalProduct['ADDITION_1C_ID'],
                'NUMBER'            => $additionalProduct['ADDITION_NUMBER'],
                'TRANSPORTATION_TY' => $additionalProduct['ADDITION_TRANSPORTATION_TY'],
                'DELIVERY_TYPE'     => $additionalProduct['ADDITION_DELIVERY_TYPE'],
                'ADVERT_50_50'      => $additionalProduct['ADDITION_ADVERT_50_50'],
                'RECEIVER'          => $additionalProduct['ADDITION_RECEIVER'],
                'DELIVERY_ADDRESS'  => $additionalProduct['ADDITION_DELIVERY_ADDRESS'],
                'DELIVERY_POINT'  => $additionalProduct['ADDITION_DELIVERY_POINT'],
            ];

            $additions[$additionalProduct['ADDITION_ID']]['products'][$additionalProduct['UF_PRODUCTS']] = [
                'id'                 => $additionalProduct['UF_PRODUCTS'],
                'volume'             => $additionalProduct['UF_PRODUCTS_CNT'],
                'priceAgreementId'   => $additionalProduct['UF_PRICE_AGREEMENT'],
                'priceAgreementName' => $additionalProduct['PRICE_AGREEMENT'],
                'priceAgreementType' => $additionalProduct['AGREEMENT_TYPE'],
                'priceTypeId'        => $additionalProduct['UF_PRICE_TYPE'],
                'priceTypeName'      => $additionalProduct['PRICE_TYPE'],
                'specialPrice'       => $additionalProduct['UF_SPECIAL_PRICE'],
            ];
        }

        return $additions;
    }

    /**
     * Сокращение списка пересекающихся товаров с учетом переданных данных по товарам
     */
    private function reduceCrossingAdditionsByCurrentProducts()
    {
        foreach ($this->arResult['crossingAdditions'] as $addition1cId => $additionData) {
            foreach ($additionData['products'] as $crossProduct1cId => $crossProduct) {
                if (!isset($this->arResult['products'][$crossProduct1cId])) {
                    unset($this->arResult['crossingAdditions'][$addition1cId]['products'][$crossProduct1cId]);
                    continue;
                }
                
                $productNotFound = true;
                foreach ($this->arResult['products'][$crossProduct1cId] as $kp => $product) {
                    if ($product['priceAgreementId'] == $crossProduct['priceAgreementId']
                        && $product['priceTypeId'] == $crossProduct['priceTypeId']
                        && $product['specialPrice'] == $crossProduct['specialPrice']
                        && $product['fiftyFyfty'] == $additionData['info']['ADVERT_50_50']
                        ) {
                        $this->arResult['crossingAdditions'][$addition1cId]['products'][$crossProduct1cId]['volume'] += $product['volume'];
                        $this->arResult['crossingAdditions'][$addition1cId]['products'][$crossProduct1cId]['priceId'] = $product['priceId'];
                        
                        if (count($this->arResult['products'][$crossProduct1cId]) > 1) {
                            unset($this->arResult['products'][$crossProduct1cId][$kp]);
                        } else {
                            unset($this->arResult['products'][$crossProduct1cId]);
                        }
                        
                        $productNotFound = false;
                    }
                }
                
                if ($productNotFound) {
                    unset($this->arResult['crossingAdditions'][$addition1cId]['products'][$crossProduct1cId]);
                }
            }
            
            if (empty($this->arResult['crossingAdditions'][$addition1cId]['products'])) {
                unset($this->arResult['crossingAdditions'][$addition1cId]);
            }
        }
    }

    private function updateProductsCountAvailable()
    {
        foreach ($this->arResult['crossingAdditions'] as $addition) {
            foreach ($addition['products'] as $product1cId => $productInfo) {
                $this->arResult['storeProducts'][$product1cId]['available'] -= $productInfo['volume'];
            }
        }
    }

    private function getProducts()
    {
        $productsCatalogInfo = $this->getManagerCatalog()->managerElements()->getCatalogProductsList(
            ['PROPERTIES_VALUES' => ['=IDENTIFIER_1C' => array_keys($this->arResult['storeProducts'])]],
            [
                'ID',
                'NAME',
                'PROPERTIES_VALUES' => ['IDENTIFIER_1C', 'BRAND', 'NORM_PALLETIZING', 'CAPACITY', 'WEIGHT_BRUTTO'],
                'MEASURE'           => 'catalog.MEASURE',
                'WEIGHT'            => 'catalog.WEIGHT',
            ],
            true,
            true
        );

        $products = [];
        foreach ($productsCatalogInfo as $product) {
            $products[$product['PROPERTIES']['IDENTIFIER_1C']] = [
                'CODE'        => $product['PROPERTIES']['IDENTIFIER_1C'],
                'NAME'        => $product['NAME'],
                'WEIGHT'      => $product['WEIGHT'],
                'BRAND'       => $product['PROPERTIES']['BRAND'],
                'PALLET_RATE' => $product['PROPERTIES']['NORM_PALLETIZING'],
                'CAPACITY'    => (double)$product['PROPERTIES']['CAPACITY'],
                'MEASURE'     => $product['MEASURE'],
            ];
        }

        return $products;
    }

    private function getPriceTypes($store1cId)
    {
        $period = new \Bitrix\Main\Type\Date($this->request->getPost('period') . "01", "Ymd");
        $priceComponent = new Price($this->arResult['user']['UF_CONTRACTOR'], $period, [$store1cId]);

        $agreements = $priceComponent->getAgreements();
        $pricesNomenclature = $priceComponent->getNomenclature(array_keys($this->arResult['products']));
        $priceTypes = $priceTypeFilter = $priceAgreementsFilter = [];

        foreach ($agreements as $agreement) {
            foreach ($pricesNomenclature as $priceNomenclature) {
                if (!empty($priceTypes[$priceNomenclature['UF_PRODUCT']][$priceNomenclature['UF_PRICE_TYPE']])
                    || (empty($priceNomenclature['UF_PRICE_TYPE']) || $priceNomenclature['UF_PRICE_TYPE'] != $agreement['PRICE_TYPE_1C_ID'])) {

                    continue;
                }

                $priceTypes[$priceNomenclature['UF_PRODUCT']][$priceNomenclature['UF_PRICE_TYPE']] =
                    $priceComponent->buildAgreementRecord($agreement, $priceNomenclature);

                $priceAgreementsFilter[$agreement['UF_1C_ID']] = $agreement;
                $priceTypeFilter[$agreement['PRICE_TYPE_1C_ID']] = $agreement;
            }
        }

        $this->arResult['priceAgreementsFilter'] = $priceAgreementsFilter;
        $this->arResult['priceTypeFilter'] = $priceTypeFilter;

        return $priceTypes;
    }
        
    private function loadBrands()
    {
        return array_column(
            $this->getManagerBrands()->findBy1cIdList(
                array_column($this->arResult['products'], 'BRAND')
            ),
            'UF_NAME',
            'UF_1C_ID'
        );
    }

    private function saveAddition() : array
    {
        $contract = $this->arResult['contract'];
        $period = $this->arResult['periodList'][$this->request->getPost('period')];
        $store = $this->request->getPost('store');
        $comments = $this->request->getPost('comments');
        $transportationType = $this->request->getPost('transportationType');
        $deliveryPoint = $this->request->getPost('deliveryPoint');
        $deliveryAddress = $this->request->getPost('deliveryAddress');
        $deliveryType = $this->request->getPost('deliveryType');
        $receiver = $this->request->getPost('receiver');
        $rawId = (int)$this->request->getPost('rawId');

        $mngAdditionals = $this->getManagerAdditionals();

        $additionIds = [];
        $newAdditions = $this->generateNewAdditions();
        $isPacking = $this->arResult['stores'][$store]['UF_TYPE'] == $this->getManagerStore()->getTypeIdPacking();

        $this->startTransaction();
        try {
            $modifyAdditionsProducts = $this->updateAndCreateAdditions($newAdditions, [
                'UF_CONTRACT'          => $contract['UF_1C_ID'],
                'UF_PERIOD'            => $period,
                'UF_TRANSPORTATION_TY' => $transportationType,
                'UF_DELIVERY_TYPE'     => $deliveryType,
                'UF_DELIVERY_POINT'    => $deliveryPoint,
                'UF_DELIVERY_ADDRESS'  => $deliveryAddress,
                'UF_RECEIVER'          => $receiver,
                'UF_SHIPPING_STORE'    => $store,
                'UF_STATUS'            => [
                    $this->getManagerAdditionals()->getStatusAgreedId(),
                    $this->getManagerAdditionals()->getStatusOnAgreedId()
                ],
                'UF_ACTIVE'            => true,
            ]);

            $this->addProductsInExistingAdditions($modifyAdditionsProducts['update'], $isPacking);
            $additionIds = $this->createNewAdditions($modifyAdditionsProducts['create'], [
                'UF_CONTRACT'          => $contract['UF_1C_ID'],
                'UF_PERIOD'            => $period,
                'UF_TRANSPORTATION_TY' => $transportationType,
                'UF_DELIVERY_TYPE'     => $deliveryType,
                'UF_DELIVERY_POINT'    => $deliveryPoint,
                'UF_DELIVERY_ADDRESS'  => $deliveryAddress,
                'UF_RECEIVER'          => $receiver,
                'UF_SHIPPING_STORE'    => $store,
                'UF_COMMENT'           => $comments,
                'UF_STATUS'            => $mngAdditionals->getStatusOnAgreedId(),
                'UF_DATE'              => new \Bitrix\Main\Type\Date,
                'UF_USER'              => $this->getManagerUser()->getCurrentUserId(),
                'UF_ACTIVE'            => true,
            ], $isPacking);

            if (!empty($this->arResult['crossingAdditions'])) {
                $this->updateAdditions($isPacking);
            }
            
            if ($rawId > 0) {
                $this->getManagerAdditionProductsRaw()->multiDeleteByFilter(['=UF_ADDITION' => $rawId]);
                $this->getManagerAdditionsRaw()->multiDeleteByFilter(['=ID' => $rawId]);
            }

            $this->commitTransaction();
        } catch (\Throwable $exception) {
            $this->rollbackTransaction();
            throw new SystemException($exception->getMessage());
        }

        return $additionIds;
    }

    /**
     * Подготовка массивов для создания новых и обновления существующих дополнений
     * @param array $products
     * @param array $additionsFilter
     * @return array
     */
    protected function updateAndCreateAdditions($products, $additionsFilter)
    {
        $result = [];
        $productsToCompare = [];
        $productsToAddition = [];

        $additions = $this->getManagerAdditionals()->find([
            'select'  => [
                'ADDITION_ID' => 'ID', 'UF_ADVERT_50_50', 'PRODUCT_ID' => 'product.UF_PRODUCTS',
                'UF_1C_ID', 'UF_ADDITION_NUMBER', 'UF_TRANSPORTATION_TY',
                'UF_DELIVERY_TYPE', 'UF_RECEIVER', 'UF_DELIVERY_ADDRESS',
            ],
            'filter'  => $additionsFilter,
            'order'   => ['ID' => 'DESC'],
            'runtime' => [
                'product' => new \Bitrix\Main\Entity\ReferenceField(
                    'product',
                    ManagerRegistry::getManagerHL('AdditionalProducts')->getDataClass(),
                    ['=this.ID' => 'ref.UF_ADDITION']
                ),
            ],
        ]);

        foreach ($additions as $additionProduct) {
            $productsToCompare[$additionProduct['UF_ADVERT_50_50']]
                [$additionProduct['ADDITION_ID']][$additionProduct['PRODUCT_ID']] = $additionProduct;
        }

        foreach ($products as $fifty => $productCombination) {
            if (empty($productsToCompare[$fifty])) {
                continue;
            }

            foreach ($productCombination as $productId => $product) {
                foreach ($productsToCompare[$fifty] as $additionId => $productInfo) {
                    if (!empty($productInfo[$productId]) || empty($products[$fifty][$productId])) {
                        continue;
                    }

                    if (!$this->arResult['crossingAdditions'][$additionId]) {
                        $firstProductInfo = current($productInfo);
                        $this->arResult['crossingAdditions'][$additionId]['info'] = [
                            'ID'                => $firstProductInfo['ADDITION_ID'],
                            '1C_ID'             => $firstProductInfo['UF_1C_ID'],
                            'NUMBER'            => $firstProductInfo['UF_ADDITION_NUMBER'],
                            'TRANSPORTATION_TY' => $firstProductInfo['UF_TRANSPORTATION_TY'],
                            'DELIVERY_TYPE'     => $firstProductInfo['UF_DELIVERY_TYPE'],
                            'ADVERT_50_50'      => $firstProductInfo['UF_ADVERT_50_50'],
                            'RECEIVER'          => $firstProductInfo['UF_RECEIVER'],
                            'DELIVERY_ADDRESS'  => $firstProductInfo['UF_DELIVERY_ADDRESS'],
                        ];
                    }
                    $productsToAddition[$additionId][$productId] = array_shift($products[$fifty][$productId]);
                    $productsToAddition[$additionId][$productId]['IS_NEW'] = true;
                    $this->arResult['crossingAdditions'][$additionId]['products'][$productId] = $productsToAddition[$additionId][$productId];

                    if (empty($products[$fifty][$productId])) {
                        unset($products[$fifty][$productId]);
                    }
                }
            }
        }

        $result['update'] = $productsToAddition;
        $result['create'] = $products;

        return $result;
    }

    /**
     * Создание новых дополнений
     * @param array $products
     * @param array $additionsData
     * @param bool $isPacking
     * @return array
     */
    protected function createNewAdditions($products, $additionsData, $isPacking)
    {
        $additionIds = [];

        foreach ($products as $fifty => $productModifications) {
            if (empty($productModifications)) {
                continue;
            }

            $additionsData['UF_ADVERT_50_50'] = $fifty;
            $additionsData['UF_ADVERT_50_50_VAL'] = $fifty == 1 ? 10 : 0;

            while (!empty($productModifications)) {
                $additionId = $this->getManagerAdditionals()->addItem($additionsData);
                $tmpProducts = [];

                foreach ($productModifications as $productId => $arProduct) {
                    $tmpProducts[] = array_shift($productModifications[$productId]);

                    if (empty($productModifications[$productId])) {
                        unset($productModifications[$productId]);
                    }
                }

                $newProducts = [];
                $additionSum = 0;
                $additionSumDiscount = 0;
                $productsPrice = $this->getManagerAdditionalProducts()->getCalcPrices($tmpProducts, $fifty, $isPacking);

                foreach ($tmpProducts as $product) {
                    $prices = $productsPrice[$product['id']];
                    $newProducts[] = array_merge([
                        'UF_ADDITION'           => $additionId,
                        'UF_PRODUCTS'           => $product['id'],
                        'UF_PRODUCTS_CNT'       => $product['volume'],
                        'UF_MEASURE'            => $product['measure'],
                        'UF_SPECIAL_PRICE'      => $product['specialPrice'],
                        'UF_PRICE_AGREEMENT'    => $product['priceAgreementId'],
                        'UF_PRICE_TYPE'         => $product['priceTypeId'],
                    ], $prices);
                    
                    $additionSum += $prices['UF_TAX_VALUE'];
                    $additionSumDiscount += $prices['UF_DISCOUNT_TOTAL'];
                }

                $this->getManagerAdditionals()->multiUpdate(['=ID' => $additionId], ['UF_TAX_PRICE' => $additionSum, 'UF_ADVERT_50_50_COST' => $additionSumDiscount]);
                $this->getManagerAdditionalProducts()->multiInsertChunk(array_keys($newProducts[0]), $newProducts);
                $additionIds[] = $additionId;
            }
        }

        return $additionIds;
    }

    /**
     * Добавление товаров в существующие дополнения
     * @param array $productsToAdditions
     * @param bool $isPacking
     * @return array
     */
    protected function addProductsInExistingAdditions($productsToAdditions, $isPacking)
    {
        foreach ($productsToAdditions as $additionId => $products) {
            $newProducts = [];
            $productsPrice = $this->getManagerAdditionalProducts()->getCalcPrices($products, current($products)['fiftyFyfty'], $isPacking);
            $additionFields = [
                'UF_ADDITION'    => $this->crossingAdditions[$additionId]['info']['UF_1C_ID'],
                'UF_ADDITION_ID' => $additionId
            ];

            foreach ($products as $product) {
                $prices = $productsPrice[$product['id']];
                $newProducts[] = array_merge([
                    'UF_ADDITION'           => $additionId,
                    'UF_PRODUCTS'           => $product['id'],
                    'UF_PRODUCTS_CNT'       => $product['volume'],
                    'UF_MEASURE'            => $product['measure'],
                    'UF_SPECIAL_PRICE'      => $product['specialPrice'],
                    'UF_PRICE_AGREEMENT'    => $product['priceAgreementId'],
                    'UF_PRICE_TYPE'         => $product['priceTypeId'],
                ], $prices);

                $this->addChanges(Loc::getMessage('K_C_ADDITIONAL_CREATE_CLASS_PRODUCT_CREATED'), $additionFields, ['UF_PRODUCTS_CNT' => 1], $product['id']);
            }

            $this->getManagerAdditionalProducts()->multiInsertChunk(array_keys($newProducts[0]), $newProducts);
        }
    }

    /**
     * Обновления дополнений
     * 
     * @param bool $isPacking
     * @return array
     */
    protected function updateAdditions($isPacking)
    {
        $mngAdditionals = $this->getManagerAdditionals();
        $mngAdditionalProducts = $this->getManagerAdditionalProducts();

        foreach ($this->arResult['crossingAdditions'] as $additionId => $crossingAddition) {
            $productsPrice = $mngAdditionalProducts->getCalcPrices($crossingAddition['products'], $crossingAddition['info']['ADVERT_50_50'], $isPacking);
            $code =  $this->crossingAdditions[$additionId]['info']['NONPALLET'] ? "UT" : ($isPacking ? "PL" : "TN");

            foreach ($crossingAddition['products'] as $productId => $product) {
                if (empty($product['IS_NEW'])) {
                    $productOrigin = [
                        'UF_PRODUCTS_CNT' => $this->crossingAdditions[$additionId]['products'][$productId]['volume'],
                        'UF_PRODUCTS' => $productId
                    ];
                    $productNew = [
                        'UF_PRODUCTS_CNT' => $product['volume'],
                    ];

                    $changes = $this->getManagerAdditionHistory()->isDiffProduct($productNew, $productOrigin, $code);

                    if (!empty($changes['changed'])) {
                        $additionFields = [
                            'UF_ADDITION' => $this->crossingAdditions[$additionId]['info']['1C_ID'],
                            'UF_ADDITION_ID' => $this->crossingAdditions[$additionId]['info']['ID']
                        ];
                        $this->addChanges($changes['text'], $additionFields, $changes['fields'], $changes['product1cId']);
                    }
                }

                $prices = $productsPrice[$product['id']];
                $newProduct = array_merge([
                    'UF_PRODUCTS_CNT'       => $product['volume'],
                    'UF_SPECIAL_PRICE'      => $product['specialPrice'],
                    'UF_PRICE_AGREEMENT'    => $product['priceAgreementId'],
                    'UF_PRICE_TYPE'         => $product['priceTypeId'],
                ], is_array($prices) ? $prices : []);
                
                $mngAdditionalProducts->multiUpdate(
                    [
                        '=UF_ADDITION' => $crossingAddition['info']['ID'],
                        '=UF_PRODUCTS' => $productId,
                    ],
                    $newProduct
                );
            }
        }
        
        $additionsSum = $this->getManagerAdditionalProducts()->find([
            'select' => [
                'UF_ADDITION',
                new ExpressionField('CNT', 'SUM(`UF_TAX_VALUE`)'),
                new ExpressionField('DISC', 'SUM(`UF_DISCOUNT_TOTAL`)'),
            ],
            'filter' => ['=UF_ADDITION' => array_keys($this->arResult['crossingAdditions'])],
        ]);
        
        foreach ($additionsSum as $additionSum) {
            $mngAdditionals->multiUpdate(
                ['=ID' => $additionSum['UF_ADDITION']],
                ['UF_STATUS' => $mngAdditionals->getStatusOnAgreedId(), 'UF_TAX_PRICE' => $additionSum['CNT'], 'UF_ADVERT_50_50_COST' => $additionSum['DISC']]
            );
        }

        $this->getManagerAdditionHistory()->saveChangesBatch($this->changes);
    }

    /**
     * Заполняет массив изменений
     * @param type  $text
     * @param array $fields
     * @param type  $product1cId
     */
    protected function addChanges($text, array $additionFields, array $fields = [], $product1cId = null)
    {
        $this->changes[] = [
            'UF_ADDITION'        => $additionFields['UF_ADDITION'],
            'UF_ADDITION_ID'     => $additionFields['UF_ADDITION_ID'],
            'UF_CHANGES'         => $text,
            'UF_CHANGES_FIELDS'  => serialize($fields),
            'UF_DATE'            => date('Y-m-d H:i:s'),
            'UF_PRODUCT'         => $product1cId,
        ];
    }

    private function handleAjax()
    {
        $action = $this->request->getPost('action');

        if ($action === 'saveRaw') {
            $this->ajaxSaveRaw();
            return;
        }
        
        if ($action === 'getStores') {
            echo json_encode(array_column($this->arResult['stores'], 'TITLE', 'UF_CODE_ASKU'));
            return;
        }

        if ($action === 'getDeliveryPoints') {
            $this->arResult['deliveryPoints'] = $this->getDeliveryPoints(
                (string)$this->request->getPost('q'),
                (string)$this->request->getPost('tt')
            );
            $this->includeComponentTemplate('ajax_delivery_points');
            return;
        }

        if ($action === 'getDeliveryAddresses') {
            $this->arResult['deliveryAddresses'] = $this->getDeliveryAddresses(
                (string)$this->request->getPost('q')
            );
            $this->includeComponentTemplate('ajax_delivery_addresses');
            return;
        }

        $this->arResult['request'] = $this->getRequest();
        if (!$this->arResult['request']) {
            echo json_encode(['data' => [], 'errorMessage' => 'Ошибка: нет оформленных заявок в выбранном периоде']);
            return;
        }

        $store1cId = $this->request->getPost('store');
        $this->arResult['storeProducts'] = $this->arResult['request']['stores'][$store1cId];
        if (!$this->arResult['storeProducts']) {
            echo json_encode([
                'data' => [],
                'errorMessage' => 'Ошибка: отсутствуют товары, соответствующие указанному складу',
            ]);
            return;
        }

        $this->arResult['crossingAdditions'] = $this->crossingAdditions = $this->getCrossingAdditions($store1cId, $action === 'save');

        if ($action === 'getProducts') {
            $this->updateProductsCountAvailable();

            $managerStore = $this->getManagerStore();
            $currentStoreTypeId = $this->arResult['stores'][$store1cId]['UF_TYPE'];
            if ($currentStoreTypeId == $managerStore->getTypeIdPacking()) {
                $this->arResult['storeType'] = 'packing';
            } elseif ($currentStoreTypeId == $managerStore->getTypeIdPouring()) {
                $this->arResult['storeType'] = 'pouring';
            }

            $this->arResult['products'] = $this->getProducts();
            $this->arResult['priceTypes'] = $this->getPriceTypes($store1cId);
            $this->arResult['brands'] = $this->loadBrands();

            $this->includeComponentTemplate('ajax_products');

            return;
        }

        if ($action === 'save') {
            $this->ajaxSave($store1cId);
            return;
        }
    }
    
    protected function ajaxSave($store1cId)
    {
        $this->arResult['products'] = $this->request->getPost('product');
        $exceededAllowedSizeProducts = $this->checkExceededAllowedSize();
        $this->reduceCrossingAdditionsByCurrentProducts();

        if (!$this->request->getPost('createNewRequest') && $exceededAllowedSizeProducts) {
            echo json_encode([
                'canCreate' => $this->getManagerPeriodWorkApplication()->isMainRequestCreateAvailable(
                    \Bitrix\Main\Type\Date::createFromTimestamp(strtotime($this->request->getPost('period') . '01'))
                ),
                'exceededLimit' => true,
                'products'      => $exceededAllowedSizeProducts,
            ]);
            return;
        }

        if ($this->request->getPost('createNewRequest') && $exceededAllowedSizeProducts) {
            try {
                $requestId = $this->createRequest((string) $store1cId, $exceededAllowedSizeProducts);
            } catch (\Throwable $exception) {
                $this->addLog([
                    'message'   => "Ошибка при создании Заявки",
                    'exception' => $exception->getMessage()]);
                echo json_encode(['error' => "Ошибка при создании Заявки"]);
                return;
            }
            
            $this->getManagerRequests()->launchBackgroundProcess($requestId, "createByAddition");
        }

        if (empty($this->arResult['crossingAdditions']) || $this->request->getPost('userAgreed')) {
            try {
                $additionIds = $this->saveAddition();

                try {
                    $this->launchBackgroundProcess($additionIds);
                } catch (SystemException $exception) {
                    $this->addLog([
                        'message'           => "Ошибка отправки письма.",
                        'exception'         => $exception->getMessage(),
                        'additionIds'       => $additionIds,
                        'crossingAdditions' => $this->arResult['crossingAdditions']]);
                }

                echo json_encode([
                     'additionIds'    => $additionIds,
                     'crossAdditions' => array_keys($this->arResult['crossingAdditions'])]
                );

            } catch (SystemException $exception) {
                echo $exception->getMessage();
                $this->addLog([
                    'message'   => "Ошибка при сохранении",
                    'exception' => $exception->getMessage()]);
                echo json_encode(['error' => 'Ошибка при сохранении']);
            }
        } else {
            echo json_encode([
                 'hasCrossing'    => true,
                 'additions'      => array_values($this->arResult['crossingAdditions']),
                 'crossAdditions' => array_keys($this->arResult['crossingAdditions']),
            ]);
        }
    }
    
    /**
     * Создает новую заявку с товарами
     * @param string $store1cId 1C склада
     * @param array $exceededAllowedSizeProducts
     * @return int ID заявки
     * @throws SystemException
     */
    protected function createRequest(string $store1cId, array $exceededAllowedSizeProducts) : int
    {
        $managerRequests = $this->getManagerRequests();
        $contract1cId = $this->request->getPost('contract');
        $objDate = \Bitrix\Main\Type\Date::createFromTimestamp(strtotime($this->request->getPost('period') . '01'));
        $mainRequestData = $managerRequests->getMainRequestByContract($contract1cId, $objDate);
        
        $this->startTransaction();
        try {
            $requestItem = [
                'UF_CONTRACT'     => $contract1cId,
                'UF_USER'         => $this->getManagerUser()->getCurrentUserId(),
                'UF_DATE_INSERT'  => new \Bitrix\Main\Type\DateTime,
                'UF_MAIN_REQUEST' => $mainRequestData['UF_1C_ID'],
                'UF_MONTH'        => $mainRequestData['UF_MONTH'],
                'UF_STATUS'       => $managerRequests->getStatusOnAgreedId(),
                'UF_TYPE'         => $managerRequests->getResourcesTypeId(),
            ];

            $iRequestId = $managerRequests->addItem($requestItem);
            $managerRequestProducts = $this->getManagerRequestProducts();
            foreach ($exceededAllowedSizeProducts as $productId => $productData) {
                $value = $productData['volume'] - $productData['limit'];
                if ($value == 0) {
                    continue;
                }
                $managerRequestProducts->addItem([
                    'UF_REQUEST' => $iRequestId,
                    'UF_PRODUCT' => $productId,
                    'UF_VOLUME'  => $value,
                    'UF_MEASURE' => $productData['measure'],
                    'UF_STORE'   => $store1cId,
                ]);
            }

            $this->commitTransaction();
        } catch (\Throwable $exception) {
            $this->rollbackTransaction();
            throw new SystemException($exception->getMessage());
        }
        
        return $iRequestId;
    }
    
    /**
     * Проверяемым заявку на наличие превышения согласованного объема
     *
     * @return array
     */
    protected function checkExceededAllowedSize() : array
    {
        $exceededAllowedSize = [];
        
        foreach ($this->arResult['products'] as $productId => $product) {
            $currentProduct = current($product);
            $sum = array_sum(array_column($product, 'volume'));
            if (isset($currentProduct['limit']) && $sum > $currentProduct['limit']) {
                $exceededAllowedSize[$productId] = [
                    'volume'  => $sum,
                    'limit'   => $currentProduct['limit'],
                    'measure' => $currentProduct['measure']
                ];
            }
        }

        return $exceededAllowedSize;
    }
    
    /**
     * Вид транспортировки
     * 
     * @return array
     */
    protected function getTransportationTypes()
    {
        return $this->getManagerTransportationTypes()->find();
    }

    /**
     * Пункты доставки
     *
     * @param string $searchName         Строка поиска по UF_NAME
     * @param string $transportationType Вид транспортировки
     *
     * @return array
     */
    protected function getDeliveryPoints(string $searchName = '', string $transportationType = '') : array
    {
        $result = [];
        if (strlen($searchName) > 0) {
            $query = ManagerRegistry::getManagerHL('DeliveryPoints')->getDataClass()::query();
            $query2 = ManagerRegistry::getManagerHL('DeliveryPoints')->getDataClass()::query()
                ->addSelect('*')
                ->addSelect(new Bitrix\Main\ORM\Fields\ExpressionField('RANK', '2', 'ID'))
                ->addFilter('%UF_NAME', $searchName)
                ->addFilter('!UF_NAME', $searchName . '%');
            
            if (!empty($transportationType)) {
                $query->addFilter('=UF_TRANSPORTATION_TY', $transportationType);
                $query2->addFilter('=UF_TRANSPORTATION_TY', $transportationType);
            }
            
            $query->addSelect('*')
                ->addSelect(new Bitrix\Main\ORM\Fields\ExpressionField('RANK', '1', 'ID'))
                ->addFilter('UF_NAME', $searchName . '%')
                ->union($query2)
                ->addUnionOrder('RANK')
                ->addUnionOrder('UF_NAME')
                ->setUnionLimit(10);

            $result = $query->exec()->fetchAll();
        } else {
            $filter = !empty($transportationType) ? ['=UF_TRANSPORTATION_TY' => $transportationType] : [];
            $result = $this->getManagerDeliveryPoints()->find(
                [
                    'filter' => $filter,
                    'order'  => ['UF_NAME' => 'asc'],
                    'limit'  => 10,
                ]
            );
        }

        return $result;
    }
    
    /**
     * Грузополучатели
     * 
     * @return array
     * @throws SystemException
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     */
    protected function getReceivers()
    {
        return $this->getManagerContractors()->getReceivers($this->arResult['contract']['UF_CONTRACTOR']);
    }

    /**
     * Способы доставки
     * 
     * @return array
     */
    protected function getDeliveryTypes()
    {
        return $this->getManagerDeliveryTypes()->find(['order' => ['UF_NAME' => 'asc']]);
    }

    /**
     * Адреса доставки
     *
     * @param string $searchAddress Строка поиска по UF_ADDRESS
     * @return array
     */
    protected function getDeliveryAddresses(string $searchAddress = '')
    {
        $result = [];
        if (strlen($searchAddress) > 0) {
            $query = ManagerRegistry::getManagerHL('DeliveryAddresses')->getDataClass()::query();
            $query2 = ManagerRegistry::getManagerHL('DeliveryAddresses')->getDataClass()::query()
                ->addSelect('*')
                ->addSelect(new Bitrix\Main\ORM\Fields\ExpressionField('RANK', '2', 'ID'))
                ->addFilter('%UF_ADDRESS', $searchAddress)
                ->addFilter('!UF_ADDRESS', $searchAddress . '%');

            $query->addFilter('=UF_B2B_CLIENT', $this->arResult['user']['UF_CONTRACTOR']);
            $query->addFilter('=UF_IS_ACTIVE', 1);
            $query2->addFilter('=UF_B2B_CLIENT', $this->arResult['user']['UF_CONTRACTOR']);
            $query2->addFilter('=UF_IS_ACTIVE', 1);

            $query->addSelect('*')
                ->addSelect(new Bitrix\Main\ORM\Fields\ExpressionField('RANK', '1', 'ID'))
                ->addFilter('UF_ADDRESS', $searchAddress . '%')
                ->union($query2)
                ->addUnionOrder('RANK')
                ->addUnionOrder('UF_ADDRESS')
                ->setUnionLimit(10);

            $result = $query->exec()->fetchAll();
        } else {
            $filter = [
                '=UF_B2B_CLIENT' => $this->arResult['user']['UF_CONTRACTOR'],
                '=UF_IS_ACTIVE'  => 1,
            ];
            $result = $this->getManagerDeliveryAddresses()->find(
                [
                    'filter' => $filter,
                    'order'  => ['UF_ADDRESS' => 'asc'],
                    'limit'  => 10,
                ]
            );
        }

        return $result;
    }

    /**
     * Соберем массив новых дополнений из товаров
     * 
     * @return array
     */
    protected function generateNewAdditions() : array
    {
        $result = [];
        foreach ($this->arResult['products'] as $productId => $products) {
            foreach ($products as $product) {
                $hash = md5($product['id'].$product['priceAgreementId'].$product['priceTypeId'].$product['fiftyFyfty'].$product['specialPrice']);

                if (!empty($result[$product['fiftyFyfty']][$productId][$hash])) {
                    $result[$product['fiftyFyfty']][$productId][$hash]['volume'] += $product['volume'];
                } else {
                    $result[$product['fiftyFyfty']][$productId][$hash] = $product;
                }
            }
        }
                
        return $result;
    }

    protected function getPeriod() 
    {
        $date = new DateTime('now');
        $date->modify('first day of this month');
        $periodNow = $date->format("d.m.Y");
        $date->modify('+1 month');
        $periodNext = $date->format("d.m.Y");

        $request = $this->getManagerRequestProducts()->getRow([
            'select' => ['MONTH' => 'UF_REQUEST_REF.UF_MONTH', new ExpressionField('CNT', 'SUM(`UF_VOLUME_MATCHED`)')],
            'filter' => [
                '=UF_REQUEST_REF.UF_CONTRACT' => $this->arResult['contract']['UF_1C_ID'],
                '!=UF_VOLUME_MATCHED'         => 0,
                '=UF_REQUEST_REF.UF_MONTH' => [$periodNow, $periodNext],
            ],
            'order' => ['UF_REQUEST_REF.UF_MONTH' => 'DESC'],
        ]);

        if ($request) {
            return $request['MONTH'];
        }
        
        return null;
    }

    /**
     * Сохранение черновика
     */
    protected function ajaxSaveRaw()
    {
        $products = $this->request->getPost('product');
        $contract = $this->arResult['contract'];
        $period = $this->arResult['periodList'][$this->request->getPost('period')];
        $store = $this->request->getPost('store');
        $comments = $this->request->getPost('comments');
        $transportationType = $this->request->getPost('transportationType');
        $deliveryPoint = $this->request->getPost('deliveryPoint');
        $deliveryAddress = $this->request->getPost('deliveryAddress');
        $deliveryType = $this->request->getPost('deliveryType');
        $receiver = $this->request->getPost('receiver');
               
        try {
            $additionId = $this->getManagerAdditionsRaw()->addItem([
                'UF_CONTRACT'          => $contract['UF_1C_ID'],
                'UF_PERIOD'            => $period,
                'UF_SHIPPING_STORE'    => $store,
                'UF_TRANSPORTATION_TY' => $transportationType,
                'UF_DELIVERY_TYPE'     => $deliveryType,
                'UF_DELIVERY_POINT'    => $deliveryPoint,
                'UF_DELIVERY_ADDRESS'  => $deliveryAddress,
                'UF_RECEIVER'          => $receiver,
                'UF_COMMENT'           => $comments,
                'UF_USER'              => $this->getManagerUser()->getCurrentUserId(),
            ]);

            $newProducts = [];
            foreach ($products as $product) {
                foreach ($product as $product) {
                    $newProducts[] = [
                        'UF_ADDITION'        => $additionId,
                        'UF_PRODUCTS'        => $product['id'],
                        'UF_VOLUME'          => $product['volume'],
                        'UF_PRICE_AGREEMENT' => $product['priceAgreementId'],
                        'UF_PRICE_TYPE'      => $product['priceTypeId'],
                        'UF_ADVERT_50_50'    => $product['fiftyFyfty'],
                        'UF_SPECIAL_PRICE'   => $product['specialPrice'],
                    ];
                }
            }
            $this->getManagerAdditionProductsRaw()->multiInsertChunk(array_keys($newProducts[0]), $newProducts);
            echo json_encode(['additionId'    => $additionId]);
        } catch (SystemException $exception) {
            $this->addLog([
                'message'   => "Ошибка при сохранении черновика",
                'exception' => $exception->getMessage()]);
            echo json_encode(['error' => 'Ошибка при сохранении черновика']);
        }
    }
    
    /**
     * Запуск процесса генерации дополнений и отправки почты
     * @param $additionIds
     */
    private function launchBackgroundProcess($additionIds)
    {
        $crossIds = [];
        foreach ($this->arResult['crossingAdditions'] as $crossingAddition) {
            $crossIds[$crossingAddition['info']['ID']] = array_keys($crossingAddition['products']);
        }

        $str = base64_encode(
            gzcompress(
                serialize(
                    ['additionIds' => $additionIds,
                     'crossIds'    => $crossIds]
                )
            )
        );

        $docRoot = Application::getDocumentRoot();
        exec(
            '/usr/bin/php '
            . $docRoot . '/../background/agreements.php '
            . $str
            . ' &>> '
            . $docRoot . '/../logs/background_agreement.log &'
        );
    }
    
    private function addLog($message = []) {
        (new Logging())->addLog(
            \CEventLog::SEVERITY_ERROR,
            'korus.component.agreements.create',
            $message
        )->save();
    }
}
