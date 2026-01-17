<?php

namespace Alebarda\UserPosts\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class UserPosts extends AbstractController
{
    public function actionGet(ParameterBag $params)
    {
        $userId = $this->filter('user_id', 'uint');
        $page = $this->filter('page', '?uint') ?: 1;
        $limit = $this->filter('limit', '?uint') ?: 50;

        // Cap limit at 50
        if ($limit > 50) {
            $limit = 50;
        }

        if (!$userId) {
            return $this->apiError(
                \XF::phrase('please_specify_valid_user_id'),
                'missing_user_id',
                [],
                400
            );
        }

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

        // Find posts by this user
        $finder = \XF::finder('XF:Post');
        $finder->where('user_id', $userId)
            ->with(['Thread', 'User'])
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
            'username' => $user->username
        ]);
    }
}
