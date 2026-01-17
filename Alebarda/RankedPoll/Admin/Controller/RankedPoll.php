<?php

namespace Alebarda\RankedPoll\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Admin controller for managing ranked polls
 */
class RankedPoll extends AbstractController
{
	/**
	 * List all polls with conversion options
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function actionIndex()
	{
		// Get all polls
		$polls = \XF::finder('XF:Poll')
			->order('poll_id', 'DESC')
			->fetch();

		$viewParams = [
			'polls' => $polls
		];

		return $this->view('Alebarda\RankedPoll:Poll\List', 'alebarda_rankedpoll_list', $viewParams);
	}

	/**
	 * Convert a standard poll to ranked poll
	 *
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function actionConvert(ParameterBag $params)
	{
		$pollId = $params->poll_id;

		if (!$pollId)
		{
			return $this->error(\XF::phrase('requested_poll_not_found'));
		}

		// Find poll
		$poll = $this->assertPollExists($pollId);

		// Check if already ranked
		if (isset($poll->poll_type) && $poll->poll_type === 'ranked')
		{
			return $this->redirect($this->buildLink('ranked-polls'), \XF::phrase('alebarda_rankedpoll_already_ranked'));
		}

		if ($this->isPost())
		{
			// Convert poll to ranked
			$this->convertPollToRanked($poll);

			return $this->redirect($this->buildLink('ranked-polls'), \XF::phrase('alebarda_rankedpoll_conversion_success'));
		}
		else
		{
			// Show confirmation dialog
			$viewParams = [
				'poll' => $poll
			];

			return $this->view('Alebarda\RankedPoll:Poll\Convert', 'alebarda_rankedpoll_convert', $viewParams);
		}
	}

	/**
	 * Convert poll to ranked type
	 *
	 * @param \XF\Entity\Poll $poll
	 * @return void
	 */
	protected function convertPollToRanked(\XF\Entity\Poll $poll)
	{
		$db = \XF::db();

		// Update poll type via direct SQL
		$db->update('xf_poll', [
			'poll_type' => 'ranked'
		], 'poll_id = ?', $poll->poll_id);

		// Create metadata record
		$db->insert('xf_alebarda_ranked_poll_metadata', [
			'poll_id' => $poll->poll_id,
			'is_ranked' => 1,
			'results_visibility' => 'realtime',
			'allowed_user_groups' => null,
			'open_date' => null,
			'close_date' => $poll->close_date,
			'show_voter_list' => 1
		], false, 'poll_id = VALUES(poll_id)');

		// Clear caches
		\XF::app()->simpleCache()->delete('poll_' . $poll->poll_id);
	}

	/**
	 * Assert poll exists
	 *
	 * @param int $pollId
	 * @param array|string|null $with
	 * @param string|null $phraseKey
	 * @return \XF\Entity\Poll
	 */
	protected function assertPollExists($pollId, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('XF:Poll', $pollId, $with, $phraseKey);
	}
}
