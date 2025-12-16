<?php
  require 'src/XF.php';
  XF::start(getcwd());
  $app = XF::setupApp('XF\Admin\App');

  $db = \XF::db();

  echo "Проверка template modifications:\n\n";

  $mods = $db->fetchAll("
      SELECT modification_key, template, enabled, action
      FROM xf_template_modification 
      WHERE addon_id = 'Alebarda/RankedPoll'
  ");

  foreach ($mods as $mod) {
      $status = $mod['enabled'] ? '✓' : '✗';
      echo "$status {$mod['modification_key']}\n";
      echo "   Template: {$mod['template']}\n";
      echo "   Action: {$mod['action']}\n\n";
  }

  if (empty($mods)) {
      echo "✗ Modifications не найдены!\n";
  }