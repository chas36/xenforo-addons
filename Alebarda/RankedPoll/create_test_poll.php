<?php
  require 'src/XF.php';
  XF::start(getcwd());
  $app = XF::setupApp('XF\Pub\App');

  $db = \XF::db();

  echo "Поиск thread для создания тестового опроса...\n";

  // Найдите существующий thread без опроса
  $threadId = $db->fetchOne("
      SELECT thread_id 
      FROM xf_thread 
      WHERE discussion_state = 'visible' 
      AND discussion_type != 'poll'
      LIMIT 1
  ");

  if (!$threadId) {
      echo "✗ Не найден подходящий thread.\n";
      echo "  Создайте новый thread вручную в форуме.\n";
      exit;
  }

  echo "✓ Используем thread ID: $threadId\n";

  // Создайте poll
  $poll = \XF::em()->create('XF:Poll');
  $poll->content_type = 'thread';
  $poll->content_id = $threadId;
  $poll->question = 'Выборы кандидата (Тест метода Шульце)';
  $poll->poll_type = 'ranked';
  $poll->ranked_results_visibility = 'realtime';
  $poll->max_votes = 0;
  $poll->change_vote = true;
  $poll->public_votes = false;
  $poll->view_results_unvoted = true;
  $poll->close_date = 0;
  $poll->save();

  echo "✓ Poll создан (ID: {$poll->poll_id})\n";

  // Добавьте кандидатов
  $editor = $poll->getResponseEditor();
  $editor->addResponses([
      'Алексей Навальный',
      'Владимир Путин',
      'Ксения Собчак',
      'Павел Грудинин',
      'Владимир Жириновский'
  ]);
  $editor->saveChanges();

  echo "✓ Добавлено 5 кандидатов\n";

  // Обновите thread
  $db->update('xf_thread', [
      'discussion_type' => 'poll'
  ], 'thread_id = ?', $threadId);

  echo "\n🎉 Готово!\n";
  echo "   Thread ID: $threadId\n";
  echo "   Poll ID: {$poll->poll_id}\n";
  echo "   URL: https://beta.politsim.ru/threads/$threadId/\n";
  echo "\nОткройте эту страницу и попробуйте проголосовать!\n";