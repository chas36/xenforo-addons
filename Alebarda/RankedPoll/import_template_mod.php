<?php
  require 'src/XF.php';
  XF::start(getcwd());
  $app = XF::setupApp('XF\Admin\App');

  $db = \XF::db();

  echo "Импорт template modification...\n";

  // Проверяем, существует ли уже
  $exists = $db->fetchOne("
      SELECT modification_id 
      FROM xf_template_modification 
      WHERE modification_key = ?
  ", ['alebarda_rankedpoll_poll_type_selector']);

  if ($exists) {
      echo "• Template modification уже существует, обновляем...\n";
      $db->update('xf_template_modification', [
          'template' => 'poll_macros',
          'description' => 'Add ranked poll type selection to poll editor',
          'execution_order' => 10,
          'enabled' => 1,
          'action' => 'str_replace',
          'find' => '<xf:numberboxrow name="max_votes"',
          'replace' => '<xf:radiorow name="poll_type" value="{$poll.poll_type}" label="Poll type:">
      <xf:option value="standard" selected="{$poll.poll_type} != \'ranked\'" label="Standard poll" />
      <xf:option value="ranked" label="Ranked-choice poll (Schulze method)">
          <xf:explain>Users rank options in order of preference. Winner determined by Schulze method.</xf:explain>
      </xf:option>
  </xf:radiorow>

  <xf:if is="{$poll.poll_type} == \'ranked\'">
      <xf:radiorow name="ranked_results_visibility" value="{$poll.ranked_results_visibility}" label="Show results:">
          <xf:option value="after_close" label="After poll closes" />
          <xf:option value="realtime" label="Real-time (as votes come in)" />
      </xf:radiorow>
  </xf:if>

  <xf:numberboxrow name="max_votes"'
      ], 'modification_id = ?', $exists);
  } else {
      echo "• Создаём новый template modification...\n";
      $db->insert('xf_template_modification', [
          'type' => 'public',
          'template' => 'poll_macros',
          'modification_key' => 'alebarda_rankedpoll_poll_type_selector',
          'description' => 'Add ranked poll type selection to poll editor',
          'execution_order' => 10,
          'enabled' => 1,
          'action' => 'str_replace',
          'find' => '<xf:numberboxrow name="max_votes"',
          'replace' => '<xf:radiorow name="poll_type" value="{$poll.poll_type}" label="Poll type:">
      <xf:option value="standard" selected="{$poll.poll_type} != \'ranked\'" label="Standard poll" />
      <xf:option value="ranked" label="Ranked-choice poll (Schulze method)">
          <xf:explain>Users rank options in order of preference. Winner determined by Schulze method.</xf:explain>
      </xf:option>
  </xf:radiorow>

  <xf:if is="{$poll.poll_type} == \'ranked\'">
      <xf:radiorow name="ranked_results_visibility" value="{$poll.ranked_results_visibility}" label="Show results:">
          <xf:option value="after_close" label="After poll closes" />
          <xf:option value="realtime" label="Real-time (as votes come in)" />
      </xf:radiorow>
  </xf:if>

  <xf:numberboxrow name="max_votes"',
          'addon_id' => 'Alebarda/RankedPoll'
      ]);
  }

  echo "✓ Template modification импортирован\n";
  echo "\nТеперь пересоберите templates:\n";
  echo "php cmd.php xf-dev:rebuild-templates\n";