<?php

namespace Alebarda\UserListByGroup\BbCode;

use XF\BbCode\Renderer\AbstractRenderer;

class UserList
{
    const MAX_LIMIT = 100;
    const DEFAULT_LIMIT = 100;

    public static function renderUserList(
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
            return '<div class="userlist-by-group--empty">'
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

        // Format 1: Simple - just group ID: [userlist=283]
        // Format 2: With limit: [userlist=283,50] or [userlist=283|50]
        if (preg_match('/^(\d+)(?:[,|](\d+))?$/', $option, $matches)) {
            $params['group'] = (int) $matches[1];
            if (!empty($matches[2])) {
                $params['limit'] = min((int) $matches[2], self::MAX_LIMIT);
            }
        }
        // Format 3: Named params (legacy): [userlist=group=5 limit=50]
        else {
            if (preg_match('/group\s*=\s*(\d+)/i', $option, $matches)) {
                $params['group'] = (int) $matches[1];
            }
            if (preg_match('/limit\s*=\s*(\d+)/i', $option, $matches)) {
                $params['limit'] = min((int) $matches[1], self::MAX_LIMIT);
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
        // XenForo stores secondary_group_ids as: 1,2,3 (no leading/trailing commas)
        // Need to match: exact, start, middle, end positions
        $secondaryFinder = \XF::finder('XF:User');
        $secondaryFinder->whereOr([
            ['secondary_group_ids', '=', $groupId],           // exact: "283"
            ['secondary_group_ids', 'LIKE', $groupId . ',%'], // start: "283,..."
            ['secondary_group_ids', 'LIKE', '%,' . $groupId], // end: "...,283"
            ['secondary_group_ids', 'LIKE', '%,' . $groupId . ',%'] // middle: "...,283,..."
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

        // Get username styling class from display_style_group_id (this is what XenForo uses for styling)
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
}
