<?php

namespace Adv\CommunicationCards\Controller;

use Adv\CommunicationCards\Service\UsersData;
use Bitrix\Main\Engine\Controller;

class Updater extends Controller
{
    public function applyAction($phone)
    {
        $userData = UsersData::getUserInfo($phone);

        return [
            'userdata' => json_encode($userData)
        ];
    }
}