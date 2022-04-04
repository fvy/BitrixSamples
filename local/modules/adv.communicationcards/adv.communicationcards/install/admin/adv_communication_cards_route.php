<?php

use Adv\CommunicationCards\Render\CartsRender;
use Adv\CommunicationCards\Render\OrdersRender;
use Adv\CommunicationCards\Render\MessagesRender;
use Adv\CommunicationCards\Service\CommunicationCardThemes;
use Adv\CommunicationCards\Service\UsersData;
use Adv\CommunicationCards\Service\CommunicationCards;
use Bitrix\Main\Context;

require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/local/modules/adv.communicationcards/include.php";

IncludeModuleLangFile(__FILE__);

require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php";

try {
    $messagesRender = new MessagesRender();

    $request = Context::getCurrent()->getRequest();
    $phone   = $request->getQuery('phone');

    if (!empty($phone)) {
        $user = UsersData::getUserInfo($phone);
    }

    $postData     = $request->getPostList();
    $isGetRequest = empty($postData['cardId']);

    $communicationCards      = new CommunicationCards($phone);
    $communicationCardThemes = new CommunicationCardThemes();

    if (!empty($phone) && $isGetRequest) {
        $lastId = $communicationCards->createDefaultCard($user);
    } elseif (!$isGetRequest) {
        $errors = $communicationCards->isErrors($postData);
        if (!empty($errors)) {
            echo $messagesRender->displayErrors($errors);
        }

        $communicationCards->update((int) $user['ID'], $postData);

        $lastId = $postData['cardId'];
    }

    if ($lastId) {
        $cCard = $communicationCards->getCardsInfo($lastId);
    }
} catch (\Throwable $e) {
    if (class_exists('MessagesRender')) {
        echo $messagesRender->displayException($e);
    } else {
        echo 'Модуль "Карточки коммуникации (adv.communicationcards)" не установлен';
        die;
    }
}

$aTabs = [
    ["DIV" => "edit1", "TAB" => "Карточка коммуникаций"],
];

