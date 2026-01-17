<?php

namespace Alebarda\RankedPoll\XF\Service\Poll;

/**
 * Extends \XF\Service\Poll\Creator
 *
 * Adds support for creating ranked-choice polls
 */
class Creator extends XFCP_Creator
{
	/**
	 * Whether to enable ranked voting for this poll
	 * @var bool
	 */
	protected $enableRankedVoting = false;

	/**
	 * Ranked poll settings
	 * @var array
	 */
	protected $rankedSettings = [
		'results_visibility' => 'after_close',
		'allowed_user_groups' => [],
		'open_date' => null,
		'close_date' => null,
		'show_voter_list' => true
	];

	/**
	 * Enable ranked-choice voting for this poll
	 *
	 * @param array $settings Override default settings
	 * @return $this
	 */
	public function setRankedVoting(array $settings = [])
	{
		$this->enableRankedVoting = true;
		$this->rankedSettings = array_merge($this->rankedSettings, $settings);

		return $this;
	}

	/**
	 * Set ranked poll visibility
	 *
	 * @param string $visibility 'realtime' or 'after_close'
	 * @return $this
	 */
	public function setRankedResultsVisibility($visibility)
	{
		if (in_array($visibility, ['realtime', 'after_close']))
		{
			$this->rankedSettings['results_visibility'] = $visibility;
		}

		return $this;
	}

	/**
	 * Set allowed user groups for voting
	 *
	 * @param array $groupIds Array of user group IDs
	 * @return $this
	 */
	public function setAllowedUserGroups(array $groupIds)
	{
		$this->rankedSettings['allowed_user_groups'] = array_map('intval', $groupIds);

		return $this;
	}

	/**
	 * Set poll open and close dates
	 *
	 * @param int|null $openDate Unix timestamp
	 * @param int|null $closeDate Unix timestamp
	 * @return $this
	 */
	public function setRankedDates($openDate = null, $closeDate = null)
	{
		$this->rankedSettings['open_date'] = $openDate;
		$this->rankedSettings['close_date'] = $closeDate;

		return $this;
	}

	/**
	 * Lifecycle hook: After saving poll
	 * Save ranked poll metadata if enabled
	 */
	protected function _save()
	{
		// Call parent save to create the poll
		$poll = parent::_save();

		// If ranked voting is enabled and poll was created successfully
		if ($this->enableRankedVoting && $poll && $poll->exists())
		{
			$this->saveRankedMetadata($poll);
		}

		return $poll;
	}

	/**
	 * Save ranked poll metadata to database
	 *
	 * @param \XF\Entity\Poll $poll
	 */
	protected function saveRankedMetadata($poll)
	{
		$db = \XF::db();

		// Set poll_type to 'ranked'
		$poll->poll_type = 'ranked';
		$poll->ranked_results_visibility = $this->rankedSettings['results_visibility'];
		$poll->save();

		// Insert metadata
		$db->insert('xf_alebarda_ranked_poll_metadata', [
			'poll_id' => $poll->poll_id,
			'is_ranked' => 1,
			'results_visibility' => $this->rankedSettings['results_visibility'],
			'allowed_user_groups' => json_encode($this->rankedSettings['allowed_user_groups']),
			'open_date' => $this->rankedSettings['open_date'],
			'close_date' => $this->rankedSettings['close_date'],
			'show_voter_list' => $this->rankedSettings['show_voter_list'] ? 1 : 0
		], false, 'poll_id = VALUES(poll_id)');
	}
}
