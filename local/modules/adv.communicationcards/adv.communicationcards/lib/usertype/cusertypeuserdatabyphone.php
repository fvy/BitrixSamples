<?php

namespace Adv\CommunicationCards\UserType;


use CUtil;

class CUserTypeUserDataByPhone
{
    const USER_TYPE_ID = 'userreferenceajax';

    /**
     * Обработчик события OnUserTypeBuildList.
     *
     * <p>Эта функция регистрируется в качестве обработчика события OnUserTypeBuildList.
     * Возвращает массив описывающий тип пользовательских свойств.</p>
     * <p>Элементы массива:</p>
     * <ul>
     * <li>USER_TYPE_ID - уникальный идентификатор
     * <li>CLASS_NAME - имя класса методы которого формируют поведение типа
     * <li>DESCRIPTION - описание для показа в интерфейсе (выпадающий список и т.п.)
     * <li>BASE_TYPE - базовый тип на котором будут основаны операции фильтра (int, double, string, date, datetime)
     * </ul>
     * @return array
     * @static
     */
    function GetUserTypeDescription()
    {
        return [
            "USER_TYPE_ID" => static::USER_TYPE_ID,
            "CLASS_NAME"   => __CLASS__,
            "DESCRIPTION"  => 'Получение данных пользователя по телефону',
            "BASE_TYPE"    => \CUserTypeManager::BASE_TYPE_STRING,
        ];
    }

    /**
     * Эта функция вызывается при добавлении нового свойства.
     *
     * <p>Эта функция вызывается для конструирования SQL запроса
     * создания колонки для хранения не множественных значений свойства.</p>
     * <p>Значения множественных свойств хранятся не в строках, а столбиках (как в инфоблоках)
     * и тип такого поля в БД всегда text.</p>
     * @param array $arUserField Массив описывающий поле
     * @return string
     * @static
     */
    function GetDBColumnType($arUserField)
    {
        global $DB;
        switch (strtolower($DB->type)) {
            case "mysql":
                return "text";
            case "oracle":
                return "varchar2(2000 char)";
            case "mssql":
                return "varchar(2000)";
        }
    }

    /**
     * Эта функция вызывается перед сохранением метаданных свойства в БД.
     *
     * <p>Она должна "очистить" массив с настройками экземпляра типа свойства.
     * Для того, чтобы случайно/намеренно никто не записал туда всякой фигни.</p>
     * @param array $arUserField Массив описывающий поле. <b>Внимание!</b> это описание поля еще не сохранено в БД!
     * @return array Массив который в дальнейшем будет сериализован и сохранен в БД.
     * @static
     */
    function PrepareSettings($arUserField)
    {
        $size = intval($arUserField["SETTINGS"]["SIZE"]);
        $rows = intval($arUserField["SETTINGS"]["ROWS"]);
        $min  = intval($arUserField["SETTINGS"]["MIN_LENGTH"]);
        $max  = intval($arUserField["SETTINGS"]["MAX_LENGTH"]);

        return [
            "SIZE"          => ($size <= 1 ? 20 : ($size > 255 ? 225 : $size)),
            "ROWS"          => ($rows <= 1 ? 1 : ($rows > 50 ? 50 : $rows)),
            "REGEXP"        => $arUserField["SETTINGS"]["REGEXP"],
            "MIN_LENGTH"    => $min,
            "MAX_LENGTH"    => $max,
            "DEFAULT_VALUE" => $arUserField["SETTINGS"]["DEFAULT_VALUE"],
        ];
    }

