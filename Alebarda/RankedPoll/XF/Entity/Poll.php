<?php

namespace Alebarda\RankedPoll\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\Poll
 *
 * COLUMNS
 * -----------
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
		return $this->poll_type === 'ranked';
	}

	/**
	 * Check if user can view ranked poll results
	 *
	 * Takes into account the ranked_results_visibility setting:
	 * - 'realtime': always visible (if user can view results in general)
	 * - 'after_close': only after poll closes or if user has voted
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
	 * Get cached Schulze results or recalculate if needed
	 *
	 * @return array|null ['winner' => int|null, 'strongestPaths' => array, 'preferences' => array, 'ranking' => array]
	 */
	public function getSchulzeResults()
	{
		if (!$this->isRankedPoll())
		{
			return null;
		}

		// Try to use cache
		if ($this->schulze_winner_cache !== null && $this->schulze_matrix_cache !== null)
		{
			try
			{
				$winner = json_decode($this->schulze_winner_cache, true);
				$matrix = json_decode($this->schulze_matrix_cache, true);

				if ($matrix !== null)
				{
					return [
						'winner' => $winner,
						'strongestPaths' => $matrix,
						'preferences' => [], // Not cached, would need recalculation
						'ranking' => [] // Not cached, would need recalculation
					];
				}
			}
			catch (\Exception $e)
			{
				// Cache corrupted, fall through to recalculation
			}
		}

		// Cache miss or invalid - need to recalculate
		return $this->getRankedPollRepo()->calculateAndCacheSchulzeResults($this);
	}

	/**
	 * Get user's ranked votes for this poll
	 *
	 * @param int|null $userId Defaults to current visitor
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
	 * Get ranked poll repository
	 *
	 * @return \Alebarda\RankedPoll\XF\Repository\PollRepository
	 */
	protected function getRankedPollRepo()
	{
		return $this->repository('XF:Poll');
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
