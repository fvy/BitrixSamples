<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Korus\Basic\Manager\ManagerStore;

/**
 * Class AgreementsList
 * Список Дополнений
 */
class AgreementsList extends CBitrixComponent
{
    use \Korus\Basic\Traits\GetManager;

    public function __construct($component = null)
    {
        parent::__construct($component);

        \Korus\Basic\Helpers\Page::initModules(['korus.b2b']);
    }

    /**
     * @inheritdoc
     *
     * @param $arParams
     *
     * @return array
     */
    public function onPrepareComponentParams($arParams)
    {
        return parent::onPrepareComponentParams($arParams);
    }

    /**
     * @inheritdoc
     *
     * @return mixed|void
     * @throws \Bitrix\Main\SystemException
     */
    public function executeComponent()
    {
        
        $contract = $this->getManagerContracts()->getRow([
            'select' => ['ID', 'UF_1C_ID'],
            'filter' => ['=ID' => (int)$this->arParams['CONTRACT_ID']]
        ]);
        $this->arResult['ADDITIONALS'] = $this->getAdditions($contract['UF_1C_ID']);
        $this->arResult['ADDITIONALS_RAW'] = $this->getAdditionsRaw($contract['UF_1C_ID']);
        $this->arResult['USERS_LIST'] = $this->getListOfUsersById();
        $this->arResult['STATUSES_LIST'] = $this->getManagerAdditionals()->getStatusValuesList();
        $this->arResult['STORES_LIST'] = $this->getStores(array_column($this->arResult['ADDITIONALS'], 'UF_SHIPPING_STORE', 'UF_SHIPPING_STORE'));

        $this->includeComponentTemplate();
    }

    /**
     * Массив пользователей с ключом по ID
     *
     * @return array
     *
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private function getListOfUsersById(): array
    {
        return array_column(
            $this->getManagerUser()->getList(
                ['=ID' => array_column($this->arResult['ADDITIONALS'], 'UF_USER')],
                ['ID', 'EMAIL']
            ),
            'EMAIL',
            'ID'
        );
    }

    /**
     * Дополнения
     * 
     * @param string $contractId
     * @return array
     */
    protected function getAdditions ($contractId) 
    {
        $result = [];
        $additions = $this->getManagerAdditionals()->find(
            [
                'filter' => [
                    '=UF_CONTRACT' => $contractId,
                    '=UF_ACTIVE'   => 1,
                ],
                'order'  => ['UF_PERIOD' => 'DESC', 'ID' => 'DESC']
            ]
        );

        $ids = array_column($additions, 'ID');
        
        $additionsProducts = $this->getAdditionProducts($ids);
        $additionsCanCancel = \Korus\B2b\Additions\Cancel::canList($ids);

        foreach ($additions as $addition) {
            $addition['COUNT'] = $additionsProducts[$addition['ID']]['COUNT'];
            $addition['WEIGHT'] = $addition['UF_WEIGHT_NETTO']
                ? $addition['UF_WEIGHT_NETTO']
                : $additionsProducts[$addition['ID']]['WEIGHT'];
            $addition['IS_RAW'] = false;
            $addition['EDIT'] = $this->getManagerAdditionals()->canEdit($addition);
            $addition['CANCEL'] = $additionsCanCancel[$addition['ID']] ?? false;
            $result[] = $addition;
        }

        return $result;
    }

    /**
     * Дополнения (черновики)
     * 
     * @param string $contractId
     * @return array
     */
    protected function getAdditionsRaw ($contractId) 
    {
        $result = [];
        $additions = $this->getManagerAdditionsRaw()->find(
            [
                'filter' => ['=UF_CONTRACT' => $contractId],
                'order'  => ['UF_PERIOD' => 'DESC', 'ID' => 'DESC']
            ]
        );
        
        
        $additionsProducts = $this->getAdditionProductsRaw(array_column($additions, 'ID'));
        
        foreach ($additions as $addition) {
            $addition['COUNT'] = $additionsProducts[$addition['ID']]['COUNT'];
            $addition['WEIGHT'] = $addition['UF_WEIGHT_NETTO']
                ? $addition['UF_WEIGHT_NETTO']
                : $additionsProducts[$addition['ID']]['WEIGHT'];
            $addition['IS_RAW'] = true;
            $result[] = $addition;
        }

        return $result;
    }
    
    protected function getAdditionProducts($additionIds)
    {
        if (empty($additionIds)) return [];
        
        $additionsProducts = $this->getManagerAdditionalProducts()->find(
            [
                'select' => ['ID', 'UF_ADDITION', 'UF_PRODUCTS', 'UF_PRODUCTS_CNT'],
                'filter' => [
                    '=UF_ADDITION' => $additionIds,
                ],
            ]
        );
        
        $productsWeight = $this->getProductsWeight(array_column($additionsProducts, 'UF_PRODUCTS'));
        
        $products = [];
        foreach ($additionsProducts as $product) {
            $products[$product['UF_ADDITION']]['COUNT'] += 1;
            $products[$product['UF_ADDITION']]['WEIGHT'] += $productsWeight[$product['UF_PRODUCTS']] * $product['UF_PRODUCTS_CNT'] / 1000;
        }

        return $products;
    }
    
    protected function getAdditionProductsRaw($additionIds)
    {
        if (empty($additionIds)) return [];
        
        $additionsProducts = $this->getManagerAdditionProductsRaw()->find(
            [
                'select' => ['ID', 'UF_ADDITION', 'UF_PRODUCTS', 'UF_VOLUME'],
                'filter' => [
                    '=UF_ADDITION' => $additionIds,
                ],
            ]
        );
        
        $productsWeight = $this->getProductsWeight(array_column($additionsProducts, 'UF_PRODUCTS'));
                
        $products = [];
        foreach ($additionsProducts as $product) {
            $products[$product['UF_ADDITION']]['COUNT'] += 1;
            $products[$product['UF_ADDITION']]['WEIGHT'] += $productsWeight[$product['UF_PRODUCTS']] * $product['UF_VOLUME'] / 1000;
        }

        return $products;
    }
    
    protected function getProductsWeight($productIds)
    {
        if (empty($productIds)) return [];
        
        $arProducts = $this->getManagerCatalog()->managerElements()->getCatalogProductsList(
            [
                'PROPERTIES_VALUES' =>
                    ['=IDENTIFIER_1C' => $productIds],
            ],
            [
                'ID',
                'WEIGHT'            => 'catalog.WEIGHT',
                'PROPERTIES_VALUES' => ['IDENTIFIER_1C', 'NORM_PALLETIZING', 'WEIGHT_BRUTTO'],
                'MEASURE'           => 'catalog.MEASURE',
            ],
            true,
            true
        );

        $weightBy1cId = [];
        $measurementPiece = (new \Korus\Basic\Manager\ManagerMeasurement)->getMeasurementByRusCode('шт');
        foreach ($arProducts as $product) {
            if ($measurementPiece['ID'] == $product['MEASURE']) {
                $weightBy1cId[$product['PROPERTIES']['IDENTIFIER_1C']] =
                    $product['WEIGHT'] * $product['PROPERTIES']['NORM_PALLETIZING'];
            } else {
                $weightBy1cId[$product['PROPERTIES']['IDENTIFIER_1C']] = $product['WEIGHT'];
            }
        }
        
        return $weightBy1cId;
    }

    protected function getStores(array $storesListBy1cId): array
    {
        $managerStore = new ManagerStore;

        $stores = $managerStore->findByCodeAskuList($storesListBy1cId);
        return array_column($stores, 'TITLE', 'UF_CODE_ASKU');
    }
}
