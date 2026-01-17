<?php

namespace Alebarda\RankedPoll\XF\Service\Poll;

/**
 * Extends \XF\Service\Poll\CreatorService
 */
class CreatorService extends XFCP_CreatorService
{
	protected $enableRankedVoting = false;

	public function setOptions(array $options)
	{
		// Check if ranked voting is enabled
		if (!empty($options['enable_ranked_voting']))
		{
			$this->enableRankedVoting = true;
			unset($options['enable_ranked_voting']); // Remove from options to avoid errors
		}

		return parent::setOptions($options);
	}

	protected function _save()
	{
		// Set poll type BEFORE saving
		if ($this->enableRankedVoting)
		{
			$this->poll->poll_type = 'ranked';
		}

		return parent::_save();
	}
}
