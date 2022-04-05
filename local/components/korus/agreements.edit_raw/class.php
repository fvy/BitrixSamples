<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use \Bitrix\Main\SystemException;
use \Korus\Basic\Manager\ManagerFramework\ManagerContracts;
use \Korus\Basic\Traits;
use \Korus\Basic\Helpers\Page;
use Korus\Catalog\Components\Agreements\Price;
use \Korus\Logging\Logging;

class AdditionalsEditRaw extends \CBitrixComponent
{
    use Traits\GetManager;
    use \Korus\Basic\Traits\Errors;
    use \Korus\Basic\Traits\Transaction;
    
    public function __construct($component = null)
    {
        Page::initModules(['korus.framework', 'korus.b2b', 'korus.logging', 'korus.exchange']);

        $this->initError();
        parent::__construct($component);
    }

    public function executeComponent()
    {
        /** @global \CMAIN $APPLICATION */
        global $APPLICATION;

        if (isset($this->arParams['PAGE_TITLE'])) {
            $APPLICATION->SetTitle($this->arParams['PAGE_TITLE']);
        }

        $this->arResult['addition'] = $this->getAddition();
        $this->arResult['additionProducts'] = $this->getAdditionProducts();
        $this->arResult['contract'] = $this->getContractData();
        $this->arResult['period'] = $this->getPeriod();
        $this->arResult['requestsData'] = $this->getRequestListWithMatchedVolumeProducts();
        $this->arResult['periodList'] = $this->getPeriods();
        $this->arResult['stores'] = $this->getStores();
        $this->arResult['user'] = $this->getManagerUser()->getCurrentUserData();;

        $managerMeasurement = new \Korus\Basic\Manager\ManagerMeasurement;
        $this->arResult['measures'] = $managerMeasurement->getAll();

        if ($this->request->isAjaxRequest()) {
            $this->handleAjax();

            return;
        }
        
        if ($this->accessCheck()) {
            $this->arResult['transportationTypes'] = $this->getTransportationTypes();
            $this->arResult['deliveryTypes'] = $this->getDeliveryTypes();
            $this->arResult['receivers'] = $this->getReceivers();
        }
        
        $this->arResult['ERRORS'] = $this->errorCollection;

        $this->includeComponentTemplate();
    }
    
    private function getAddition()
    {
        $id = $this->arParams['AGREEMENT_ID'];
        $rawId = (int)$this->request->getPost('rawId');
        if (empty($id) && empty($rawId)) {
            return [];
        }
        
        return $this->getManagerAdditionsRaw()->getRow([
            'select' => [
                '*',
                'DELIVERY_POINT_NAME'   => 'UF_DELIVERY_POINT_REF.UF_NAME',
                'DELIVERY_ADDRESS_NAME' => 'UF_DELIVERY_ADDRESS_REF.UF_ADDRESS',
            ],
            'filter' => ['=ID' => !empty($id) ? $id : $rawId],
        ]);
    }
    
    private function getAdditionProducts()
    {
        $products = [];
        $productsRaw = $this->getManagerAdditionProductsRaw()->find(['filter' => ['=UF_ADDITION' => $this->arResult['addition']['ID']]]);
        
        foreach ($productsRaw as $product) {
            $products[$this->arResult['addition']['UF_SHIPPING_STORE']][$product['UF_PRODUCTS']][] = [
                'ID'              => $product['UF_PRODUCTS'],
                'VOLUME'          => $product['UF_VOLUME'],
                'PRICE_AGREEMENT' => $product['UF_PRICE_AGREEMENT'],
                'PRICE_TYPE'      => $product['UF_PRICE_TYPE'],
                'ADVERT_50_50'    => $product['UF_ADVERT_50_50'],
                'SPECIAL_PRICE'   => $product['UF_SPECIAL_PRICE'],
            ];
        }

        return $products;
    }
    
