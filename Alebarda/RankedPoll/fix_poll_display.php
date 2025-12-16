<?php
  require 'src/XF.php';
  XF::start(getcwd());
  $app = XF::setupApp('XF\Admin\App');

  $db = \XF::db();

  echo "Добавление template modification для отображения ranked polls...\n";

  // Проверяем, существует ли уже
  $exists = $db->fetchOne("
      SELECT modification_id 
      FROM xf_template_modification 
      WHERE modification_key = ?
  ", ['alebarda_rankedpoll_poll_block_switch']);

  $modificationData = [
      'type' => 'public',
      'template' => 'poll_block',
      'modification_key' => 'alebarda_rankedpoll_poll_block_switch',
      'description' => 'Switch to ranked poll interface for ranked polls',
      'execution_order' => 10,
      'enabled' => 1,
      'action' => 'str_replace',
      'find' => '<xf:if is="$poll.canVote()">',
      'replace' => '<xf:if is="$poll.canVote()">
      <xf:if is="$poll.isRankedPoll()">
          <xf:include template="poll_block_ranked" />
      <xf:else />',
      'addon_id' => 'Alebarda/RankedPoll'
  ];

  if ($exists) {
      echo "• Обновление существующей модификации...\n";
      $db->update('xf_template_modification', $modificationData, 'modification_id = ?', $exists);
  } else {
      echo "• Создание новой модификации...\n";
      $db->insert('xf_template_modification', $modificationData);
  }

  // Также нужно закрыть <xf:else />
  $exists2 = $db->fetchOne("
      SELECT modification_id 
      FROM xf_template_modification 
      WHERE modification_key = ?
  ", ['alebarda_rankedpoll_poll_block_close']);

  $modificationData2 = [
      'type' => 'public',
      'template' => 'poll_block',
      'modification_key' => 'alebarda_rankedpoll_poll_block_close',
      'description' => 'Close ranked poll conditional',
      'execution_order' => 20,
      'enabled' => 1,
      'action' => 'str_replace',
      'find' => '</xf:if>
  <xf:else />',
      'replace' => '</xf:if>
      </xf:if>
  <xf:else />',
      'addon_id' => 'Alebarda/RankedPoll'
  ];

  if ($exists2) {
      echo "• Обновление closing модификации...\n";
      $db->update('xf_template_modification', $modificationData2, 'modification_id = ?', $exists2);
  } else {
      echo "• Создание closing модификации...\n";
      $db->insert('xf_template_modification', $modificationData2);
  }

  echo "✓ Template modifications импортированы\n";
  echo "\nПересоберите templates:\n";
  echo "php cmd.php xf-dev:rebuild-caches\n";