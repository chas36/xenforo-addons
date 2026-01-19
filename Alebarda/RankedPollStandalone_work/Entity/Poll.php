<?php

namespace Alebarda\RankedPollStandalone\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $poll_id
 * @property string $title
 * @property string $description
 * @property int $created_by_user_id
 * @property int $created_date
 * @property int|null $open_date
 * @property int|null $close_date
 * @property string $poll_status
 * @property string $results_visibility
 * @property string|null $allowed_user_groups
 * @property bool $show_voter_list
 * @property bool $allow_vote_change
 * @property bool $require_all_ranked
 * @property string $winner_mode
 * @property int $winner_count
 * @property int $voter_count
 * @property int $view_count
 * @property string|null $cached_results
 * @property array|null $allocation_results
 * @property int|null $results_cache_date
 *
 * RELATIONS
 * @property \XF\Entity\User $Creator
 * @property \XF\Mvc\Entity\AbstractCollection|\Alebarda\RankedPollStandalone\Entity\PollOption[] $Options
 * @property \XF\Mvc\Entity\AbstractCollection|\Alebarda\RankedPollStandalone\Entity\PollVote[] $Votes
 */
class Poll extends Entity
{
    /**
     * Проверка: открыт ли опрос для голосования
     */
    public function isOpen()
    {
        if ($this->poll_status !== 'open') {
            return false;
        }

        $now = \XF::$time;

        // Проверить время открытия
        if ($this->open_date && $now < $this->open_date) {
            return false;
        }

        // Проверить время закрытия
        if ($this->close_date && $now > $this->close_date) {
            return false;
        }

        return true;
    }

    /**
     * Проверка: закрыт ли опрос
     */
    public function isClosed()
    {
        if ($this->poll_status === 'closed') {
            return true;
        }

        // Автоматическое закрытие по времени
        if ($this->close_date && \XF::$time > $this->close_date) {
            return true;
        }

        return false;
    }

    /**
     * Получить разрешённые группы пользователей
     */
    public function getAllowedUserGroups()
    {
        if (!$this->allowed_user_groups) {
            return [];
        }

        $groups = json_decode($this->allowed_user_groups, true);
        return is_array($groups) ? $groups : [];
    }

    /**
     * Получить кэшированные результаты
     */
    public function getCachedResults()
    {
        if (!$this->cached_results) {
            return null;
        }

        $results = json_decode($this->cached_results, true);
        return is_array($results) ? $results : null;
    }

    /**
     * Сохранить результаты в кэш
     */
    public function setCachedResults(array $results)
    {
        $this->cached_results = json_encode($results);
        $this->results_cache_date = \XF::$time;
    }

    /**
     * Инвалидировать кэш результатов
     */
    public function invalidateResultsCache()
    {
        $this->cached_results = null;
        $this->allocation_results = null;
        $this->results_cache_date = null;
    }

    /**
     * Получить результаты распределения мандатов
     */
    public function getAllocationResults()
    {
        return $this->allocation_results ?: [];
    }

    /**
     * Установить результаты распределения мандатов
     */
    public function setAllocationResults(array $results)
    {
        $this->allocation_results = $results ?: null;
    }

    /**
     * Проверка, используется ли режим множественных победителей
     */
    public function hasMultipleWinners()
    {
        return in_array($this->winner_mode, ['top_n', 'seat_allocation'], true);
    }

    /**
     * Получить массив ID победителей
     */
    public function getWinnerIds()
    {
        $results = $this->getCachedResults();
        if (!$results) {
            return [];
        }

        if ($this->winner_mode === 'single') {
            return $results['winner_id'] ? [$results['winner_id']] : [];
        }

        if ($this->winner_mode === 'top_n') {
            return array_slice($results['ranking'], 0, $this->winner_count);
        }

        if ($this->winner_mode === 'seat_allocation') {
            $allocation = $this->getAllocationResults();
            return array_keys($allocation['allocations'] ?? []);
        }

        return [];
    }

    /**
     * Валидация перед сохранением
     */
    protected function _preSave()
    {
        parent::_preSave();

        if ($this->winner_mode === 'single') {
            $this->winner_count = 1;
        } elseif ($this->winner_mode === 'top_n') {
            $optionCount = $this->Options ? $this->Options->count() : 0;
            if ($optionCount && $this->winner_count > $optionCount) {
                $this->error(\XF::phrase('alebarda_rankedpoll_winner_count_exceeds_options'));
            }
        } elseif ($this->winner_mode === 'seat_allocation') {
            if ($this->winner_count < 1) {
                $this->error(\XF::phrase('alebarda_rankedpoll_seat_count_minimum'));
            }
        }
    }

    /**
     * Проверка: может ли пользователь просматривать опрос
     */
    public function canView(&$error = null)
    {
        $visitor = \XF::visitor();

        // Админы всегда могут
        if ($visitor->is_admin) {
            return true;
        }

        // Проверить группы доступа
        $allowedGroups = $this->getAllowedUserGroups();
        if (!empty($allowedGroups)) {
            $userGroups = array_merge(
                [$visitor->user_group_id],
                $visitor->secondary_group_ids
            );

            if (!array_intersect($userGroups, $allowedGroups)) {
                $error = \XF::phraseDeferred('alebarda_rankedpoll_not_in_allowed_group');
                return false;
            }
        }

        return true;
    }

