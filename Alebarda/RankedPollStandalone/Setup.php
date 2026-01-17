<?php

namespace Alebarda\RankedPollStandalone;

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

    /**
     * Создание таблиц при установке аддона
     */
    public function installStep1()
    {
        $sm = $this->schemaManager();

        // Таблица: xf_alebarda_rankedpoll (основная таблица опросов)
        $sm->createTable('xf_alebarda_rankedpoll', function(Create $table)
        {
            $table->addColumn('poll_id', 'int')->autoIncrement();
            $table->addColumn('title', 'varchar', 255);
            $table->addColumn('description', 'text')->nullable();

            // Автор
            $table->addColumn('created_by_user_id', 'int')->setDefault(0);
            $table->addColumn('created_date', 'int')->setDefault(0);

            // Временные рамки
            $table->addColumn('open_date', 'int')->nullable()->comment('NULL = открыт сразу');
            $table->addColumn('close_date', 'int')->nullable()->comment('NULL = без ограничения');

            // Статус
            $table->addColumn('poll_status', 'enum')->values(['draft', 'open', 'closed'])->setDefault('draft');

            // Видимость результатов
            $table->addColumn('results_visibility', 'enum')
                ->values(['realtime', 'after_vote', 'after_close', 'never'])
                ->setDefault('after_close');

            // Контроль доступа
            $table->addColumn('allowed_user_groups', 'text')->nullable()->comment('JSON: [2,3,4] - ID групп');

            // Настройки
            $table->addColumn('show_voter_list', 'tinyint')->setDefault(1)->comment('Показывать список проголосовавших');
            $table->addColumn('allow_vote_change', 'tinyint')->setDefault(1)->comment('Можно изменить голос');
            $table->addColumn('require_all_ranked', 'tinyint')->setDefault(0)->comment('Требовать ранжирование всех вариантов');

            // Статистика
            $table->addColumn('voter_count', 'int')->setDefault(0);
            $table->addColumn('view_count', 'int')->setDefault(0);

            // Кэш результатов
            $table->addColumn('cached_results', 'mediumtext')->nullable()->comment('JSON с результатами Schulze');
            $table->addColumn('results_cache_date', 'int')->nullable();

            $table->addPrimaryKey('poll_id');
            $table->addKey(['poll_status', 'open_date', 'close_date'], 'idx_status_dates');
            $table->addKey('created_by_user_id', 'idx_created_by');
        });

        // Таблица: xf_alebarda_rankedpoll_option (варианты ответов)
        $sm->createTable('xf_alebarda_rankedpoll_option', function(Create $table)
        {
            $table->addColumn('option_id', 'int')->autoIncrement();
            $table->addColumn('poll_id', 'int');
            $table->addColumn('option_text', 'varchar', 500);
            $table->addColumn('option_description', 'text')->nullable();
            $table->addColumn('display_order', 'int')->setDefault(0);

            // Статистика (для quick stats)
            $table->addColumn('times_ranked_first', 'int')->setDefault(0);
            $table->addColumn('times_ranked', 'int')->setDefault(0);

            $table->addPrimaryKey('option_id');
            $table->addKey(['poll_id', 'display_order'], 'idx_poll_order');
        });

        // Таблица: xf_alebarda_rankedpoll_vote (голоса пользователей)
        $sm->createTable('xf_alebarda_rankedpoll_vote', function(Create $table)
        {
            $table->addColumn('vote_id', 'int')->autoIncrement();
            $table->addColumn('poll_id', 'int');
            $table->addColumn('user_id', 'int');
            $table->addColumn('option_id', 'int');
            $table->addColumn('rank_position', 'int')->comment('1 = первое место, 2 = второе, etc.');
            $table->addColumn('vote_date', 'int');

            $table->addPrimaryKey('vote_id');
            $table->addUniqueKey(['poll_id', 'user_id', 'option_id'], 'unique_vote');
            $table->addKey(['poll_id', 'user_id'], 'idx_poll_user');
            $table->addKey(['poll_id', 'option_id', 'rank_position'], 'idx_poll_option_rank');
        });

        // Таблица: xf_alebarda_rankedpoll_voter (список проголосовавших)
        $sm->createTable('xf_alebarda_rankedpoll_voter', function(Create $table)
        {
            $table->addColumn('poll_id', 'int');
            $table->addColumn('user_id', 'int');
            $table->addColumn('vote_date', 'int');

            $table->addPrimaryKey(['poll_id', 'user_id']);
            $table->addKey(['poll_id', 'vote_date'], 'idx_vote_date');
        });
    }

    /**
     * Удаление таблиц при удалении аддона
     */
    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        $sm->dropTable('xf_alebarda_rankedpoll_voter');
        $sm->dropTable('xf_alebarda_rankedpoll_vote');
        $sm->dropTable('xf_alebarda_rankedpoll_option');
        $sm->dropTable('xf_alebarda_rankedpoll');
    }
}