    private function getContractData()
    {
        return $this->getManagerContracts()->getRow([
            'filter' => [
                '=ID'            => ManagerContracts::getContractIdCookie(),
                '=UF_ACTIVITY'   => 1,
                '=UF_CONTRACTOR' => $this->getManagerUser()->getCurrentUserData()["UF_CONTRACTOR"],
            ],
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
            '=UF_NONPALLET'                => 0,
            '=UF_REQUEST_REF.UF_MONTH'     => $this->arResult['period']->format("01.m.Y"),
            '=UF_REQUEST_REF.UF_IS_ACTIVE' => true,
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
        if(empty($this->arResult['requestsData'])) {
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
     * @param type $store1cId
     * 
     * @return array
     * @throws SystemException
     */
    private function getCrossingAdditions($store1cId)
    {        
        $additions = [];
        
        if (!empty($store1cId)) {
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
                    'PRICE_TYPE'                 => 'UF_PRICE_TYPE_REF.UF_NAME'
                ],
                'filter' => [
                    '=UF_PRODUCTS'                       => array_keys($this->arResult['storeProducts']),
                    '=UF_ADDITION_REF.UF_CONTRACT'       => $this->arResult['contract']['UF_1C_ID'],
                    '=UF_ADDITION_REF.UF_PERIOD'         => $this->arResult['request']['REQUEST_MONTH'],
                    '=UF_ADDITION_REF.UF_SHIPPING_STORE' => $store1cId,
                    '=UF_ADDITION_REF.UF_NONPALLET'      => 0,
                    '=UF_ADDITION_REF.UF_ACTIVE'         => true,
                    '=UF_ADDITION_REF.UF_STATUS'         => [
                        $this->getManagerAdditionals()->getStatusAgreedId(),
                        $this->getManagerAdditionals()->getStatusOnAgreedId(),
                    ],
                ],
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
                ];
                
                $additions[$additionalProduct['ADDITION_ID']]['products'][$additionalProduct['UF_PRODUCTS']] = [
                    'id'                 => $additionalProduct['UF_PRODUCTS'],
                    'volume'             => $additionalProduct['UF_PRODUCTS_CNT'],
                    'priceAgreement'     => $additionalProduct['UF_PRICE_AGREEMENT'],
                    'priceAgreementName' => $additionalProduct['PRICE_AGREEMENT'],
                    'priceAgreementType' => $additionalProduct['AGREEMENT_TYPE'],
                    'priceType'          => $additionalProduct['UF_PRICE_TYPE'],
                    'priceTypeName'      => $additionalProduct['PRICE_TYPE'],
                    'specialPrice'       => $additionalProduct['UF_SPECIAL_PRICE'],
                ];
            }
        }

        return $additions;
    }

    /**
     * Сокращение списка пересекающихся товаров с учетом переданных данных по товарам
     */
    private function reduceCrossingAdditionsByCurrentProducts()
    {
        $deliveryType = $this->request->getPost('deliveryType');
        $deliveryAddress = $this->request->getPost('deliveryAddress');
        $transportationType = $this->request->getPost('transportationType');
        $receiver = $this->request->getPost('receiver');

        foreach ($this->arResult['crossingAdditions'] as $addition1cId => $additionData) {
            if ((
                    !empty($additionData['info']['TRANSPORTATION_TY']) && !empty($transportationType) 
                    && $additionData['info']['TRANSPORTATION_TY'] != $transportationType
                ) || (
                    !empty($additionData['info']['DELIVERY_TYPE']) && !empty($deliveryType) 
                    && $additionData['info']['DELIVERY_TYPE'] != $deliveryType
                ) || (
                    !empty($additionData['info']['DELIVERY_ADDRESS']) && !empty($deliveryAddress) 
                    && $additionData['info']['DELIVERY_ADDRESS'] != $deliveryAddress
                ) || (
                    !empty($additionData['info']['RECEIVER']) && !empty($receiver) 
                    && $additionData['info']['RECEIVER'] != $receiver
                )) {
                unset($this->arResult['crossingAdditions'][$addition1cId]);
                continue;
            }
            
            
            foreach ($additionData['products'] as $crossProduct1cId => $crossProduct) {
                if (!isset($this->arResult['products'][$crossProduct1cId])) {
                    unset($this->arResult['crossingAdditions'][$addition1cId]['products'][$crossProduct1cId]);
                    continue;
                }
                
                $productNotFound = true;
                foreach ($this->arResult['products'][$crossProduct1cId] as $kp => $product) {
                    if ($product['priceAgreementId'] == $crossProduct['priceAgreement']
                        && $product['priceTypeId'] == $crossProduct['priceType']
                        && $product['specialPrice'] == $crossProduct['specialPrice']
                        && $product['fiftyFyfty'] == $additionData['info']['ADVERT_50_50']
                        ) {
                        $this->arResult['crossingAdditions'][$addition1cId]['products'][$crossProduct1cId]['volume'] += $product['volume']; 
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
        $priceComponent = new Price($this->arResult['contract']['UF_CONTRACTOR'], $period, [$store1cId]);

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
    
    private function handleAjax()
    {
        if (!$this->accessCheck()) {
            echo json_encode(['error' => 'Ошибка доступа']);
            return;
        }
        
        $action = $this->request->getPost('action');
        
        if ($action === 'delete') {
            $this->ajaxDeleteRaw();
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

        $this->arResult['crossingAdditions'] = $this->getCrossingAdditions($store1cId);
        
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
        
        if ($action === 'saveRaw') {
            $this->ajaxSaveRaw();
            return;
        }
        
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

    protected function getPeriod() 
    {
        return $this->arResult['addition']['UF_PERIOD'];
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
            $this->getManagerAdditionProductsRaw()->multiDeleteByFilter(['=UF_ADDITION' => $this->arResult['addition']['ID']]);
            $this->getManagerAdditionsRaw()->multiUpdate(
                ['=ID' => $this->arResult['addition']['ID']],
                [
                    'UF_CONTRACT'          => $contract['UF_1C_ID'],
                    'UF_PERIOD'            => $period->format("Y-m-d"),
                    'UF_SHIPPING_STORE'    => $store,
                    'UF_TRANSPORTATION_TY' => $transportationType,
                    'UF_DELIVERY_TYPE'     => $deliveryType,
                    'UF_DELIVERY_POINT'    => $deliveryPoint,
                    'UF_DELIVERY_ADDRESS'  => $deliveryAddress,
                    'UF_RECEIVER'          => $receiver,
                    'UF_COMMENT'           => $comments,
                ]
            );

            $newProducts = [];
            foreach ($products as $product) {
                foreach ($product as $product) {
                    $newProducts[] = [
                        'UF_ADDITION'        => $this->arResult['addition']['ID'],
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

            echo json_encode(['additionId'    => $this->arResult['addition']['ID']]);
        } catch (SystemException $exception) {
            $this->addLog([
                'message'   => "Ошибка при сохранении черновика",
                'exception' => $exception->getMessage()]);
            echo json_encode(['error' => 'Ошибка при сохранении черновика']);
        }
    }    
     
    protected function accessCheck() 
    {
        if (empty($this->arResult['addition'])) {
            $this->addError('У вас нет доступа к данному Черновику Дополнения', 'CRITICAL_ERROR');

            return false;
        }
        
        if ($this->arResult['contract']['UF_1C_ID'] != $this->arResult['addition']['UF_CONTRACT']) {
            LocalRedirect("/contracts/view/");
        }
        
        $user = $this->getManagerUser()->getCurrentUserData();
        $contractor = $this->getManagerContracts()->findBy1cId($this->arResult['addition']['UF_CONTRACT']);

        if ($contractor['UF_CONTRACTOR'] != $user['UF_CONTRACTOR']) {
            $this->addError('У вас нет доступа к данному Черновику Дополнения', 'CRITICAL_ERROR');

            return false;
        }

        return true;
    }
    
    protected function ajaxDeleteRaw()
    {
        try {
            $this->getManagerAdditionProductsRaw()->multiDeleteByFilter(['=UF_ADDITION' => $this->arResult['addition']['ID']]);
            $this->getManagerAdditionsRaw()->multiDeleteByFilter(['=ID' => $this->arResult['addition']['ID']]);
            echo json_encode(['success' => true]);
        } catch (\Throwable $exception) {
            echo json_encode(['success' => false]);
        }
    }
    
    private function addLog($message = []) {
        (new Logging())->addLog(
            \CEventLog::SEVERITY_ERROR,
            'korus.component.agreements.create',
            $message
        )->save();
    }
}
