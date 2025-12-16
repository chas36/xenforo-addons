<?php

namespace Alebarda\RankedPoll\XF\Repository;

use Alebarda\RankedPoll\Voting\SchulzeCalculator;
use XF\Entity\Poll;
use XF\Entity\User;

/**
 * Extends \XF\Repository\PollRepository
 */
class PollRepository extends XFCP_PollRepository
{
	/**
	 * Maximum number of options allowed in a ranked poll
	 */
	const MAX_RANKED_OPTIONS = 50;

	/**
	 * Override setupPollForSave to capture poll_type and ranked_results_visibility
	 *
	 * @param Poll $poll
	 * @param array $options
	 * @param bool $isInsert
	 * @return void
	 */
	public function setupPollForSave(Poll $poll, array $options, $isInsert = true)
	{
		// Call parent first
		parent::setupPollForSave($poll, $options, $isInsert);

		// Capture ranked poll options
		if (isset($options['poll_type']))
		{
			$poll->poll_type = $options['poll_type'];
		}

		if (isset($options['ranked_results_visibility']))
		{
			$poll->ranked_results_visibility = $options['ranked_results_visibility'];
		}
	}

	/**
	 * Override voteOnPoll to handle both standard and ranked polls
	 *
	 * @param Poll $poll
	 * @param mixed $votes For standard: array of response IDs. For ranked: array of [response_id => rank]
	 * @param User|null $voter
	 * @return bool
	 */
	public function voteOnPoll(Poll $poll, $votes, ?User $voter = null)
	{
		if ($poll->isRankedPoll())
		{
			return $this->voteOnRankedPoll($poll, $votes, $voter);
		}

		// Standard poll - use parent implementation
		return parent::voteOnPoll($poll, $votes, $voter);
	}

