<?php

namespace Alebarda\RankedPoll\XF\Service\Poll;

/**
 * Extends \XF\Service\Poll\Creator
 */
class Creator extends XFCP_Creator
{
	/**
	 * Override setupPollData to capture ranked poll type
	 *
	 * @param array $options
	 */
	public function setupPollData(array $options)
	{
		parent::setupPollData($options);

		$poll = $this->poll;

		// Capture poll_type
		if (isset($options['poll_type']))
		{
			$poll->poll_type = $options['poll_type'];
		}

		// Capture ranked_results_visibility
		if (isset($options['ranked_results_visibility']))
		{
			$poll->ranked_results_visibility = $options['ranked_results_visibility'];
		}
	}
}
