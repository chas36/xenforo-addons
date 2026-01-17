<?php

namespace Alebarda\RankedPoll\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Standalone controller for ranked poll voting
 * Does NOT extend standard Poll controller to avoid breaking the forum
 */
class Vote extends AbstractController
{
	/**
	 * Display ranked poll voting interface
	 */
	public function actionIndex(ParameterBag $params)
	{
		$poll = $this->assertViewablePoll($params->poll_id);

		// Verify this is a ranked poll
		if (!$poll->isRankedPoll())
		{
			return $this->error('This is not a ranked poll. Use the standard poll interface.');
		}

		// Get user's existing ranked votes if they voted before
		$visitor = \XF::visitor();
		$userRankedVotes = [];

		if ($visitor->user_id)
		{
			$votes = $this->app->db()->fetchPairs("
				SELECT poll_response_id, rank_position
				FROM xf_poll_ranked_vote
				WHERE poll_id = ? AND user_id = ?
			", [$poll->poll_id, $visitor->user_id]);

			$userRankedVotes = $votes;
		}

		$viewParams = [
			'poll' => $poll,
			'userRankedVotes' => $userRankedVotes
		];

		return $this->view('Alebarda\RankedPoll:Vote', 'poll_block_ranked', $viewParams);
	}

	/**
	 * Handle ranked poll vote submission
	 */
	public function actionVote(ParameterBag $params)
	{
		$poll = $this->assertViewablePoll($params->poll_id);

		// Verify this is a ranked poll
		if (!$poll->isRankedPoll())
		{
			return $this->error(\XF::phrase('requested_poll_not_found'));
		}

		// Check voting permissions
		if (!$poll->canVote($error))
		{
			return $this->noPermission($error);
		}

		$this->assertPostOnly();

		// Get rankings from request (format: rankings[response_id] = rank)
		$rankings = $this->filter('rankings', 'array-uint');

		try
		{
			// Save votes
			$this->saveRankedVotes($poll, $rankings);

			return $this->redirect($this->buildLink('ranked-polls', $poll));
		}
		catch (\XF\PrintableException $e)
		{
			return $this->error($e->getMessage());
		}
	}

	/**
	 * Save ranked votes for a poll
	 */
	protected function saveRankedVotes($poll, array $rankings)
	{
		$visitor = \XF::visitor();
		$db = $this->app->db();

		// Filter out non-ranked items (empty values)
		$rankings = array_filter($rankings, function($rank) {
			return $rank > 0;
		});

		if (empty($rankings))
		{
			throw new \XF\PrintableException('Пожалуйста, проранжируйте хотя бы один вариант.');
		}

		// Check for duplicate ranks
		$uniqueRanks = array_unique(array_values($rankings));
		if (count($uniqueRanks) !== count($rankings))
		{
			throw new \XF\PrintableException('Каждый вариант должен иметь уникальный ранг.');
		}

		$db->beginTransaction();

		// Delete existing votes
		$db->delete('xf_poll_ranked_vote', 'poll_id = ? AND user_id = ?', [$poll->poll_id, $visitor->user_id]);

		// Insert new votes
		foreach ($rankings as $responseId => $rank)
		{
			$db->insert('xf_poll_ranked_vote', [
				'poll_id' => $poll->poll_id,
				'user_id' => $visitor->user_id,
				'poll_response_id' => $responseId,
				'rank_position' => $rank,
				'vote_date' => \XF::$time
			]);
		}

		// Update poll voter count if this is a new vote
		$hasVotedBefore = $db->fetchOne("
			SELECT COUNT(*) FROM xf_poll_vote
			WHERE poll_id = ? AND user_id = ?
		", [$poll->poll_id, $visitor->user_id]);

		if (!$hasVotedBefore)
		{
			// Mark in standard vote table to track that user has voted
			$db->insert('xf_poll_vote', [
				'poll_id' => $poll->poll_id,
				'user_id' => $visitor->user_id,
				'poll_response_id' => 0, // Not used for ranked
				'vote_date' => \XF::$time
			], false, 'vote_date = VALUES(vote_date)');

			// Update voter count
			$db->query("
				UPDATE xf_poll
				SET voter_count = voter_count + 1
				WHERE poll_id = ?
			", $poll->poll_id);
		}

		$db->commit();
	}

	/**
	 * Assert that poll exists and is viewable
	 */
	protected function assertViewablePoll($pollId, array $extraWith = [])
	{
		$visitor = \XF::visitor();

		$poll = \XF::em()->find('XF:Poll', $pollId, $extraWith);
		if (!$poll)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_poll_not_found')));
		}

		$content = $poll->Content;
		if (!$content || !$content->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $poll;
	}
}
