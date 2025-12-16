<?php

namespace Alebarda\RankedPoll\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

/**
 * Extends \XF\Pub\Controller\Poll
 */
class Poll extends XFCP_Poll
{
	/**
	 * Override vote action to handle both standard and ranked polls
	 *
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function actionVote(ParameterBag $params)
	{
		$poll = $this->assertViewablePoll($params->poll_id);

		if ($poll->isRankedPoll())
		{
			return $this->actionVoteRanked($params);
		}

		// Standard poll - use parent implementation
		return parent::actionVote($params);
	}

	/**
	 * Handle ranked poll voting
	 *
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function actionVoteRanked(ParameterBag $params)
	{
		$poll = $this->assertViewablePoll($params->poll_id);

		if (!$poll->canVote($error))
		{
			return $this->noPermission($error);
		}

		$this->assertPostOnly();

		// Get rankings from request
		// Format can be either:
		// - rankings[response_id] = rank (from dropdowns)
		// - ranked_responses[] = response_id (from drag-and-drop, in order)
		$rankings = $this->filter('rankings', 'array');
		$rankedResponses = $this->filter('ranked_responses', 'array-uint');

		// Prefer ranked_responses if provided (drag-and-drop)
		if (!empty($rankedResponses))
		{
			$rankings = $rankedResponses; // Already in order, will be normalized in repository
		}

		try
		{
			/** @var \Alebarda\RankedPoll\XF\Repository\PollRepository $pollRepo */
			$pollRepo = $this->repository('XF:Poll');
			$pollRepo->voteOnRankedPoll($poll, $rankings);

			return $this->redirect($this->buildLink('polls', $poll));
		}
		catch (\XF\PrintableException $e)
		{
			return $this->error($e->getMessage());
		}
	}

	/**
	 * View poll results (extended for ranked polls)
	 *
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function actionIndex(ParameterBag $params)
	{
		$reply = parent::actionIndex($params);

		if ($reply instanceof \XF\Mvc\Reply\View)
		{
			$poll = $reply->getParam('poll');

			if ($poll && $poll->isRankedPoll())
			{
				// Add Schulze results if user can view them
				if ($poll->canViewRankedResults())
				{
					/** @var \Alebarda\RankedPoll\XF\Repository\PollRepository $pollRepo */
					$pollRepo = $this->repository('XF:Poll');
					$schulzeResults = $pollRepo->getFullSchulzeResults($poll);

					$reply->setParam('schulzeResults', $schulzeResults);
					$reply->setParam('isRankedPoll', true);
				}

				// Add user's current votes
				$userRankedVotes = $poll->getUserRankedVotes();
				$reply->setParam('userRankedVotes', $userRankedVotes);
			}
		}

		return $reply;
	}

	/**
	 * Override edit action to save poll_type
	 *
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function actionEdit(ParameterBag $params)
	{
		$reply = parent::actionEdit($params);

		// If POST request (saving), update poll_type via DB
		if ($this->request->isPost() && $reply instanceof \XF\Mvc\Reply\Redirect)
		{
			$pollId = $params->poll_id;
			$pollInput = $this->filter('poll', 'array');

			if (isset($pollInput['poll_type']))
			{
				$this->db()->update('xf_poll', [
					'poll_type' => $pollInput['poll_type'],
					'ranked_results_visibility' => $pollInput['ranked_results_visibility'] ?? 'after_close'
				], 'poll_id = ?', $pollId);
			}
		}

		return $reply;
	}

	/**
	 * Helper to assert viewable poll
	 *
	 * @param int $pollId
	 * @param array $extraWith
	 * @return \Alebarda\RankedPoll\XF\Entity\Poll
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewablePoll($pollId, array $extraWith = [])
	{
		$poll = parent::assertViewablePoll($pollId, $extraWith);

		return $poll;
	}
}
