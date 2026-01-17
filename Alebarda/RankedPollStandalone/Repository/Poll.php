<?php

namespace Alebarda\RankedPollStandalone\Repository;

use XF\Mvc\Entity\Repository;
use Alebarda\RankedPollStandalone\Entity\Poll as PollEntity;

class Poll extends Repository
{
    /**
     * Получить опрос по ID
     */
    public function getPoll($pollId, $with = null)
    {
        return $this->em->find('Alebarda\RankedPollStandalone:Poll', $pollId, $with);
    }

    /**
     * Получить список всех опросов (для админки)
     */
    public function findPollsForList()
    {
        return $this->finder('Alebarda\RankedPollStandalone:Poll')
            ->with('Creator')
            ->order('created_date', 'DESC');
    }

    /**
     * Получить открытые опросы
     */
    public function findOpenPolls()
    {
        $time = \XF::$time;

        return $this->finder('Alebarda\RankedPollStandalone:Poll')
            ->where('poll_status', 'open')
            ->where([
                ['open_date', '=', null],
                ['open_date', '<=', $time]
            ], 'OR')
            ->where([
                ['close_date', '=', null],
                ['close_date', '>', $time]
            ], 'OR')
            ->order('created_date', 'DESC');
    }

    /**
     * Получить все голоса опроса для расчёта результатов
     *
     * @param PollEntity $poll
     * @return array Формат: [user_id => [option_id => rank_position]]
     */
    public function getAllVotes(PollEntity $poll)
    {
        $votes = $this->db()->fetchAll("
            SELECT user_id, option_id, rank_position
            FROM xf_alebarda_rankedpoll_vote
            WHERE poll_id = ?
            ORDER BY user_id, rank_position
        ", $poll->poll_id);

        // Преобразовать в формат для Schulze
        $formattedVotes = [];
        foreach ($votes as $vote) {
            $formattedVotes[$vote['user_id']][$vote['option_id']] = $vote['rank_position'];
        }

        return $formattedVotes;
    }

    /**
     * Получить голоса пользователя
     */
    public function getUserVotes(PollEntity $poll, $userId)
    {
        return $this->db()->fetchPairs("
            SELECT option_id, rank_position
            FROM xf_alebarda_rankedpoll_vote
            WHERE poll_id = ? AND user_id = ?
            ORDER BY rank_position
        ", [$poll->poll_id, $userId]);
    }

    /**
     * Получить список проголосовавших
     */
    public function getVoters(PollEntity $poll, $limit = 50, $offset = 0)
    {
        $voterIds = $this->db()->fetchAllColumn("
            SELECT user_id
            FROM xf_alebarda_rankedpoll_voter
            WHERE poll_id = ?
            ORDER BY vote_date DESC
            LIMIT ? OFFSET ?
        ", [$poll->poll_id, $limit, $offset]);

        if (!$voterIds) {
            return [];
        }

        return $this->em->findByIds('XF:User', $voterIds);
    }

    /**
     * Сохранить голос пользователя
     *
     * @param PollEntity $poll
     * @param int $userId
     * @param array $rankings Формат: [option_id => rank_position]
     * @return bool
     */
    public function castVote(PollEntity $poll, $userId, array $rankings)
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            // Проверить что пользователь может голосовать
            if (!$poll->canVote($error)) {
                throw new \XF\PrintableException($error);
            }

            // Валидация рангов
            $this->validateRankings($poll, $rankings);

            // Удалить старые голоса (если разрешено изменение)
            $hadVotedBefore = $db->fetchOne("
                SELECT 1
                FROM xf_alebarda_rankedpoll_voter
                WHERE poll_id = ? AND user_id = ?
            ", [$poll->poll_id, $userId]);

            if ($hadVotedBefore) {
                $db->delete('xf_alebarda_rankedpoll_vote',
                    'poll_id = ? AND user_id = ?',
                    [$poll->poll_id, $userId]
                );
            }

            // Вставить новые голоса
            $voteDate = \XF::$time;
            foreach ($rankings as $optionId => $rankPosition) {
                if ($rankPosition > 0) { // Пропустить не ранжированные (rank = 0)
                    $db->insert('xf_alebarda_rankedpoll_vote', [
                        'poll_id' => $poll->poll_id,
                        'user_id' => $userId,
                        'option_id' => $optionId,
                        'rank_position' => $rankPosition,
                        'vote_date' => $voteDate
                    ]);
                }
            }

            // Обновить таблицу voters
            if (!$hadVotedBefore) {
                $db->insert('xf_alebarda_rankedpoll_voter', [
                    'poll_id' => $poll->poll_id,
                    'user_id' => $userId,
                    'vote_date' => $voteDate
                ]);

                // Увеличить счётчик голосов
                $poll->voter_count++;
            } else {
                // Обновить дату голоса
                $db->update('xf_alebarda_rankedpoll_voter',
                    ['vote_date' => $voteDate],
                    'poll_id = ? AND user_id = ?',
                    [$poll->poll_id, $userId]
                );
            }

            // Обновить статистику опций
            $this->updateOptionStats($poll);

            // Инвалидировать кэш результатов
            $poll->invalidateResultsCache();
            $poll->save();

            $db->commit();
            return true;

        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Валидация рангов
     */
    protected function validateRankings(PollEntity $poll, array $rankings)
    {
        // Проверить что есть хотя бы один ранг
        $rankedCount = 0;
        foreach ($rankings as $rank) {
            if ($rank > 0) {
                $rankedCount++;
            }
        }

        if ($rankedCount == 0) {
            throw new \XF\PrintableException(\XF::phrase('alebarda_rankedpoll_must_rank_at_least_one'));
        }

        // Если требуется ранжировать все варианты
        if ($poll->require_all_ranked && $rankedCount != $poll->getOptionCount()) {
            throw new \XF\PrintableException(\XF::phrase('alebarda_rankedpoll_must_rank_all_options'));
        }

        // Проверить уникальность рангов
        $usedRanks = [];
        foreach ($rankings as $optionId => $rank) {
            if ($rank > 0) {
                if (isset($usedRanks[$rank])) {
                    throw new \XF\PrintableException(\XF::phrase('alebarda_rankedpoll_duplicate_rank'));
                }
                $usedRanks[$rank] = true;
            }
        }

        // Проверить что option_id существуют
        $validOptionIds = array_keys($poll->Options->toArray());
        foreach ($rankings as $optionId => $rank) {
            if (!in_array($optionId, $validOptionIds)) {
                throw new \XF\PrintableException(\XF::phrase('alebarda_rankedpoll_invalid_option'));
            }
        }
    }

    /**
     * Обновить статистику опций (сколько раз на 1 месте, сколько раз вообще ранжирован)
     */
    protected function updateOptionStats(PollEntity $poll)
    {
        $this->db()->update('xf_alebarda_rankedpoll_option', [
            'times_ranked_first' => 0,
            'times_ranked' => 0
        ], 'poll_id = ?', $poll->poll_id);

        $stats = $this->db()->fetchAll("
            SELECT option_id,
                   SUM(CASE WHEN rank_position = 1 THEN 1 ELSE 0 END) as first_count,
                   COUNT(*) as total_count
            FROM xf_alebarda_rankedpoll_vote
            WHERE poll_id = ?
            GROUP BY option_id
        ", $poll->poll_id);

        foreach ($stats as $stat) {
            $this->db()->update('xf_alebarda_rankedpoll_option', [
                'times_ranked_first' => $stat['first_count'],
                'times_ranked' => $stat['total_count']
            ], 'option_id = ?', $stat['option_id']);
        }
    }

    /**
     * Удалить все голоса пользователя по опросу
     */
    public function removeUserVotes(PollEntity $poll, $userId)
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $hadVotes = $db->fetchOne("
                SELECT 1
                FROM xf_alebarda_rankedpoll_voter
                WHERE poll_id = ? AND user_id = ?
            ", [$poll->poll_id, $userId]);

            if (!$hadVotes) {
                $db->rollback();
                return false;
            }

            $db->delete('xf_alebarda_rankedpoll_vote',
                'poll_id = ? AND user_id = ?',
                [$poll->poll_id, $userId]
            );

            $db->delete('xf_alebarda_rankedpoll_voter',
                'poll_id = ? AND user_id = ?',
                [$poll->poll_id, $userId]
            );

            $this->updateOptionStats($poll);

            $poll->voter_count = (int)$db->fetchOne("
                SELECT COUNT(*)
                FROM xf_alebarda_rankedpoll_voter
                WHERE poll_id = ?
            ", $poll->poll_id);

            $poll->invalidateResultsCache();
            $poll->save();

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Подсчитать результаты опроса по методу Шульце
     */
    public function calculateResults(PollEntity $poll, $useCache = true)
    {
        // Проверить кэш
        if ($useCache) {
            $cached = $poll->getCachedResults();
            if ($cached !== null) {
                return $cached;
            }
        }

        // Получить все голоса
        $votes = $this->getAllVotes($poll);

        if (empty($votes)) {
            return [
                'winner_id' => null,
                'ranking' => [],
                'pairwise_matrix' => [],
                'strongest_paths' => []
            ];
        }

        // Получить ID опций
        $optionIds = array_keys($poll->Options->toArray());

        // Запустить алгоритм Шульце
        /** @var \Alebarda\RankedPollStandalone\Voting\Schulze $schulze */
        $schulze = new \Alebarda\RankedPollStandalone\Voting\Schulze();
        $results = $schulze->calculateWinner($votes, $optionIds);

        // Сохранить в кэш
        $poll->setCachedResults($results);
        $poll->save();

        return $results;
    }

    /**
     * Закрыть опрос
     */
    public function closePoll(PollEntity $poll)
    {
        $poll->poll_status = 'closed';
        $poll->save();

        // Пересчитать результаты для кэша
        $this->calculateResults($poll, false);

        return true;
    }

    /**
     * Открыть опрос
     */
    public function openPoll(PollEntity $poll)
    {
        $poll->poll_status = 'open';
        $poll->save();

        return true;
    }
}
