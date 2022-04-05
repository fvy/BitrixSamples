<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use \Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use \Bitrix\Main\SystemException;
use \Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\Date;
use \Korus\Basic\Manager\ManagerFramework\ManagerContracts;
use \Korus\Basic\Helpers\Page;
use Korus\Catalog\Components\Agreements\Price;
use \Korus\Logging\Logging;
use \Korus\Framework\Manager\Data\ManagerRegistry;
use \Bitrix\Main\ORM\Fields\ExpressionField;

class AdditionalsEdit extends \CBitrixComponent
{
    use \Korus\Basic\Traits\GetManager;
    use \Korus\Basic\Traits\Errors;
    use \Korus\Basic\Traits\Transaction;
    
    protected $crossingAdditions = [];
    protected $changes = [];
    private $editedProductIds;

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
        
        $this->arResult['addition'] = $this->getAddition();
        $this->arResult['store'] = $this->getStore();
        $this->arResult['additionProducts'] = $this->getAdditionProducts();
        $this->arResult['contract'] = $this->getContractData();
        $this->arResult['user'] = $this->getManagerUser()->getCurrentUserData();

        $managerMeasurement = new \Korus\Basic\Manager\ManagerMeasurement;
        $this->arResult['measures'] = $managerMeasurement->getAll();

        $this->arResult['orderProducts'] = $this->getOrderProducts();
            
        if ($this->request->isAjaxRequest()) {
            $this->handleAjax();

            return;
        }
        
        if ($this->accessCheck()) {
            $this->arResult['remains5050'] = $this->getRemains();
        }
        
        $this->arResult['ERRORS'] = $this->errorCollection;

