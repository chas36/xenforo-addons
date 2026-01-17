<?php

namespace Alebarda\RankedPoll\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\Poll
 *
 * COLUMNS
 * @property string poll_type
 * @property string ranked_results_visibility
 * @property string|null schulze_winner_cache
 * @property string|null schulze_matrix_cache
 */
class Poll extends XFCP_Poll
{
	/**
	 * Check if this is a ranked poll
	 *
	 * @return bool
	 */
	public function isRankedPoll()
	{
		return isset($this->poll_type) && $this->poll_type === 'ranked';
	}

	/**
	 * Check if user can view ranked poll results
	 *
	 * @param string|null $error
	 * @return bool
	 */
	public function canViewRankedResults(&$error = null)
	{
		if (!$this->isRankedPoll())
		{
			return false;
		}

		// Check base permission first
		if (!$this->canViewResults($error))
		{
			return false;
		}

		// Real-time visibility
		if ($this->ranked_results_visibility === 'realtime')
		{
			return true;
		}

		// After close: show if poll is closed OR user has voted
		return $this->isClosed() || $this->hasVoted();
	}

	/**
	 * Get user's ranked votes for this poll
	 *
	 * @param int|null $userId
	 * @return array [response_id => rank_position]
	 */
	public function getUserRankedVotes($userId = null)
	{
		if (!$this->isRankedPoll())
		{
			return [];
		}

		$userId = $userId ?: \XF::visitor()->user_id;
		if (!$userId)
		{
			return [];
		}

		$votes = $this->db()->fetchPairs("
			SELECT poll_response_id, rank_position
			FROM xf_poll_ranked_vote
			WHERE poll_id = ? AND user_id = ?
			ORDER BY rank_position ASC
		", [$this->poll_id, $userId]);

		return $votes;
	}

	/**
	 * Get ranked poll metadata
	 *
	 * @return array|null
	 */
	public function getRankedMetadata()
	{
		if (!$this->isRankedPoll())
		{
			return null;
		}

		// Cache metadata to avoid repeated queries
		if (!isset($this->_rankedMetadata))
		{
			$this->_rankedMetadata = $this->db()->fetchRow("
				SELECT *
				FROM xf_alebarda_ranked_poll_metadata
				WHERE poll_id = ?
			", $this->poll_id);
		}

		return $this->_rankedMetadata ?: null;
	}

	/**
	 * Lifecycle hook: After saving poll
	 * Invalidate cached results when poll closes
	 */
	protected function _postSave()
	{
		parent::_postSave();

		// If ranked poll and close_date changed, invalidate cache
		if ($this->isRankedPoll() && $this->isChanged('close_date'))
		{
			$this->schulze_winner_cache = null;
			$this->schulze_matrix_cache = null;
		}
	}

	/**
	 * Lifecycle hook: After deleting poll
	 * Clean up ranked voting data
	 */
	protected function _postDelete()
	{
		parent::_postDelete();

		if ($this->isRankedPoll())
		{
			// Delete ranked votes
			$this->db()->delete('xf_poll_ranked_vote', 'poll_id = ?', $this->poll_id);

			// Delete metadata
			$this->db()->delete('xf_alebarda_ranked_poll_metadata', 'poll_id = ?', $this->poll_id);
		}
	}

	/**
	 * Extend entity structure with ranked poll fields
	 *
	 * @param Structure $structure
	 * @return Structure
	 */
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['poll_type'] = [
			'type' => self::STR,
			'default' => 'standard',
			'allowedValues' => ['standard', 'ranked']
		];

		$structure->columns['ranked_results_visibility'] = [
			'type' => self::STR,
			'default' => 'after_close',
			'allowedValues' => ['realtime', 'after_close']
		];

		$structure->columns['schulze_winner_cache'] = [
			'type' => self::STR,
			'default' => null,
			'nullable' => true
		];

		$structure->columns['schulze_matrix_cache'] = [
			'type' => self::STR,
			'default' => null,
			'nullable' => true
		];

		return $structure;
	}
}