if (empty($lastId)) {
    $tabControl = new CAdminTabControl("tabControl", $aTabs);
    $tabControl->Begin();
    $tabControl->BeginNextTab();

    $hlCardsBlockId       = $communicationCards->getHlBlockId();
    $hlCardsThemesBlockId = $communicationCardThemes->getHlBlockId();
    ?>
    <ul>
        <li><a href="highloadblock_rows_list.php?ENTITY_ID=<?= $hlCardsBlockId ?>&amp;lang=ru"><span
                        class="adm-submenu-item-name-link-text">Карточки коммуникаций для клиента</span></a>
        <li><a href="highloadblock_rows_list.php?ENTITY_ID=<?= $hlCardsThemesBlockId ?>&amp;lang=ru"><span
                        class="adm-submenu-item-name-link-text">Карточки коммуникаций (Темы обращений)</span></a>
    </ul>
    <?php
    $tabControl->EndTab();
    $tabControl->End();
} else {
    ?>
    <form method="POST" action="" enctype="multipart/form-data">
        <?php
        $tabControl = new CAdminTabControl("tabControl", $aTabs);
        $tabControl->Begin();
        $tabControl->BeginNextTab();
        ?>
        <tr>
            <td width="30%" style="text-align:left; vertical-align: top; padding: 5px 10px 5px 5px;">

                <input type="hidden" name="cardId" value="<?= $lastId ?>"/>

                <table border="0" cellspacing="0" cellpadding="0" width="100%"
                       class="adm-detail-content-table edit-table">
                    <tr class="heading">
                        <td colspan="2">Данные из карточки коммуникаций</td>
                    </tr>
                    <tr>
                        <td width="40%">ID Звонка:</td>
                        <td class="adm-detail-content-cell-r" width="60%">
                            <div><?= $cCard['ID']; ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>Дата и время:</td>
                        <td class="adm-detail-content-cell-r">
                            <div><?= $cCard['DATE']; ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>Тип Звонка:</td>
                        <td class="adm-detail-content-cell-r">
                            <div><?= $communicationCards->getUfTypeValById($cCard['TYPE']) ?></div>
                        </td>
                    </tr>
                </table>

                <table border="0" cellspacing="0" cellpadding="0" width="100%"
                       class="adm-detail-content-table edit-table">
                    <tr class="heading">
                        <td colspan="2">Данные клиента</td>
                    </tr>
                    <tr>
                        <td class="!adm-detail-content-cell-l" width="40%"><b>Телефон:</b>
                            <div>
                                <?php
                                if ($user['LOGIN']) { ?>
                                    [<a target="_blank"
                                        href="<?= $adminPage->getSelfFolderUrl(
                                        ) . "sale_buyers_profile.php?USER_ID=" . $user['ID'] . "&lang=" . LANG; ?>"
                                        target="_top"><?php
                                        echo join(' ', [$user['SECOND_NAME'], $user['NAME'], $user['LAST_NAME']]);
                                        ?></a>]
                                    <?php
                                } else { ?>
                                    [Профайл не найден]
                                    <?php
                                }
                                ?>
                            </div>
                        </td>
                        <td class="adm-detail-content-cell-r">
                            <input name="login" value="<?= $postData['login'] ?? $cCard['PHONE']; ?>"
                                   autocomplete="off">
                        </td>
                    </tr>
                    <tr>
                        <td><b>Имя:</b></td>
                        <td class="adm-detail-content-cell-r">
                            <input name="name" value="<?= $postData['name'] ?? $cCard['CUSTOMER_NAME']; ?>"
                                   autocomplete="off">
                        </td>
                    </tr>
                    <tr>
                        <td>Фамилия:</td>
                        <td class="adm-detail-content-cell-r">
                            <input name="secondName"
                                   value="<?= $cCard['CUSTOMER_SECOND_NAME'] ?: $user['SECOND_NAME']; ?>"
                                   autocomplete="off">
                        </td>
                    </tr>
                    <tr>
                        <td>Отчество:</td>
                        <td class="adm-detail-content-cell-r">
                            <input name="lastName" value="<?= $cCard['CUSTOMER_LAST_NAME'] ?: $user['LAST_NAME']; ?>"
                                   autocomplete="off">
                        </td>
                    </tr>
                    <tr>
                        <td>Email:</td>
                        <td class="adm-detail-content-cell-r">
                            <input name="email" value="<?= $cCard['CUSTOMER_EMAIL'] ?: $user['EMAIL']; ?>"
                                   autocomplete="off">
                        </td>
                    </tr>
                    <tr class="heading">
                        <td colspan="2" width="100%">Тема обращения и комментарий</td>
                    </tr>
                    <tr>
                        <td><b>Тема обращения:</b></td>
                        <td class="adm-detail-content-cell-r" width="90%">
                            <div>
                                <select name="cardTitle" style="width:100%">
                                    <option value="0">Не выбрана</option>
                                    <?php
                                    $themes = $communicationCardThemes->getList();
                                    foreach ($themes as $id => $theme) {
                                        $selectedTitle = $postData['cardTitle'] == $id ? ' selected' : '';
                                        echo "<option value='" . $id . "' " . $selectedTitle . ">" . $theme . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="adm-detail-content-cell-r"><b>Комментарий:</b></td>
                    </tr>
                    <tr>
                        <td class="adm-detail-content-cell-l" colspan="2">
                            <textarea name="cardComment" rows="5" cols="25"
                                      style="width:95%"><?= $postData['cardComment'] ?? $cCard['COMMENT']; ?></textarea>
                        </td>
                    </tr>
                </table>
            </td>

            <td width="70%" style="vertical-align: top; background: white; border: 1px silver solid">

                <div class="adm-detail-content" id="tab5" style="display: block;">
                    <div class="adm-detail-title">Заказы клиента</div>
                    <div class="!adm-detail-content-item-block">
                        <?
                        $ordersRender = new OrdersRender();
                        $ordersRender->display($user["ID"]);
                        ?>
                    </div>
                </div>

                <br>

                <div class="adm-detail-content" id="tab5" style="display: block;">
                    <div class="adm-detail-title">Коммуникации клиента</div>
                    <div class="!adm-detail-content-item-block">

                        <div class="adm-list-table-wrap adm-list-table-without-header adm-list-table-without-footer">
                            <table class="adm-list-table" id="tbl_sale_buyers_profile_tab3">
                                <thead>
                                <tr class="adm-list-table-header">
                                    <td class="adm-list-table-cell">
                                        <div class="adm-list-table-cell-inner">Дата</div>
                                    </td>
                                    <td class="adm-list-table-cell">
                                        <div class="adm-list-table-cell-inner">ID</div>
                                    </td>
                                    <td class="adm-list-table-cell">
                                        <div class="adm-list-table-cell-inner">Тип</div>
                                    </td>
                                    <td class="adm-list-table-cell">
                                        <div class="adm-list-table-cell-inner">Тема</div>
                                    </td>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                foreach ($communicationCards->getList(3) as $item) {
                                    ?>
                                    <tr align="center" class="adm-list-table-row">
                                        <td class="adm-list-table-cell"
                                            width="20%"><?= $item["UF_CALL_CREATED_AT"] ?? $item["UF_CALL_CREATED_AT"]->format(
                                                "d.m.Y h:i:s"
                                            ); ?></td>
                                        <td class="adm-list-table-cell" width="10%">
                                            <a target="_blank" href="<?= $communicationCards->buildCardUrl(
                                                $item["ID"]
                                            ); ?>"><?= $item["ID"]; ?></a>
                                        </td>
                                        <td class="adm-list-table-cell"><?= $communicationCards->getUfTypeValById(
                                                $item["UF_TYPE"]
                                            ); ?></td>
                                        <td class="adm-list-table-cell"><?= $themes[$item["UF_THEME"]]; ?></td>
                                    </tr>
                                    <?
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

                <div class="adm-detail-content" id="tab5" style="display: block;">
                    <div class="adm-detail-title">Корзина клиента</div>
                    <div class="!adm-detail-content-item-block">
                        <?php
                        $cartsRender = new CartsRender('');
                        $cartsRender->display($user["ID"]);
                        ?>
                    </div>
                </div>

            </td>
        </tr>
        <?php
        $tabControl->EndTab();
        $tabControl->Buttons();
        ?>
        <input type="submit" name="save_finish" value="Завершить" class="adm-btn-save">
        <input type="submit" name="save" value="Сохранить"/>
        <?
        $tabControl->End();
        ?>
    </form>
    <?php
}
require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php";
