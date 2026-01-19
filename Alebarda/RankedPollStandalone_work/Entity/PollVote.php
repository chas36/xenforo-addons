<?php

namespace Alebarda\RankedPollStandalone\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $vote_id
 * @property int $poll_id
 * @property int $user_id
 * @property int $option_id
 * @property int $rank_position
 * @property int $vote_date
 *
 * RELATIONS
 * @property \Alebarda\RankedPollStandalone\Entity\Poll $Poll
 * @property \XF\Entity\User $User
 * @property \Alebarda\RankedPollStandalone\Entity\PollOption $Option
 */
class PollVote extends Entity
{
    /**
     * Структура entity
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_alebarda_rankedpoll_vote';
        $structure->shortName = 'Alebarda\RankedPollStandalone:PollVote';
        $structure->primaryKey = 'vote_id';

        $structure->columns = [
            'vote_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'poll_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'option_id' => ['type' => self::UINT, 'required' => true],
            'rank_position' => ['type' => self::UINT, 'required' => true],
            'vote_date' => ['type' => self::UINT, 'default' => \XF::$time],
        ];

        $structure->relations = [
            'Poll' => [
                'entity' => 'Alebarda\RankedPollStandalone:Poll',
                'type' => self::TO_ONE,
                'conditions' => 'poll_id',
                'primary' => true
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Option' => [
                'entity' => 'Alebarda\RankedPollStandalone:PollOption',
                'type' => self::TO_ONE,
                'conditions' => 'option_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}
