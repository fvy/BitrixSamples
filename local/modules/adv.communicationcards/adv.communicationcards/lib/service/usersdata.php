<?php


namespace Adv\CommunicationCards\Service;

use Bitrix\Main\UserTable;

/**
 * Класс для работы с данными пользователя
 * Class ClientsData
 */
class UsersData
{
    /**
     * @param string $phone
     * @return array|bool|false
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function getUserInfo(string $phone)
    {
        $phone = self::cleanPhoneNumber($phone);

        $filter = ['=LOGIN' => $phone];
        $select = [
            'ID',
            'LOGIN',
            'NAME',
            'SECOND_NAME',
            'LAST_NAME',
            'PERSONAL_PHONE',
            'EMAIL',
        ];

        $users = UserTable::getList(['select' => $select, 'filter' => $filter,]);

        return $users->fetch();
    }

    private static function cleanPhoneNumber(string $phone)
    {
        $phone = rawurldecode($phone);

        return preg_replace("/[^0-9]/", "", $phone);
    }
}