<?php

namespace Alebarda\RankedPollStandalone\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class RankedPoll extends AbstractController
{
    /**
     * Список всех опросов
     */
    public function actionIndex()
    {
        /** @var \Alebarda\RankedPollStandalone\Repository\Poll $pollRepo */
        $pollRepo = $this->repository('Alebarda\RankedPollStandalone:Poll');

        $pollFinder = $pollRepo->findPollsForList();
        $polls = $pollFinder->fetch();

        $viewParams = [
            'polls' => $polls,
        ];

        return $this->view('Alebarda\RankedPollStandalone:Poll\List', 'rankedpoll_list', $viewParams);
    }

    /**
     * Форма создания нового опроса
     */
    public function actionAdd()
    {
        /** @var \Alebarda\RankedPollStandalone\Entity\Poll $poll */
        $poll = $this->em()->create('Alebarda\RankedPollStandalone:Poll');

        return $this->pollAddEdit($poll);
    }

    /**
     * Форма редактирования опроса
     */
    public function actionEdit(ParameterBag $params)
    {
        $poll = $this->assertPollExists($params->poll_id);

        return $this->pollAddEdit($poll);
    }

    /**
     * Просмотр опроса в админке (результаты и список проголосовавших)
     */
    public function actionView(ParameterBag $params)
    {
        $poll = $this->assertPollExists($params->poll_id);

        /** @var \Alebarda\RankedPollStandalone\Repository\Poll $pollRepo */
        $pollRepo = $this->repository('Alebarda\RankedPollStandalone:Poll');
        $results = $pollRepo->calculateResults($poll);

        $optionNames = [];
        foreach ($poll->Options as $option) {
            $optionNames[$option->option_id] = $option->option_text;
        }

        $page = $this->filterPage();
        $perPage = 50;
        $voters = $pollRepo->getVoters($poll, $perPage, ($page - 1) * $perPage);

        $viewParams = [
            'poll' => $poll,
            'results' => $results,
            'optionNames' => $optionNames,
            'options' => $poll->Options,
            'voters' => $voters,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $poll->voter_count,
        ];

        return $this->view('Alebarda\RankedPollStandalone:Poll\View', 'rankedpoll_view', $viewParams);
    }

    /**
     * Общая логика для формы создания/редактирования
     */
    protected function pollAddEdit(\Alebarda\RankedPollStandalone\Entity\Poll $poll)
    {
        // Получить список групп пользователей
        $userGroups = $this->em()->getRepository('XF:UserGroup')->getUserGroupTitlePairs();

        // Текущие разрешённые группы
        $allowedGroups = $poll->getAllowedUserGroups();

        $viewParams = [
            'poll' => $poll,
            'userGroups' => $userGroups,
            'allowedGroups' => $allowedGroups,
        ];

        return $this->view('Alebarda\RankedPollStandalone:Poll\Edit', 'rankedpoll_edit', $viewParams);
    }

