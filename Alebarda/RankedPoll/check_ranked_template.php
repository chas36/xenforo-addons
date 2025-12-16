 <?php
  require 'src/XF.php';
  XF::start(getcwd());
  $app = XF::setupApp('XF\Admin\App');

  $db = \XF::db();

  echo "Проверка существования template poll_block_ranked:\n";

  $template = $db->fetchOne("
      SELECT template_id
      FROM xf_template
      WHERE type = 'public' 
      AND title = 'poll_block_ranked'
      AND style_id = 0
  ");

  if ($template) {
      echo "✓ Template poll_block_ranked существует (ID: $template)\n";
  } else {
      echo "✗ Template poll_block_ranked НЕ СУЩЕСТВУЕТ!\n";
      echo "\nЭто проблема! Нужно импортировать template.\n";
  }

  // Также проверим poll_results_ranked
  $template2 = $db->fetchOne("
      SELECT template_id
      FROM xf_template
      WHERE type = 'public' 
      AND title = 'poll_results_ranked'
      AND style_id = 0
  ");

  if ($template2) {
      echo "✓ Template poll_results_ranked существует (ID: $template2)\n";
  } else {
      echo "✗ Template poll_results_ranked НЕ СУЩЕСТВУЕТ!\n";
  }