<?php

namespace Alebarda\RankedPoll\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Represents a single ranked vote in a ranked poll
 *
 * REPRESENTS
 * -----------
 * xf_poll_ranked_vote table
 *
 * RELATIONS
 * -----------
 * @property \XF\Entity\Poll Poll
 * @property \XF\Entity\User User
 * @property \XF\Entity\PollResponse Response
 *
 * COLUMNS
 * -----------
 * @property int ranked_vote_id
 * @property int poll_id
 * @property int user_id
 * @property int poll_response_id
 * @property int rank_position - 1 = first choice, 2 = second choice, etc.
 * @property int vote_date
 */
class RankedVote extends Entity
{
	/**
	 * Prevent updates to ranked votes
	 *
	 * Following XenForo's pattern for PollVote, ranked votes are immutable.
	 * Changing a vote requires deleting old votes and inserting new ones.
	 *
	 * @throws \LogicException if attempting to update
	 */
	protected function _preSave()
	{
		if ($this->isUpdate())
		{
			throw new \LogicException("Ranked votes cannot be updated. Delete and re-insert to change votes.");
		}
	}

	/**
	 * Define entity structure
	 *
	 * @param Structure $structure
	 * @return Structure
	 */
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_poll_ranked_vote';
		$structure->shortName = 'Alebarda\RankedPoll:RankedVote';
		$structure->primaryKey = 'ranked_vote_id';

		$structure->columns = [
			'ranked_vote_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'poll_id' => ['type' => self::UINT, 'required' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'poll_response_id' => ['type' => self::UINT, 'required' => true],
			'rank_position' => ['type' => self::UINT, 'required' => true, 'min' => 1],
			'vote_date' => ['type' => self::UINT, 'default' => \XF::$time],
		];

		$structure->relations = [
			'Poll' => [
				'entity' => 'XF:Poll',
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
			'Response' => [
				'entity' => 'XF:PollResponse',
				'type' => self::TO_ONE,
				'conditions' => 'poll_response_id',
				'primary' => true
			],
		];

		return $structure;
	}
}
