<?php

namespace Alebarda\NodeLastPost\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Member extends AbstractController
{
	public function actionNodeLastPosts(ParameterBag $params)
	{
		$userId = (int)$params->user_id;
		if (!$userId)
		{
			return $this->notFound(\XF::phrase('requested_user_not_found'));
		}

		/** @var \XF\Entity\User $user */
		$user = $this->em()->find('XF:User', $userId);
		if (!$user)
		{
			return $this->notFound(\XF::phrase('requested_user_not_found'));
		}

		$error = null;
		if (method_exists($user, 'canView') && !$user->canView($error))
		{
			return $this->noPermission($error);
		}

		$countryNodes = [
			['id' => 55, 'label' => 'Австрийская империя'],
			['id' => 123, 'label' => 'Королевство Испания'],
			['id' => 252, 'label' => 'Российская империя'],
			['id' => 46, 'label' => 'Соединенное Королевство'],
			['id' => 15, 'label' => 'Соединенные Штаты Америки'],
			['id' => 133, 'label' => 'Федеративная Республика Германия']
		];

		$results = [];
		foreach ($countryNodes as $country)
		{
			$results[] = $this->getLastPostForNode($userId, $country['id'], $country['label']);
		}

		$customNodeId = $this->filter('custom_node_id', '?uint');
		$customResult = null;
		if ($customNodeId)
		{
			$customResult = $this->getLastPostForNode($userId, $customNodeId, null);
		}

		$viewParams = [
			'user' => $user,
			'profileUser' => $user,
			'results' => $results,
			'customNodeId' => $customNodeId,
			'customResult' => $customResult,
			'selectedTab' => 'node_last_posts'
		];

		return $this->view('Alebarda\\NodeLastPost:Member', 'alebarda_member_node_last_posts', $viewParams);
	}

	public function actionUserStatuses(ParameterBag $params)
	{
		$userId = (int)$params->user_id;
		if (!$userId)
		{
			return $this->notFound(\XF::phrase('requested_user_not_found'));
		}

		/** @var \XF\Entity\User $user */
		$user = $this->em()->find('XF:User', $userId);
		if (!$user)
		{
			return $this->notFound(\XF::phrase('requested_user_not_found'));
		}

		$error = null;
		if (method_exists($user, 'canView') && !$user->canView($error))
		{
			return $this->noPermission($error);
		}

		$viewParams = [
			'user' => $user,
			'profileUser' => $user,
			'selectedTab' => 'user_statuses'
		];

		return $this->view('Alebarda\\NodeLastPost:MemberStatuses', 'alebarda_member_statuses', $viewParams);
	}

	protected function getLastPostForNode($userId, $nodeId, $label)
	{
		/** @var \XF\Entity\Node $node */
		$node = $this->em()->find('XF:Node', (int)$nodeId);
		if (!$node)
		{
			return [
				'nodeId' => (int)$nodeId,
				'label' => $label ?: ('ID ' . (int)$nodeId),
				'nodeTitle' => null,
				'post' => null,
				'canViewPost' => false,
				'notFound' => true
			];
		}

		$nodeFinder = $this->finder('XF:Node');
		$nodeFinder->where('lft', '>=', $node->lft)
			->where('rgt', '<=', $node->rgt);
		$nodes = $nodeFinder->fetch();
		$nodeIds = $nodes->keys();
		if (!$nodeIds)
		{
			$nodeIds = [(int)$nodeId];
		}

		$postFinder = $this->finder('XF:Post');
		$postFinder->where('Thread.node_id', $nodeIds)
			->where('message_state', 'visible')
			->where('Thread.discussion_state', 'visible')
			->where('user_id', $userId)
			->with(['Thread', 'Thread.Forum', 'User'])
			->order('post_date', 'DESC')
			->limit(1);

		$post = $postFinder->fetchOne();
		$canViewPost = false;
		if ($post)
		{
			$error = null;
			$canViewPost = $post->canView($error);
		}

		return [
			'nodeId' => (int)$nodeId,
			'label' => $label ?: $node->title,
			'nodeTitle' => $node->title,
			'post' => $post,
			'canViewPost' => $canViewPost,
			'notFound' => false
		];
	}
}
