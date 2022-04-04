<?php

namespace Adv\CommunicationCards\Model;

use Adv\CommunicationCards\Enum\BitrixEnum;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\EnumField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

class CommunicationCardThemesTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return 'hl_communication_card_themes';
    }

    /**
     * @return array
     * @throws \Bitrix\Main\SystemException
     */
    public static function getMap(): array
    {
        return [
            new IntegerField(
                'ID',
                [
                    'primary'      => true,
                    'autocomplete' => true,
                ]
            ),
            new StringField(
                'UF_NAME',
                [
                    'title'   => 'Тема',
                    'default' => null,
                ]
            ),
        ];
    }
}
