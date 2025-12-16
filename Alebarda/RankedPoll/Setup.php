<?php

namespace Alebarda\RankedPoll;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	// ##################### INSTALLATION #####################

	public function installStep1()
	{
		$sm = $this->schemaManager();

		// Extend xf_poll table with ranked poll fields
		$sm->alterTable('xf_poll', function(Alter $table)
		{
			$table->addColumn('poll_type', 'enum')
				->values(['standard', 'ranked'])
				->setDefault('standard')
				->after('close_date');

			$table->addColumn('ranked_results_visibility', 'enum')
				->values(['realtime', 'after_close'])
				->setDefault('after_close')
				->after('poll_type');

			$table->addColumn('schulze_winner_cache', 'text')
				->nullable(true)
				->after('ranked_results_visibility');

			$table->addColumn('schulze_matrix_cache', 'mediumtext')
				->nullable(true)
				->after('schulze_winner_cache');
		});
	}

	public function installStep2()
	{
		$sm = $this->schemaManager();

		// Create xf_poll_ranked_vote table
		$sm->createTable('xf_poll_ranked_vote', function(Create $table)
		{
			$table->addColumn('ranked_vote_id', 'int')->autoIncrement();
			$table->addColumn('poll_id', 'int')->unsigned();
			$table->addColumn('user_id', 'int')->unsigned();
			$table->addColumn('poll_response_id', 'int')->unsigned();
			$table->addColumn('rank_position', 'tinyint')->unsigned();
			$table->addColumn('vote_date', 'int')->unsigned();

			$table->addPrimaryKey('ranked_vote_id');
			$table->addUniqueKey(['poll_id', 'user_id', 'poll_response_id'], 'user_response');
			$table->addKey(['poll_id', 'user_id'], 'poll_user');
			$table->addKey('poll_response_id', 'response_id');
		});
	}

	// ##################### UPGRADE #####################

	public function upgrade1000010Step1()
	{
		// Future upgrade step placeholder
		// Example: Add index optimization, new features, etc.
	}

	// ##################### UNINSTALLATION #####################

	public function uninstallStep1()
	{
		$sm = $this->schemaManager();

		// Drop ranked vote table
		$sm->dropTable('xf_poll_ranked_vote');
	}

	public function uninstallStep2()
	{
		$sm = $this->schemaManager();

		// Remove columns from xf_poll
		$sm->alterTable('xf_poll', function(Alter $table)
		{
			$table->dropColumns([
				'poll_type',
				'ranked_results_visibility',
				'schulze_winner_cache',
				'schulze_matrix_cache'
			]);
		});
	}
}
