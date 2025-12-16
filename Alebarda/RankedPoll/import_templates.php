<?php
  require 'src/XF.php';
  XF::start(getcwd());
  $app = XF::setupApp('XF\Admin\App');

  $db = \XF::db();

  echo "Импорт templates для RankedPoll...\n\n";

  // Template poll_block_ranked
  $pollBlockRanked = file_get_contents('src/addons/Alebarda/RankedPoll/_output/templates/public/poll_block_ranked.html');

  if (!$pollBlockRanked) {
      echo "✗ Не удалось прочитать poll_block_ranked.html\n";
      exit;
  }

  echo "✓ Прочитан poll_block_ranked.html (" . strlen($pollBlockRanked) . " байт)\n";

  // Проверяем, существует ли уже
  $existsId = $db->fetchOne("
      SELECT template_id
      FROM xf_template
      WHERE type = 'public' 
      AND title = 'poll_block_ranked'
      AND style_id = 0
  ");

  if ($existsId) {
      echo "• Обновление существующего template...\n";
      $db->update('xf_template', [
          'template' => $pollBlockRanked,
          'last_edit_date' => time(),
          'addon_id' => 'Alebarda/RankedPoll'
      ], 'template_id = ?', $existsId);
  } else {
      echo "• Создание нового template...\n";
      $db->insert('xf_template', [
          'type' => 'public',
          'title' => 'poll_block_ranked',
          'style_id' => 0,
          'template' => $pollBlockRanked,
          'last_edit_date' => time(),
          'addon_id' => 'Alebarda/RankedPoll',
          'version_id' => 1000000,
          'version_string' => '1.0.0 Alpha 1'
      ]);
  }

  echo "✓ poll_block_ranked импортирован\n\n";

  // Template poll_results_ranked
  $pollResultsRanked = file_get_contents('src/addons/Alebarda/RankedPoll/_output/templates/public/poll_results_ranked.html');

  if (!$pollResultsRanked) {
      echo "✗ Не удалось прочитать poll_results_ranked.html\n";
      exit;
  }

  echo "✓ Прочитан poll_results_ranked.html (" . strlen($pollResultsRanked) . " байт)\n";

  $existsId2 = $db->fetchOne("
      SELECT template_id
      FROM xf_template
      WHERE type = 'public' 
      AND title = 'poll_results_ranked'
      AND style_id = 0
  ");

  if ($existsId2) {
      echo "• Обновление существующего template...\n";
      $db->update('xf_template', [
          'template' => $pollResultsRanked,
          'last_edit_date' => time(),
          'addon_id' => 'Alebarda/RankedPoll'
      ], 'template_id = ?', $existsId2);
  } else {
      echo "• Создание нового template...\n";
      $db->insert('xf_template', [
          'type' => 'public',
          'title' => 'poll_results_ranked',
          'style_id' => 0,
          'template' => $pollResultsRanked,
          'last_edit_date' => time(),
          'addon_id' => 'Alebarda/RankedPoll',
          'version_id' => 1000000,
          'version_string' => '1.0.0 Alpha 1'
      ]);
  }

  echo "✓ poll_results_ranked импортирован\n\n";

  echo "🎉 Готово! Теперь перекомпилируйте:\n";
  echo "php cmd.php xf-dev:recompile-templates\n";