    /**
     * Сохранение опроса
     */
    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->poll_id) {
            $poll = $this->assertPollExists($params->poll_id);
        } else {
            /** @var \Alebarda\RankedPollStandalone\Entity\Poll $poll */
            $poll = $this->em()->create('Alebarda\RankedPollStandalone:Poll');
        }

        $this->pollSaveProcess($poll)->run();

        return $this->redirect($this->buildLink('ranked-polls'));
    }

    /**
     * Процесс сохранения опроса
     */
    protected function pollSaveProcess(\Alebarda\RankedPollStandalone\Entity\Poll $poll)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'title' => 'str',
            'description' => 'str',
            'poll_status' => 'str',
            'results_visibility' => 'str',
            'allowed_user_groups' => 'array-uint',
            'show_voter_list' => 'bool',
            'allow_vote_change' => 'bool',
            'require_all_ranked' => 'bool',
            'open_date' => 'str',
            'close_date' => 'str',
        ]);

        $form->basicEntitySave($poll, [
            'title' => $input['title'],
            'description' => $input['description'],
            'poll_status' => $input['poll_status'],
            'results_visibility' => $input['results_visibility'],
            'show_voter_list' => $input['show_voter_list'],
            'allow_vote_change' => $input['allow_vote_change'],
            'require_all_ranked' => $input['require_all_ranked'],
        ]);

        // Конвертировать даты
        if ($input['open_date']) {
            $openDate = strtotime($input['open_date']);
            $poll->open_date = $openDate ?: null;
        } else {
            $poll->open_date = null;
        }

        if ($input['close_date']) {
            $closeDate = strtotime($input['close_date']);
            $poll->close_date = $closeDate ?: null;
        } else {
            $poll->close_date = null;
        }

        // Сохранить разрешённые группы
        $poll->allowed_user_groups = !empty($input['allowed_user_groups'])
            ? json_encode(array_values($input['allowed_user_groups']))
            : null;

        // Установить автора (если новый)
        if (!$poll->exists()) {
            $poll->created_by_user_id = \XF::visitor()->user_id;
            $poll->created_date = \XF::$time;
        }

        // Сохранить опции
        $options = $this->filter('options', 'array');
        if (!empty($options)) {
            $form->complete(function() use ($poll, $options) {
                $this->saveOptions($poll, $options);
            });
        }

        return $form;
    }

    /**
     * Сохранить опции опроса
     */
    protected function saveOptions(\Alebarda\RankedPollStandalone\Entity\Poll $poll, array $options)
    {
        $db = $this->app->db();

        // Если опрос новый - создать опции
        // Если существующий - обновить
        if ($poll->exists()) {
            // Удалить существующие опции (только если нет голосов)
            if ($poll->voter_count == 0) {
                $db->delete('xf_alebarda_rankedpoll_option', 'poll_id = ?', $poll->poll_id);
            } else {
                // Нельзя изменять опции если есть голоса
                return;
            }
        }

        $displayOrder = 0;
        foreach ($options as $optionData) {
            if (empty($optionData['text'])) {
                continue;
            }

            /** @var \Alebarda\RankedPollStandalone\Entity\PollOption $option */
            $option = $this->em()->create('Alebarda\RankedPollStandalone:PollOption');
            $option->poll_id = $poll->poll_id;
            $option->option_text = $optionData['text'];
            $option->option_description = $optionData['description'] ?? '';
            $option->display_order = $displayOrder++;
            $option->save();
        }
    }

    /**
     * Удаление опроса
     */
    public function actionDelete(ParameterBag $params)
    {
        $poll = $this->assertPollExists($params->poll_id);

        if ($this->isPost()) {
            $poll->delete();

            return $this->redirect($this->buildLink('ranked-polls'));
        } else {
            $viewParams = [
                'poll' => $poll,
            ];

            return $this->view('Alebarda\RankedPollStandalone:Poll\Delete', 'rankedpoll_delete', $viewParams);
        }
    }

    /**
     * Закрыть опрос
     */
    public function actionClose(ParameterBag $params)
    {
        $poll = $this->assertPollExists($params->poll_id);

        /** @var \Alebarda\RankedPollStandalone\Repository\Poll $pollRepo */
        $pollRepo = $this->repository('Alebarda\RankedPollStandalone:Poll');
        $pollRepo->closePoll($poll);

        return $this->redirect($this->buildLink('ranked-polls'));
    }

    /**
     * Удалить голоса конкретного пользователя (админ)
     */
    public function actionRemoveVoter(ParameterBag $params)
    {
        $this->assertPostOnly();

        $poll = $this->assertPollExists($params->poll_id);
        $userId = $this->filter('user_id', 'uint');

        if (!$userId) {
            return $this->error(\XF::phrase('requested_user_not_found'));
        }

        /** @var \Alebarda\RankedPollStandalone\Repository\Poll $pollRepo */
        $pollRepo = $this->repository('Alebarda\RankedPollStandalone:Poll');
        $removed = $pollRepo->removeUserVotes($poll, $userId);

        if (!$removed) {
            return $this->error(\XF::phrase('no_votes_cast'));
        }

        return $this->redirect($this->buildLink('ranked-polls/view', $poll));
    }

    /**
     * Открыть опрос
     */
    public function actionOpen(ParameterBag $params)
    {
        $poll = $this->assertPollExists($params->poll_id);

        /** @var \Alebarda\RankedPollStandalone\Repository\Poll $pollRepo */
        $pollRepo = $this->repository('Alebarda\RankedPollStandalone:Poll');
        $pollRepo->openPoll($poll);

        return $this->redirect($this->buildLink('ranked-polls'));
    }

    /**
     * Получить опрос или выбросить ошибку
     */
    protected function assertPollExists($pollId)
    {
        /** @var \Alebarda\RankedPollStandalone\Entity\Poll $poll */
        $poll = $this->em()->find('Alebarda\RankedPollStandalone:Poll', $pollId, ['Creator']);

        if (!$poll) {
            throw $this->exception($this->notFound(\XF::phrase('alebarda_rankedpoll_not_found')));
        }

        return $poll;
    }
}
