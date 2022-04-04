<?php

namespace Adv\CommunicationCards\Model;

use Adv\CommunicationCards\Enum\BitrixEnum;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\EnumField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;

class CommunicationCardsTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return 'hl_communication_cards';
    }

    /**
     * @return array
     * @throws \Bitrix\Main\SystemException
     */
    public static function getMap(): array
    {
        $bitrixEnum = new BitrixEnum("CommunicationCards");
        $ufTypeValues = $bitrixEnum->getPropEnumValues('UF_TYPE');

        return [
            new IntegerField(
                'ID',
                [
                    'primary'      => true,
                    'autocomplete' => true,
                ]
            ),
            new EnumField(
                'UF_TYPE',
                [
                    'values' => $ufTypeValues,
                ]
            ),
            new DatetimeField(
                'UF_CALL_CREATED_AT',
                [
                    'title' => 'Дата создания',
                ]
            ),
            new IntegerField(
                'UF_CALL_DURATION',
                [
                    'title'   => 'Длительность звонка',
                    'default' => BitrixEnum::NO,
                ]
            ),
            new TextField(
                'UF_PHONE',
                [
                    'title'      => 'Телефон',
                    'serialized' => false,
                ]
            ),
            new TextField(
                'UF_COMMENT',
                [
                    'title'      => 'Комментарий',
                    'serialized' => false,
                ]
            ),
            new IntegerField(
                'UF_THEME',
                [
                    // Связь с таблицей Тем для карточек коммуникаций
                    'title' => 'Тема обращения',
                ]
            ),
            new TextField(
                'UF_CUSTOMER_EMAIL',
                [
                    'title'      => 'Email',
                    'serialized' => false,
                ]
            ),
            new TextField(
                'UF_CUSTOMER_NAME',
                [
                    'title'      => 'Имя',
                    'serialized' => false,
                ]
            ),
            new TextField(
                'UF_CUSTOMER_SECOND_NAME',
                [
                    'title'      => 'Фамилия',
                    'serialized' => false,
                ]
            ),
            new TextField(
                'UF_CUSTOMER_LAST_NAME',
                [
                    'title'      => 'Отчество',
                    'serialized' => false,
                ]
            ),
        ];
    }
}
