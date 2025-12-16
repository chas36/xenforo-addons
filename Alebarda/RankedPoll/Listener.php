<?php

namespace Alebarda\RankedPoll;

use XF\Mvc\Entity\Entity;

class Listener
{
	/**
	 * Listen to entity_pre_save to capture poll_type and ranked_results_visibility from input
	 *
	 * @param Entity $entity
	 */
	public static function pollEntityPreSave(Entity $entity)
	{
		// Debug: Log to file instead of XF error log
		$logFile = '/var/www/u0513784/data/www/beta.politsim.ru/internal_data/rankedpoll_debug.log';
		$timestamp = date('Y-m-d H:i:s');

		file_put_contents($logFile, "[{$timestamp}] Listener called for: " . get_class($entity) . "\n", FILE_APPEND);

		if (!($entity instanceof \XF\Entity\Poll))
		{
			return;
		}

		file_put_contents($logFile, "[{$timestamp}] Entity is Poll! Proceeding...\n", FILE_APPEND);

		/** @var \XF\Entity\Poll $poll */
		$poll = $entity;

		$pollType = null;
		$rankedVisibility = null;

		// Method 1: Try session (set by Forum controller)
		$session = \XF::session();
		if ($session)
		{
			$pollType = $session->get('_pollTypeForSave');
			$rankedVisibility = $session->get('_pollRankedVisibilityForSave');

			// Clear from session after reading
			if ($pollType)
			{
				$session->remove('_pollTypeForSave');
				$session->remove('_pollRankedVisibilityForSave');
			}
		}

		// Method 2: Try Request
		if (!$pollType)
		{
			try
			{
				$request = \XF::app()->request();
				if ($request && $request->exists('poll'))
				{
					$pollInput = $request->filter('poll', 'array');
					$pollType = $pollInput['poll_type'] ?? null;
					$rankedVisibility = $pollInput['ranked_results_visibility'] ?? null;
				}
			}
			catch (\Exception $e)
			{
				// Request not available
			}
		}

		// Method 3: Fallback to $_POST
		if (!$pollType && !empty($_POST['poll']))
		{
			$pollType = $_POST['poll']['poll_type'] ?? null;
			$rankedVisibility = $_POST['poll']['ranked_results_visibility'] ?? null;
		}

		// Log what we found
		file_put_contents($logFile, "[{$timestamp}] Found poll_type: " . ($pollType ?: 'null') . "\n", FILE_APPEND);
		file_put_contents($logFile, "[{$timestamp}] Found ranked_visibility: " . ($rankedVisibility ?: 'null') . "\n", FILE_APPEND);

		// Apply values if found
		if ($pollType)
		{
			$poll->poll_type = $pollType;
			file_put_contents($logFile, "[{$timestamp}] Set poll_type to {$pollType} for poll_id " . ($poll->poll_id ?? 'new') . "\n", FILE_APPEND);
		}

		if ($rankedVisibility)
		{
			$poll->ranked_results_visibility = $rankedVisibility;
			file_put_contents($logFile, "[{$timestamp}] Set ranked_results_visibility to {$rankedVisibility}\n", FILE_APPEND);
		}
	}
}
