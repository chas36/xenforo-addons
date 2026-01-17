<?php

namespace Alebarda\RankedPoll;

use XF\Template\Templater;

/**
 * Code Event Listeners for Ranked Poll
 */
class Listener
{
	/**
	 * Listen to template pre-render to switch poll_block to ranked version
	 *
	 * Event: templater_template_pre_render
	 * Hint: public:poll_block
	 *
	 * @param Templater $templater
	 * @param string $type Template type (e.g., 'public')
	 * @param string $template Template name (e.g., 'poll_block')
	 * @param array $params Template parameters
	 */
	public static function templaterTemplatePreRender(
		Templater $templater,
		&$type,
		&$template,
		array &$params
	)
	{
		// Only process poll_block template
		if ($template !== 'poll_block')
		{
			return;
		}

		// Check if we have a poll in params
		if (empty($params['poll']))
		{
			return;
		}

		$poll = $params['poll'];

		// Check if poll type is ranked
		// Use direct property access to avoid calling methods
		if (isset($poll->poll_type) && $poll->poll_type === 'ranked')
		{
			// Switch to ranked poll template
			$template = 'poll_block_ranked';

			// Add user's ranked votes to params if user is logged in
			$visitor = \XF::visitor();
			if ($visitor->user_id && method_exists($poll, 'getUserRankedVotes'))
			{
				$params['userRankedVotes'] = $poll->getUserRankedVotes($visitor->user_id);
			}
			else
			{
				$params['userRankedVotes'] = [];
			}
		}
	}

	/**
	 * Listen to macro pre-render to add ranked checkbox to poll form
	 *
	 * Event: templater_macro_pre_render
	 * Hint: public:poll_macros:add_edit_inputs
	 *
	 * @param Templater $templater
	 * @param string $type
	 * @param string $template
	 * @param string $name Macro name
	 * @param array $arguments
	 * @param array $globalVars
	 */
	public static function templaterMacroPreRender(
		Templater $templater,
		&$type,
		&$template,
		&$name,
		array &$arguments,
		array &$globalVars
	)
	{
		// Only process poll_macros:add_edit_inputs
		if ($template !== 'poll_macros' || $name !== 'add_edit_inputs')
		{
			return;
		}

		// Add flag to indicate we should show ranked option
		// This will be used in template modification (if we add one later)
		$arguments['showRankedOption'] = true;
	}

	/**
	 * Listen to entity_pre_save event for Poll
	 * Intercept poll creation to set ranked voting options
	 *
	 * Event: entity_pre_save
	 * Hint: XF:Poll
	 *
	 * @param \XF\Mvc\Entity\Entity $entity
	 */
	public static function pollEntityPreSave(\XF\Mvc\Entity\Entity $entity)
	{
		// Only handle Poll entities
		if (!($entity instanceof \XF\Entity\Poll))
		{
			return;
		}

		/** @var \XF\Entity\Poll $poll */
		$poll = $entity;

		// Check if this is a new poll being created
		if (!$poll->exists())
		{
			// Get request data
			$request = \XF::app()->request();
			$pollData = $request->filter('poll', 'array');

			// Check if ranked voting is enabled
			if (!empty($pollData['enable_ranked_voting']))
			{
				// Set poll type to ranked
				$poll->poll_type = 'ranked';
			}
		}
	}

	/**
	 * Listen to entity_post_save event for Poll
	 * Save ranked poll metadata after poll is created
	 *
	 * Event: entity_post_save
	 * Hint: XF:Poll
	 *
	 * @param \XF\Mvc\Entity\Entity $entity
	 */
	public static function pollEntityPostSave(\XF\Mvc\Entity\Entity $entity)
	{
		// Only handle Poll entities
		if (!($entity instanceof \XF\Entity\Poll))
		{
			return;
		}

		/** @var \Alebarda\RankedPoll\XF\Entity\Poll $poll */
		$poll = $entity;

		// Check if this is a ranked poll and it's being inserted (not updated)
		if ($poll->poll_type === 'ranked' && $poll->isInsert())
		{
			// Get request data for additional settings
			$request = \XF::app()->request();
			$pollData = $request->filter('poll', 'array');

			// Get visibility setting
			$visibility = $pollData['ranked_results_visibility'] ?? 'after_close';
			if (!in_array($visibility, ['realtime', 'after_close']))
			{
				$visibility = 'after_close';
			}

			// Get allowed user groups (default: all registered users)
			$allowedGroups = $pollData['allowed_user_groups'] ?? [2]; // 2 = Registered

			// Get voter list visibility
			$showVoterList = !empty($pollData['show_voter_list']) ? 1 : 0;

			// Insert metadata
			$db = \XF::db();
			$db->insert('xf_alebarda_ranked_poll_metadata', [
				'poll_id' => $poll->poll_id,
				'is_ranked' => 1,
				'results_visibility' => $visibility,
				'allowed_user_groups' => json_encode($allowedGroups),
				'open_date' => null,
				'close_date' => $poll->close_date,
				'show_voter_list' => $showVoterList
			], false, 'poll_id = VALUES(poll_id)');
		}
	}
}
