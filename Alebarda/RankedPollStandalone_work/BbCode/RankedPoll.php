<?php

namespace Alebarda\RankedPollStandalone\BbCode;

use XF\BbCode\Renderer\AbstractRenderer;

class RankedPoll
{
    /**
     * Рендеринг BB code [rankedpoll=ID]
     *
     * @param array $children Содержимое внутри тега
     * @param mixed $option Значение после = (poll_id)
     * @param array $tag Информация о теге
     * @param array $options Дополнительные опции
     * @param AbstractRenderer $renderer Рендерер
     * @return string
     */
    public static function renderTagRankedpoll(array $children, $option, array $tag, array $options, AbstractRenderer $renderer)
    {
        // Получить poll_id
        $pollId = intval($option);

        if (!$pollId) {
            // Если ID не указан - вернуть ошибку
            return '[INVALID POLL ID]';
        }

        // Загрузить опрос из БД
        /** @var \Alebarda\RankedPollStandalone\Entity\Poll $poll */
        $poll = \XF::em()->find('Alebarda\RankedPollStandalone:Poll', $pollId, ['Creator']);

        if (!$poll) {
            // Опрос не найден
            return \XF::phrase('alebarda_rankedpoll_not_found')->render();
        }

        // Проверить права доступа (только для просмотра, не для редактирования)
        if (!$poll->canView($error)) {
            // Если нет прав - показать сообщение
            return sprintf(
                '<div class="blockMessage blockMessage--error">%s</div>',
                htmlspecialchars($error ? $error->render() : \XF::phrase('no_permission')->render())
            );
        }

        // Генерировать HMAC signature для защиты
        $signature = self::generateSignature($pollId);

        // Получить visitor
        $visitor = \XF::visitor();

        // Проверить: может ли пользователь голосовать
        $canVote = $poll->canVote($voteError);

        // Проверить: проголосовал ли уже
        $hasVoted = $poll->hasVoted($visitor->user_id);

        // Проверить: может ли просматривать результаты
        $canViewResults = $poll->canViewResults();

        // Получить статус опроса
        $isOpen = $poll->isOpen();
        $isClosed = $poll->isClosed();

        // Рендерить только для HTML (не для email, plain text, etc.)
        if ($renderer instanceof \XF\BbCode\Renderer\Html) {
            // Получить templater
            $templater = \XF::app()->templater();

            // Передать данные в шаблон
            $templateParams = [
                'poll' => $poll,
                'signature' => $signature,
                'canVote' => $canVote,
                'voteError' => $voteError ?? null,
                'hasVoted' => $hasVoted,
                'canViewResults' => $canViewResults,
                'isOpen' => $isOpen,
                'isClosed' => $isClosed,
            ];

            return $templater->renderTemplate('public:rankedpoll_bbcode_embed', $templateParams);
        }

        // Для других рендереров (email, plain text) - вернуть простую ссылку
        $link = \XF::app()->router('public')->buildLink('canonical:ranked-polls', $poll);
        return sprintf(
            '%s: %s',
            \XF::phrase('poll')->render(),
            $link
        );
    }

    /**
     * Генерировать HMAC signature для защиты от подделки
     *
     * @param int $pollId
     * @return string
     */
    protected static function generateSignature($pollId)
    {
        $salt = \XF::config('globalSalt');
        return hash_hmac('sha256', $pollId, $salt);
    }

    /**
     * Проверить HMAC signature
     *
     * @param int $pollId
     * @param string $signature
     * @return bool
     */
    public static function verifySignature($pollId, $signature)
    {
        $expectedSignature = self::generateSignature($pollId);
        return hash_equals($expectedSignature, $signature);
    }
}