	/**
	 * Handle voting on a ranked poll
	 *
	 * @param Poll $poll
	 * @param array $rankings [response_id => rank_position] or [response_id, response_id, ...] in rank order
	 * @param User|null $voter
	 * @return bool
	 * @throws \XF\PrintableException
	 */
	public function voteOnRankedPoll(Poll $poll, array $rankings, ?User $voter = null)
	{
		$voter = $voter ?: \XF::visitor();
		$db = $this->db();

		// Normalize rankings format
		$rankings = $this->normalizeRankings($rankings);

		// Validate rankings
		$validatedRankings = $this->validateRankings($poll, $rankings);
		if (empty($validatedRankings))
		{
			throw new \XF\PrintableException(\XF::phrase('alebarda_rankedpoll_must_rank_at_least_one'));
		}

		$db->beginTransaction();

		try
		{
			// Optimistic lock on poll
			$rawPoll = $db->fetchRow("
				SELECT *
				FROM xf_poll
				WHERE poll_id = ?
				FOR UPDATE
			", $poll->poll_id);

			if (!$rawPoll)
			{
				throw new \XF\PrintableException(\XF::phrase('requested_poll_not_found'));
			}

			// Delete existing ranked votes for this user
			$previousVotes = $db->delete(
				'xf_poll_ranked_vote',
				'poll_id = ? AND user_id = ?',
				[$poll->poll_id, $voter->user_id]
			);

			$newVoter = ($previousVotes == 0);

			// Insert new rankings
			foreach ($validatedRankings as $responseId => $rank)
			{
				$db->insert('xf_poll_ranked_vote', [
					'poll_id' => $poll->poll_id,
					'user_id' => $voter->user_id,
					'poll_response_id' => $responseId,
					'rank_position' => $rank,
					'vote_date' => \XF::$time,
				], false, false, 'IGNORE');
			}

			// Invalidate Schulze cache
			$db->update('xf_poll', [
				'schulze_winner_cache' => null,
				'schulze_matrix_cache' => null,
			], 'poll_id = ?', $poll->poll_id);

			// Update voter count if new voter
			if ($newVoter)
			{
				$poll->voter_count = $rawPoll['voter_count'] + 1;
				$poll->saveIfChanged();
			}

			// Recalculate Schulze results if real-time visibility
			if ($poll->ranked_results_visibility === 'realtime')
			{
				$this->calculateAndCacheSchulzeResults($poll);
			}

			$db->commit();

			return true;
		}
		catch (\Exception $e)
		{
			$db->rollback();
			throw $e;
		}
	}

	/**
	 * Normalize rankings to [response_id => rank_position] format
	 *
	 * Accepts either:
	 * - Associative array: [response_id => rank]
	 * - Sequential array: [response_id, response_id, ...] where position = rank
	 *
	 * @param array $rankings
	 * @return array
	 */
	protected function normalizeRankings(array $rankings)
	{
		// Already in correct format
		if ($this->isAssociativeArray($rankings))
		{
			return $rankings;
		}

		// Sequential array - convert to [id => position]
		$normalized = [];
		$position = 1;
		foreach ($rankings as $responseId)
		{
			if ($responseId !== null && $responseId !== '')
			{
				$normalized[$responseId] = $position++;
			}
		}

		return $normalized;
	}

	/**
	 * Check if array is associative
	 *
	 * @param array $arr
	 * @return bool
	 */
	protected function isAssociativeArray(array $arr)
	{
		if (empty($arr)) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	/**
	 * Validate rankings against poll responses
	 *
	 * @param Poll $poll
	 * @param array $rankings [response_id => rank_position]
	 * @return array Validated rankings
	 * @throws \XF\PrintableException
	 */
	protected function validateRankings(Poll $poll, array $rankings)
	{
		$validated = [];
		$responses = $poll->Responses;
		$usedRanks = [];

		foreach ($rankings as $responseId => $rank)
		{
			// Validate response exists
			if (!isset($responses[$responseId]))
			{
				continue; // Skip invalid response IDs
			}

			// Validate rank is positive integer
			$rank = intval($rank);
			if ($rank < 1)
			{
				continue; // Skip invalid ranks
			}

			// Check for duplicate ranks
			if (isset($usedRanks[$rank]))
			{
				throw new \XF\PrintableException(
					\XF::phrase('alebarda_rankedpoll_duplicate_rank', ['rank' => $rank])
				);
			}

			$validated[$responseId] = $rank;
			$usedRanks[$rank] = true;
		}

		return $validated;
	}

	/**
	 * Calculate Schulze results and cache them in the poll
	 *
	 * @param Poll $poll
	 * @return array ['winner' => int|null, 'strongestPaths' => array, 'preferences' => array, 'ranking' => array]
	 */
	public function calculateAndCacheSchulzeResults(Poll $poll)
	{
		$calculator = new SchulzeCalculator();

		// Fetch all ranked votes
		$voteRows = $this->db()->fetchAll("
			SELECT user_id, poll_response_id, rank_position
			FROM xf_poll_ranked_vote
			WHERE poll_id = ?
			ORDER BY user_id, rank_position ASC
		", $poll->poll_id);

		// Transform to format: [user_id => [response_id => rank]]
		$rankedVotes = [];
		foreach ($voteRows as $vote)
		{
			$rankedVotes[$vote['user_id']][$vote['poll_response_id']] = $vote['rank_position'];
		}

		$allResponseIds = array_keys($poll->Responses->toArray());

		// Calculate Schulze results
		$results = $calculator->calculate($rankedVotes, $allResponseIds);

		// Cache results
		$this->db()->update('xf_poll', [
			'schulze_winner_cache' => json_encode($results['winner']),
			'schulze_matrix_cache' => json_encode($results['strongestPaths']),
		], 'poll_id = ?', $poll->poll_id);

		// Update poll entity
		$poll->fastUpdate([
			'schulze_winner_cache' => json_encode($results['winner']),
			'schulze_matrix_cache' => json_encode($results['strongestPaths']),
		]);

		return $results;
	}

	/**
	 * Get full Schulze results for a poll (including non-cached data)
	 *
	 * @param Poll $poll
	 * @return array
	 */
	public function getFullSchulzeResults(Poll $poll)
	{
		$calculator = new SchulzeCalculator();

		// Fetch all ranked votes
		$voteRows = $this->db()->fetchAll("
			SELECT user_id, poll_response_id, rank_position
			FROM xf_poll_ranked_vote
			WHERE poll_id = ?
			ORDER BY user_id, rank_position ASC
		", $poll->poll_id);

		// Transform to format
		$rankedVotes = [];
		foreach ($voteRows as $vote)
		{
			$rankedVotes[$vote['user_id']][$vote['poll_response_id']] = $vote['rank_position'];
		}

		$allResponseIds = array_keys($poll->Responses->toArray());

		// Calculate full results
		return $calculator->calculate($rankedVotes, $allResponseIds);
	}

	/**
	 * Rebuild ranked poll data (similar to rebuildPollData for standard polls)
	 *
	 * @param int $pollId
	 */
	public function rebuildRankedPollData($pollId)
	{
		$poll = $this->em->find('XF:Poll', $pollId);
		if (!$poll || !$poll->isRankedPoll())
		{
			return;
		}

		$db = $this->db();
		$db->beginTransaction();

		try
		{
			// Invalidate cache
			$db->update('xf_poll', [
				'schulze_winner_cache' => null,
				'schulze_matrix_cache' => null,
			], 'poll_id = ?', $pollId);

			// Recalculate voter count
			$voterCount = $db->fetchOne("
				SELECT COUNT(DISTINCT user_id)
				FROM xf_poll_ranked_vote
				WHERE poll_id = ?
			", $pollId);

			$poll->voter_count = $voterCount;
			$poll->save();

			// Recalculate Schulze results
			$this->calculateAndCacheSchulzeResults($poll);

			$db->commit();
		}
		catch (\Exception $e)
		{
			$db->rollback();
			throw $e;
		}
	}

	/**
	 * Validate poll can be ranked
	 *
	 * @param Poll $poll
	 * @param string|null $error
	 * @return bool
	 */
	public function canCreateRankedPoll(Poll $poll, &$error = null)
	{
		$responseCount = $poll->Responses->count();

		if ($responseCount > self::MAX_RANKED_OPTIONS)
		{
			$error = \XF::phrase('alebarda_rankedpoll_too_many_options', [
				'max' => self::MAX_RANKED_OPTIONS
			]);
			return false;
		}

		if ($responseCount < 2)
		{
			$error = \XF::phrase('please_enter_at_least_two_poll_responses');
			return false;
		}

		return true;
	}
}
