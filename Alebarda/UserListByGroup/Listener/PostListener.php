<?php

namespace Alebarda\UserListByGroup\Listener;

use XF\Entity\Post;
use Alebarda\UserListByGroup\BbCode\NotifyList;

class PostListener
{
    /**
     * Слушатель события создания поста
     * Ищет [notifylist] BB-код и отправляет уведомления
     */
    public static function postInsert(\XF\Mvc\Entity\Entity $entity)
    {
        if (!($entity instanceof Post)) {
            return;
        }

        self::processNotifyList($entity);
    }

    protected static function processNotifyList(Post $post)
    {
        $message = $post->message;

        // Ищем все [notifylist=XXX] в сообщении
        preg_match_all('/\[notifylist=(\d+)(?:[,|](\d+))?\]/i', $message, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return;
        }

        $sender = $post->User;
        if (!$sender) {
            return;
        }

        $alertedUserIds = [];

        foreach ($matches as $match) {
            $groupId = (int) $match[1];
            $limit = isset($match[2]) ? min((int) $match[2], 50) : 50;

            $userIds = NotifyList::getUserIdsByGroup($groupId, $limit);

            foreach ($userIds as $userId) {
                // Не уведомляем автора поста и уже уведомлённых
                if ($userId == $sender->user_id || in_array($userId, $alertedUserIds)) {
                    continue;
                }

                self::sendAlert($post, $userId, $sender);
                $alertedUserIds[] = $userId;
            }
        }
    }

    protected static function sendAlert(Post $post, int $userId, \XF\Entity\User $sender)
    {
        $user = \XF::em()->find('XF:User', $userId);
        if (!$user) {
            return;
        }

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = \XF::repository('XF:UserAlert');

        $alertRepo->alert(
            $user,
            $sender->user_id,
            $sender->username,
            'post',
            $post->post_id,
            'mention'
        );
    }
}