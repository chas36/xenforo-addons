<?php

namespace Alebarda\RankedPoll\XF\Repository;

use XF\Entity\Poll;
use XF\Entity\User;

/**
 * Extends \XF\Repository\PollRepository
 *
 * Intercepts poll voting to handle ranked-choice voting
 */
class PollRepository extends XFCP_PollRepository
{
	/**
	 * Override vote handling for ranked polls
	 *
	 * @param Poll $poll
	 * @param array|int $votes For ranked polls: [response_id => rank], for standard: [response_id, ...]
	 * @param User|null $voter
	 * @return bool
	 */
	public function voteOnPoll(Poll $poll, $votes, ?User $voter = null)
	{
		// Check if this is a ranked poll
		if ($poll->isRankedPoll())
		{
			// Use our custom ranked voting logic
			return $this->voteOnRankedPoll($poll, $votes, $voter);
		}

		// For standard polls, use original XenForo logic
		return parent::voteOnPoll($poll, $votes, $voter);
	}

	/**
	 * Handle ranked-choice voting
	 *
	 * @param Poll $poll
	 * @param array $rankings [response_id => rank_position]
	 * @param User|null $voter
	 * @return bool
	 * @throws \XF\PrintableException
	 */
	protected function voteOnRankedPoll(Poll $poll, array $rankings, ?User $voter = null)
	{
		$voter = $voter ?: \XF::visitor();

		if (!$voter->user_id)
		{
			throw new \XF\PrintableException(\XF::phrase('you_must_be_logged_in'));
		}

		// Validate voting permission
		$error = null;
		if (!$poll->canVote($error))
		{
			throw new \XF\PrintableException($error ?: \XF::phrase('you_cannot_vote_on_this_poll'));
		}

		// Filter out non-ranked items (empty or zero values)
		$rankings = array_filter($rankings, function($rank) {
			return is_numeric($rank) && $rank > 0;
		});

		if (empty($rankings))
		{
			throw new \XF\PrintableException(\XF::phrase('alebarda_rankedpoll_must_rank_at_least_one'));
		}

		// Check for duplicate ranks
		$uniqueRanks = array_unique(array_values($rankings));
		if (count($uniqueRanks) !== count($rankings))
		{
			throw new \XF\PrintableException(\XF::phrase('alebarda_rankedpoll_duplicate_rank'));
		}

		// Validate response IDs exist
		$validResponseIds = array_keys($poll->responses);
		foreach (array_keys($rankings) as $responseId)
		{
			if (!in_array($responseId, $validResponseIds))
			{
				throw new \XF\PrintableException(\XF::phrase('invalid_poll_response'));
			}
		}

		$db = $this->db();
		$db->beginTransaction();

		try
		{
			// Check if user has voted before
			$hasVotedBefore = $db->fetchOne("
				SELECT 1 FROM xf_poll_vote
				WHERE poll_id = ? AND user_id = ?
			", [$poll->poll_id, $voter->user_id]);

			// Delete existing ranked votes if user is changing vote
			$db->delete('xf_poll_ranked_vote',
				'poll_id = ? AND user_id = ?',
				[$poll->poll_id, $voter->user_id]
			);

			// Insert new ranked votes
			foreach ($rankings as $responseId => $rank)
			{
				$db->insert('xf_poll_ranked_vote', [
					'poll_id' => $poll->poll_id,
					'user_id' => $voter->user_id,
					'poll_response_id' => $responseId,
					'rank_position' => $rank,
					'vote_date' => \XF::$time
				]);
			}

			// Mark in standard vote table (for voter_count tracking)
			if (!$hasVotedBefore)
			{
				// Insert marker vote (response_id = 0 indicates ranked vote)
				$db->insert('xf_poll_vote', [
					'poll_id' => $poll->poll_id,
					'user_id' => $voter->user_id,
					'poll_response_id' => 0, // Special marker for ranked votes
					'vote_date' => \XF::$time
				]);

				// Increment voter count
				$poll->voter_count++;
				$poll->saveIfChanged();
			}
			else
			{
				// Update vote date for existing marker
				$db->update('xf_poll_vote', [
					'vote_date' => \XF::$time
				], 'poll_id = ? AND user_id = ?', [$poll->poll_id, $voter->user_id]);
			}

			// Invalidate cached results
			$this->invalidateRankedCache($poll);

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
	 * Invalidate cached Schulze results
	 *
	 * @param Poll $poll
	 */
	protected function invalidateRankedCache(Poll $poll)
	{
		if ($poll->schulze_winner_cache || $poll->schulze_matrix_cache)
		{
			$poll->schulze_winner_cache = null;
			$poll->schulze_matrix_cache = null;
			$poll->saveIfChanged();
		}
	}

	/**
	 * Get all ranked votes for a poll
	 *
	 * @param Poll $poll
	 * @return array [user_id => [response_id => rank]]
	 */
	public function getRankedVotes(Poll $poll)
	{
		if (!$poll->isRankedPoll())
		{
			return [];
		}

		$votes = $this->db()->fetchAll("
			SELECT user_id, poll_response_id, rank_position
			FROM xf_poll_ranked_vote
			WHERE poll_id = ?
			ORDER BY user_id, rank_position ASC
		", $poll->poll_id);

		// Format as [user_id => [response_id => rank]]
		$formatted = [];
		foreach ($votes as $vote)
		{
			$userId = $vote['user_id'];
			$responseId = $vote['poll_response_id'];
			$rank = $vote['rank_position'];

			if (!isset($formatted[$userId]))
			{
				$formatted[$userId] = [];
			}

			$formatted[$userId][$responseId] = $rank;
		}

		return $formatted;
	}

	/**
	 * Get list of users who voted on a ranked poll
	 *
	 * @param Poll $poll
	 * @param int $limit
	 * @return array
	 */
	public function getRankedVoters(Poll $poll, $limit = 100)
	{
		if (!$poll->isRankedPoll())
		{
			return [];
		}

		return $this->db()->fetchAll("
			SELECT DISTINCT rv.user_id, rv.vote_date, u.username
			FROM xf_poll_ranked_vote rv
			INNER JOIN xf_user u ON u.user_id = rv.user_id
			WHERE rv.poll_id = ?
			ORDER BY rv.vote_date DESC
			LIMIT ?
		", [$poll->poll_id, $limit]);
	}
}