    /**
     * Проверка: может ли пользователь голосовать
     */
    public function canVote(&$error = null)
    {
        if (!$this->canView($error)) {
            return false;
        }

        $visitor = \XF::visitor();

        // Гость не может голосовать
        if (!$visitor->user_id) {
            $error = \XF::phraseDeferred('alebarda_rankedpoll_guests_cannot_vote');
            return false;
        }

        // Проверить что опрос открыт
        if (!$this->isOpen()) {
            if ($this->isClosed()) {
                $error = \XF::phraseDeferred('alebarda_rankedpoll_closed');
            } else {
                $error = \XF::phraseDeferred('alebarda_rankedpoll_not_yet_open');
            }
            return false;
        }

        // Проверить: уже голосовал и нельзя менять голос
        if (!$this->allow_vote_change && $this->hasVoted($visitor->user_id)) {
            $error = \XF::phraseDeferred('alebarda_rankedpoll_already_voted');
            return false;
        }

        return true;
    }

    /**
     * Проверка: может ли пользователь просматривать результаты
     */
    public function canViewResults(&$error = null)
    {
        if (!$this->canView($error)) {
            return false;
        }

        $visitor = \XF::visitor();

        // Админы всегда могут
        if ($visitor->is_admin) {
            return true;
        }

        // Автор всегда может
        if ($visitor->user_id && $visitor->user_id == $this->created_by_user_id) {
            return true;
        }

        // Проверить настройку видимости
        switch ($this->results_visibility) {
            case 'realtime':
                return true;

            case 'after_vote':
                return $this->hasVoted($visitor->user_id);

            case 'after_close':
                return $this->isClosed();

            case 'never':
                return false;

            default:
                return false;
        }
    }

    /**
     * Проверка: проголосовал ли пользователь
     */
    public function hasVoted($userId = null)
    {
        $userId = $userId ?: \XF::visitor()->user_id;

        if (!$userId) {
            return false;
        }

        return $this->db()->fetchOne("
            SELECT 1
            FROM xf_alebarda_rankedpoll_voter
            WHERE poll_id = ? AND user_id = ?
        ", [$this->poll_id, $userId]) ? true : false;
    }

    /**
     * Получить голоса пользователя
     */
    public function getUserVotes($userId = null)
    {
        $userId = $userId ?: \XF::visitor()->user_id;

        if (!$userId) {
            return [];
        }

        return $this->db()->fetchPairs("
            SELECT option_id, rank_position
            FROM xf_alebarda_rankedpoll_vote
            WHERE poll_id = ? AND user_id = ?
            ORDER BY rank_position ASC
        ", [$this->poll_id, $userId]);
    }

    /**
     * Проверка: может ли пользователь редактировать опрос
     */
    public function canEdit(&$error = null)
    {
        $visitor = \XF::visitor();

        if ($visitor->is_admin) {
            return true;
        }

        if (!$visitor->hasPermission('alebardaRankedPoll', 'edit')) {
            $error = \XF::phraseDeferred('no_permission');
            return false;
        }

        // Автор может редактировать до начала голосования
        if ($visitor->user_id == $this->created_by_user_id && $this->voter_count == 0) {
            return true;
        }

        return false;
    }

    /**
     * Проверка: может ли пользователь удалить опрос
     */
    public function canDelete(&$error = null)
    {
        $visitor = \XF::visitor();

        if ($visitor->is_admin) {
            return true;
        }

        if (!$visitor->hasPermission('alebardaRankedPoll', 'delete')) {
            $error = \XF::phraseDeferred('no_permission');
            return false;
        }

        return false;
    }

    /**
     * Получить количество вариантов ответа
     */
    public function getOptionCount()
    {
        return $this->Options->count();
    }

    /**
     * Структура entity
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_alebarda_rankedpoll';
        $structure->shortName = 'Alebarda\RankedPollStandalone:Poll';
        $structure->primaryKey = 'poll_id';

        $structure->columns = [
            'poll_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'title' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'description' => ['type' => self::STR, 'default' => ''],
            'created_by_user_id' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'open_date' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
            'close_date' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
            'poll_status' => ['type' => self::STR, 'default' => 'draft',
                'allowedValues' => ['draft', 'open', 'closed']],
            'results_visibility' => ['type' => self::STR, 'default' => 'after_close',
                'allowedValues' => ['realtime', 'after_vote', 'after_close', 'never']],
            'winner_mode' => ['type' => self::STR, 'default' => 'single',
                'allowedValues' => ['single', 'top_n', 'seat_allocation']],
            'winner_count' => ['type' => self::UINT, 'default' => 1, 'min' => 1, 'max' => 100],
            'allowed_user_groups' => ['type' => self::STR, 'nullable' => true, 'default' => null],
            'show_voter_list' => ['type' => self::BOOL, 'default' => true],
            'allow_vote_change' => ['type' => self::BOOL, 'default' => true],
            'require_all_ranked' => ['type' => self::BOOL, 'default' => false],
            'voter_count' => ['type' => self::UINT, 'default' => 0],
            'view_count' => ['type' => self::UINT, 'default' => 0],
            'cached_results' => ['type' => self::STR, 'nullable' => true, 'default' => null],
            'allocation_results' => ['type' => self::JSON_ARRAY, 'nullable' => true, 'default' => null],
            'results_cache_date' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
        ];

        $structure->relations = [
            'Creator' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [['user_id', '=', '$created_by_user_id']],
                'primary' => true
            ],
            'Options' => [
                'entity' => 'Alebarda\RankedPollStandalone:PollOption',
                'type' => self::TO_MANY,
                'conditions' => 'poll_id',
                'order' => 'display_order'
            ],
            'Votes' => [
                'entity' => 'Alebarda\RankedPollStandalone:PollVote',
                'type' => self::TO_MANY,
                'conditions' => 'poll_id'
            ]
        ];

        return $structure;
    }
}
