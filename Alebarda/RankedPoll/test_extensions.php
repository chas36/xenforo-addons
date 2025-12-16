<?php

  require 'src/XF.php';
  XF::start(getcwd());
  $app = XF::setupApp('XF\Pub\App');

  echo "Проверка расширений классов...\n\n";

  // Тест 1: Проверка Poll entity
  $poll = \XF::em()->create('XF:Poll');
  echo "1. Poll entity класс: " . get_class($poll) . "\n";
  echo "   Метод isRankedPoll() существует? " . (method_exists($poll, 'isRankedPoll') ? '✓ ДА' : '✗ НЕТ') . "\n\n";

  // Тест 2: Проверка PollRepository
  $pollRepo = \XF::repository('XF:Poll');
  echo "2. PollRepository класс: " . get_class($pollRepo) . "\n";
  echo "   Метод voteOnRankedPoll() существует? " . (method_exists($pollRepo, 'voteOnRankedPoll') ? '✓ ДА' : '✗ НЕТ') . "\n\n";

  // Тест 3: Проверка SchulzeCalculator
  $calculatorExists = class_exists('Alebarda\\RankedPoll\\Voting\\SchulzeCalculator');
  echo "3. SchulzeCalculator класс существует? " . ($calculatorExists ? '✓ ДА' : '✗ НЕТ') . "\n\n";

  // Тест 4: Проверка расширений в БД
  $db = \XF::db();
  $extensions = $db->fetchAll("
      SELECT from_class, to_class, active 
      FROM xf_class_extension 
      WHERE addon_id = 'Alebarda/RankedPoll'
  ");

  echo "4. Расширения в базе данных:\n";
  foreach ($extensions as $ext) {
      $status = $ext['active'] ? '✓' : '✗';
      echo "   $status {$ext['from_class']} -> {$ext['to_class']}\n";
  }

  echo "\n";

  if (method_exists($poll, 'isRankedPoll')) {
      echo "🎉 УСПЕХ! Расширения работают корректно!\n";
  } else {
      echo "⚠️  Расширения зарегистрированы, но не загружены.\n";
      echo "   Попробуйте очистить кеш приложения или перезагрузить PHP-FPM.\n";
  }