    /**
     * Эта функция вызывается при выводе формы настройки свойства.
     *
     * <p>Возвращает html для встраивания в 2-х колоночную таблицу.
     * в форму usertype_edit.php</p>
     * <p>т.е. tr td bla-bla /td td edit-edit-edit /td /tr </p>
     * @param array $arUserField   Массив описывающий поле. Для нового (еще не добавленного поля - <b>false</b>)
     * @param array $arHtmlControl Массив управления из формы. Пока содержит только один элемент NAME (html безопасный)
     * @return string HTML для вывода.
     * @static
     */
    function GetSettingsHTML($arUserField = false, $arHtmlControl, $bVarsFromForm)
    {
        $result = '';
        if ($bVarsFromForm) {
            $value = htmlspecialcharsbx($GLOBALS[$arHtmlControl["NAME"]]["DEFAULT_VALUE"]);
        } elseif (is_array($arUserField)) {
            $value = htmlspecialcharsbx($arUserField["SETTINGS"]["DEFAULT_VALUE"]);
        } else {
            $value = "";
        }
        $result .= '
		<tr>
			<td>' . GetMessage("USER_TYPE_STRING_DEFAULT_VALUE") . ':</td>
			<td>
				<input type="text" name="' . $arHtmlControl["NAME"] . '[DEFAULT_VALUE]" size="20"  maxlength="225" value="' . $value . '">
			</td>
		</tr>
		';
        if ($bVarsFromForm) {
            $value = intval($GLOBALS[$arHtmlControl["NAME"]]["SIZE"]);
        } elseif (is_array($arUserField)) {
            $value = intval($arUserField["SETTINGS"]["SIZE"]);
        } else {
            $value = 20;
        }
        $result .= '
		<tr>
			<td>' . GetMessage("USER_TYPE_STRING_SIZE") . ':</td>
			<td>
				<input type="text" name="' . $arHtmlControl["NAME"] . '[SIZE]" size="20"  maxlength="20" value="' . $value . '">
			</td>
		</tr>
		';
        if ($bVarsFromForm) {
            $value = intval($GLOBALS[$arHtmlControl["NAME"]]["ROWS"]);
        } elseif (is_array($arUserField)) {
            $value = intval($arUserField["SETTINGS"]["ROWS"]);
        } else {
            $value = 1;
        }
        if ($value < 1) {
            $value = 1;
        }
        $result .= '
		<tr>
			<td>' . GetMessage("USER_TYPE_STRING_ROWS") . ':</td>
			<td>
				<input type="text" name="' . $arHtmlControl["NAME"] . '[ROWS]" size="20"  maxlength="20" value="' . $value . '">
			</td>
		</tr>
		';
        if ($bVarsFromForm) {
            $value = intval($GLOBALS[$arHtmlControl["NAME"]]["MIN_LENGTH"]);
        } elseif (is_array($arUserField)) {
            $value = intval($arUserField["SETTINGS"]["MIN_LENGTH"]);
        } else {
            $value = 0;
        }
        $result .= '
		<tr>
			<td>' . GetMessage("USER_TYPE_STRING_MIN_LEGTH") . ':</td>
			<td>
				<input type="text" name="' . $arHtmlControl["NAME"] . '[MIN_LENGTH]" size="20"  maxlength="20" value="' . $value . '">
			</td>
		</tr>
		';
        if ($bVarsFromForm) {
            $value = intval($GLOBALS[$arHtmlControl["NAME"]]["MAX_LENGTH"]);
        } elseif (is_array($arUserField)) {
            $value = intval($arUserField["SETTINGS"]["MAX_LENGTH"]);
        } else {
            $value = 0;
        }
        $result .= '
		<tr>
			<td>' . GetMessage("USER_TYPE_STRING_MAX_LENGTH") . ':</td>
			<td>
				<input type="text" name="' . $arHtmlControl["NAME"] . '[MAX_LENGTH]" size="20"  maxlength="20" value="' . $value . '">
			</td>
		</tr>
		';
        if ($bVarsFromForm) {
            $value = htmlspecialcharsbx($GLOBALS[$arHtmlControl["NAME"]]["REGEXP"]);
        } elseif (is_array($arUserField)) {
            $value = htmlspecialcharsbx($arUserField["SETTINGS"]["REGEXP"]);
        } else {
            $value = "";
        }
        $result .= '
		<tr>
			<td>' . GetMessage("USER_TYPE_STRING_REGEXP") . ':</td>
			<td>
				<input type="text" name="' . $arHtmlControl["NAME"] . '[REGEXP]" size="20"  maxlength="200" value="' . $value . '">
			</td>
		</tr>
		';

        return $result;
    }

