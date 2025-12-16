<?php
  // Правильное чтение config
  $config = [];
  require 'src/config.php';

  if (empty($config['db'])) {
      die("✗ Не удалось прочитать config.php\n");
  }

  echo "✓ Config прочитан\n";

  $mysqli = new mysqli(
      $config['db']['host'],
      $config['db']['username'],
      $config['db']['password'],
      $config['db']['dbname'],
      $config['db']['port'] ?? 3306
  );

  if ($mysqli->connect_error) {
      die("✗ Connection failed: " . $mysqli->connect_error . "\n");
  }

  echo "✓ Подключено к БД: {$config['db']['dbname']}\n";

  // Читаем templates
  $pollBlockRanked = file_get_contents('src/addons/Alebarda/RankedPoll/_output/templates/public/poll_block_ranked.html');
  $pollResultsRanked = file_get_contents('src/addons/Alebarda/RankedPoll/_output/templates/public/poll_results_ranked.html');

  if (!$pollBlockRanked || !$pollResultsRanked) {
      die("✗ Не удалось прочитать файлы templates\n");
  }

  echo "✓ Templates прочитаны (" . strlen($pollBlockRanked) . " + " . strlen($pollResultsRanked) . " байт)\n";

  $time = time();

  // Импорт poll_block_ranked
  $pollBlockRanked = $mysqli->real_escape_string($pollBlockRanked);
  $sql = "INSERT INTO xf_template (type, title, style_id, template, last_edit_date, addon_id, version_id, version_string)
          VALUES ('public', 'poll_block_ranked', 0, '{$pollBlockRanked}', {$time}, 'Alebarda/RankedPoll', 1000000, '1.0.0 Alpha 1')
          ON DUPLICATE KEY UPDATE 
              template = VALUES(template),
              last_edit_date = VALUES(last_edit_date)";

  if ($mysqli->query($sql)) {
      echo "✓ poll_block_ranked импортирован\n";
  } else {
      echo "✗ Ошибка poll_block_ranked: " . $mysqli->error . "\n";
  }

  // Импорт poll_results_ranked
  $pollResultsRanked = $mysqli->real_escape_string($pollResultsRanked);
  $sql2 = "INSERT INTO xf_template (type, title, style_id, template, last_edit_date, addon_id, version_id, version_string)
           VALUES ('public', 'poll_results_ranked', 0, '{$pollResultsRanked}', {$time}, 'Alebarda/RankedPoll', 1000000, '1.0.0 Alpha 1')
           ON DUPLICATE KEY UPDATE 
               template = VALUES(template),
               last_edit_date = VALUES(last_edit_date)";

  if ($mysqli->query($sql2)) {
      echo "✓ poll_results_ranked импортирован\n";
  } else {
      echo "✗ Ошибка poll_results_ranked: " . $mysqli->error . "\n";
  }

  $mysqli->close();

  echo "\n✅ Готово! Теперь выполните:\n";
  echo "php cmd.php xf-dev:recompile-templates\n";