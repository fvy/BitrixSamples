<?php

namespace Adv\CommunicationCards\Service;


use Adv\CommunicationCards\Enum\BitrixEnum;
use Adv\CommunicationCards\Model\CommunicationCardThemesTable;

class CommunicationCardThemes
{
    protected const HL_BLOCK = 'CommunicationCardThemes';
    /**
     * @var BitrixEnum
     */
    private $bitrixEnum;
    /**
     * @var mixed
     */
    private $hlBlockId;

    public function __construct()
    {
        $this->bitrixEnum = new BitrixEnum(self::HL_BLOCK);

        $hlBlock = $this->bitrixEnum->getByNameHL(self::HL_BLOCK);
        if (!empty($hlBlock['ID'])) {
            $this->hlBlockId = $hlBlock['ID'];
        }
    }

    public function getList()
    {
        $select = ['*'];
        $res    = CommunicationCardThemesTable::getList(['select' => $select]);
        $row    = $res->fetchAll();

        return array_column($row, 'UF_NAME', 'ID');
    }

    public function getHlBlockId() {

        return $this->hlBlockId;
    }
}