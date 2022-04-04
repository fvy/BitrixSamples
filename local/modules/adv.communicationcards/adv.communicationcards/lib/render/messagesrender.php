<?php


namespace Adv\CommunicationCards\Render;


use CAdminException;
use CAdminMessage;

class MessagesRender
{
    public const EXCEPTION_MSG = "Возникла неустранимая ошибка.";
    public const ERRORS_MSG    = "Следующие данные не сохранены. Исправьте ошибки.";

    public function __construct()
    {
    }

    /**
     * @param $e
     * @return string
     */
    public function displayException($e): string
    {
        $aMsg[]['text'] = $e->getTraceAsString();
        $aMsg[]['text'] = $e->getMessage();

        $message = $this->createMsgObject($aMsg, self::EXCEPTION_MSG);

        return $message->Show();
    }

    /**
     * @param array $errors
     * @return string
     */
    public function displayErrors(array $errors): string
    {
        foreach ($errors as $error) {
            $aMsg[]['text'] = $error;
        }

        $message = $this->createMsgObject($aMsg);

        return $message->Show();
    }

    private function createMsgObject($aMsg, $titleText = '')
    {
        $e = new CAdminException($aMsg);
        $GLOBALS["APPLICATION"]->ThrowException($e);

        return new CAdminMessage($titleText, $e);
    }
}