        $this->includeComponentTemplate();
    }
    
    protected function getAddition()
    {
        $id = $this->arParams['AGREEMENT_ID'];
        $additionId = $this->request->getPost('additionId');
        if (empty($id) && empty($additionId)) {
            return [];
        }
        
        return $this->getManagerAdditionals()->getRow([
            'select' => [
                '*',
                'RECEIVER_NAME'           => 'UF_RECEIVER_REF.UF_NAME',
                'DELIVERY_TYPE'           => 'UF_DELIVERY_TYPE_REF.UF_NAME',
                'DELIVERY_POINT_NAME'     => 'UF_DELIVERY_POINT_REF.UF_NAME',
                'DELIVERY_ADDRESS_NAME'   => 'UF_DELIVERY_ADDRESS_REF.UF_ADDRESS',
                'TRANSPORTATION_TYPE'     => 'UF_TRANSPORTATION_TY_REF.UF_NAME',
            ],
            'filter' => [
                '=ID'        => !empty($id) ? $id : $additionId,
                '=UF_ACTIVE' => 1,
            ],
        ]);
    }
    
    protected function getAdditionProducts()
    {
        $result = [];
        $productsInfo = [];
        if (empty($this->arResult['addition']['ID'])) return $result;
        
        $products = $this->getManagerAdditionalProducts()->find(['filter' => ['=UF_ADDITION' => $this->arResult['addition']['ID']]]);
        $isPacking = $this->arResult['store']['UF_TYPE'] == $this->getManagerStore()->getTypeIdPacking();

        if (empty($this->arResult['addition']['UF_NONPALLET']) && $isPacking) {
            $productsInfo = $this->getProducts(array_column($products, 'UF_PRODUCTS'));
        }

        foreach ($products as $product) {
            $value = (!empty($productsInfo[$product['UF_PRODUCTS']]['PALLET_RATE']))
                ? $productsInfo[$product['UF_PRODUCTS']]['PALLET_RATE'] * $product['UF_PRODUCTS_CNT']
                : $product['UF_PRODUCTS_CNT'];
            
            $result[$product['UF_PRODUCTS']] = [
                'ID'              => $product['UF_PRODUCTS'],
                'VOLUME'          => $product['UF_PRODUCTS_CNT'],
                'VOLUME_PIECE'    => $value,
                'PRICE_AGREEMENT' => $product['UF_PRICE_AGREEMENT'],
                'PRICE_TYPE'      => $product['UF_PRICE_TYPE'],
                'ADVERT_50_50'    => $this->arResult['addition']['UF_ADVERT_50_50'],
                'SPECIAL_PRICE'   => $product['UF_SPECIAL_PRICE'],

            ];
        }

        return $result;
    }
    
    protected function getContractData()
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
    protected function getRequestListWithMatchedVolumeProducts()
    {
        if (!$this->arResult['contract'] || !$this->arResult['contract']['UF_1C_ID'] || !$this->arResult['addition']['UF_PERIOD']) {
            return [];
        }
    
        $requestProducts = $this->getRequestProducts();
        
        $result = [];
        foreach ($requestProducts as $requestProduct) {
            $requestId = ($requestProduct['REQUEST_TYPE'] == $this->getManagerRequests()->getMainTypeId()) ? $requestProduct['REQUEST_1C_ID'] : $requestProduct['REQUEST_MAIN_REQUEST'];
            if (!isset($result[$requestId])) {
                $result[$requestId] = [
                    'REQUEST_ID'    => $requestId,
                    'REQUEST_MONTH' => $requestProduct['REQUEST_MONTH'],
                    'products'      => [],
                ];
            }

            $products = $result[$requestId]['products'];

            $available = $matched = 0;
            if ($products[$requestProduct['PRODUCT_1C_ID']]) {
                $matched = $products[$requestProduct['PRODUCT_1C_ID']]['matched'];
                $available = $products[$requestProduct['PRODUCT_1C_ID']]['available'];
            }

            $products[$requestProduct['PRODUCT_1C_ID']] = [
                'matched'   => $matched + $requestProduct['PRODUCT_VOLUME_MATCHED'],
                'available' => $available + $requestProduct['PRODUCT_VOLUME_MATCHED'],
            ];

            $result[$requestId]['products'] = $products;
        }
        
        return $result;
    }
    
    protected function getRequestProducts()
    {
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
            '=UF_STORE'                    => $this->arResult['addition']['UF_SHIPPING_STORE'],
            '=UF_REQUEST_REF.UF_MONTH'     => $this->arResult['addition']['UF_PERIOD']->format("01.m.Y"),
            '=UF_REQUEST_REF.UF_IS_ACTIVE' => true,
            '=UF_NONPALLET'                => 0,
        ];
        
        $requestProducts = $this->getManagerRequestProducts()->find([
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
        
        return $requestProducts;
    }
    
    protected function getStore(): array
    {
        if (empty($this->arResult['addition']['UF_SHIPPING_STORE'])) return [];
        return $this->getManagerStore()->getRowByCodeAsku($this->arResult['addition']['UF_SHIPPING_STORE'], ['TITLE', 'UF_*']);
    }

    protected function handleAjax()
    {
        $action = $this->request->getPost('action');

        if (!$this->accessCheck($action)) {
            echo json_encode(['errorMessage' => $this->errorCollection->getErrorByCode('CRITICAL_ERROR')->getMessage()]);
            return;
        }

        $this->arResult['requestsData'] = $this->getRequestListWithMatchedVolumeProducts();
        $this->arResult['request'] = $this->getRequest();

        if (!$this->arResult['request']) {
            echo json_encode(['data' => [], 'errorMessage' => 'Ошибка: нет оформленных заявок в выбранном периоде']);
            return;
        }

        $this->arResult['storeProducts'] = $this->arResult['request']['products'];
        $this->updateStoreProduct();
        if (!$this->arResult['storeProducts']) {
            echo json_encode([
                'data' => [],
                'errorMessage' => 'Ошибка: отсутствуют товары, соответствующие указанному складу',
            ]);
            return;
        }

        if ($action === 'cancel') {
            if ($this->cancelAddition()) {
                echo json_encode(['status' => true]);
            } else {
                echo json_encode(['errorMessage' => 'Ошибка: Вы не можете отменить данное дополнение']);
            }

            return;
        }

        if ($action === 'getProducts') {
            $this->arResult['crossingAdditions'] = $this->getCrossingAdditions();
            
            $this->updateProductsCountAvailable();

            $currentStoreTypeId = $this->arResult['store']['UF_TYPE'];
            if ($currentStoreTypeId == $this->getManagerStore()->getTypeIdPacking()) {
                $this->arResult['storeType'] = 'packing';
            } elseif ($currentStoreTypeId == $this->getManagerStore()->getTypeIdPouring()) {
                $this->arResult['storeType'] = 'pouring';
            }

            $this->arResult['products'] = $this->getProducts(array_keys($this->arResult['storeProducts']));
            $this->arResult['priceTypes'] = $this->getPriceTypes();
            $this->arResult['brands'] = $this->loadBrands();

            $this->includeComponentTemplate('ajax_products');

            return;
        }

        if ($action === 'save') {
            $this->arResult['crossingAdditions'] = $this->crossingAdditions = $this->getCrossingAdditions(true);
            $this->ajaxSave();
            return;
        }
    }

    /**
     * В редких случаях может сложиться, что товар в дополнении есть, а в заявке - нет
     */
    protected function updateStoreProduct()
    {
        if (empty($this->arResult['addition']['UF_1C_ID'])) {
            return;
        }

        foreach ($this->arResult['additionProducts'] as $additionProduct) {
            if (!isset($this->arResult['storeProducts'][$additionProduct['ID']])) {
                $this->arResult['storeProducts'][$additionProduct['ID']]['available'] = $additionProduct['VOLUME'];
                $this->arResult['storeProducts'][$additionProduct['ID']]['matched'] = 0;
            }
        }
    }

    protected function ajaxSave()
    {
        $this->arResult['products'] = $this->request->getPost('product');
        $exceededAllowedSizeProducts = $this->checkExceededAllowedSize();
        $this->reduceCrossingAdditionsByCurrentProducts();

        $conflictData = $this->compareTemporaryReservesAndOrders();
        if (!empty($conflictData)) {
            echo json_encode([
                'conflict'  => true,
                'products'  => $conflictData['products'],
                'errorType' => $conflictData['errorType'],
            ]);
            return;
        }

        if (!$this->request->getPost('createNewRequest') && $exceededAllowedSizeProducts) {
            echo json_encode([
                'canCreate' => $this->getManagerPeriodWorkApplication()->isMainRequestCreateAvailable(
                    $this->arResult['addition']['UF_PERIOD'] ?: new Date(date("Y.m.01"), "Y.m.d")
                ),
                'exceededLimit' => true,
                'products'      => $exceededAllowedSizeProducts,
            ]);
            return;
        }

        if ($this->request->getPost('createNewRequest') && $exceededAllowedSizeProducts) {
            try {
                $requestId = $this->createRequest($exceededAllowedSizeProducts);
            } catch (\Throwable $exception) {
                $this->addLog([
                    'message'   => "Ошибка при создании Заявки",
                    'exception' => $exception->getMessage()]);
                echo json_encode(['error' => "Ошибка при создании Заявки"]);
                return;
            }

            $this->getManagerRequests()->launchBackgroundProcess($requestId, "createByAdditionEdit");
        }

        try {
            $newAdditionId = $this->saveAddition();

            try {
                $this->launchBackgroundProcess($newAdditionId);
            } catch (SystemException $exception) {
                $this->addLog([
                    'message'           => "Ошибка отправки письма.",
                    'exception'         => $exception->getMessage(),
                    'newAdditionId'     => $newAdditionId,
                    'crossingAdditions' => $this->arResult['crossingAdditions'],
                ]);
            }

            echo json_encode([
                'additionIds'    => $newAdditionId ? [$newAdditionId] : [],
                'crossAdditions' => array_keys($this->arResult['crossingAdditions']),
            ]);
        } catch (ArgumentException $exception) {
            if ($exception->getParameter() == 'empty') {
                echo json_encode(['isEmpty'  => true]);
            } else {
                $this->addLog([
                    'message'   => "Ошибка при сохранении",
                    'exception' => $exception->getMessage(),
                ]);
                echo json_encode(['error' => 'Ошибка при сохранении']);
            }
        } catch (SystemException $exception) {
            $this->addLog([
                'message'   => "Ошибка при сохранении",
                'exception' => $exception->getMessage(),
            ]);
            echo json_encode(['error' => 'Ошибка при сохранении']);
        }
    }

    private function saveAddition()
    {
        $newAdditionId = null;
        $changedAdditions = [$this->arResult['addition']['ID'] => $this->arResult['addition']['ID']];
        $cancelAddition = (bool)$this->request->getPost('cancelAddition');

        $this->startTransaction();
        try {

            $separationDataAdditions = $this->separationDataAdditions();
            $this->checkAdditionOnChangeAdvert($separationDataAdditions);

            // Проверим на то, что в дополнении остались товары после распределения
            $additionProductsId = array_column($this->arResult['crossingAdditions'][$this->arResult['addition']['ID']]['products'], 'id', 'id');

            if (
                empty($separationDataAdditions['insert']) 
                && (empty($additionProductsId) || empty(array_diff_key($additionProductsId, $separationDataAdditions['delete']))) 
            ) {
                if (!$cancelAddition) {
                    throw new ArgumentException('', 'empty');
                } else {
                    $this->cancelAddition(false);
                }
            }

            // Создаём новое дополнение и закидываем в него товары
            if (!empty($separationDataAdditions['new'])) {
                $newAdditionId = $this->createAddition($separationDataAdditions['new']);
            }

            if (!empty($separationDataAdditions['delete'])) {
                $this->deleteProductsAddition($separationDataAdditions['delete']);
            }
            
            if (!empty($separationDataAdditions['insert'])) {
                $this->createProductsAddition($separationDataAdditions['insert']);
                $this->arResult['addition']['IS_CHANGES'] = true;
            }

            if (!empty($separationDataAdditions['newInsert'])) {
                foreach ($separationDataAdditions['newInsert'] as $additionId => $products) {
                    if (!empty($products)) {
                        $this->createProductsAddition($products, $additionId);
                        $changedAdditions[$additionId] = $additionId;
                    }
                }
            }

            if (!empty($this->arResult['crossingAdditions'])) {
                $changedAdditions = array_merge($this->updateAdditions(), $changedAdditions);
            }
            
            if ($this->arResult['addition']['IS_CHANGES']) {
                $this->updateAdditionParams([$this->arResult['addition']['ID']]);
            }

            foreach ($this->arResult['crossingAdditions'] as $additionId => $data) {
                if (!in_array($additionId, $changedAdditions)) {
                    unset($this->arResult['crossingAdditions'][$additionId]);
                }
            }

            $this->commitTransaction();
        } catch (ArgumentException $exception) {
            $this->rollbackTransaction();
            throw new ArgumentException($exception->getMessage(), $exception->getParameter());
        } catch (\Throwable $exception) {
            echo $exception->getMessage();
            $this->rollbackTransaction();
            throw new SystemException($exception->getMessage());
        }

        return $newAdditionId;
    }
    
    /**
     * Проверим товары дополнения, если у всех поставили флаг 50/50,
     * то поменяем у дополнения флаг 50/50
     */
    protected function checkAdditionOnChangeAdvert(&$separationDataAdditions)
    {
        $products = $this->request->getPost('product');
        if (empty($products)) {
            return;
        }
        
        // Проверим на то, что у всех товаров поставили флаг 50/50 или сняли
        $notChangeAllAdvert = in_array($this->arResult['addition']['UF_ADVERT_50_50'], array_column($products, 'fiftyFyfty'));

        if ($notChangeAllAdvert) {
            return;
        }

        $this->arResult['addition']['UF_ADVERT_50_50'] = (int)!($this->arResult['addition']['UF_ADVERT_50_50'] == 1);
        $this->arResult['addition']['IS_CHANGES'] = true;

        $this->getManagerAdditionals()->multiUpdate(
            ['=ID' => $this->arResult['addition']['ID']],
            [
                'UF_ADVERT_50_50' => $this->arResult['addition']['UF_ADVERT_50_50'],
                'UF_ADVERT_50_50_VAL' => $this->arResult['addition']['UF_ADVERT_50_50'] == 1 ? $this->getManagerAdditionals()::ADVERT_50_50 : 0,
            ]
        );
            
        foreach ($separationDataAdditions['new'] as $product) {
            unset($separationDataAdditions['delete'][$product['id']]);
            $this->arResult['crossingAdditions'][$this->arResult['addition']['ID']]['products'][$product['id']] = $product;
        }
            
        foreach ($separationDataAdditions['newInsert'] as $additionId => $addition) {
            foreach ($addition as $product) {
                if ($separationDataAdditions['delete'][$product['id']]) {
                    unset($separationDataAdditions['delete'][$product['id']], $separationDataAdditions['newInsert'][$additionId][$product['id']]);
                    $this->arResult['crossingAdditions'][$this->arResult['addition']['ID']]['products'][$product['id']] = $product;
                }
            }
        }
        
        $this->arResult['crossingAdditions'][$this->arResult['addition']['ID']]['info'] = [
            'ID'           => $this->arResult['addition']['ID'],
            '1C_ID'        => $this->arResult['addition']['UF_1C_ID'],
            'NUMBER'       => $this->arResult['addition']['UF_ADDITION_NUMBER'],
            'ADVERT_50_50' => $this->arResult['addition']['UF_ADVERT_50_50'],
        ];
        
        $separationDataAdditions['new'] = [];
    }

    /**
     * Поиск подходящего дополнения если флаг 50на50 не совпадает
     * @param $product
     * @return bool|int|string (bool или id дополнения в который нужно добавить товар)
     */
    protected function changeFiftyFindAddition($product)
    {
        foreach ($this->arResult['crossingAdditions'] as $additionId => &$data) {
            if ($data['info']['ADVERT_50_50'] != $product['fiftyFyfty']) {
                continue;
            }

            if (isset($data['products'][$product['id']])) {
                if ($data['products'][$product['id']]['priceAgreement'] != $product['priceAgreementId']
                    || $data['products'][$product['id']]['specialPrice'] != $product['specialPrice']) {
                    continue;
                }
                $data['products'][$product['id']]['volume'] += $product['volume'];
                $data['products'][$product['id']]['priceId'] = $product['priceId'];
                return true;
            }
        }
        unset($data);

        foreach ($this->arResult['crossingAdditions'] as $additionId => $data) {
            if ($data['info']['ADVERT_50_50'] != $product['fiftyFyfty']) {
                continue;
            }

            if (isset($data['products'][$product['id']])) {
                continue;
            }

            $product['priceAgreement'] = $product['priceAgreementId'];
            $product['priceType'] = $product['priceTypeId'];

            return $additionId;
        }

        return false;
    }

    protected function separationDataAdditions()
    {
        $result = ['insert' => [], 'delete' => [], 'new' => [], 'newInsert' => []];

        $products = $this->request->getPost('product');
        $notChangeAllAdvert = !$products || in_array($this->arResult['addition']['UF_ADVERT_50_50'], array_column($products, 'fiftyFyfty'));

        foreach ($this->arResult['products'] as $product) {
            // Если флаг 50на50 не совпадает ищем подходящее дополнение, если его нет добавляем в массив для создания нового
            if ($product['fiftyFyfty'] != $this->arResult['addition']['UF_ADVERT_50_50'] && $notChangeAllAdvert) {
                $isset = $this->changeFiftyFindAddition($product);

                if ($isset) {
                    $result['delete'][] = $product['id'];
                    if ($isset !== true) {
                        $result['newInsert'][$isset][$product['id']] = $product;
                    }
                }
                
                if (!$isset) {
                    $result['new'][$product['id']] = [
                        'id'               => $product['id'],
                        'volume'           => $product['volume'],
                        'priceAgreement'   => $product['priceAgreementId'],
                        'priceAgreementId' => $product['priceAgreementId'],
                        'priceType'        => $product['priceTypeId'],
                        'priceTypeId'      => $product['priceTypeId'],
                        'specialPrice'     => $product['specialPrice'],
                        'priceId'          => $product['priceId'],
                        'measure'          => $product['measure'],
                        'fiftyFyfty'       => $product['fiftyFyfty'],
                    ];

                    if (isset($this->arResult['additionProducts'][$product['id']])) {
                        unset($this->arResult['additionProducts'][$product['id']]);
                    }
                }
            } elseif (!empty($this->arResult['additionProducts'][$product['id']])) {
                unset($this->arResult['additionProducts'][$product['id']]);

                $this->arResult['crossingAdditions'][$this->arResult['addition']['ID']]['info'] = [
                    'ID'           => $this->arResult['addition']['ID'],
                    '1C_ID'        => $this->arResult['addition']['UF_1C_ID'],
                    'NUMBER'       => $this->arResult['addition']['UF_ADDITION_NUMBER'],
                    'ADVERT_50_50' => $this->arResult['addition']['UF_ADVERT_50_50'],
                ];
                
                $this->arResult['crossingAdditions'][$this->arResult['addition']['ID']]['products'][$product['id']] = [
                    'id'             => $product['id'],
                    'volume'         => $product['volume'],
                    'priceAgreement' => $product['priceAgreementId'],
                    'priceType'      => $product['priceTypeId'],
                    'specialPrice'   => $product['specialPrice'],
                    'priceId'        => $product['priceId'],
                ];
            } else {
                $result['insert'][$product['id']] = $product;
                $this->editedProductIds[] = $product['id'];
            }
        }
        

        $result['delete'] = array_merge(
            $result['delete'],
            array_keys($this->arResult['additionProducts']),
            array_keys($result['new'])
        );
        
        if (!empty($this->editedProductIds)) {
            $this->editedProductIds = array_merge($this->editedProductIds, $result['delete']);
        } else {
            $this->editedProductIds = $result['delete'];
        }

        $result['delete'] = array_combine($result['delete'], $result['delete']);
        
        $this->arResult['crossingAdditions'][$this->arResult['addition']['ID']]['info']['ID'] = $this->arResult['addition']['ID'];
        if (!isset($this->arResult['crossingAdditions'][$this->arResult['addition']['ID']]['products'])) {
            $this->arResult['crossingAdditions'][$this->arResult['addition']['ID']]['products'] = [];
        }

        return $result;
    }

    /**
     * Создание нового дополнения
     * @param $products
     * @return int
     * @throws SystemException
     */
    public function createAddition($products): int
    {
        $fiftyFyfty = current($products)['fiftyFyfty'];

        $additionId = $this->getManagerAdditionals()->addItem([
            'UF_CONTRACT' =>  $this->arResult['addition']['UF_CONTRACT'],
            'UF_PERIOD' =>  $this->arResult['addition']['UF_PERIOD'],
            'UF_STATUS' =>  $this->getManagerAdditionals()->getStatusOnAgreedId(),
            'UF_SHIPPING_STORE' =>  $this->arResult['addition']['UF_SHIPPING_STORE'],
            'UF_TRANSPORTATION_TY' =>  $this->arResult['addition']['UF_TRANSPORTATION_TY'],
            'UF_DELIVERY_TYPE' =>  $this->arResult['addition']['UF_DELIVERY_TYPE'],
            'UF_SHIPPING_POINT' =>  $this->arResult['addition']['UF_SHIPPING_POINT'],
            'UF_DELIVERY_POINT' =>  $this->arResult['addition']['UF_DELIVERY_POINT'],
            'UF_DELIVERY_ADDRESS' =>  $this->arResult['addition']['UF_DELIVERY_ADDRESS'],
            'UF_RECEIVER' =>  $this->arResult['addition']['UF_RECEIVER'],
            'UF_TRANSPORT_TYPE' =>  $this->arResult['addition']['UF_TRANSPORT_TYPE'],
            'UF_DELIVERY_IN_PRICE' =>  $this->arResult['addition']['UF_DELIVERY_IN_PRICE'],
            'UF_SHIPPING_AGENT' =>  $this->arResult['addition']['UF_SHIPPING_AGENT'],
            'UF_PASSING_OF_PROP' =>  $this->arResult['addition']['UF_PASSING_OF_PROP'],
            'UF_DATE' =>  new Date(),
            'UF_USER' =>  $this->getManagerUser()->getCurrentUserId(),
            'UF_ADVERT_50_50' =>  current($products)['fiftyFyfty'],
            'UF_ADVERT_50_50_VAL' =>  $fiftyFyfty == 1 ? $this->getManagerAdditionals()::ADVERT_50_50 : 0,
            'UF_ADVERT_50_50_COST' =>  $this->arResult['addition']['UF_ADVERT_50_50_COST'],
            'UF_ACTIVE' =>  $this->arResult['addition']['UF_ACTIVE'],
        ]);
        return $this->createProductsAddition($products, $additionId);
    }

    /**
     * Добавление новых продуктов в Дополнение
     * Обновление статуса Дополнения
     *
     * @param array $products
     * @param int|null $additionId
     * @return int
     * @throws SystemException
     */
    protected function createProductsAddition($products, $additionId = null)
    {
        $mngAdditionals = $this->getManagerAdditionals();
        $mngAdditionalProducts = $this->getManagerAdditionalProducts();

        if ($additionId) {
            $addition = $mngAdditionals->findById($additionId);
            $advert =  $addition['UF_ADVERT_50_50'];
            $additionFields = [
                'UF_ADDITION'    => $addition['UF_1C_ID'],
                'UF_ADDITION_ID' => $additionId,
            ];
        } else {
            $additionId =  $this->arResult['addition']['ID'];
            $advert =  $this->arResult['addition']['UF_ADVERT_50_50'];
            $additionFields = [
                'UF_ADDITION'    => $this->arResult['addition']['UF_1C_ID'],
                'UF_ADDITION_ID' => $this->arResult['addition']['ID'],
            ];
        }

        $newProducts = [];

        $productsPrice = $mngAdditionalProducts->getCalcPrices($products, $advert, true);

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

            $this->addChanges(Loc::getMessage('K_C_ADDITIONAL_EDIT_CLASS_PRODUCT_CREATED'), $additionFields, ['UF_PRODUCTS_CNT' => 1], $product['id']);
        }

        $mngAdditionalProducts->multiInsertChunk(array_keys($newProducts[0]), $newProducts);
        
        $additionSum = $this->getManagerAdditionalProducts()->getRow([
            'select' => [
                'UF_ADDITION',
                new ExpressionField('CNT', 'SUM(`UF_TAX_VALUE`)'),
                new ExpressionField('DISC', 'SUM(`UF_DISCOUNT_TOTAL`)'),
            ],
            'filter' => ['=UF_ADDITION' => $additionId],
        ]);

        $mngAdditionals->multiUpdate(
            ['=ID' => $additionId],
            ['UF_STATUS' => $mngAdditionals->getStatusOnAgreedId(), 'UF_TAX_PRICE' => $additionSum['CNT'], 'UF_ADVERT_50_50_COST' => $additionSum['DISC']]
        );
        
        return $additionId;
    }

    protected function deleteProductsAddition($products)
    {
        $mngAdditionalProducts = $this->getManagerAdditionalProducts();
        $additionFields = [
            'UF_ADDITION'    => $this->arResult['addition']['UF_1C_ID'],
            'UF_ADDITION_ID' => $this->arResult['addition']['ID']
        ];
        
        $this->arResult['addition']['IS_CHANGES'] = true;

        foreach ($products as $product) {
            $this->addChanges(Loc::getMessage('K_C_ADDITIONAL_EDIT_CLASS_PRODUCT_DELETED'), $additionFields, ['UF_PRODUCTS_CNT' => 1], $product);
        }

        $mngAdditionalProducts->multiDeleteByFilter([
            '=UF_ADDITION' => $this->arResult['addition']['ID'],
            '=UF_PRODUCTS' => array_values($products),
        ]);
    }

    /**
     * Обновления дополнений
     *
     * @return array
     * @throws SystemException
     */
    protected function updateAdditions()
    {
        $mngAdditionalProducts = $this->getManagerAdditionalProducts();
        $isPacking = $this->arResult['store']['UF_TYPE'] == $this->getManagerStore()->getTypeIdPacking();
        $changedAdditions = [];

        foreach ($this->arResult['crossingAdditions'] as $additionId => $crossingAddition) {
            $productsPrice = $mngAdditionalProducts->getCalcPrices(
                $crossingAddition['products'],
                $crossingAddition['info']['ADVERT_50_50'] ?? $this->arResult['addition']['UF_ADVERT_50_50'],
                $isPacking
            );
            $code =  $this->crossingAdditions[$additionId]['info']['NONPALLET'] ? "UT" : ($isPacking ? "PL" : "TN");

            foreach ($crossingAddition['products'] as $productId => $product) {
                $prices = $productsPrice[$productId] ?? [];

                if (empty($prices) || !$prices['UF_PRICE']) {
                    continue;
                }

                $productOrigin = [
                    'UF_PRODUCTS_CNT' => $this->crossingAdditions[$additionId]['products'][$productId]['volume_old'],
                    'UF_PRICE_AGREEMENT' => $this->crossingAdditions[$additionId]['products'][$productId]['priceAgreement'],
                    'PRICE_AGREEMENT_NAME' => $this->crossingAdditions[$additionId]['products'][$productId]['priceAgreementName'],
                    'UF_PRICE_TYPE' => $this->crossingAdditions[$additionId]['products'][$productId]['priceType'],
                    'PRICE_TYPE_NAME' => $this->crossingAdditions[$additionId]['products'][$productId]['priceTypeName'],
                    'UF_SPECIAL_PRICE' => $this->crossingAdditions[$additionId]['products'][$productId]['specialPrice'],
                    'UF_CHANGED' => $this->crossingAdditions[$additionId]['products'][$productId]['changed'],
                    'UF_PRODUCTS' => $productId
                ];
                
                $productNew = [
                    'UF_PRODUCTS_CNT' => $product['volume'],
                    'UF_PRICE_AGREEMENT' => $product['priceAgreement'],
                    'PRICE_AGREEMENT_NAME' => $this->getManagerPriceAgreement()->getBy1cId($product['priceAgreement'], ['UF_NAME'])['UF_NAME'] ?? '',
                    'UF_PRICE_TYPE' => $product['priceType'],
                    'PRICE_TYPE_NAME' => $this->getManagerPriceType()->findBy1cId($product['priceType'])['UF_NAME'] ?? '',
                    'UF_SPECIAL_PRICE' => $product['specialPrice'],
                ];

                $changes = $this->getManagerAdditionHistory()->isDiffProduct($productNew, $productOrigin, $code);

                if (!empty($changes['changed'])) {
                    $additionFields = [
                        'UF_ADDITION' => $this->crossingAdditions[$additionId]['info']['1C_ID'],
                        'UF_ADDITION_ID' => $this->crossingAdditions[$additionId]['info']['ID']
                    ];
                    $this->addChanges($changes['text'], $additionFields, $changes['fields'], $changes['product1cId']);
                }
                
                $newProduct = array_merge([
                    'UF_PRODUCTS_CNT'       => $product['volume'],
                    'UF_SPECIAL_PRICE'      => $product['specialPrice'],
                    'UF_PRICE_AGREEMENT'    => $product['priceAgreement'],
                    'UF_PRICE_TYPE'         => $product['priceType'],
                    'UF_CHANGED'            => $product['changed'] || $productOrigin['UF_CHANGED'] || !empty($changes['changed']),
                ], $prices);

                $mngAdditionalProducts->multiUpdate([
                    '=UF_ADDITION' => $additionId,
                    '=UF_PRODUCTS' => $productId,
                ], $newProduct);

                if (!empty($changes['changed'])) {
                    $this->editedProductIds[] = $product['id'];
                    $changedAdditions[$additionId] = $additionId;
                }
            }
        }

        if (!empty($changedAdditions)) {
            $this->updateAdditionParams($changedAdditions);
        }

        $this->getManagerAdditionHistory()->saveChangesBatch($this->changes);

        return $changedAdditions;
    }
    
    protected function updateAdditionParams(array $additionsId) {
        $additionsSum = $this->getManagerAdditionalProducts()->find([
            'select' => [
                'UF_ADDITION',
                new ExpressionField('CNT', 'SUM(`UF_TAX_VALUE`)'),
                new ExpressionField('DISC', 'SUM(`UF_DISCOUNT_TOTAL`)'),
            ],
            'filter' => ['=UF_ADDITION' => $additionsId],
        ]);

        foreach ($additionsSum as $additionSum) {
            $data = [
                'UF_STATUS' => $this->getManagerAdditionals()->getStatusOnAgreedId(),
                'UF_TAX_PRICE' => $additionSum['CNT'],
                'UF_ADVERT_50_50_COST' => $additionSum['DISC'],
            ];

            if ($this->changes) {
                $data['UF_CHANGED'] = true;
            }

            $this->getManagerAdditionals()->multiUpdate(['=ID' => $additionSum['UF_ADDITION']], $data);
        }
    }

    /**
     * Заполняет массив изменений
     * @param mixed $text
     * @param array $additionFields
     * @param array $fields
     * @param mixed $product1cId
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

    /**
     * Создает новую заявку с товарами
     * 
     * @param array $exceededAllowedSizeProducts
     * @return int ID заявки
     * @throws SystemException
     */
    protected function createRequest(array $exceededAllowedSizeProducts) : int
    {
        $managerRequests = $this->getManagerRequests();
        $contract1cId = $this->arResult['contract']['UF_1C_ID'];
        $period = $this->arResult['addition']['UF_PERIOD'] ?: new Date(date("Y.m.01"), "Y.m.d");
        $mainRequestData = $managerRequests->getMainRequestByContract($contract1cId, $period);

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
                    'UF_STORE'   => $this->arResult['addition']['UF_SHIPPING_STORE'],
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
            $additionProductVolume = 0;
            if (!empty($this->arResult['additionProducts'][$productId])) {
                $additionProductVolume = $this->arResult['additionProducts'][$productId]['VOLUME'];
            }
            if (isset($product['limit']) && ($product['volume'] - $additionProductVolume) > $product['limit']) {
                $exceededAllowedSize[$productId] = [
                    'volume'  => $product['volume'],
                    'limit'   => ($product['limit'] + $additionProductVolume),
                    'measure' => $product['measure']
                ];
            }
        }

        return $exceededAllowedSize;
    }
    
    protected function getRequest()
    {
        $request = null;
        foreach ($this->arResult['requestsData'] as $requestData) {
            if ($requestData['REQUEST_MONTH'] == $this->arResult['addition']['UF_PERIOD']) {
                $request = $requestData;
                break;
            }
        }

        return $request;
    }
    
    /**
     * Формирование данных по пересекающимся товарам дополнений с группировкой по дополнениям
     *
     * @return array
     * @throws SystemException
     */
    private function getCrossingAdditions(bool $filterByAddition = false)
    {
        $additions = [];
        $filter = [
            '=UF_PRODUCTS'                          => array_keys($this->arResult['storeProducts']),
            '=UF_ADDITION_REF.UF_NONPALLET'         => 0,
            '=UF_ADDITION_REF.UF_CONTRACT'          => $this->arResult['addition']['UF_CONTRACT'],
            '=UF_ADDITION_REF.UF_PERIOD'            => $this->arResult['addition']['UF_PERIOD'],
            '=UF_ADDITION_REF.UF_SHIPPING_STORE'    => $this->arResult['addition']['UF_SHIPPING_STORE'],
            '=UF_ADDITION_REF.UF_ACTIVE'            => true,
            '=UF_ADDITION_REF.UF_STATUS'            => [
                $this->getManagerAdditionals()->getStatusAgreedId(),
                $this->getManagerAdditionals()->getStatusOnAgreedId()
            ],
        ];
        
        if ($filterByAddition) {
            $filter['=UF_ADDITION_REF.UF_TRANSPORTATION_TY'] = $this->arResult['addition']['UF_TRANSPORTATION_TY'];
            $filter['=UF_ADDITION_REF.UF_DELIVERY_TYPE'] = $this->arResult['addition']['UF_DELIVERY_TYPE'];
            $filter['=UF_ADDITION_REF.UF_DELIVERY_ADDRESS'] = $this->arResult['addition']['UF_DELIVERY_ADDRESS'];
            $filter['=UF_ADDITION_REF.UF_RECEIVER'] = $this->arResult['addition']['UF_RECEIVER'];
            $filter['=UF_ADDITION_REF.UF_DELIVERY_POINT'] = $this->arResult['addition']['UF_DELIVERY_POINT'];
        }
        
        $additionalProducts = $this->getManagerAdditionalProducts()->find([
            'select' => [
                'ID', 'UF_ADDITION', 'UF_PRODUCTS', 'UF_PRODUCTS_CNT', 'UF_PRICE_AGREEMENT', 'UF_PRICE_TYPE', 'UF_SPECIAL_PRICE', 'UF_CHANGED',
                'PRICE_TYPE_NAME'            => 'UF_PRICE_TYPE_REF.UF_NAME',
                'AGREEMENT_TYPE'             => 'UF_PRICE_AGREEMENT_REF.UF_TYPE',
                'AGREEMENT_NAME'             => 'UF_PRICE_AGREEMENT_REF.UF_NAME',
                'ADDITION_ID'                => 'UF_ADDITION_REF.ID',
                'ADDITION_1C_ID'             => 'UF_ADDITION_REF.UF_1C_ID',
                'ADDITION_NUMBER'            => 'UF_ADDITION_REF.UF_ADDITION_NUMBER',
                'ADDITION_TRANSPORTATION_TY' => 'UF_ADDITION_REF.UF_TRANSPORTATION_TY',
                'ADDITION_DELIVERY_TYPE'     => 'UF_ADDITION_REF.UF_DELIVERY_TYPE',
                'ADDITION_RECEIVER'          => 'UF_ADDITION_REF.UF_RECEIVER',
                'ADDITION_DELIVERY_ADDRESS'  => 'UF_ADDITION_REF.UF_DELIVERY_ADDRESS',
                'ADDITION_ADVERT_50_50'      => 'UF_ADDITION_REF.UF_ADVERT_50_50',
                'ADDITION_NONPALLET'         => 'UF_ADDITION_REF.UF_NONPALLET',
                'STORE'                      => 'UF_ADDITION_REF.UF_SHIPPING_STORE',
            ],
            'filter' => $filter,
            'order' => ['UF_PRODUCTS' => 'ASC']
        ]);

        foreach ($additionalProducts as $additionalProduct) {
            $additions[$additionalProduct['ADDITION_ID']]['info'] = [
                'ID'                => $additionalProduct['ADDITION_ID'],
                '1C_ID'             => $additionalProduct['ADDITION_1C_ID'],
                'NUMBER'            => $additionalProduct['ADDITION_NUMBER'],
                'ADVERT_50_50'      => $additionalProduct['ADDITION_ADVERT_50_50'],
                'NONPALLET'         => $additionalProduct['ADDITION_NONPALLET'],
                'STORE'             => $additionalProduct['STORE'],
                'TRANSPORTATION_TY' => $additionalProduct['ADDITION_TRANSPORTATION_TY'],
                'DELIVERY_TYPE'     => $additionalProduct['ADDITION_DELIVERY_TYPE'],
                'RECEIVER'          => $additionalProduct['ADDITION_RECEIVER'],
                'DELIVERY_ADDRESS'  => $additionalProduct['ADDITION_DELIVERY_ADDRESS'],
            ];

            $additions[$additionalProduct['ADDITION_ID']]['products'][$additionalProduct['UF_PRODUCTS']] = [
                'id'                 => $additionalProduct['UF_PRODUCTS'],
                'volume'             => $additionalProduct['UF_PRODUCTS_CNT'],
                'volume_old'         => $additionalProduct['UF_PRODUCTS_CNT'],
                'priceAgreement'     => $additionalProduct['UF_PRICE_AGREEMENT'],
                'priceAgreementType' => $additionalProduct['AGREEMENT_TYPE'],
                'priceAgreementName' => $additionalProduct['AGREEMENT_NAME'],
                'priceType'          => $additionalProduct['UF_PRICE_TYPE'],
                'priceTypeName'      => $additionalProduct['PRICE_TYPE_NAME'],
                'specialPrice'       => $additionalProduct['UF_SPECIAL_PRICE'],
                'changed'            => $additionalProduct['UF_CHANGED'],
            ];
        }

        return $additions;
    }

    /**
     * Сокращение списка пересекающихся товаров с учетом переданных данных по товарам
     */
    private function reduceCrossingAdditionsByCurrentProducts()
    {
        $productsPost = $this->request->getPost('product');

        foreach ($this->arResult['crossingAdditions'] as $addition1cId => $additionData) {
            if ((int)$additionData['info']['ADVERT_50_50'] != (int)$this->arResult['addition']['UF_ADVERT_50_50']) {
                continue;
            }
            
            foreach ($additionData['products'] as $crossProduct1cId => $crossProduct) {
                $product = $this->arResult['products'][$crossProduct1cId];

                if (!isset($product)) {
                    $product = $productsPost[$crossProduct1cId];
                    $this->arResult['crossingAdditions'][$addition1cId]['products'][$crossProduct1cId]['volume'] = $product['volume'];
                    continue;
                }

                $productNotFound = true;
                if ($product['priceAgreementId'] == $crossProduct['priceAgreement']
                    && $product['priceTypeId'] == $crossProduct['priceType']
                    && $product['specialPrice'] == $crossProduct['specialPrice']
                    && $product['fiftyFyfty'] == $additionData['info']['ADVERT_50_50']
                    ) {
                    if ($additionData['info']['ID'] == $this->arResult['addition']['ID']) {
                        $this->arResult['crossingAdditions'][$addition1cId]['products'][$crossProduct1cId]['volume'] = $product['volume'];
                        $this->arResult['crossingAdditions'][$addition1cId]['products'][$crossProduct1cId]['priceId'] = $product['priceId'];
                        unset($this->arResult['additionProducts'][$crossProduct1cId]);
                    } else {
                        $this->arResult['crossingAdditions'][$addition1cId]['products'][$crossProduct1cId]['volume'] += $product['volume'];
                        $this->arResult['crossingAdditions'][$addition1cId]['products'][$crossProduct1cId]['priceId'] = $product['priceId'];
                    }

                    unset($this->arResult['products'][$crossProduct1cId]);

                    $productNotFound = false;
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

    private function getProducts($productsId)
    {
        $productsCatalogInfo = $this->getManagerCatalog()->managerElements()->getCatalogProductsList(
            ['PROPERTIES_VALUES' => ['=IDENTIFIER_1C' => $productsId]],
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
                'MEASUREMENT' => $this->arResult['measures'][$product['MEASURE']]['SYMBOL'],
            ];
        }

        return $products;
    }
    
    private function getPriceTypes()
    {
        $period = $this->arResult['addition']['UF_PERIOD'];
        $now = new Date(date("Y.m.d"), "Y.m.d");
        if ($period->getTimestamp() <= $now->getTimestamp()) {
            $period = $now;
        }

        $priceComponent = new Price(
            $this->arResult['user']['UF_CONTRACTOR'],
            $this->arResult['addition']['UF_PERIOD'],
            [$this->arResult['addition']['UF_SHIPPING_STORE']],
            $this->arResult['addition']['UF_ADVERT_50_50'] == 1
        );

        $agreements = $priceComponent->getAgreements();
        $pricesNomenclature = $priceComponent->getNomenclature(array_keys($this->arResult['products']), false);
        $priceTypes = $priceTypeFilter = $priceAgreementsFilter = [];

        $now = new \Bitrix\Main\Type\Date();
        $agreementIndividualId = $this->getManagerPriceAgreement()->getIndividualAgreementId();

        foreach ($agreements as $agreement) {
            foreach ($pricesNomenclature as $priceNomenclature) {
                if ($agreement['PRICE_TYPE_1C_ID'] != $priceNomenclature['UF_PRICE_TYPE']
                    || !$this->checkAndCorrectivePriceNomenclature(
                        $priceNomenclature, $agreement['UF_TYPE'] != $agreementIndividualId, $period)
                    || !empty($priceTypes[$priceNomenclature['UF_PRODUCT']][$priceNomenclature['UF_PRICE_TYPE']])
                    || (empty($priceNomenclature['UF_PRICE_TYPE']) || $priceNomenclature['UF_PRICE_TYPE'] != $agreement['PRICE_TYPE_1C_ID'])
                ) {
                    continue;
                }

                $record = $priceComponent->buildAgreementRecord($agreement, $priceNomenclature);
                $record = array_merge($record, [
                    'READONLY' => $priceNomenclature['UF_END_DATE'] && $priceNomenclature['UF_END_DATE'] < $now,
                ]);

                $priceTypes[$priceNomenclature['UF_PRODUCT']][$priceNomenclature['UF_PRICE_TYPE']] = $record;
                $priceAgreementsFilter[$agreement['UF_1C_ID']] = $agreement;
                $priceTypeFilter[$agreement['PRICE_TYPE_1C_ID']] = $agreement;
            }
        }

        $this->arResult['priceAgreementsFilter'] = $priceAgreementsFilter;
        $this->arResult['priceTypeFilter'] = $priceTypeFilter;

        return $priceTypes;
    }

    /**
     * Проверка и корректировка объемов по ценам номенклатуры
     *
     * @param array $priceNomenclature
     * @param bool  $isTypical
     * @return boolean
     * @throws \Bitrix\Main\ObjectException
     */
    protected function checkAndCorrectivePriceNomenclature(&$priceNomenclature, $isTypical, Date $period)
    {
        $productEdit = $this->arResult['additionProducts'][$priceNomenclature['UF_PRODUCT']];

        if (empty($productEdit) && $priceNomenclature['UF_ACTIVE'] == 0) {
            return false;
        }

        if ($priceNomenclature['UF_END_DATE'] && $priceNomenclature['UF_END_DATE'] < new Date()) {
            if (empty($productEdit['VOLUME'])
                || $productEdit['PRICE_AGREEMENT'] != $priceNomenclature['UF_PRICE_AGREEMENT']
                || $productEdit['PRICE_TYPE'] != $priceNomenclature['UF_PRICE_TYPE']
            ) {
                return false;
            } else {
                $priceNomenclature['UF_AVAILABLE_VOLUME'] = $productEdit['VOLUME']
                    * $this->arResult['products'][$priceNomenclature['UF_PRODUCT']]['PALLET_RATE'];
                return true;
            }
        }

        if ($priceNomenclature['UF_END_DATE'] && $priceNomenclature['UF_END_DATE'] < $period) {
            return false;
        }

        if (!empty($productEdit)
            && !empty($priceNomenclature['UF_PRICE_TYPE'])
            && $productEdit['PRICE_TYPE'] == $priceNomenclature['UF_PRICE_TYPE']
        ) {
            $priceNomenclature['UF_AVAILABLE_VOLUME'] =
                ((double)$priceNomenclature['UF_AVAILABLE_VOLUME'] > 0 || $priceNomenclature['UF_ACTIVE'] == 0)
                    ? (double)$priceNomenclature['UF_AVAILABLE_VOLUME'] + $productEdit['VOLUME_PIECE']
                    : (double)$priceNomenclature['UF_AVAILABLE_VOLUME'];
        } elseif ($priceNomenclature['UF_ACTIVE'] == 0) {
            return false;
        } elseif (!empty($priceNomenclature['UF_PRICE_TYPE']) && $productEdit['PRICE_TYPE'] != $priceNomenclature['UF_PRICE_TYPE']) {
            return $isTypical || $priceNomenclature['UF_AVAILABLE_VOLUME'] > 0;
        }

        return true;
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
    
    protected function getOrderProducts()
    {
        $result = [];

        if (empty($this->arResult['addition']['UF_1C_ID'])) {
            return $result;
        }

        $products = $this->getManagerOrderAdditions()->find([
            'select' => [
                'UF_ADDITION', 'ID',
                'PRODUCT'           => 'orderProduct.UF_PRODUCTS',
                'VOLUME'            => 'orderProduct.UF_VOLUME',
                'PRICE_AGREEMENT'   => 'additionProduct.UF_PRICE_AGREEMENT',
                'PRICE_TYPE'        => 'additionProduct.UF_PRICE_TYPE',
                'SPECIAL_PRICE'     => 'additionProduct.UF_SPECIAL_PRICE',
            ],
            'filter' => [
                '=UF_ADDITION'             => $this->arResult['addition']['UF_1C_ID'],
                '!=UF_ORDER_REF.UF_STATUS' => $this->getManagerOrders()->getStatusCanceledId(),
            ],
            'runtime' => [
                'orderProduct' => new \Bitrix\Main\Entity\ReferenceField(
                    'orderProduct',
                    ManagerRegistry::getManagerHL('OrderProducts')->getDataClass(),
                    [
                        '=ref.UF_ORDER'  => 'this.ID',
                    ],
                    ['join_type' => 'LEFT']
                ),
                'additionProduct' => new \Bitrix\Main\Entity\ReferenceField(
                    'additionProduct',
                    ManagerRegistry::getManagerHL('AdditionalProducts')->getDataClass(),
                    [
                        '=ref.UF_ADDITION'  => 'this.UF_ADDITION_REF.ID',
                        '=ref.UF_PRODUCTS'  => 'this.orderProduct.UF_PRODUCTS',
                    ],
                    ['join_type' => 'LEFT']
                ),
            ]
        ]);

        foreach ($products as $product) {
            $product['ADVERT_50_50'] = $this->arResult['addition']['UF_ADVERT_50_50'];
            $result[$product['PRODUCT']][] = $product;
        }

        return $result;
    }
    
    protected function accessCheck(string $action = null)
    {
        if (empty($this->arResult['addition'])) {
            $this->addError(Loc::getMessage('K_C_ADDITIONAL_EDIT_CLASS_ADDITION_NOT_EXIST_ERROR'), 'CRITICAL_ERROR');
            return false;
        }
        
        if ($this->arResult['contract']['UF_1C_ID'] != $this->arResult['addition']['UF_CONTRACT']) {
            LocalRedirect("/contracts/view/");
        }
        
        $now = new Date(date("01.m.Y"), "d.m.Y");

        if ($action !== 'cancel' && !$this->getManagerAdditionals()->canEdit($this->arResult['addition'])) {
            $this->addError(Loc::getMessage('K_C_ADDITIONAL_EDIT_CLASS_ADDITION_NOT_EXIST_ERROR'), 'CRITICAL_ERROR');
            return false;
        }
        
        $user = $this->getManagerUser()->getCurrentUserData();
        $contractor = $this->getManagerContracts()->findBy1cId($this->arResult['addition']['UF_CONTRACT']);

        if ($contractor['UF_CONTRACTOR'] != $user['UF_CONTRACTOR']) {
            $this->addError(Loc::getMessage('K_C_ADDITIONAL_EDIT_CLASS_EDIT_UNAVAILABLE_ERROR'), 'CRITICAL_ERROR');

            return false;
        }

        return true;
    }

    /**
     * Запуск процесса генерации дополнений и отправки почты
     * @param int|null $newAdditionId
     */
    private function launchBackgroundProcess(?int $newAdditionId)
    {
        $currentAdditionId = $this->arResult['addition']['ID'];
        $crossIds = [];
        $deletedItems = [];
        foreach ($this->arResult['crossingAdditions'] as $additionId => $crossingAddition) {
            if ($additionId == $currentAdditionId) {
                if (!empty($this->editedProductIds)) {
                    $crossIds[$additionId] = $this->editedProductIds;
                }
            } else {
                $crossIds[$additionId] = array_keys($crossingAddition['products']);
            }
        }
        foreach (array_unique($this->editedProductIds) as $product1cId) {
            if (!empty($this->arResult['additionProducts'][$product1cId])) {
                $deletedItems[$currentAdditionId][$product1cId] = $this->arResult['crossingAdditions'][$currentAdditionId]['products'][$product1cId];
            }
        }

        if (empty($newAdditionId) && empty($crossIds)) {
            return;
        }

        $this->getManagerAdditionals()->background($newAdditionId, $crossIds, true, $deletedItems);
    }
    
    private function addLog($message = [])
    {
        (new Logging())->addLog(
            \CEventLog::SEVERITY_ERROR,
            'korus.component.agreements.edit',
            $message
        )->save();
    }
    
    protected function getReserves()
    {
        $result = [];

        if (empty($this->arResult['addition']['UF_1C_ID'])) {
            return $result;
        }

        $reserves = $this->getManagerTemporaryReserves()->find([
            'filter' => [
                'UF_ADDITION' => $this->arResult['addition']['UF_1C_ID']
            ],
            'select' => ['UF_PRODUCTS', 'UF_VOLUME']
        ]);

        if (!empty($reserves)) {
            foreach ($reserves as $product) {
                $result[$product['UF_PRODUCTS']] = $product['UF_VOLUME'];
            }
        }

        return $result;
    }

    protected function compareTemporaryReservesAndOrders()
    {
        $reserves = $this->getReserves();
        $orderProducts = $this->getOrderProducts();
        $currentAdditionId = $this->arResult['addition']['ID'];
        $products = [];
        $arOrderedVolumes = [];
        $result = [
            'errorType' => '',
            'products' => [],
        ];

        foreach ($orderProducts as $prodId => $orderProduct) {
            foreach ($orderProduct as $product) {
                $arOrderedVolumes[$prodId] += $product['VOLUME'];
            }
        }

        foreach ($this->arResult['crossingAdditions'] as $additionId => $addition) {
            foreach ($addition['products'] as $product) {
                if (!empty($reserves[$product['id']])
                    && (empty($result['errorType']) || $result['errorType'] === 'reserves')
                    && ($additionId == $currentAdditionId && $product['volume'] < $reserves[$product['id']])
                ) {
                    $products[$product['id']]['volume'] = $product['volume'];
                    $products[$product['id']]['volume_old'] = $product['volume_old'];
                    if ($additionId != $currentAdditionId
                        && empty($this->arResult['crossingAdditions'][$currentAdditionId]['products'][$product['id']])
                    ) {
                        $products[$product['id']]['addition_bx_id'] = $additionId;
                    }
                    $result['errorType'] = 'reserves';
                }

                if (!empty($arOrderedVolumes[$product['id']])
                    && (empty($result['errorType']) || $result['errorType'] === 'orders')
                    && ($additionId == $currentAdditionId && $product['volume'] < $arOrderedVolumes[$product['id']])
                ) {
                    $products[$product['id']]['volume'] = $product['volume'];
                    $products[$product['id']]['volume_ordered'] = $arOrderedVolumes[$product['id']];
                    if ($additionId != $currentAdditionId
                        && empty($this->arResult['crossingAdditions'][$currentAdditionId]['products'][$product['id']])
                    ) {
                        $products[$product['id']]['addition_bx_id'] = $additionId;
                    }
                    $result['errorType'] = 'orders';
                }
            }
        }

        if ($products) {
            $result['products'] = $this->getProducts(array_keys($products));

            foreach ($products as $id => $product) {
                $result['products'][$id]['VOLUME'] = $product['volume'];
                $result['products'][$id]['VOLUME_OLD'] = $product['volume_old'];
                $result['products'][$id]['VOLUME_ORDERED'] = $product['volume_ordered'];
                $result['products'][$id]['ADDITION_BX_ID'] = $product['addition_bx_id'];
            }
        }

        return $result['products'] ? $result : [];
    }
    
    protected function getRemains()
    {
        $remains = (float)$this->arResult['contract']['UF_REMAINS_50_50'];
        
        if (!empty($this->arResult['addition']['UF_ADDITION_NUMBER'])) {
            $remains += (float)$this->arResult['addition']['UF_ADVERT_50_50_COST'];
        }
        
        return $remains;
    }

    /**
     * Отменить дополнение
     * @param bool $background - запустить обмен
     * @return bool
     * @throws SystemException
     */
    protected function cancelAddition(bool $background = true): bool
    {
        $canceler = new \Korus\B2b\Additions\Cancel($this->arResult['addition']['ID']);
        if (!$canceler->can()) {
            return false;
        }

        $canceler->cancel($background);

        return true;
    }
}
