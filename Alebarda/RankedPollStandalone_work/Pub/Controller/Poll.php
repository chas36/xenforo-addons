<?php

namespace Alebarda\RankedPollStandalone\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Poll extends AbstractController
{
    /**
     * Просмотр опроса / голосование
     */
    public function actionIndex(ParameterBag $params)
    {
        $poll = $this->assertViewablePoll($params->poll_id);

        // Увеличить счётчик просмотров
        $poll->view_count++;
        $poll->saveIfChanged();

        $visitor = \XF::visitor();

        // Получить голоса текущего пользователя (если есть)
        $userVotes = [];
        if ($visitor->user_id) {
            /** @var \Alebarda\RankedPollStandalone\Repository\Poll $pollRepo */
            $pollRepo = $this->repository('Alebarda\RankedPollStandalone:Poll');
            $userVotes = $pollRepo->getUserVotes($poll, $visitor->user_id);
        }

        $rankedOptions = [];
        $unrankedOptions = [];
        foreach ($poll->Options as $option) {
            $rank = $userVotes[$option->option_id] ?? 0;
            if ($rank > 0) {
                $rankedOptions[(int)$rank] = [
                    'option' => $option,
                    'rank' => (int)$rank
                ];
            } else {
                $unrankedOptions[] = $option;
            }
        }
        if ($rankedOptions) {
            ksort($rankedOptions);
            $rankedOptions = array_values($rankedOptions);
        }
        if ($poll->require_all_ranked) {
            foreach ($unrankedOptions as $option) {
                $rankedOptions[] = [
                    'option' => $option,
                    'rank' => 0
                ];
            }
            $unrankedOptions = [];
        }

        $viewParams = [
            'poll' => $poll,
            'userVotes' => $userVotes,
            'rankChoices' => range(1, max(1, count($poll->Options))),
            'rankedOptions' => $rankedOptions,
            'unrankedOptions' => $unrankedOptions,
            'canVote' => $poll->canVote($voteError),
            'voteError' => $voteError ?? null,
            'canViewResults' => $poll->canViewResults($resultsError),
            'resultsError' => $resultsError ?? null,
        ];

        return $this->view('Alebarda\RankedPollStandalone:Poll\View', 'rankedpoll_view', $viewParams);
    }

    /**
     * Обработка голосования (POST)
     */
    public function actionVote(ParameterBag $params)
    {
        $this->assertPostOnly();

        $poll = $this->assertViewablePoll($params->poll_id);
        $visitor = \XF::visitor();

        if (!$visitor->user_id) {
            return $this->noPermission(\XF::phrase('alebarda_rankedpoll_guests_cannot_vote'));
        }

        if (!$poll->canVote($error)) {
            return $this->noPermission($error);
        }

        // Получить ранги из формы
        $rankings = $this->filter('rankings', 'array-uint');

        /** @var \Alebarda\RankedPollStandalone\Repository\Poll $pollRepo */
        $pollRepo = $this->repository('Alebarda\RankedPollStandalone:Poll');

        try {
            $pollRepo->castVote($poll, $visitor->user_id, $rankings);

            return $this->redirect(
                $this->buildLink('ranked-polls', $poll),
                \XF::phrase('alebarda_rankedpoll_vote_cast_successfully')
            );

        } catch (\XF\PrintableException $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Просмотр результатов
     */
    public function actionResults(ParameterBag $params)
    {
        $poll = $this->assertViewablePoll($params->poll_id);

        if (!$poll->canViewResults($error)) {
            return $this->noPermission($error);
        }

        /** @var \Alebarda\RankedPollStandalone\Repository\Poll $pollRepo */
        $pollRepo = $this->repository('Alebarda\RankedPollStandalone:Poll');
        $results = $pollRepo->calculateResults($poll);

        // Получить имена опций для отображения
        $optionNames = [];
        foreach ($poll->Options as $option) {
            $optionNames[$option->option_id] = $option->option_text;
        }

        $viewParams = [
            'poll' => $poll,
            'results' => $results,
            'optionNames' => $optionNames,
            'options' => $poll->Options,
        ];

        return $this->view('Alebarda\RankedPollStandalone:Poll\Results', 'rankedpoll_results', $viewParams);
    }

    /**
     * Список проголосовавших
     */
    public function actionVoters(ParameterBag $params)
    {
        $poll = $this->assertViewablePoll($params->poll_id);

        if (!$poll->canView($error)) {
            return $this->noPermission($error);
        }

        if (!$poll->show_voter_list) {
            return $this->noPermission(\XF::phrase('alebarda_rankedpoll_voter_list_disabled'));
        }

        $page = $this->filterPage();
        $perPage = 50;

        /** @var \Alebarda\RankedPollStandalone\Repository\Poll $pollRepo */
        $pollRepo = $this->repository('Alebarda\RankedPollStandalone:Poll');
        $voters = $pollRepo->getVoters($poll, $perPage, ($page - 1) * $perPage);

        $viewParams = [
            'poll' => $poll,
            'voters' => $voters,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $poll->voter_count,
        ];

        return $this->view('Alebarda\RankedPollStandalone:Poll\Voters', 'rankedpoll_voters', $viewParams);
    }

    /**
     * Получить опрос и проверить права на просмотр
     */
    protected function assertViewablePoll($pollId)
    {
        /** @var \Alebarda\RankedPollStandalone\Entity\Poll $poll */
        $poll = $this->em()->find('Alebarda\RankedPollStandalone:Poll', $pollId, ['Creator']);

        if (!$poll) {
            throw $this->exception($this->notFound(\XF::phrase('alebarda_rankedpoll_not_found')));
        }

        if (!$poll->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $poll;
    }
}