    /**
     * Эта функция вызывается при выводе формы редактирования значения свойства.
     *
     * <p>Возвращает html для встраивания в ячейку таблицы.
     * в форму редактирования сущности (на вкладке "Доп. свойства")</p>
     * <p>Элементы $arHtmlControl приведены к html безопасному виду.</p>
     * @param array $arUserField   Массив описывающий поле.
     * @param array $arHtmlControl Массив управления из формы. Содержит элементы NAME и VALUE.
     * @return string HTML для вывода.
     * @static
     */
    function GetEditFormHTML($arUserField, $arHtmlControl)
    {
        if (!$arUserField['VALUE']) {
            $arHtmlControl['VALUE'] = htmlspecialcharsbx($arUserField["SETTINGS"]["DEFAULT_VALUE"]);
        } else {
            $arHtmlControl['VALUE'] = $arUserField['VALUE'];
        }

        \CJSCore::Init(['jquery2']);
        CUtil::InitJSCore(['adv_ajax']);

        $return = '<input id="findbyphone" name="findbyphone" type="button" value="Найти" class="button">'
            . '<div id="phone_div" style="display: inline-grid;padding: 5px;">Нет данных</div>';

        return $return;
    }

    /**
     * Эта функция вызывается при выводе фильтра на странице списка.
     *
     * <p>Возвращает html для встраивания в ячейку таблицы.</p>
     * <p>Элементы $arHtmlControl приведены к html безопасному виду.</p>
     * @param array $arUserField   Массив описывающий поле.
     * @param array $arHtmlControl Массив управления из формы. Содержит элементы NAME и VALUE.
     * @return string HTML для вывода.
     * @static
     */
    function GetFilterHTML($arUserField, $arHtmlControl)
    {
        return '<input type="text" ' .
            'name="' . $arHtmlControl["NAME"] . '" ' .
            'size="' . $arUserField["SETTINGS"]["SIZE"] . '" ' .
            'value="' . $arHtmlControl["VALUE"] . '"' .
            '>';
    }

    function GetFilterData($arUserField, $arHtmlControl)
    {
        return [
            "id"         => $arHtmlControl["ID"],
            "name"       => $arHtmlControl["NAME"],
            "filterable" => "",
        ];
    }

    /**
     * Эта функция вызывается при выводе значения свойства в списке элементов.
     *
     * <p>Возвращает html для встраивания в ячейку таблицы.</p>
     * <p>Элементы $arHtmlControl приведены к html безопасному виду.</p>
     * @param array $arUserField   Массив описывающий поле.
     * @param array $arHtmlControl Массив управления из формы. Содержит элементы NAME и VALUE.
     * @return string HTML для вывода.
     * @static
     */
    function GetAdminListViewHTML($arUserField, $arHtmlControl)
    {
        if (strlen($arHtmlControl["VALUE"]) > 0) {
            return $arHtmlControl["VALUE"];
        } else {
            return '&nbsp;';
        }
    }

    /**
     * Эта функция вызывается при выводе значения свойства в списке элементов в режиме <b>редактирования</b>.
     *
     * <p>Возвращает html для встраивания в ячейку таблицы.</p>
     * <p>Элементы $arHtmlControl приведены к html безопасному виду.</p>
     * @param array $arUserField   Массив описывающий поле.
     * @param array $arHtmlControl Массив управления из формы. Содержит элементы NAME и VALUE.
     * @return string HTML для вывода.
     * @static
     */
    function GetAdminListEditHTML($arUserField, $arHtmlControl)
    {
        if ($arUserField["SETTINGS"]["ROWS"] < 2) {
            return '<input type="text" ' .
                'name="' . $arHtmlControl["NAME"] . '" ' .
                'size="' . $arUserField["SETTINGS"]["SIZE"] . '" ' .
                ($arUserField["SETTINGS"]["MAX_LENGTH"] > 0 ? 'maxlength="' . $arUserField["SETTINGS"]["MAX_LENGTH"] . '" ' : '') .
                'value="' . $arHtmlControl["VALUE"] . '" ' .
                '>';
        } else {
            return '<textarea ' .
                'name="' . $arHtmlControl["NAME"] . '" ' .
                'cols="' . $arUserField["SETTINGS"]["SIZE"] . '" ' .
                'rows="' . $arUserField["SETTINGS"]["ROWS"] . '" ' .
                ($arUserField["SETTINGS"]["MAX_LENGTH"] > 0 ? 'maxlength="' . $arUserField["SETTINGS"]["MAX_LENGTH"] . '" ' : '') .
                '>' . $arHtmlControl["VALUE"] . '</textarea>';
        }
    }

