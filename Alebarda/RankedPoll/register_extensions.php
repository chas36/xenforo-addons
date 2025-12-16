<?php

  require 'src/XF.php';
  XF::start(getcwd());
  $app = XF::setupApp('XF\Pub\App');

  $db = XF::db();

  echo "Регистрация расширений классов для Alebarda/RankedPoll...\n\n";

  // Расширения для регистрации
  $extensions = [
      ['XF\\Entity\\Poll', 'Alebarda\\RankedPoll\\XF\\Entity\\Poll'],
      ['XF\\Repository\\PollRepository', 'Alebarda\\RankedPoll\\XF\\Repository\\PollRepository'],
      ['XF\\Pub\\Controller\\Poll', 'Alebarda\\RankedPoll\\XF\\Pub\\Controller\\Poll']
  ];

  foreach ($extensions as list($fromClass, $toClass)) {
      // Проверяем, есть ли уже
      $exists = $db->fetchOne("
          SELECT extension_id 
          FROM xf_class_extension 
          WHERE from_class = ? AND to_class = ?
      ", [$fromClass, $toClass]);

      if (!$exists) {
          $db->insert('xf_class_extension', [
              'from_class' => $fromClass,
              'to_class' => $toClass,
              'execute_order' => 10,
              'active' => 1,
              'addon_id' => 'Alebarda/RankedPoll'
          ]);
          echo "✓ Зарегистрировано: $fromClass -> $toClass\n";
      } else {
          echo "• Уже существует: $fromClass -> $toClass\n";
      }
  }

  echo "\n✅ Готово!\n";
  echo "Теперь выполните: php cmd.php xf-dev:rebuild-caches\n";
 