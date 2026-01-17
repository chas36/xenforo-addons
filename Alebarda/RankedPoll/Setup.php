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

	// Installation steps
	public function installStep1()
	{
		$sm = $this->schemaManager();

		// Add columns to xf_poll table
		$sm->alterTable('xf_poll', function(Alter $table)
		{
			$table->addColumn('poll_type', 'enum')->values(['standard', 'ranked'])->setDefault('standard')->after('max_votes');
			$table->addColumn('ranked_results_visibility', 'enum')->values(['realtime', 'after_close'])->setDefault('after_close')->after('poll_type');
			$table->addColumn('schulze_winner_cache', 'mediumtext')->nullable()->after('ranked_results_visibility');
			$table->addColumn('schulze_matrix_cache', 'mediumtext')->nullable()->after('schulze_winner_cache');
		});
	}

	public function installStep2()
	{
		$sm = $this->schemaManager();

		// Create ranked vote table
		$sm->createTable('xf_poll_ranked_vote', function(Create $table)
		{
			$table->addColumn('poll_id', 'int')->unsigned();
			$table->addColumn('user_id', 'int')->unsigned();
			$table->addColumn('poll_response_id', 'int')->unsigned();
			$table->addColumn('rank_position', 'int')->unsigned();
			$table->addColumn('vote_date', 'int')->unsigned()->setDefault(0);
			$table->addPrimaryKey(['poll_id', 'user_id', 'poll_response_id']);
			$table->addKey(['poll_id', 'poll_response_id'], 'poll_response');
			$table->addKey('user_id');
		});
	}

	public function installStep3()
	{
		$sm = $this->schemaManager();

		// Create ranked poll metadata table
		$sm->createTable('xf_alebarda_ranked_poll_metadata', function(Create $table)
		{
			$table->addColumn('poll_id', 'int')->unsigned()->primaryKey();
			$table->addColumn('is_ranked', 'tinyint')->unsigned()->setDefault(1);
			$table->addColumn('results_visibility', 'enum')->values(['realtime', 'after_close'])->setDefault('after_close');
			$table->addColumn('allowed_user_groups', 'text')->nullable();
			$table->addColumn('open_date', 'int')->unsigned()->nullable();
			$table->addColumn('close_date', 'int')->unsigned()->nullable();
			$table->addColumn('show_voter_list', 'tinyint')->unsigned()->setDefault(1);
		});
	}

	// Upgrade to version 1000011 (1.0.0 Alpha 2)
	public function upgrade1000011Step1()
	{
		$sm = $this->schemaManager();

		// Create ranked poll metadata table if it doesn't exist
		if (!$sm->tableExists('xf_alebarda_ranked_poll_metadata'))
		{
			$sm->createTable('xf_alebarda_ranked_poll_metadata', function(Create $table)
			{
				$table->addColumn('poll_id', 'int')->unsigned()->primaryKey();
				$table->addColumn('is_ranked', 'tinyint')->unsigned()->setDefault(1);
				$table->addColumn('results_visibility', 'enum')->values(['realtime', 'after_close'])->setDefault('after_close');
				$table->addColumn('allowed_user_groups', 'text')->nullable();
				$table->addColumn('open_date', 'int')->unsigned()->nullable();
				$table->addColumn('close_date', 'int')->unsigned()->nullable();
				$table->addColumn('show_voter_list', 'tinyint')->unsigned()->setDefault(1);
			});
		}
	}

	// Uninstallation
	public function uninstallStep1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_poll', function(Alter $table)
		{
			$table->dropColumns(['poll_type', 'ranked_results_visibility', 'schulze_winner_cache', 'schulze_matrix_cache']);
		});
	}

	public function uninstallStep2()
	{
		$sm = $this->schemaManager();
		$sm->dropTable('xf_poll_ranked_vote');
	}

	public function uninstallStep3()
	{
		$sm = $this->schemaManager();
		$sm->dropTable('xf_alebarda_ranked_poll_metadata');
	}
}
