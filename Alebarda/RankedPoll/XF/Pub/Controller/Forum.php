<?php

namespace Alebarda\RankedPoll\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

/**
 * Extends \XF\Pub\Controller\Forum
 */
class Forum extends XFCP_Forum
{
	/**
	 * Override addThread to update poll_type AFTER thread creation
	 *
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function actionAddThread(ParameterBag $params)
	{
		$logFile = '/var/www/u0513784/data/www/beta.politsim.ru/internal_data/rankedpoll_debug.log';
		$timestamp = date('Y-m-d H:i:s');

		// Call parent to handle thread creation
		$reply = parent::actionAddThread($params);

		file_put_contents($logFile, "[{$timestamp}] Forum::actionAddThread called\n", FILE_APPEND);

		// Only process POST requests (actual thread creation)
		if (!$this->request->isPost())
		{
			file_put_contents($logFile, "[{$timestamp}] Not POST, returning\n", FILE_APPEND);
			return $reply;
		}

		file_put_contents($logFile, "[{$timestamp}] Is POST request\n", FILE_APPEND);

		// Get poll data from request
		$pollData = $this->filter('poll', 'array');
		file_put_contents($logFile, "[{$timestamp}] Poll data keys: " . implode(', ', array_keys($pollData)) . "\n", FILE_APPEND);

		if (isset($pollData['poll_type'])) {
			file_put_contents($logFile, "[{$timestamp}] poll_type = {$pollData['poll_type']}\n", FILE_APPEND);
		} else {
			file_put_contents($logFile, "[{$timestamp}] poll_type NOT SET\n", FILE_APPEND);
		}

		// If no poll_type specified, nothing to do
		if (empty($pollData['poll_type']) || $pollData['poll_type'] === 'standard')
		{
			file_put_contents($logFile, "[{$timestamp}] poll_type is standard or empty, returning\n", FILE_APPEND);
			return $reply;
		}

		file_put_contents($logFile, "[{$timestamp}] Reply type: " . get_class($reply) . "\n", FILE_APPEND);

		// If reply is a redirect (successful thread creation), update the poll
		if ($reply instanceof \XF\Mvc\Reply\Redirect)
		{
			// Extract thread ID from redirect URL
			$redirectUrl = $reply->getRedirectUrl();
			file_put_contents($logFile, "[{$timestamp}] Redirect URL: {$redirectUrl}\n", FILE_APPEND);

			// Try to find thread from URL pattern
			if (preg_match('#threads/[^/]+\.(\d+)#', $redirectUrl, $match))
			{
				$threadId = $match[1];
				file_put_contents($logFile, "[{$timestamp}] Found thread ID: {$threadId}\n", FILE_APPEND);

				/** @var \XF\Entity\Thread $thread */
				$thread = $this->em()->find('XF:Thread', $threadId);

				if ($thread && $thread->Poll)
				{
					$poll = $thread->Poll;
					file_put_contents($logFile, "[{$timestamp}] Found poll ID: {$poll->poll_id}\n", FILE_APPEND);

					// Update poll directly via DB to bypass validation
					$this->db()->update('xf_poll', [
						'poll_type' => $pollData['poll_type'],
						'ranked_results_visibility' => $pollData['ranked_results_visibility'] ?? 'after_close'
					], 'poll_id = ?', $poll->poll_id);

					file_put_contents($logFile, "[{$timestamp}] Updated poll_type to {$pollData['poll_type']}\n", FILE_APPEND);
				} else {
					file_put_contents($logFile, "[{$timestamp}] Thread or Poll not found\n", FILE_APPEND);
				}
			} else {
				file_put_contents($logFile, "[{$timestamp}] Could not extract thread ID from URL\n", FILE_APPEND);
			}
		}

		return $reply;
	}
}
