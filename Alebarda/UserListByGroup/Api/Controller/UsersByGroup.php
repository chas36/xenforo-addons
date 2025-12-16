<?php

namespace Alebarda\UserListByGroup\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class UsersByGroup extends AbstractController
{
    public function actionGet(ParameterBag $params)
    {
        $groupId = $this->filter('group_id', 'uint');
        $limit = $this->filter('limit', '?uint');

        if (!$groupId) {
            return $this->apiError(
                \XF::phrase('please_specify_valid_group_id'),
                'missing_group_id',
                [],
                400
            );
        }

        // Verify group exists
        $group = $this->em()->find('XF:UserGroup', $groupId);
        if (!$group) {
            return $this->apiError(
                \XF::phrase('requested_user_group_not_found'),
                'group_not_found',
                [],
                404
            );
        }

        $users = $this->fetchUsersByGroup($groupId, $limit);

        $userData = [];
        foreach ($users as $user) {
            $userData[] = [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'profile_url' => $this->buildLink('canonical:members', $user),
                'avatar_url' => $user->getAvatarUrl('m')
            ];
        }

        return $this->apiResult([
            'users' => $userData,
            'total' => count($userData),
            'group_id' => $groupId,
            'group_title' => $group->title
        ]);
    }

    protected function fetchUsersByGroup(int $groupId, ?int $limit): \XF\Mvc\Entity\ArrayCollection
    {
        // Find users where primary group matches
        $primaryFinder = \XF::finder('XF:User');
        $primaryFinder->where('user_group_id', $groupId)
            ->order('username', 'ASC');

        // Find users where secondary groups contain this group
        $secondaryFinder = \XF::finder('XF:User');
        $secondaryFinder->where('secondary_group_ids', 'LIKE',
            $secondaryFinder->expression('CONCAT("%,", ? , ",%")', [$groupId]))
            ->order('username', 'ASC');

        // Fetch all from both
        $primaryUsers = $primaryFinder->fetch();
        $secondaryUsers = $secondaryFinder->fetch();

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

        // Apply limit if specified
        if ($limit !== null && $limit > 0) {
            $allUsers = array_slice($allUsers, 0, $limit, true);
        }

        return new \XF\Mvc\Entity\ArrayCollection($allUsers);
    }
}
