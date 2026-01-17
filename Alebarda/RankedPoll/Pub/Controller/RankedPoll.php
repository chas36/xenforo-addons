<?php

namespace Alebarda\RankedPoll\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Controller for viewing ranked poll results and voter lists
 */
class RankedPoll extends AbstractController
{
	/**
	 * Display ranked poll results using Schulze method
	 *
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function actionResults(ParameterBag $params)
	{
		$poll = $this->assertViewablePoll($params->poll_id);

		if (!$poll->isRankedPoll())
		{
			return $this->error(\XF::phrase('this_is_not_a_ranked_poll'));
		}

		// Check permission to view results
		$error = null;
		if (!$poll->canViewRankedResults($error))
		{
			return $this->noPermission($error);
		}

		// Get all ranked votes
		/** @var \Alebarda\RankedPoll\XF\Repository\PollRepository $pollRepo */
		$pollRepo = $this->repository('XF:PollRepository');
		$votes = $pollRepo->getRankedVotes($poll);

		// Calculate results using Schulze method
		$results = null;
		$candidateNames = [];
		$voterCount = count($votes);

		if ($voterCount > 0)
		{
			$schulze = new \Alebarda\RankedPoll\Voting\Schulze();
			$candidates = array_keys($poll->responses);
			$results = $schulze->calculateWinner($votes, $candidates);

			// Build candidate names map
			foreach ($poll->responses as $response)
			{
				$candidateNames[$response->poll_response_id] = $response->response;
			}
		}

		$viewParams = [
			'poll' => $poll,
			'results' => $results,
			'candidateNames' => $candidateNames,
			'voterCount' => $voterCount,
			'content' => $poll->Content
		];

		return $this->view('Alebarda\RankedPoll:Results', 'poll_results_ranked', $viewParams);
	}

	/**
	 * Display list of users who voted on this poll
	 *
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function actionVoters(ParameterBag $params)
	{
		$poll = $this->assertViewablePoll($params->poll_id);

		if (!$poll->isRankedPoll())
		{
			return $this->error(\XF::phrase('this_is_not_a_ranked_poll'));
		}

		// Check if voter list is enabled
		$metadata = $poll->getRankedMetadata();
		if (!$metadata || !$metadata['show_voter_list'])
		{
			return $this->noPermission(\XF::phrase('voter_list_not_available'));
		}

		// Get voters
		/** @var \Alebarda\RankedPoll\XF\Repository\PollRepository $pollRepo */
		$pollRepo = $this->repository('XF:PollRepository');
		$voters = $pollRepo->getRankedVoters($poll, 100);

		$viewParams = [
			'poll' => $poll,
			'voters' => $voters,
			'content' => $poll->Content
		];

		return $this->view('Alebarda\RankedPoll:Voters', 'poll_voters_ranked', $viewParams);
	}

	/**
	 * Assert that poll exists and is viewable
	 *
	 * @param int $pollId
	 * @return \Alebarda\RankedPoll\XF\Entity\Poll
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewablePoll($pollId)
	{
		/** @var \Alebarda\RankedPoll\XF\Entity\Poll $poll */
		$poll = $this->em()->find('XF:Poll', $pollId);

		if (!$poll)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_poll_not_found')));
		}

		$content = $poll->Content;
		if (!$content)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_poll_not_found')));
		}

		$error = null;
		if (!$content->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $poll;
	}
}
