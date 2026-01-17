<?php

namespace Alebarda\UserListByGroup\BbCode;

use XF\BbCode\Renderer\AbstractRenderer;

class UserListNode
{
	const MAX_LIMIT = 100;
	const DEFAULT_LIMIT = 100;

	public static function renderUserListNode(
		array $children,
		$option,
		array $tag,
		array $options,
		AbstractRenderer $renderer
	) {
		$params = self::parseOptions($option);

		if (!$params['group'] || !$params['node'])
		{
			return '<div class="userlist-by-group--empty">'
				. 'Не задана группа или узел'
				. '</div>';
		}

		$users = self::fetchUsersByGroup($params['group'], $params['limit']);
		if ($users->count() === 0)
		{
			return '<div class="userlist-by-group--empty">'
				. \XF::phrase('no_users_found')
				. '</div>';
		}

		$node = \XF::app()->em()->find('XF:Node', $params['node']);
		if (!$node)
		{
			return '<div class="userlist-by-group--empty">'
				. 'Узел не найден'
				. '</div>';
		}

		$nodeIds = self::fetchNodeIds($node);
		return self::renderHtml($users, $nodeIds);
	}

	protected static function parseOptions($option): array
	{
		$params = [
			'group' => 0,
			'node' => 0,
			'limit' => self::DEFAULT_LIMIT
		];

		$option = trim($option);

		// Format: [userlistnode=group,node] or [userlistnode=group,node,limit]
		if (preg_match('/^(\d+)[,|](\d+)(?:[,|](\d+))?$/', $option, $matches))
		{
			$params['group'] = (int) $matches[1];
			$params['node'] = (int) $matches[2];
			if (!empty($matches[3]))
			{
				$params['limit'] = min((int) $matches[3], self::MAX_LIMIT);
			}
		}
		else
		{
			if (preg_match('/group\s*=\s*(\d+)/i', $option, $matches))
			{
				$params['group'] = (int) $matches[1];
			}
			if (preg_match('/node\s*=\s*(\d+)/i', $option, $matches))
			{
				$params['node'] = (int) $matches[1];
			}
			if (preg_match('/limit\s*=\s*(\d+)/i', $option, $matches))
			{
				$params['limit'] = min((int) $matches[1], self::MAX_LIMIT);
			}
		}

		return $params;
	}

	protected static function fetchUsersByGroup(int $groupId, int $limit): \XF\Mvc\Entity\ArrayCollection
	{
		$primaryFinder = \XF::finder('XF:User');
		$primaryFinder->where('user_group_id', $groupId)
			->order('username', 'ASC');

		$secondaryFinder = \XF::finder('XF:User');
		$secondaryFinder->whereOr([
			['secondary_group_ids', '=', $groupId],
			['secondary_group_ids', 'LIKE', $groupId . ',%'],
			['secondary_group_ids', 'LIKE', '%,' . $groupId],
			['secondary_group_ids', 'LIKE', '%,' . $groupId . ',%']
		])->order('username', 'ASC');

		$primaryUsers = $primaryFinder->fetch($limit);
		$secondaryUsers = $secondaryFinder->fetch($limit);

		$allUsers = $primaryUsers->toArray();
		foreach ($secondaryUsers as $userId => $user)
		{
			if (!isset($allUsers[$userId]))
			{
				$allUsers[$userId] = $user;
			}
		}

		uasort($allUsers, function($a, $b)
		{
			return strcasecmp($a->username, $b->username);
		});

		$allUsers = array_slice($allUsers, 0, $limit, true);

		return new \XF\Mvc\Entity\ArrayCollection($allUsers);
	}

	protected static function fetchNodeIds(\XF\Entity\Node $node): array
	{
		$nodeFinder = \XF::finder('XF:Node');
		$nodeFinder->where('lft', '>=', $node->lft)
			->where('rgt', '<=', $node->rgt);
		$nodes = $nodeFinder->fetch();
		$nodeIds = $nodes->keys();
		if (!$nodeIds)
		{
			$nodeIds = [(int)$node->node_id];
		}

		return $nodeIds;
	}

	protected static function renderHtml(\XF\Mvc\Entity\ArrayCollection $users, array $nodeIds): string
	{
		$html = '<table class="dataList userlist-by-group-node">'
			. '<thead><tr>'
			. '<th>Пользователь</th>'
			. '<th>Последнее сообщение</th>'
			. '</tr></thead>'
			. '<tbody>';

		foreach ($users as $user)
		{
			$html .= '<tr>'
				. '<td>' . self::renderUsername($user) . '</td>'
				. '<td>' . self::renderLastPostCell($user, $nodeIds) . '</td>'
				. '</tr>';
		}

		$html .= '</tbody></table>';

		return $html;
	}

	protected static function renderLastPostCell(\XF\Entity\User $user, array $nodeIds): string
	{
		$postFinder = \XF::finder('XF:Post');
		$postFinder->where('Thread.node_id', $nodeIds)
			->where('message_state', 'visible')
			->where('Thread.discussion_state', 'visible')
			->where('user_id', $user->user_id)
			->with(['Thread', 'User'])
			->order('post_date', 'DESC')
			->limit(1);

		$post = $postFinder->fetchOne();
		if (!$post)
		{
			return '<span class="u-muted">—</span>';
		}

		$templater = \XF::app()->templater();
		$dateHtml = $templater->func('date_time', [$post->post_date]);

		$error = null;
		if ($post->canView($error))
		{
			$href = \XF::app()->router('public')->buildLink('posts', $post);
			return '<a href="' . htmlspecialchars($href) . '">' . $dateHtml . '</a>';
		}

		return '<span class="u-muted">' . $dateHtml . ' (закрытый узел)</span>';
	}

	protected static function renderUsername(\XF\Entity\User $user): string
	{
		$userId = $user->user_id;
		$username = htmlspecialchars($user->username);
		$href = \XF::app()->router('public')->buildLink('members', $user);

		$styleClass = '';
		$displayStyleGroupId = $user->display_style_group_id;
		if ($displayStyleGroupId)
		{
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
