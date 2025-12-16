<?php

namespace Alebarda\UserListByGroup\BbCode;

use XF\BbCode\Renderer\AbstractRenderer;

class NotifyList
{
    const MAX_LIMIT = 50;
    const DEFAULT_LIMIT = 50;

    public static function renderNotifyList(
        array $children,
        $option,
        array $tag,
        array $options,
        AbstractRenderer $renderer
    ) {
        $params = self::parseOptions($option);

        if (!$params['group']) {
            return '';
        }

        $users = self::fetchUsersByGroup($params['group'], $params['limit']);

        if ($users->count() === 0) {
            return '<div class="notifylist-by-group--empty">'
                . \XF::phrase('no_users_found')
                . '</div>';
        }

        return self::renderHtml($users);
    }

    protected static function parseOptions($option): array
    {
        $params = [
            'group' => 0,
            'limit' => self::DEFAULT_LIMIT
        ];

        $option = trim($option);

        // Format: [notifylist=283] or [notifylist=283,10]
        if (preg_match('/^(\d+)(?:[,|](\d+))?$/', $option, $matches)) {
            $params['group'] = (int) $matches[1];
            if (!empty($matches[2])) {
                $params['limit'] = min((int) $matches[2], self::MAX_LIMIT);
            }
        }

        return $params;
    }

    protected static function fetchUsersByGroup(int $groupId, int $limit): \XF\Mvc\Entity\ArrayCollection
    {
        // Find users where primary group matches
        $primaryFinder = \XF::finder('XF:User');
        $primaryFinder->where('user_group_id', $groupId)
            ->order('username', 'ASC');

        // Find users where secondary groups contain this group
        $secondaryFinder = \XF::finder('XF:User');
        $secondaryFinder->whereOr([
            ['secondary_group_ids', '=', $groupId],
            ['secondary_group_ids', 'LIKE', $groupId . ',%'],
            ['secondary_group_ids', 'LIKE', '%,' . $groupId],
            ['secondary_group_ids', 'LIKE', '%,' . $groupId . ',%']
        ])->order('username', 'ASC');

        // Fetch from both
        $primaryUsers = $primaryFinder->fetch($limit);
        $secondaryUsers = $secondaryFinder->fetch($limit);

        // Merge and deduplicate
        $allUsers = $primaryUsers->toArray();
        foreach ($secondaryUsers as $userId => $user) {
            if (!isset($allUsers[$userId])) {
                $allUsers[$userId] = $user;
            }
        }

        // Sort by username
        uasort($allUsers, function($a, $b) {
            return strcasecmp($a->username, $b->username);
        });

        // Apply limit
        $allUsers = array_slice($allUsers, 0, $limit, true);

        return new \XF\Mvc\Entity\ArrayCollection($allUsers);
    }

    protected static function renderHtml(\XF\Mvc\Entity\ArrayCollection $users): string
    {
        $userLinks = [];

        foreach ($users as $user) {
            $userLinks[] = self::renderUsername($user);
        }

        return implode('<br />', $userLinks);
    }

    protected static function renderUsername(\XF\Entity\User $user): string
    {
        $userId = $user->user_id;
        $username = htmlspecialchars($user->username);
        $href = \XF::app()->router('public')->buildLink('members', $user);

        $styleClass = '';
        $displayStyleGroupId = $user->display_style_group_id;
        if ($displayStyleGroupId) {
            $styleClass = 'username--style' . $displayStyleGroupId;
        }

        return '<a href="' . htmlspecialchars($href) . '" '
            . 'class="username" '
            . 'data-user-id="' . $userId . '" '
            . 'data-xf-init="member-tooltip">'
            . '<span class="' . $styleClass . '">' . $username . '</span>'
            . '</a>';
    }

    /**
     * Получить user IDs из группы (используется listener'ом для отправки уведомлений)
     */
    public static function getUserIdsByGroup(int $groupId, int $limit = 50): array
    {
        $users = self::fetchUsersByGroup($groupId, $limit);
        return array_keys($users->toArray());
    }
}