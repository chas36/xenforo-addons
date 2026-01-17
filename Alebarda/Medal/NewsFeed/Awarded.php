<?php

namespace Xfrocks\Medal\NewsFeed;

use XF\Mvc\Entity\Entity;
use XF\NewsFeed\AbstractHandler;

class Awarded extends AbstractHandler
{
    public function getEntityWith()
    {
        return ['User', 'Medal'];
    }

    public function canViewContent(Entity $entity, &$error = null)
    {
        // Если у вас есть метод canView в сущности Awarded
        if (method_exists($entity, 'canView'))
        {
            return $entity->canView($error);
        }
        
        // По умолчанию разрешаем просмотр
        return true;
    }

public function contentIsVisible(\XF\Mvc\Entity\Entity $entity, &$error = null)
{
    // Проверяем, что контент существует
    if (!$entity)
    {
        return false;
    }

    // Проверяем, что медаль существует
    if (!$entity->Medal)
    {
        return false;
    }

    // Проверяем, что пользователь существует
    if (!$entity->User)
    {
        return false;
    }

    // Медали видны всем
    return true;
}


    protected function addAttachmentsToContent($content)
    {
        return $content;
    }
}
