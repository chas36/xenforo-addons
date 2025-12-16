<?php
  require 'src/XF.php';
  XF::start(getcwd());
  $app = XF::setupApp('XF\Admin\App');

  $db = \XF::db();

  // Получаем template
  $template = $db->fetchRow("
      SELECT template_id, template
      FROM xf_template
      WHERE type = 'public' 
      AND title = 'poll_block'
      AND style_id = 0
  ");

  if (!$template) {
      echo "✗ Template не найден\n";
      exit;
  }

  echo "=== Содержимое template poll_block ===\n\n";

  // Ищем строку с canVote
  $lines = explode("\n", $template['template']);
  $foundLine = false;

  foreach ($lines as $num => $line) {
      if (stripos($line, 'canVote') !== false) {
          $foundLine = true;
          echo "Строка " . ($num + 1) . ": " . trim($line) . "\n";
      }
  }

  if (!$foundLine) {
      echo "✗ Строка с canVote не найдена!\n";
  }

  echo "\n\n=== Проверка, применяются ли modifications ===\n";

  // Ищем признаки наших модификаций
  if (strpos($template['template'], 'poll_block_ranked') !== false) {
      echo "✓ poll_block_ranked УЖЕ в template!\n";
  } else {
      echo "✗ poll_block_ranked НЕТ в template\n";
  }

  if (strpos($template['template'], 'isRankedPoll') !== false) {
      echo "✓ isRankedPoll() УЖЕ в template!\n";
  } else {
      echo "✗ isRankedPoll() НЕТ в template\n";
  }

  echo "\n=== Первые 50 строк template ===\n";
  $first50 = array_slice($lines, 0, 50);
  foreach ($first50 as $num => $line) {
      echo ($num + 1) . ": " . $line . "\n";
  }