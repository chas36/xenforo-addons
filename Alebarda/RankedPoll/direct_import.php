<?php
  // Прямой импорт через mysqli без XF

  $config = require 'src/config.php';

  $mysqli = new mysqli(
      $config['db']['host'],
      $config['db']['username'],
      $config['db']['password'],
      $config['db']['dbname'],
      $config['db']['port']
  );

  if ($mysqli->connect_error) {
      die("Connection failed: " . $mysqli->connect_error);
  }

  echo "✓ Подключено к БД\n";

  // Читаем templates
  $pollBlockRanked = file_get_contents('src/addons/Alebarda/RankedPoll/_output/templates/public/poll_block_ranked.html');
  $pollResultsRanked = file_get_contents('src/addons/Alebarda/RankedPoll/_output/templates/public/poll_results_ranked.html');

  if (!$pollBlockRanked || !$pollResultsRanked) {
      die("✗ Не удалось прочитать файлы templates\n");
  }

  echo "✓ Templates прочитаны\n";

  // Импорт poll_block_ranked
  $stmt = $mysqli->prepare("
      INSERT INTO xf_template (type, title, style_id, template, last_edit_date, addon_id, version_id, version_string)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE 
          template = VALUES(template),
          last_edit_date = VALUES(last_edit_date)
  ");

  $type = 'public';
  $title1 = 'poll_block_ranked';
  $styleId = 0;
  $time = time();
  $addonId = 'Alebarda/RankedPoll';
  $versionId = 1000000;
  $versionString = '1.0.0 Alpha 1';

  $stmt->bind_param('ssissis', $type, $title1, $styleId, $pollBlockRanked, $time, $addonId, $versionId, $versionString);

  if ($stmt->execute()) {
      echo "✓ poll_block_ranked импортирован\n";
  } else {
      echo "✗ Ошибка: " . $stmt->error . "\n";
  }

  // Импорт poll_results_ranked
  $title2 = 'poll_results_ranked';
  $stmt->bind_param('ssissis', $type, $title2, $styleId, $pollResultsRanked, $time, $addonId, $versionId, $versionString);

  if ($stmt->execute()) {
      echo "✓ poll_results_ranked импортирован\n";
  } else {
      echo "✗ Ошибка: " . $stmt->error . "\n";
  }

  $stmt->close();
  $mysqli->close();

  echo "\n✅ Готово! Теперь выполните:\n";
  echo "php cmd.php xf-dev:recompile-templates\n";