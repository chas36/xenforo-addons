<?php

namespace Alebarda\NodeLastPost\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class NodeLastPost extends AbstractController
{
	public function actionIndex(ParameterBag $params)
	{
		$nodeId = (int)$params->node_id;
		$userId = $this->filter('user_id', 'uint');
		if (!$userId && $params->user_id)
		{
			$userId = (int)$params->user_id;
		}

		$user = null;
		if ($userId)
		{
			$user = $this->em()->find('XF:User', $userId);
			if (!$user)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}
		}

		if (!$nodeId)
		{
			return $this->error(\XF::phrase('requested_node_not_found'));
		}

		/** @var \XF\Entity\Node $node */
		$node = $this->em()->find('XF:Node', $nodeId);
		if (!$node)
		{
			return $this->notFound(\XF::phrase('requested_node_not_found'));
		}

		$error = null;
		if (!$node->canView($error))
		{
			return $this->noPermission($error);
		}

		$nodeFinder = $this->finder('XF:Node');
		$nodeFinder->where('lft', '>=', $node->lft)
			->where('rgt', '<=', $node->rgt);
		$nodes = $nodeFinder->fetch();
		$nodeIds = $nodes->keys();
		if (!$nodeIds)
		{
			$nodeIds = [$nodeId];
		}

		$postFinder = $this->finder('XF:Post');
		$postFinder->where('Thread.node_id', $nodeIds)
			->where('message_state', 'visible')
			->where('Thread.discussion_state', 'visible')
			->with(['Thread', 'Thread.Forum', 'User'])
			->order('post_date', 'DESC')
			->limit(1);
		if ($userId)
		{
			$postFinder->where('user_id', $userId);
		}

		$post = $postFinder->fetchOne();
		$canViewPost = false;
		if ($post)
		{
			$error = null;
			$canViewPost = $post->canView($error);
		}

		$viewParams = [
			'node' => $node,
			'post' => $post,
			'user' => $user,
			'canViewPost' => $canViewPost
		];

		return $this->view('Alebarda\NodeLastPost:Index', 'alebarda_node_last_post', $viewParams);
	}
}
