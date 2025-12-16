<?php

namespace Alebarda\UserListByGroup\BbCode;

use XF\BbCode\Renderer\AbstractRenderer;

class AvatarList
{
    const MAX_LIMIT = 100;
    const DEFAULT_LIMIT = 100;
    const AVATARS_PER_ROW = 5;

    public static function renderAvatarList(
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
            return '<div class="avatarlist-by-group--empty">'
                . \XF::phrase('no_users_found')
                . '</div>';
        }

        return self::renderHtml($users, $params['size']);
    }

    protected static function parseOptions($option): array
    {
        $params = [
            'group' => 0,
            'limit' => self::DEFAULT_LIMIT,
            'size' => 'm' // s=small, m=medium, l=large
        ];

        $option = trim($option);

        // Format: [avatarlist=283] or [avatarlist=283,50] or [avatarlist=283,50,l]
        $parts = preg_split('/[,|]/', $option);

        if (!empty($parts[0]) && is_numeric($parts[0])) {
            $params['group'] = (int) $parts[0];
        }

        if (!empty($parts[1]) && is_numeric($parts[1])) {
            $params['limit'] = min((int) $parts[1], self::MAX_LIMIT);
        }

        if (!empty($parts[2]) && in_array($parts[2], ['s', 'm', 'l', 'o'])) {
            $params['size'] = $parts[2];
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

    protected static function renderHtml(\XF\Mvc\Entity\ArrayCollection $users, string $size): string
    {
        $html = '<div class="avatarlist-by-group" style="display: flex; flex-wrap: wrap; gap: 8px; max-width: 100%;">';

        foreach ($users as $user) {
            $html .= self::renderAvatar($user, $size);
        }

        $html .= '</div>';

        return $html;
    }

    protected static function renderAvatar(\XF\Entity\User $user, string $size): string
    {
        $userId = $user->user_id;
        $username = htmlspecialchars($user->username);
        $href = \XF::app()->router('public')->buildLink('members', $user);
        $avatarUrl = $user->getAvatarUrl($size);

        // Size in pixels
        $sizeMap = [
            's' => 48,
            'm' => 96,
            'l' => 192,
            'o' => 384
        ];
        $sizePx = $sizeMap[$size] ?? 96;

        return '<a href="' . htmlspecialchars($href) . '" '
            . 'title="' . $username . '" '
            . 'data-user-id="' . $userId . '" '
            . 'data-xf-init="member-tooltip" '
            . 'style="display: inline-block; width: calc(20% - 8px); min-width: ' . $sizePx . 'px; max-width: ' . $sizePx . 'px;">'
            . '<img src="' . htmlspecialchars($avatarUrl) . '" '
            . 'alt="' . $username . '" '
            . 'style="width: 100%; height: auto; border-radius: 4px;" '
            . 'loading="lazy" />'
            . '</a>';
    }
}
