<?php

namespace Alebarda\RankedPoll\XF\Service\Thread;

/**
 * Extends \XF\Service\Thread\Creator
 */
class Creator extends XFCP_Creator
{
	public function __construct(\XF\Entity\Forum $forum, \XF\Entity\User $creator = null)
	{
		$logFile = '/var/www/u0513784/data/www/beta.politsim.ru/internal_data/rankedpoll_debug.log';
		$timestamp = date('Y-m-d H:i:s');
		file_put_contents($logFile, "[{$timestamp}] Thread\\Creator constructed! Our extension is active!\n", FILE_APPEND);

		parent::__construct($forum, $creator);
	}

	/**
	 * Override setupPoll to capture ranked poll data
	 *
	 * @param array $input
	 * @return \XF\Entity\Poll|null
	 */
	public function setupPoll(array $input)
	{
		$logFile = '/var/www/u0513784/data/www/beta.politsim.ru/internal_data/rankedpoll_debug.log';
		$timestamp = date('Y-m-d H:i:s');

		file_put_contents($logFile, "[{$timestamp}] Thread\\Creator::setupPoll called\n", FILE_APPEND);
		file_put_contents($logFile, "[{$timestamp}] Input keys: " . implode(', ', array_keys($input)) . "\n", FILE_APPEND);

		if (isset($input['poll_type'])) {
			file_put_contents($logFile, "[{$timestamp}] poll_type in input: {$input['poll_type']}\n", FILE_APPEND);
		} else {
			file_put_contents($logFile, "[{$timestamp}] poll_type NOT in input\n", FILE_APPEND);
		}

		// Call parent to setup poll normally
		$poll = parent::setupPoll($input);

		if ($poll)
		{
			file_put_contents($logFile, "[{$timestamp}] Poll created, poll_id: " . ($poll->poll_id ?? 'new') . "\n", FILE_APPEND);

			// Capture poll_type and ranked_results_visibility from input
			if (isset($input['poll_type']))
			{
				$poll->poll_type = $input['poll_type'];
				file_put_contents($logFile, "[{$timestamp}] Set poll_type to: {$input['poll_type']}\n", FILE_APPEND);
			}

			if (isset($input['ranked_results_visibility']))
			{
				$poll->ranked_results_visibility = $input['ranked_results_visibility'];
				file_put_contents($logFile, "[{$timestamp}] Set ranked_results_visibility to: {$input['ranked_results_visibility']}\n", FILE_APPEND);
			}
		}

		return $poll;
	}
}