    /**
     * Эта функция валидатор.
     *
     * <p>Вызывается из метода CheckFields объекта $USER_FIELD_MANAGER.</p>
     * <p>Который в свою очередь может быть вызван из методов Add/Update сущности владельца свойств.</p>
     * <p>Выполняется 2 проверки:</p>
     * <ul>
     * <li>на минимальную длину (если в настройках минимальная длина больше 0).
     * <li>на регулярное выражение (если задано в настройках).
     * </ul>
     * @param array  $arUserField Массив описывающий поле.
     * @param string $value       значение для проверки на валидность
     * @return array массив массивов ("id","text") ошибок.
     * @static
     */
    function CheckFields($arUserField, $value)
    {
        $aMsg = [];
        if ($value <> '' && strlen($value) < $arUserField["SETTINGS"]["MIN_LENGTH"]) {
            $aMsg[] = [
                "id"   => $arUserField["FIELD_NAME"],
                "text" => GetMessage(
                    "USER_TYPE_STRING_MIN_LEGTH_ERROR",
                    [
                        "#FIELD_NAME#" => ($arUserField["EDIT_FORM_LABEL"] <> '' ? $arUserField["EDIT_FORM_LABEL"] : $arUserField["FIELD_NAME"]),
                        "#MIN_LENGTH#" => $arUserField["SETTINGS"]["MIN_LENGTH"],
                    ]
                ),
            ];
        }
        if ($arUserField["SETTINGS"]["MAX_LENGTH"] > 0 && strlen($value) > $arUserField["SETTINGS"]["MAX_LENGTH"]) {
            $aMsg[] = [
                "id"   => $arUserField["FIELD_NAME"],
                "text" => GetMessage(
                    "USER_TYPE_STRING_MAX_LEGTH_ERROR",
                    [
                        "#FIELD_NAME#" => ($arUserField["EDIT_FORM_LABEL"] <> '' ? $arUserField["EDIT_FORM_LABEL"] : $arUserField["FIELD_NAME"]),
                        "#MAX_LENGTH#" => $arUserField["SETTINGS"]["MAX_LENGTH"],
                    ]
                ),
            ];
        }
        if ($arUserField["SETTINGS"]["REGEXP"] <> '' && !preg_match($arUserField["SETTINGS"]["REGEXP"], $value)) {
            $aMsg[] = [
                "id"   => $arUserField["FIELD_NAME"],
                "text" => (strlen($arUserField["ERROR_MESSAGE"]) > 0 ?
                    $arUserField["ERROR_MESSAGE"] :
                    GetMessage(
                        "USER_TYPE_STRING_REGEXP_ERROR",
                        [
                            "#FIELD_NAME#" => ($arUserField["EDIT_FORM_LABEL"] <> '' ? $arUserField["EDIT_FORM_LABEL"] : $arUserField["FIELD_NAME"]),
                        ]
                    )
                ),
            ];
        }

        return $aMsg;
    }

    /**
     * Эта функция должна вернуть представление значения поля для поиска.
     *
     * <p>Вызывается из метода OnSearchIndex объекта $USER_FIELD_MANAGER.</p>
     * <p>Который в свою очередь вызывается и функции обновления поискового индекса сущности.</p>
     * <p>Для множественных значений поле VALUE - массив.</p>
     * @param array $arUserField Массив описывающий поле.
     * @return string посковое содержимое.
     * @static
     */
    function OnSearchIndex($arUserField)
    {
        if (is_array($arUserField["VALUE"])) {
            return implode("\r\n", $arUserField["VALUE"]);
        } else {
            return $arUserField["VALUE"];
        }
    }
}
