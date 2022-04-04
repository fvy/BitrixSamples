<?php

namespace Adv\CommunicationCards\Enum;

use Bitrix\Highloadblock as HL;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\SystemException;

class BitrixEnum
{
    public const YES      = 'Y';
    public const ERROR    = 'E';
    public const NO       = 'N';
    public const TYPE_IN  = 'IN';
    public const TYPE_OUT = 'OUT';

    private $entity;
    private $data;

    /**
     * BitrixEnum constructor.
     * @param $name
     * @throws SystemException
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     */
    public function __construct($name)
    {
        $data = $this->getByNameHL($name);
        if ($data == null) {
            throw new SystemException('Не найден HL для NAME = ' . $name);
        }

        $result['data'] = $data;

        $this->data   = $result['data'];
        $this->entity = $this->getEntity();
    }

    /**
     * Получение значений перечислимого свойства (списка)
     *
     * @param string $name
     * @return array
     * @throws SystemException
     */
    public function getPropEnumList(string $name): array
    {
        static $enumList;

        if (!isset($enumList[$this->data['TABLE_NAME']])) {
            $enumList[$this->data['TABLE_NAME']] = [];
        }

        if (!isset($enumList[$this->data['TABLE_NAME']][$name])) {
            $propId = $this->getIdProp($name, 'enumeration');
            if (!$propId) {
                throw new SystemException('Свойство "' . $name . '" не существует или не является списком');
            }

            $resultDb = (new \CUserFieldEnum())->GetList([], ['USER_FIELD_ID' => $propId]);

            $list = [];
            while ($row = $resultDb->Fetch()) {
                $list[$row['ID']] = $row;
            }

            $enumList[$this->data['TABLE_NAME']][$name] = $list;
        }

        return $enumList[$this->data['TABLE_NAME']][$name];
    }

    public function getPropEnumValues(string $name): array
    {
        return array_column($this->getPropEnumList($name), 'ID');
    }


    /**
     * Возвращает ID Пользовательского свойства UF_
     *
     * @param string $name
     * @param string $userTypeId
     * @return int
     */
    public function getIdProp(string $name, string $userTypeId = ''): int
    {
        $res = \CUserTypeEntity::GetList(
            [],
            [
                'ENTITY_ID'    => 'HLBLOCK_' . $this->data['ID'],
                'FIELD_NAME'   => $name,
                'USER_TYPE_ID' => $userTypeId,
            ]
        )->Fetch();

        return $res ? (int) $res['ID'] : 0;
    }

    /**
     * Возвращает данные HL по его имени
     *
     * @param string $name
     * @return array
     * @throws SystemException
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     */
    public function getByNameHL(string $name): array
    {
        $row = HL\HighloadBlockTable::getRow(['filter' => ['NAME' => $name]]);

        return $row ? $row : [];
    }

    /**
     * Сущность HL
     *
     * @return Entity
     * @throws SystemException
     */
    public function getEntity()
    {
        if ($this->entity === null) {
            $this->entity = HL\HighloadBlockTable::compileEntity($this->data);
        }

        return $this->entity;
    }

    /**
     * @return array|mixed|null
     * @throws SystemException
     */
    public function getTypeInId()
    {
        return array_column($this->getPropEnumList('UF_TYPE'), 'ID', 'XML_ID')[self::TYPE_IN];
    }

    /**
     * @return array|mixed|null
     * @throws SystemException
     */
    public function getTypeOutId()
    {
        return array_column($this->getPropEnumList('UF_TYPE'), 'ID', 'XML_ID')[self::TYPE_OUT];
    }

    public function getHlBlockId() {

        return $this->hlBlockId;
    }
}
