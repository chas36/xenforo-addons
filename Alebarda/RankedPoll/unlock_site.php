<?php
  require 'src/XF.php';
  XF::start(getcwd());

  $options = \XF::options();
  echo "Board active: " . ($options->boardActive ? 'YES' : 'NO') . "\n";
  echo "Upgrade pending: " . (\XF::$versionId > $options->currentVersionId ? 'YES' : 'NO') . "\n";

  // Попробуем активировать
  if (!$options->boardActive) {
      echo "\nСайт неактивен. Попробуйте активировать через Admin CP.\n";
  }