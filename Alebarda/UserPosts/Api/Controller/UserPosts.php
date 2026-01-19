<?php

namespace Alebarda\UserPosts\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class UserPosts extends AbstractController
{
    public function actionGet(ParameterBag $params)
    {
        $userId = $this->filter('user_id', '?uint');
        $page = $this->filter('page', '?uint') ?: 1;
        $limit = $this->filter('limit', '?uint') ?: 50;
        $nodeIds = $this->filter('node_ids', 'array-uint');
        $nodeId = $this->filter('node_id', '?uint');
        $dateFrom = $this->filter('date_from', '?uint');
        $dateTo = $this->filter('date_to', '?uint');

        // Cap limit at 50
        if ($limit > 50) {
            $limit = 50;
        }

        if ($nodeId && !$nodeIds) {
            $nodeIds = [$nodeId];
        }

        if (!$userId && !$nodeIds && !$dateFrom && !$dateTo) {
            return $this->apiError(
                'Please specify at least one filter (user_id, node_ids, date_from, date_to).',
                'missing_filters',
                [],
                400
            );
        }

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            return $this->apiError(
                'date_from must be less than or equal to date_to.',
                'invalid_date_range',
                [],
                400
            );
        }

        $user = null;
        if ($userId) {
            // Verify user exists
            $user = $this->em()->find('XF:User', $userId);
            if (!$user) {
                return $this->apiError(
                    \XF::phrase('requested_user_not_found'),
                    'user_not_found',
                    [],
                    404
                );
            }
        }

        // Find posts using provided filters
        $finder = \XF::finder('XF:Post');
        if ($userId) {
            $finder->where('user_id', $userId);
        }
        if ($nodeIds) {
            $finder->where('Thread.node_id', $nodeIds);
        }
        if ($dateFrom) {
            $finder->where('post_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $finder->where('post_date', '<=', $dateTo);
        }
        $finder->with(['Thread', 'User'])
            ->order('post_date', 'DESC')
            ->limitByPage($page, $limit);

        $total = $finder->total();
        $posts = $finder->fetch();

        $postData = [];
        foreach ($posts as $post) {
            $thread = $post->Thread;
            $postData[] = [
                'post_id' => $post->post_id,
                'thread_id' => $post->thread_id,
                'user_id' => $post->user_id,
                'username' => $post->User ? $post->User->username : 'Unknown',
                'post_date' => $post->post_date,
                'message' => $post->message,
                'position' => $post->position,
                'reaction_score' => $post->reaction_score,
                'Thread' => $thread ? [
                    'thread_id' => $thread->thread_id,
                    'title' => $thread->title,
                    'node_id' => $thread->node_id
                ] : null,
                'User' => $post->User ? [
                    'user_id' => $post->User->user_id,
                    'username' => $post->User->username,
                    'avatar_url' => $post->User->getAvatarUrl('m')
                ] : null
            ];
        }

        $lastPage = ceil($total / $limit);

        return $this->apiResult([
            'posts' => $postData,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $limit,
                'total' => $total
            ],
            'user_id' => $userId,
            'username' => $user ? $user->username : null,
            'node_ids' => $nodeIds,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
    }
}
