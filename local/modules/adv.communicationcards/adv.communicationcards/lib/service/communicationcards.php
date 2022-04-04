<?php


namespace Adv\CommunicationCards\Service;

use Adv\CommunicationCards\Enum\BitrixEnum;
use Adv\CommunicationCards\Model\CommunicationCardsTable;
use Bitrix\Main\Type;

/**
 * Класс для работы с сущностями Карточек коммуникации
 * Class CommunicationCards
 */
class CommunicationCards
{
    protected const HL_BLOCK = 'CommunicationCards';

    private $phone;
    /**
     * @var BitrixEnum
     */
    private $bitrixEnum;
    /**
     * @var mixed
     */
    private $hlBlockId;

    private $requiredFields = [
        'login'       => 'Телефон',
        'name'        => 'Имя',
        'cardTitle'   => 'Тема обращения',
        'cardComment' => 'Комментарий',
    ];

    public function __construct($phone)
    {
        $this->phone      = $phone;
        $this->bitrixEnum = new BitrixEnum(self::HL_BLOCK);

        $hlBlock = $this->bitrixEnum->getByNameHL(self::HL_BLOCK);
        if (!empty($hlBlock['ID'])) {
            $this->hlBlockId = $hlBlock['ID'];
        }
    }

    /**
     * @param string $phone
     * @param        $user
     * @return \Bitrix\Main\ORM\Data\AddResult|bool
     * @throws \Exception
     */
    public function createDefaultCard($user)
    {
        $data['fields'] = [
            'UF_PHONE'                => $this->phone,
            'UF_TYPE'                 => $this->bitrixEnum->getTypeInId(),
            'UF_CALL_DURATION'        => 0,
            'UF_CALL_CREATED_AT'      => new Type\DateTime(),
            'UF_CUSTOMER_EMAIL'       => $user['EMAIL'],
            'UF_CUSTOMER_NAME'        => $user['NAME'],
            'UF_CUSTOMER_SECOND_NAME' => $user['SECOND_NAME'],
            'UF_CUSTOMER_LAST_NAME'   => $user['LAST_NAME'],
        ];

        $addedId = CommunicationCardsTable::add($data);
        if (!$addedId->isSuccess()) {
            return false;
        }

        return $addedId->getId();
    }

    /**
     * @param int                      $userId
     * @param Type\ParameterDictionary $postData
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function update(int $userId, Type\ParameterDictionary $postData)
    {
        $duration = $this->calcDuration($postData['cardId']);

        $data['UF_USER'] = $userId;

        // Обновляем время, только если нажали на кнопку "Завершить"
        if (isset($postData['save_finish'])) {
            $data['UF_CALL_DURATION'] = $duration;
        }

        // Связываем карточку с пользователем по номеру телефона,
        // который является логином в профиле покупателя.
        if (!empty($postData['login'])) {
            $data['UF_PHONE'] = $postData['login'];
        }
        if (!empty($postData['cardTitle'])) {
            $data['UF_THEME'] = (int) $postData['cardTitle'];
        }
        if (!empty($postData['email'])) {
            $data['UF_CUSTOMER_EMAIL'] = $postData['email'];
        }
        if (!empty($postData['name'])) {
            $data['UF_CUSTOMER_NAME'] = $postData['name'];
        }
        if (!empty($postData['secondName'])) {
            $data['UF_CUSTOMER_SECOND_NAME'] = $postData['secondName'];
        }
        if (!empty($postData['lastName'])) {
            $data['UF_CUSTOMER_LAST_NAME'] = $postData['lastName'];
        }
        if (!empty($postData['cardComment'])) {
            $data['UF_COMMENT'] = $postData['cardComment'];
        }

        $res = CommunicationCardsTable::update($postData['cardId'], $data);

        return $res->isSuccess();
    }

    /**
     * @param int $cardId
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function getCardsInfo(int $cardId)
    {
        $filter = ['=ID' => (int) $cardId];
        $select = ['*'];

        $res = CommunicationCardsTable::getList(['select' => $select, 'filter' => $filter]);
        $row = $res->fetch();

        $date = $row['UF_CALL_CREATED_AT']->format('d.m.Y H:i:s');

        return [
            "ID"                   => $cardId,
            "DATE"                 => $date,
            "TYPE"                 => $row['UF_TYPE'],
            "PHONE"                => $row['UF_PHONE'],
            "COMMENT"              => $row['UF_COMMENT'],
            "CUSTOMER_NAME"        => $row['UF_CUSTOMER_NAME'],
            "CUSTOMER_SECOND_NAME" => $row['UF_CUSTOMER_SECOND_NAME'],
            "CUSTOMER_LAST_NAME"   => $row['UF_CUSTOMER_LAST_NAME'],
            "CUSTOMER_EMAIL"       => $row['UF_CUSTOMER_EMAIL'],
            "CUSTOMER_THEME"       => $row['UF_THEME'],
        ];
    }

    /**
     * @param int $limit
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function getList(int $limit)
    {
        $select = ['*'];

        $res = CommunicationCardsTable::getList(
            [
                'select' => $select,
                'filter' => ['=UF_PHONE' => $this->phone],
                'limit'  => $limit,
                'order'  => ['UF_CALL_CREATED_AT' => 'desc'],
            ]
        );

        return $res->fetchAll();
    }

    /**
     * @param int $cardId
     * @return int
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private function calcDuration(int $cardId): int
    {
        $row = CommunicationCardsTable::getRowById($cardId);

        $pastTime    = $row['UF_CALL_CREATED_AT'] ? $row['UF_CALL_CREATED_AT']->format("U") : 0;
        $currentTime = (new Type\DateTime())->format("U");

        return (int) ($currentTime - $pastTime);
    }

    public function getUfTypeValById($val)
    {
        $arrList = $this->bitrixEnum->getPropEnumList('UF_TYPE');

        $arr = array_column($arrList, 'VALUE', 'ID');

        return $arr[$val];
    }

    public function buildCardUrl($id)
    {
        return "/bitrix/admin/highloadblock_row_edit.php?ENTITY_ID=" . $this->hlBlockId . "&ID=" . $id . "&lang=" . LANG;
    }

    public function getHlBlockId()
    {
        return $this->hlBlockId;
    }

    public function isErrors($inFields)
    {
        $errors = [];
        foreach ($this->requiredFields as $field => $fieldValue) {
            if (empty($inFields[$field])) {
                $errors[$field] = "Поле \"{$fieldValue}\" не может быть пустым";
            }
        }

        return $errors;
    }
}