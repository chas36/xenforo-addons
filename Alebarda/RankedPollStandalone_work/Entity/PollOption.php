<?php

namespace Alebarda\RankedPollStandalone\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $option_id
 * @property int $poll_id
 * @property string $option_text
 * @property string $option_description
 * @property int $display_order
 * @property int $times_ranked_first
 * @property int $times_ranked
 *
 * RELATIONS
 * @property \Alebarda\RankedPollStandalone\Entity\Poll $Poll
 */
class PollOption extends Entity
{
    /**
     * Получить процент выбора на первое место
     */
    public function getFirstPlacePercentage()
    {
        if ($this->Poll->voter_count == 0) {
            return 0;
        }

        return round(($this->times_ranked_first / $this->Poll->voter_count) * 100, 1);
    }

    /**
     * Получить процент ранжирования (вообще)
     */
    public function getRankedPercentage()
    {
        if ($this->Poll->voter_count == 0) {
            return 0;
        }

        return round(($this->times_ranked / $this->Poll->voter_count) * 100, 1);
    }

    /**
     * Структура entity
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_alebarda_rankedpoll_option';
        $structure->shortName = 'Alebarda\RankedPollStandalone:PollOption';
        $structure->primaryKey = 'option_id';

        $structure->columns = [
            'option_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'poll_id' => ['type' => self::UINT, 'required' => true],
            'option_text' => ['type' => self::STR, 'maxLength' => 500, 'required' => true],
            'option_description' => ['type' => self::STR, 'default' => ''],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'times_ranked_first' => ['type' => self::UINT, 'default' => 0],
            'times_ranked' => ['type' => self::UINT, 'default' => 0],
        ];

        $structure->relations = [
            'Poll' => [
                'entity' => 'Alebarda\RankedPollStandalone:Poll',
                'type' => self::TO_ONE,
                'conditions' => 'poll_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}
