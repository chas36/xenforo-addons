<?php

namespace Alebarda\RankedPollStandalone;

use XF\Mvc\Entity\Entity;
use XF\Template\Templater;

class Listener
{
    protected static $handledThreads = [];

    /**
     * Добавить данные опроса в шаблоны создания/редактирования темы
     */
    public static function templaterTemplatePreRender(
        Templater $templater,
        &$type,
        &$template,
        array &$params
    ) {
        if ($type !== 'public') {
            return;
        }

        if (!in_array($template, ['thread_create', 'thread_edit', 'thread_view', 'thread_type_fields_poll', 'thread_view_type_poll'], true)) {
            return;
        }

        $thread = $params['thread'] ?? null;
        $poll = null;
        if ($thread && $thread->thread_id) {
            /** @var \Alebarda\RankedPollStandalone\Repository\Poll $pollRepo */
            $pollRepo = \XF::repository('Alebarda\\RankedPollStandalone:Poll');
            $poll = $pollRepo->getPollByThreadId($thread->thread_id, ['Options']);
        }

        $params['rankedPoll'] = $poll;

        $request = \XF::app()->request();
        $input = [];
        if ($request && $request->isPost()) {
            $input = $request->filter('ranked_poll', 'array');
            $params['rankedPollInput'] = $input;
        } else {
            $params['rankedPollInput'] = [];
        }

        $params['rankedPollOptions'] = self::getOptionsForTemplate($poll, $input['options'] ?? []);

        $params['rankedPollOpenDateTime'] = $poll && $poll->open_date ? date('Y-m-d\\TH:i', $poll->open_date) : '';
        $params['rankedPollCloseDateTime'] = $poll && $poll->close_date ? date('Y-m-d\\TH:i', $poll->close_date) : '';

        if (in_array($template, ['thread_view', 'thread_view_type_poll'], true) && $poll) {
            $signature = hash_hmac('sha256', $poll->poll_id, \XF::config('globalSalt'));
            $visitor = \XF::visitor();
            $canVote = $poll->canVote($voteError);
            $hasVoted = $poll->hasVoted($visitor->user_id);
            $canViewResults = $poll->canViewResults($resultsError);

            $params['rankedPollSignature'] = $signature;
            $params['rankedPollCanVote'] = $canVote;
            $params['rankedPollVoteError'] = $voteError ?? null;
            $params['rankedPollHasVoted'] = $hasVoted;
            $params['rankedPollCanViewResults'] = $canViewResults;
            $params['rankedPollIsOpen'] = $poll->isOpen();
            $params['rankedPollIsClosed'] = $poll->isClosed();
        }
    }

    /**
     * Создать или обновить ranked poll после сохранения темы
     */
    public static function threadEntityPostSave(Entity $entity)
    {
        if (!($entity instanceof \XF\Entity\Thread)) {
            return;
        }

        $threadId = $entity->thread_id;
        if (!$threadId) {
            return;
        }

        if (isset(self::$handledThreads[$threadId])) {
            return;
        }
        self::$handledThreads[$threadId] = true;

        $request = \XF::app()->request();
        if (!$request || !$request->isPost()) {
            return;
        }

        $input = $request->filter('ranked_poll', 'array');
        if (!$input) {
            return;
        }

        /** @var \Alebarda\RankedPollStandalone\Repository\Poll $pollRepo */
        $pollRepo = \XF::repository('Alebarda\\RankedPollStandalone:Poll');
        $existingPoll = $pollRepo->getPollByThreadId($entity->thread_id, ['Options']);

        if (empty($input['enabled'])) {
            if ($existingPoll) {
                $existingPoll->delete();
            }
            return;
        }

        try {
            $poll = $pollRepo->savePollForThread($entity, $input, \XF::visitor());
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * Удалить связанный опрос при удалении темы
     */
    public static function threadEntityPostDelete(Entity $entity)
    {
        if (!($entity instanceof \XF\Entity\Thread)) {
            return;
        }

        /** @var \Alebarda\RankedPollStandalone\Repository\Poll $pollRepo */
        $pollRepo = \XF::repository('Alebarda\\RankedPollStandalone:Poll');
        $poll = $pollRepo->getPollByThreadId($entity->thread_id, ['Options']);

        if ($poll) {
            $poll->delete();
        }
    }

    /**
     * Подготовить варианты для отображения в форме
     */
    protected static function getOptionsForTemplate(?\Alebarda\RankedPollStandalone\Entity\Poll $poll, array $inputOptions)
    {
        $options = [];

        if (!empty($inputOptions)) {
            foreach ($inputOptions as $option) {
                $text = trim((string)($option['text'] ?? ''));
                $options[] = ['option_text' => $text];
            }
            return $options;
        }

        if ($poll && $poll->Options) {
            foreach ($poll->Options as $option) {
                $options[] = ['option_text' => $option->option_text];
            }
        }

        return $options;
    }
}
