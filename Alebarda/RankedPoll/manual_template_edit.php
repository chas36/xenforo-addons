<?php
  require 'src/XF.php';
  XF::start(getcwd());
  $app = XF::setupApp('XF\Admin\App');

  $db = \XF::db();

  echo "Прямое редактирование template poll_block...\n";

  // Получаем master template
  $template = $db->fetchRow("
      SELECT *
      FROM xf_template
      WHERE type = 'public' 
      AND title = 'poll_block'
      AND style_id = 0
  ");

  if (!$template) {
      echo "✗ Template poll_block не найден\n";
      exit;
  }

  echo "✓ Template найден (ID: {$template['template_id']})\n";

  // Проверяем, уже модифицирован ли
  if (strpos($template['template'], 'poll_block_ranked') !== false) {
      echo "• Template уже содержит poll_block_ranked\n";
      exit;
  }

  // Модифицируем template напрямую
  $oldContent = '<xf:if is="$poll.canVote()">';
  $newContent = '<xf:if is="$poll.canVote()">
        <xf:if is="$poll.isRankedPoll()">
                <xf:include template="poll_block_ranked" />
        <xf:else />';

  $updatedTemplate = str_replace($oldContent, $newContent, $template['template']);

  if ($updatedTemplate === $template['template']) {
      echo "✗ Не удалось найти место для вставки\n";
      echo "Ищем: $oldContent\n";
      exit;
  }

  // Также нужно закрыть else
  $updatedTemplate = str_replace(
      '</xf:if>' . "\n" . '<xf:else />',
      '</xf:if>' . "\n\t" . '</xf:if>' . "\n" . '<xf:else />',
      $updatedTemplate,
      $count
  );

  echo "• Модификация применена (заменено $count мест)\n";

  // Сохраняем
  $db->update('xf_template', [
      'template' => $updatedTemplate,
      'last_edit_date' => time()
  ], 'template_id = ?', $template['template_id']);

  echo "✓ Template обновлён!\n";
  echo "\nТеперь пересоберите:\n";
  echo "php cmd.php xf-dev:recompile-templates\n";